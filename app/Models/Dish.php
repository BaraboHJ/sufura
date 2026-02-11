<?php

namespace App\Models;

use PDO;
use PDOException;

class Dish
{
    private static ?string $categoryColumn = null;

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

        $id = (int) $pdo->lastInsertId();
        $dish = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish', $id, 'create', null, $dish);

        return $dish ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $categoryColumn = self::categoryColumn($pdo);
        $before = self::findById($pdo, $orgId, $id);
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

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $categoryColumn = self::categoryColumn($pdo);
        $stmt = $pdo->prepare(
            "SELECT d.id, d.org_id, d.name, d.{$categoryColumn} AS category_id, c.name AS category_name, d.description, d.yield_servings, d.active, d.created_at, d.updated_at
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
            "SELECT d.id, d.name, d.{$categoryColumn} AS category_id, c.name AS category_name, d.description, d.yield_servings, d.active, d.created_at, d.updated_at
             FROM dishes d
             JOIN dish_categories c ON c.id = d.{$categoryColumn} AND c.org_id = d.org_id
             WHERE d.org_id = :org_id
             ORDER BY c.name, d.name"
        );
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll();
    }

    public static function categoryColumn(PDO $pdo): string
    {
        if (self::$categoryColumn !== null) {
            return self::$categoryColumn;
        }

        if (self::columnExists($pdo, 'category_id')) {
            self::$categoryColumn = 'category_id';
            return self::$categoryColumn;
        }

        if (self::columnExists($pdo, 'dish_category_id')) {
            self::$categoryColumn = 'dish_category_id';
            return self::$categoryColumn;
        }

        self::$categoryColumn = 'category_id';
        return self::$categoryColumn;
    }

    private static function columnExists(PDO $pdo, string $column): bool
    {
        try {
            $pdo->query("SELECT {$column} FROM dishes LIMIT 0");
            return true;
        } catch (PDOException $e) {
            return false;
        }
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
        $stmt = $pdo->prepare(
            'SELECT 1 FROM menu_items WHERE org_id = :org_id AND dish_id = :id LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hasMenuSnapshots(PDO $pdo, int $orgId, int $id): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM menu_item_cost_snapshots WHERE org_id = :org_id AND dish_id = :id LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        return (bool) $stmt->fetchColumn();
    }
}
