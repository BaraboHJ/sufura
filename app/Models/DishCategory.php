<?php

namespace App\Models;

use PDO;

class DishCategory
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO dish_categories (org_id, name, created_at, updated_at)
             VALUES (:org_id, :name, NOW(), NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'name' => $payload['name'],
        ]);

        $id = (int) $pdo->lastInsertId();
        $category = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish_category', $id, 'create', null, $category);

        return $category ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE dish_categories
             SET name = :name,
                 updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish_category', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function delete(PDO $pdo, int $orgId, int $actorUserId, int $id): void
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare('DELETE FROM dish_categories WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        Audit::log($pdo, $orgId, $actorUserId, 'dish_category', $id, 'delete', $before, null);
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, org_id, name, created_at, updated_at
             FROM dish_categories
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function listByOrg(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, created_at, updated_at
             FROM dish_categories
             WHERE org_id = :org_id
             ORDER BY name ASC'
        );
        $stmt->execute(['org_id' => $orgId]);

        return $stmt->fetchAll();
    }

    public static function nameExists(PDO $pdo, int $orgId, string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT 1 FROM dish_categories WHERE org_id = :org_id AND LOWER(name) = LOWER(:name)';
        $params = ['org_id' => $orgId, 'name' => $name];
        if ($ignoreId) {
            $sql .= ' AND id != :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public static function hasDishes(PDO $pdo, int $orgId, int $id): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM dishes WHERE org_id = :org_id AND category_id = :id LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);

        return (bool) $stmt->fetchColumn();
    }
}
