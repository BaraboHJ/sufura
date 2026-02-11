<?php

namespace App\Models;

use PDO;

class Dish
{
    private const FALLBACK_CATEGORY_COLUMN = 'dish_category_id';
    private static ?string $cachedCategoryColumn = null;

    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        $categoryColumn = self::categoryColumn($pdo);

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

        $stmt->execute([
            'org_id' => $orgId,
            'name' => $payload['name'],
            'category_id' => $payload['category_id'],
            'description' => $payload['description'],
            'yield_servings' => $payload['yield_servings'] ?? 1,
            'active' => $payload['active'] ?? 1,
        ]);

        $id = (int) $pdo->lastInsertId();
        $dish = self::findById($pdo, $orgId, $id);

        Audit::log($pdo, $orgId, $actorUserId, 'dish', $id, 'create', null, $dish);

        return $dish ?: [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $categoryColumn = self::categoryColumn($pdo);

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

        $stmt->execute([
            'name' => $payload['name'],
            'category_id' => $payload['category_id'],
            'description' => $payload['description'],
            'yield_servings' => $payload['yield_servings'] ?? 1,
            'active' => $payload['active'] ?? 1,
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish', $id, 'update', $before, $after);

        return $after ?: [];
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $categoryColumn = self::categoryColumn($pdo);

        $stmt = $pdo->prepare(
            "SELECT d.id,
                    d.org_id,
                    d.name,
                    d.{$categoryColumn} AS category_id,
                    c.name AS category_name,
                    d.description,
                    d.yield_servings,
                    d.active,
                    d.created_at,
                    d.updated_at
             FROM dishes d
             JOIN dish_categories c ON c.id = d.{$categoryColumn} AND c.org_id = d.org_id
             WHERE d.org_id = :org_id AND d.id = :id"
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByOrg(PDO $pdo, int $orgId): array
    {
        $categoryColumn = self::categoryColumn($pdo);

        $stmt = $pdo->prepare(
            "SELECT d.id,
                    d.name,
                    d.{$categoryColumn} AS category_id,
                    c.name AS category_name,
                    d.description,
                    d.yield_servings,
                    d.active,
                    d.created_at,
                    d.updated_at
             FROM dishes d
             JOIN dish_categories c ON c.id = d.{$categoryColumn} AND c.org_id = d.org_id
             WHERE d.org_id = :org_id
             ORDER BY c.name ASC, d.name ASC"
        );
        $stmt->execute(['org_id' => $orgId]);

        return $stmt->fetchAll();
    }

    public static function searchByOrg(PDO $pdo, int $orgId, int $categoryId = 0, string $query = ''): array
    {
        $categoryColumn = self::categoryColumn($pdo);

        $sql = "SELECT id, name, description, yield_servings, {$categoryColumn} AS category_id
                FROM dishes
                WHERE org_id = :org_id";
        $params = ['org_id' => $orgId];

        if ($categoryId > 0) {
            $sql .= " AND {$categoryColumn} = :category_id";
            $params['category_id'] = $categoryId;
        }

        if ($query !== '') {
            $sql .= ' AND name LIKE :query';
            $params['query'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY name ASC';

    private static function isUnknownColumnError(PDOException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $vendorCode = (int) ($e->errorInfo[1] ?? 0);
        $message = strtolower($e->getMessage());

        if ($vendorCode === 1054 || $sqlState === '42S22') {
            return true;
        }

        return str_contains($message, 'unknown column');
    }

        return $stmt->fetchAll();
    }

    public static function categoryColumn(PDO $pdo): string
    {
        if (self::$cachedCategoryColumn !== null) {
            return self::$cachedCategoryColumn;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );

        if (!$stmt) {
            self::$resolvedCategoryColumn = self::DEFAULT_CATEGORY_COLUMN;
            return self::$resolvedCategoryColumn;
        }

        foreach (['dish_category_id', 'category_id'] as $column) {
            if ($stmt->execute(['table_name' => 'dishes', 'column_name' => $column]) && $stmt->fetchColumn()) {
                self::$resolvedCategoryColumn = $column;
                return $column;
            }

            $stmt->closeCursor();
        }

        if (in_array('category_id', $found, true)) {
            self::$cachedCategoryColumn = 'category_id';
            return self::$cachedCategoryColumn;
        }

        self::$cachedCategoryColumn = self::FALLBACK_CATEGORY_COLUMN;
        return self::$cachedCategoryColumn;
    }

    private static function dishesTableHasColumn(PDO $pdo, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );

        if (!$stmt) {
            return false;
        }

        $stmt->execute([
            'table_name' => 'dishes',
            'column_name' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
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
