<?php

namespace App\Models;

use PDO;

class Menu
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO menus (org_id, name, servings, cost_mode, created_at) VALUES (:org_id, :name, :servings, :cost_mode, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'name' => $payload['name'],
            'servings' => $payload['servings'] ?? 1,
            'cost_mode' => $payload['cost_mode'] ?? 'live',
        ]);

        $id = (int) $pdo->lastInsertId();
        $menu = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu', $id, 'create', null, $menu);

        return $menu ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE menus SET name = :name, servings = :servings, updated_at = NOW() WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
            'servings' => $payload['servings'] ?? 1,
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function lock(PDO $pdo, int $orgId, int $actorUserId, int $id): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE menus SET cost_mode = "locked", locked_at = NOW(), locked_by_user_id = :actor, updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'actor' => $actorUserId,
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu', $id, 'lock', $before, $after);

        return $after ?? [];
    }

    public static function unlock(PDO $pdo, int $orgId, int $actorUserId, int $id): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE menus SET cost_mode = "live", locked_at = NULL, locked_by_user_id = NULL, updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu', $id, 'unlock', $before, $after);

        return $after ?? [];
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, org_id, name, servings, cost_mode, locked_at, locked_by_user_id, created_at, updated_at
             FROM menus WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
