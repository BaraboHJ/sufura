<?php

namespace App\Models;

use PDO;

class Ingredient
{
    public static function create(PDO $pdo, int $orgId, int $actorUserId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO ingredients (org_id, name, uom_set_id, notes, active, created_at)
             VALUES (:org_id, :name, :uom_set_id, :notes, :active, NOW())'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'name' => $payload['name'],
            'uom_set_id' => $payload['uom_set_id'],
            'notes' => $payload['notes'],
            'active' => $payload['active'],
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
            'UPDATE ingredients
             SET name = :name,
                 uom_set_id = :uom_set_id,
                 notes = :notes,
                 active = :active,
                 updated_at = NOW()
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'name' => $payload['name'],
            'uom_set_id' => $payload['uom_set_id'],
            'notes' => $payload['notes'],
            'active' => $payload['active'],
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
            'INSERT INTO ingredient_costs (
                org_id, ingredient_id, purchase_qty, purchase_uom_id, total_cost_minor,
                cost_per_base_x10000, currency, effective_at
             )
             VALUES (
                :org_id, :ingredient_id, :purchase_qty, :purchase_uom_id, :total_cost_minor,
                :cost_per_base_x10000, :currency, :effective_at
             )'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'ingredient_id' => $ingredientId,
            'purchase_qty' => $payload['purchase_qty'] ?? null,
            'purchase_uom_id' => $payload['purchase_uom_id'] ?? null,
            'total_cost_minor' => $payload['total_cost_minor'] ?? null,
            'cost_per_base_x10000' => $payload['cost_per_base_x10000'],
            'currency' => $payload['currency'],
            'effective_at' => $payload['effective_at'],
        ]);

        $after = self::latestCost($pdo, $orgId, $ingredientId);
        Audit::log($pdo, $orgId, $actorUserId, 'ingredient_cost', $ingredientId, 'update_cost', $before, $after);

        return $after ?? [];
    }

    public static function findById(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, org_id, name, uom_set_id, notes, active, created_at, updated_at
             FROM ingredients
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function latestCost(PDO $pdo, int $orgId, int $ingredientId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, ingredient_id, purchase_qty, purchase_uom_id, total_cost_minor,
                    cost_per_base_x10000, currency, effective_at, created_at
             FROM ingredient_costs
             WHERE org_id = :org_id AND ingredient_id = :ingredient_id
             ORDER BY effective_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'ingredient_id' => $ingredientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByOrgWithCosts(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare(
            'SELECT i.id,
                    i.name,
                    i.uom_set_id,
                    i.notes,
                    i.active,
                    i.created_at,
                    i.updated_at,
                    us.name AS uom_set_name,
                    base_uom.symbol AS base_uom_symbol,
                    (SELECT ic.cost_per_base_x10000
                     FROM ingredient_costs ic
                     WHERE ic.org_id = i.org_id AND ic.ingredient_id = i.id
                     ORDER BY ic.effective_at DESC, ic.id DESC
                     LIMIT 1) AS cost_per_base_x10000,
                    (SELECT ic.effective_at
                     FROM ingredient_costs ic
                     WHERE ic.org_id = i.org_id AND ic.ingredient_id = i.id
                     ORDER BY ic.effective_at DESC, ic.id DESC
                     LIMIT 1) AS cost_effective_at
             FROM ingredients i
             JOIN uom_sets us ON us.id = i.uom_set_id AND us.org_id = i.org_id
             JOIN uoms base_uom ON base_uom.uom_set_id = i.uom_set_id
                 AND base_uom.org_id = i.org_id
                 AND base_uom.is_base = 1
             WHERE i.org_id = :org_id
             ORDER BY i.name'
        );
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll();
    }

    public static function findByIdWithMeta(PDO $pdo, int $orgId, int $id): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT i.id,
                    i.org_id,
                    i.name,
                    i.uom_set_id,
                    i.notes,
                    i.active,
                    i.created_at,
                    i.updated_at,
                    us.name AS uom_set_name,
                    base_uom.symbol AS base_uom_symbol
             FROM ingredients i
             JOIN uom_sets us ON us.id = i.uom_set_id AND us.org_id = i.org_id
             JOIN uoms base_uom ON base_uom.uom_set_id = i.uom_set_id
                 AND base_uom.org_id = i.org_id
                 AND base_uom.is_base = 1
             WHERE i.org_id = :org_id AND i.id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listUomSets(PDO $pdo, int $orgId): array
    {
        $stmt = $pdo->prepare(
            'SELECT us.id, us.name, base_uom.symbol AS base_uom_symbol
             FROM uom_sets us
             JOIN uoms base_uom ON base_uom.uom_set_id = us.id
                 AND base_uom.org_id = us.org_id
                 AND base_uom.is_base = 1
             WHERE us.org_id = :org_id
             ORDER BY us.name'
        );
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll();
    }

    public static function uomSetExists(PDO $pdo, int $orgId, int $uomSetId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM uom_sets WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $uomSetId]);
        return (bool) $stmt->fetchColumn();
    }

    public static function findUomById(PDO $pdo, int $orgId, int $uomId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, uom_set_id, name, symbol, factor_to_base, is_base
             FROM uoms
             WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $uomId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function costHistory(PDO $pdo, int $orgId, int $ingredientId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, cost_per_base_x10000, currency, effective_at, created_at
             FROM ingredient_costs
             WHERE org_id = :org_id AND ingredient_id = :ingredient_id
             ORDER BY effective_at DESC, id DESC'
        );
        $stmt->execute(['org_id' => $orgId, 'ingredient_id' => $ingredientId]);
        return $stmt->fetchAll();
    }

    public static function listUomsBySet(PDO $pdo, int $orgId, int $uomSetId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, symbol, factor_to_base, is_base
             FROM uoms
             WHERE org_id = :org_id AND uom_set_id = :uom_set_id
             ORDER BY factor_to_base ASC'
        );
        $stmt->execute(['org_id' => $orgId, 'uom_set_id' => $uomSetId]);
        return $stmt->fetchAll();
    }

    public static function findBaseUomBySet(PDO $pdo, int $orgId, int $uomSetId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, symbol, factor_to_base, is_base
             FROM uoms
             WHERE org_id = :org_id AND uom_set_id = :uom_set_id AND is_base = 1
             LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'uom_set_id' => $uomSetId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function nameExists(PDO $pdo, int $orgId, string $name, ?int $excludeId = null): bool
    {
        $query = 'SELECT id FROM ingredients WHERE org_id = :org_id AND LOWER(name) = LOWER(:name)';
        $params = ['org_id' => $orgId, 'name' => $name];
        if ($excludeId !== null) {
            $query .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public static function delete(PDO $pdo, int $orgId, int $actorUserId, int $id): void
    {
        $before = self::findById($pdo, $orgId, $id);
        $stmt = $pdo->prepare('DELETE FROM ingredient_costs WHERE org_id = :org_id AND ingredient_id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM ingredients WHERE org_id = :org_id AND id = :id');
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        Audit::log($pdo, $orgId, $actorUserId, 'ingredient', $id, 'delete', $before, null);
    }

    public static function hasDishLines(PDO $pdo, int $orgId, int $id): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM dish_lines WHERE org_id = :org_id AND ingredient_id = :id LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hasMenuSnapshots(PDO $pdo, int $orgId, int $id): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM menu_ingredient_cost_snapshots WHERE org_id = :org_id AND ingredient_id = :id LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hasCostImportRows(PDO $pdo, int $orgId, int $id): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM cost_import_rows WHERE org_id = :org_id AND matched_ingredient_id = :id LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'id' => $id]);
        return (bool) $stmt->fetchColumn();
    }
}
