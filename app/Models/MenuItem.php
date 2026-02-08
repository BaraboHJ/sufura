<?php

namespace App\Models;

use PDO;

class MenuItem
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, int $groupId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO menu_items
                (org_id, menu_group_id, dish_id, display_name, display_description, uptake_pct, portion, waste_pct, selling_price_minor, sort_order, created_at)
             VALUES
                (:org_id, :menu_group_id, :dish_id, :display_name, :display_description, :uptake_pct, :portion, :waste_pct, :selling_price_minor, :sort_order, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'menu_group_id' => $groupId,
            'dish_id' => $payload['dish_id'],
            'display_name' => $payload['display_name'],
            'display_description' => $payload['display_description'],
            'uptake_pct' => $payload['uptake_pct'],
            'portion' => $payload['portion'],
            'waste_pct' => $payload['waste_pct'],
            'selling_price_minor' => $payload['selling_price_minor'],
            'sort_order' => $payload['sort_order'] ?? 0,
        ]);

        $id = (int) $pdo->lastInsertId();
        $item = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu_item', $id, 'create', null, $item);

        return $item ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE menu_items
             SET display_name = :display_name,
                 display_description = :display_description,
                 uptake_pct = :uptake_pct,
                 portion = :portion,
                 waste_pct = :waste_pct,
                 selling_price_minor = :selling_price_minor,
                 sort_order = :sort_order,
                 updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'display_name' => $payload['display_name'],
            'display_description' => $payload['display_description'],
            'uptake_pct' => $payload['uptake_pct'],
            'portion' => $payload['portion'],
            'waste_pct' => $payload['waste_pct'],
            'selling_price_minor' => $payload['selling_price_minor'],
            'sort_order' => $payload['sort_order'] ?? 0,
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'menu_item', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function delete(PDO $pdo, int $orgId, int $actorUserId, int $id): void
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare('DELETE FROM menu_items WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        Audit::log($pdo, $orgId, $actorUserId, 'menu_item', $id, 'delete', $before, null);
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, org_id, menu_group_id, dish_id, display_name, display_description, uptake_pct, portion, waste_pct,
                    selling_price_minor, sort_order, created_at, updated_at
             FROM menu_items
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByMenu(PDO $pdo, int $orgId, int $menuId): array
    {
        $stmt = $pdo->prepare(
            'SELECT mi.id,
                    mi.menu_group_id,
                    mi.dish_id,
                    mi.display_name,
                    mi.display_description,
                    mi.uptake_pct,
                    mi.portion,
                    mi.waste_pct,
                    mi.selling_price_minor,
                    mi.sort_order,
                    mi.created_at,
                    mi.updated_at,
                    d.name AS dish_name,
                    d.description AS dish_description,
                    d.yield_servings AS dish_yield_servings
             FROM menu_items mi
             JOIN menu_groups mg ON mg.id = mi.menu_group_id AND mg.org_id = mi.org_id
             JOIN dishes d ON d.id = mi.dish_id AND d.org_id = mi.org_id
             WHERE mi.org_id = :org_id AND mg.menu_id = :menu_id
             ORDER BY mg.sort_order ASC, mi.sort_order ASC, mi.id ASC'
        );
        $stmt->execute(['org_id' => $orgId, 'menu_id' => $menuId]);
        return $stmt->fetchAll();
    }
}
