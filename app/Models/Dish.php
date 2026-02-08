<?php

namespace App\Models;

use PDO;

class Dish
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO dishes (org_id, name, description, yield_servings, active, created_at)
             VALUES (:org_id, :name, :description, :yield_servings, :active, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'name' => $payload['name'],
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
                 description = :description,
                 yield_servings = :yield_servings,
                 active = :active,
                 updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
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
            'SELECT id, org_id, name, description, yield_servings, active, created_at, updated_at
             FROM dishes
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByOrg(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, description, yield_servings, active, created_at, updated_at
             FROM dishes
             WHERE org_id = :org_id
             ORDER BY name'
        );
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll();
    }
}
