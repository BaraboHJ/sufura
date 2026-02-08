<?php

namespace App\Models;

use PDO;

class DishLine
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, int $dishId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO dish_lines (org_id, dish_id, ingredient_id, quantity, uom_id, sort_order, created_at)
             VALUES (:org_id, :dish_id, :ingredient_id, :quantity, :uom_id, :sort_order, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'dish_id' => $dishId,
            'ingredient_id' => $payload['ingredient_id'],
            'quantity' => $payload['quantity'],
            'uom_id' => $payload['uom_id'],
            'sort_order' => $payload['sort_order'] ?? 0,
        ]);

        $id = (int) $pdo->lastInsertId();
        $line = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish_line', $id, 'create', null, $line);

        return $line ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE dish_lines
             SET ingredient_id = :ingredient_id,
                 quantity = :quantity,
                 uom_id = :uom_id,
                 sort_order = :sort_order
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'ingredient_id' => $payload['ingredient_id'],
            'quantity' => $payload['quantity'],
            'uom_id' => $payload['uom_id'],
            'sort_order' => $payload['sort_order'] ?? 0,
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'dish_line', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function delete(PDO $pdo, int $orgId, int $actorUserId, int $id): void
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare('DELETE FROM dish_lines WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        Audit::log($pdo, $orgId, $actorUserId, 'dish_line', $id, 'delete', $before, null);
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, org_id, dish_id, ingredient_id, quantity, uom_id, sort_order, waste_pct, created_at
             FROM dish_lines
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByDish(PDO $pdo, int $orgId, int $dishId): array
    {
        $stmt = $pdo->prepare(
            'SELECT dl.id,
                    dl.ingredient_id,
                    dl.quantity,
                    dl.uom_id,
                    dl.sort_order,
                    i.name AS ingredient_name,
                    i.uom_set_id,
                    u.name AS uom_name,
                    u.symbol AS uom_symbol
             FROM dish_lines dl
             JOIN ingredients i ON i.id = dl.ingredient_id AND i.org_id = dl.org_id
             JOIN uoms u ON u.id = dl.uom_id AND u.org_id = dl.org_id
             WHERE dl.org_id = :org_id AND dl.dish_id = :dish_id
             ORDER BY dl.sort_order ASC, dl.id ASC'
        );
        $stmt->execute(['org_id' => $orgId, 'dish_id' => $dishId]);
        return $stmt->fetchAll();
    }
}
