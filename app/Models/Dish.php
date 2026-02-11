<?php

namespace App\Models;

use PDO;
use PDOException;

class Dish
{
    private const DEFAULT_CATEGORY_COLUMN = 'dish_category_id';
    private static ?string $resolvedCategoryColumn = null;
    private const CATEGORY_COLUMNS = ['category_id', 'dish_category_id'];

    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        self::runWriteWithCategoryColumn(
            $pdo,
            static function (string $categoryColumn) use ($pdo, $orgId, $payload): void {
                $stmt = $pdo->prepare(
                    "INSERT INTO dishes (org_id, name, {$categoryColumn}, description, yield_servings, active, created_at)
                     VALUES (:org_id, :name, :category_id, :description, :yield_servings, :active, NOW())"
                );
                $stmt->execute([
                    'org_id' => $orgId,
                    'name' => $payload['name'],
                    'category_id' => $payload['category_id'],
                    'description' => $payload['description'],
                    'yield_servings' => $payload['yield_servings'] ?? 1,
                    'active' => $payload['active'] ?? 1,
                ]);
            }
        );

        $id = (int) $pdo->lastInsertId();
        $dish = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish', $id, 'create', null, $dish);

        return $dish ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        self::runWriteWithCategoryColumn(
            $pdo,
            static function (string $categoryColumn) use ($pdo, $orgId, $id, $payload): void {
                $stmt = $pdo->prepare(
                    "UPDATE dishes
                     SET name = :name,
                         {$categoryColumn} = :category_id,
                         description = :description,
                         yield_servings = :yield_servings,
                         active = :active,
                         updated_at = NOW()
                     WHERE org_id = :org_id AND id = :id"
                );
                $stmt->execute([
                    'name' => $payload['name'],
                    'category_id' => $payload['category_id'],
                    'description' => $payload['description'],
                    'yield_servings' => $payload['yield_servings'] ?? 1,
                    'active' => $payload['active'] ?? 1,
                    'org_id' => $orgId,
                    'id' => $id,
                ]);
            }
        );

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $row = self::runSelectWithCategoryColumn(
            $pdo,
            static function (string $categoryColumn) use ($pdo, $orgId, $id) {
                $stmt = $pdo->prepare(
                    "SELECT d.id, d.org_id, d.name, d.{$categoryColumn} AS category_id, c.name AS category_name, d.description, d.yield_servings, d.active, d.created_at, d.updated_at
                     FROM dishes d
                     JOIN dish_categories c ON c.id = d.{$categoryColumn} AND c.org_id = d.org_id
                     WHERE d.org_id = :org_id AND d.id = :id"
                );
                $stmt->execute(['org_id' => $orgId, 'id' => $id]);
                return $stmt->fetch() ?: null;
            }
        );

        return $row ?: null;
    }

    public static function listByOrg(PDO $pdo, int $orgId): array
    {
        return self::runSelectWithCategoryColumn(
            $pdo,
            static function (string $categoryColumn) use ($pdo, $orgId) {
                $stmt = $pdo->prepare(
                    "SELECT d.id, d.name, d.{$categoryColumn} AS category_id, c.name AS category_name, d.description, d.yield_servings, d.active, d.created_at, d.updated_at
                     FROM dishes d
                     JOIN dish_categories c ON c.id = d.{$categoryColumn} AND c.org_id = d.org_id
                     WHERE d.org_id = :org_id
                     ORDER BY c.name, d.name"
                );
                $stmt->execute(['org_id' => $orgId]);
                return $stmt->fetchAll();
            }
        );
    }

    public static function searchByOrg(PDO $pdo, int $orgId, int $categoryId = 0, string $query = ''): array
    {
        return self::runSelectWithCategoryColumn(
            $pdo,
            static function (string $categoryColumn) use ($pdo, $orgId, $categoryId, $query): array {
                $sql = "SELECT id, name, description, yield_servings, {$categoryColumn} AS category_id
                        FROM dishes
                        WHERE org_id = :org_id";
                $params = ['org_id' => $orgId];

                if ($categoryId > 0) {
                    $sql .= ' AND ' . $categoryColumn . ' = :category_id';
                    $params['category_id'] = $categoryId;
                }

                if ($query !== '') {
                    $sql .= ' AND name LIKE :query';
                    $params['query'] = '%' . $query . '%';
                }

                $sql .= ' ORDER BY name';

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                return $stmt->fetchAll();
            }
        );
    }

    /**
     * @template T
     * @param callable(string): T $callback
     * @return T
     */
    private static function runSelectWithCategoryColumn(PDO $pdo, callable $callback)
    {
        $columnsToTry = array_values(array_unique([self::categoryColumn($pdo), ...self::CATEGORY_COLUMNS]));
        $lastException = null;

        foreach ($columnsToTry as $column) {
            try {
                return $callback($column);
            } catch (PDOException $e) {
                if (!self::isUnknownColumnError($e)) {
                    throw $e;
                }

                $lastException = $e;
            }
        }

        if ($lastException instanceof PDOException) {
            throw $lastException;
        }

        throw new PDOException('Unable to resolve dish category column.');
    }

    private static function isUnknownColumnError(PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1054;
    }

    private static function runWriteWithCategoryColumn(PDO $pdo, callable $callback): void
    {
        self::runSelectWithCategoryColumn(
            $pdo,
            static function (string $column) use ($callback): bool {
                $callback($column);
                return true;
            }
        );
    }

    public static function categoryColumn(PDO $pdo): string
    {
        if (self::$resolvedCategoryColumn !== null) {
            return self::$resolvedCategoryColumn;
        }

        foreach (['dish_category_id', 'category_id'] as $column) {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM dishes LIKE :column_name');
            if ($stmt && $stmt->execute(['column_name' => $column]) && $stmt->fetch()) {
                self::$resolvedCategoryColumn = $column;
                return $column;
            }
        }

        self::$resolvedCategoryColumn = self::DEFAULT_CATEGORY_COLUMN;
        return self::$resolvedCategoryColumn;
    }

    public static function delete(PDO $pdo, int $orgId, int $actorUserId, int $id): void
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare('DELETE FROM dishes WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        Audit::log($pdo, $orgId, $actorUserId, 'dish', $id, 'delete', $before, null);
    }

    public static function hasMenuItems(PDO $pdo, int $orgId, int $id): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM menu_items WHERE org_id = :org_id AND dish_id = :id LIMIT 1');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hasMenuSnapshots(PDO $pdo, int $orgId, int $id): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM menu_item_cost_snapshots WHERE org_id = :org_id AND dish_id = :id LIMIT 1');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        return (bool) $stmt->fetchColumn();
    }
}
