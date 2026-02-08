<?php

namespace App\Models;

use PDO;

class Ingredient
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO ingredients (org_id, name, base_uom_id, created_at) VALUES (:org_id, :name, :base_uom_id, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'name' => $payload['name'],
            'base_uom_id' => $payload['base_uom_id'],
        ]);

        $id = (int) $pdo->lastInsertId();
        $ingredient = self::findById($pdo, $orgId, $id);

        Audit::log($pdo, $orgId, $actorUserId, 'ingredient', $id, 'create', null, $ingredient);

        return $ingredient ?? [];
    }

    public static function update(PDO $pdo, int $orgId, int $actorUserId, int $id, array $payload): array
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare(
            'UPDATE ingredients SET name = :name, base_uom_id = :base_uom_id, updated_at = NOW() WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
            'base_uom_id' => $payload['base_uom_id'],
            'org_id' => $orgId,
            'id' => $id,
        ]);

        $after = self::findById($pdo, $orgId, $id);
        Audit::log($pdo, $orgId, $actorUserId, 'ingredient', $id, 'update', $before, $after);

        return $after ?? [];
    }

    public static function addCost(PDO $pdo, int $orgId, int $actorUserId, int $ingredientId, array $payload): array
    {
        $before = self::latestCost($pdo, $orgId, $ingredientId);
        $stmt = $pdo->prepare(
            'INSERT INTO ingredient_costs (org_id, ingredient_id, cost_per_base_minor, currency, effective_at)
             VALUES (:org_id, :ingredient_id, :cost_per_base_minor, :currency, :effective_at)'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'ingredient_id' => $ingredientId,
            'cost_per_base_minor' => $payload['cost_per_base_minor'],
            'currency' => $payload['currency'],
            'effective_at' => $payload['effective_at'],
        ]);

        $after = self::latestCost($pdo, $orgId, $ingredientId);
        Audit::log($pdo, $orgId, $actorUserId, 'ingredient_cost', $ingredientId, 'update_cost', $before, $after);

        return $after ?? [];
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, org_id, name, base_uom_id, created_at, updated_at FROM ingredients WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function latestCost(PDO $pdo, int $orgId, int $ingredientId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, ingredient_id, cost_per_base_minor, currency, effective_at, created_at
             FROM ingredient_costs
             WHERE org_id = :org_id AND ingredient_id = :ingredient_id
             ORDER BY effective_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'ingredient_id' => $ingredientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
