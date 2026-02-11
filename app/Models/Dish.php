<?php

namespace App\Models;

use PDO;

class Dish
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO dishes (org_id, name, category_id, description, yield_servings, active, created_at)
             VALUES (:org_id, :name, :category_id, :description, :yield_servings, :active, NOW())'
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
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE dishes
             SET name = :name,
                 category_id = :category_id,
                 description = :description,
                 yield_servings = :yield_servings,
                 active = :active,
                 updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
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
        $stmt = $pdo->prepare(
            'SELECT d.id, d.org_id, d.name, d.category_id, c.name AS category_name, d.description, d.yield_servings, d.active, d.created_at, d.updated_at
             FROM dishes d
             JOIN dish_categories c ON c.id = d.category_id AND c.org_id = d.org_id
             WHERE d.org_id = :org_id AND d.id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByOrg(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare(
            'SELECT d.id, d.name, d.category_id, c.name AS category_name, d.description, d.yield_servings, d.active, d.created_at, d.updated_at
             FROM dishes d
             JOIN dish_categories c ON c.id = d.category_id AND c.org_id = d.org_id
             WHERE d.org_id = :org_id
             ORDER BY c.name, d.name'
        );
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll();
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
