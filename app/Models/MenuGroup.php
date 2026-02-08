<?php

namespace App\Models;

use PDO;

class MenuGroup
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, int $menuId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO menu_groups (org_id, menu_id, name, uptake_pct, portion, waste_pct, sort_order, created_at)
             VALUES (:org_id, :menu_id, :name, :uptake_pct, :portion, :waste_pct, :sort_order, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'menu_id' => $menuId,
            'name' => $payload['name'],
            'uptake_pct' => $payload['uptake_pct'],
            'portion' => $payload['portion'],
            'waste_pct' => $payload['waste_pct'],
            'sort_order' => $payload['sort_order'] ?? 0,
        ]);

        $id = (int) $pdo->lastInsertId();
        $group = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu_group', $id, 'create', null, $group);

        return $group ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE menu_groups
             SET name = :name,
                 uptake_pct = :uptake_pct,
                 portion = :portion,
                 waste_pct = :waste_pct,
                 sort_order = :sort_order,
                 updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
            'uptake_pct' => $payload['uptake_pct'],
            'portion' => $payload['portion'],
            'waste_pct' => $payload['waste_pct'],
            'sort_order' => $payload['sort_order'] ?? 0,
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu_group', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function delete(PDO $pdo, int $orgId, int $actorUserId, int $id): void
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare('DELETE FROM menu_groups WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        Audit::log($pdo, $orgId, $actorUserId, 'menu_group', $id, 'delete', $before, null);
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, org_id, menu_id, name, uptake_pct, portion, waste_pct, sort_order, created_at, updated_at
             FROM menu_groups WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByMenu(PDO $pdo, int $orgId, int $menuId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, menu_id, name, uptake_pct, portion, waste_pct, sort_order, created_at, updated_at
             FROM menu_groups
             WHERE org_id = :org_id AND menu_id = :menu_id
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['org_id' => $orgId, 'menu_id' => $menuId]);
        return $stmt->fetchAll();
    }
}
