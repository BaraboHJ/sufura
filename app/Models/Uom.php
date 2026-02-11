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

    public static function updateSetAndUoms(PDO $pdo, int $orgId, int $setId, string $setName, array $uoms, int $baseUomId): void
    {
        $pdo->beginTransaction();
        try {
            $setStmt = $pdo->prepare('UPDATE uom_sets SET name = :name WHERE org_id = :org_id AND id = :id');
            $setStmt->execute([
                'name' => $setName,
                'org_id' => $orgId,
                'id' => $setId,
            ]);

            $updateStmt = $pdo->prepare(
                'UPDATE uoms
                 SET name = :name,
                     symbol = :symbol,
                     factor_to_base = :factor_to_base,
                     is_base = CASE WHEN id = :base_uom_id THEN 1 ELSE 0 END
                 WHERE org_id = :org_id AND uom_set_id = :uom_set_id AND id = :id'
            );

            foreach ($uoms as $uom) {
                $updateStmt->execute([
                    'name' => $uom['name'],
                    'symbol' => $uom['symbol'],
                    'factor_to_base' => $uom['factor_to_base'],
                    'base_uom_id' => $baseUomId,
                    'org_id' => $orgId,
                    'uom_set_id' => $setId,
                    'id' => $uom['id'],
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
