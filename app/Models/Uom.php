<?php

namespace App\Models;

use PDO;

class Uom
{
    public static function listSetsWithUoms(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare(
            'SELECT
                us.id AS set_id,
                us.name AS set_name,
                u.id,
                u.name,
                u.symbol,
                u.factor_to_base,
                u.is_base
             FROM uom_sets us
             JOIN uoms u ON u.uom_set_id = us.id AND u.org_id = us.org_id
             WHERE us.org_id = :org_id
             ORDER BY us.name ASC, u.factor_to_base ASC, u.name ASC'
        );
        $stmt->execute(['org_id' => $orgId]);

        $sets = [];
        while ($row = $stmt->fetch()) {
            $setId = (int) $row['set_id'];
            if (!isset($sets[$setId])) {
                $sets[$setId] = [
                    'id' => $setId,
                    'name' => $row['set_name'],
                    'uoms' => [],
                ];
            }

            $sets[$setId]['uoms'][] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'symbol' => $row['symbol'],
                'factor_to_base' => (float) $row['factor_to_base'],
                'is_base' => (int) $row['is_base'] === 1,
            ];
        }

        return array_values($sets);
    }

    public static function findSet(PDO $pdo, int $orgId, int $setId): ?array
    {
        $stmt = $pdo->prepare('SELECT id, name FROM uom_sets WHERE org_id = :org_id AND id = :id LIMIT 1');
        $stmt->execute(['org_id' => $orgId, 'id' => $setId]);
        $set = $stmt->fetch();

        return $set ?: null;
    }

    public static function updateSetAndUoms(PDO $pdo, int $orgId, int $setId, string $setName, array $uoms, string $baseUomKey): void
    {
        $existingStmt = $pdo->prepare(
            'SELECT id
             FROM uoms
             WHERE org_id = :org_id AND uom_set_id = :uom_set_id'
        );
        $existingStmt->execute(['org_id' => $orgId, 'uom_set_id' => $setId]);
        $existingIds = array_map('intval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
        $existingLookup = array_fill_keys($existingIds, true);

        $baseUomId = null;
        $submittedExistingIds = [];
        foreach ($uoms as $uom) {
            $id = (int) ($uom['id'] ?? 0);
            if ($id > 0) {
                $submittedExistingIds[] = $id;
            }

            if ((string) ($uom['key'] ?? '') === $baseUomKey && $id > 0) {
                $baseUomId = $id;
            }
        }

        $idsToDelete = array_values(array_diff($existingIds, $submittedExistingIds));
        $usedIds = self::findReferencedUomIds($pdo, $orgId, $idsToDelete);
        if (!empty($usedIds)) {
            throw new \RuntimeException('One or more UoMs cannot be removed because they are already in use.');
        }

        $pdo->beginTransaction();
        try {
            $setStmt = $pdo->prepare('UPDATE uom_sets SET name = :name WHERE org_id = :org_id AND id = :id');
            $setStmt->execute([
                'name' => $setName,
                'org_id' => $orgId,
                'id' => $setId,
            ]);

            $insertStmt = $pdo->prepare(
                'INSERT INTO uoms (org_id, uom_set_id, name, symbol, factor_to_base, is_base)
                 VALUES (:org_id, :uom_set_id, :name, :symbol, :factor_to_base, 0)'
            );
            $updateStmt = $pdo->prepare(
                'UPDATE uoms
                 SET name = :name,
                     symbol = :symbol,
                     factor_to_base = :factor_to_base,
                     is_base = 0
                 WHERE org_id = :org_id AND uom_set_id = :uom_set_id AND id = :id'
            );

            foreach ($uoms as $uom) {
                $params = [
                    'name' => $uom['name'],
                    'symbol' => $uom['symbol'],
                    'factor_to_base' => $uom['factor_to_base'],
                    'org_id' => $orgId,
                    'uom_set_id' => $setId,
                ];

                $id = (int) ($uom['id'] ?? 0);
                if ($id > 0 && isset($existingLookup[$id])) {
                    $updateStmt->execute($params + ['id' => $id]);
                } else {
                    $insertStmt->execute($params);
                    $id = (int) $pdo->lastInsertId();
                    if ((string) ($uom['key'] ?? '') === $baseUomKey) {
                        $baseUomId = $id;
                    }
                }
            }

            if (!empty($idsToDelete)) {
                $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
                $deleteStmt = $pdo->prepare("DELETE FROM uoms WHERE org_id = ? AND uom_set_id = ? AND id IN ({$placeholders})");
                $deleteStmt->execute(array_merge([$orgId, $setId], $idsToDelete));
            }

            if ($baseUomId === null) {
                throw new \RuntimeException('Please choose a valid base UoM.');
            }

            $baseStmt = $pdo->prepare(
                'UPDATE uoms
                 SET is_base = CASE WHEN id = :base_uom_id THEN 1 ELSE 0 END
                 WHERE org_id = :org_id AND uom_set_id = :uom_set_id'
            );
            $baseStmt->execute([
                'base_uom_id' => $baseUomId,
                'org_id' => $orgId,
                'uom_set_id' => $setId,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private static function findReferencedUomIds(PDO $pdo, int $orgId, array $uomIds): array
    {
        if (empty($uomIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($uomIds), '?'));
        $queries = [
            "SELECT purchase_uom_id AS uom_id FROM ingredient_costs WHERE org_id = ? AND purchase_uom_id IN ({$placeholders})",
            "SELECT uom_id FROM dish_lines WHERE org_id = ? AND uom_id IN ({$placeholders})",
            "SELECT base_uom_id AS uom_id FROM menu_ingredient_snapshots WHERE org_id = ? AND base_uom_id IN ({$placeholders})",
            "SELECT purchase_uom_id AS uom_id FROM cost_import_rows WHERE org_id = ? AND purchase_uom_id IN ({$placeholders})",
        ];

        $used = [];
        foreach ($queries as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$orgId], $uomIds));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $used[(int) $id] = true;
            }
        }

        return array_keys($used);
    }
}
