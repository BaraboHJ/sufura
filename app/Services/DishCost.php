<?php

namespace App\Services;

use PDO;

class DishCost
{
    public static function summary(PDO $pdo, int $orgId, int $dishId, int $yieldServings): array
    {
        $breakdown = self::breakdown($pdo, $orgId, $dishId);
        $totalCostMinor = 0;
        $unknownCount = 0;
        $invalidUnitCount = 0;

        foreach ($breakdown as $line) {
            if ($line['invalid_units']) {
                $invalidUnitCount++;
                continue;
            }
            if ($line['cost_per_base_x10000'] === null) {
                $unknownCount++;
                continue;
            }
            $totalCostMinor += $line['line_cost_minor'];
        }

        $status = 'complete';
        if ($invalidUnitCount > 0) {
            $status = 'invalid_units';
        } elseif ($unknownCount > 0) {
            $status = 'incomplete_missing_ingredient_cost';
        }

        $costPerServing = $yieldServings > 0 ? (int) round($totalCostMinor / $yieldServings) : null;

        return [
            'total_cost_minor' => $totalCostMinor,
            'unknown_cost_lines_count' => $unknownCount,
            'invalid_units_count' => $invalidUnitCount,
            'cost_per_serving_minor' => $costPerServing,
            'status' => $status,
            'lines_count' => count($breakdown),
        ];
    }

    public static function breakdown(PDO $pdo, int $orgId, int $dishId): array
    {
        $stmt = $pdo->prepare(
            'SELECT dl.id,
                    dl.ingredient_id,
                    dl.quantity,
                    dl.uom_id,
                    dl.sort_order,
                    i.name AS ingredient_name,
                    i.uom_set_id AS ingredient_uom_set_id,
                    u.name AS uom_name,
                    u.symbol AS uom_symbol,
                    u.uom_set_id AS uom_set_id,
                    u.factor_to_base,
                    base_uom.id AS base_uom_id,
                    base_uom.symbol AS base_uom_symbol,
                    (SELECT ic.cost_per_base_x10000
                     FROM ingredient_costs ic
                     WHERE ic.org_id = dl.org_id AND ic.ingredient_id = dl.ingredient_id
                     ORDER BY ic.effective_at DESC, ic.id DESC
                     LIMIT 1) AS cost_per_base_x10000,
                    (SELECT ic.effective_at
                     FROM ingredient_costs ic
                     WHERE ic.org_id = dl.org_id AND ic.ingredient_id = dl.ingredient_id
                     ORDER BY ic.effective_at DESC, ic.id DESC
                     LIMIT 1) AS cost_effective_at
             FROM dish_lines dl
             JOIN ingredients i ON i.id = dl.ingredient_id AND i.org_id = dl.org_id
             JOIN uoms u ON u.id = dl.uom_id AND u.org_id = dl.org_id
             JOIN uoms base_uom ON base_uom.uom_set_id = i.uom_set_id
                 AND base_uom.org_id = dl.org_id
                 AND base_uom.is_base = 1
             WHERE dl.org_id = :org_id AND dl.dish_id = :dish_id
             ORDER BY dl.sort_order ASC, dl.id ASC'
        );
        $stmt->execute(['org_id' => $orgId, 'dish_id' => $dishId]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row): array {
            $quantity = (float) $row['quantity'];
            $factor = (float) $row['factor_to_base'];
            $qtyInBase = $quantity * $factor;
            $costPerBase = $row['cost_per_base_x10000'] !== null ? (int) $row['cost_per_base_x10000'] : null;
            $invalidUnits = ((int) $row['uom_set_id']) !== ((int) $row['ingredient_uom_set_id']);

            $lineCostMinor = null;
            if ($costPerBase !== null && !$invalidUnits) {
                $lineCostMinor = (int) round(($qtyInBase * $costPerBase) / 10000);
            }

            return [
                'id' => (int) $row['id'],
                'ingredient_id' => (int) $row['ingredient_id'],
                'ingredient_name' => $row['ingredient_name'],
                'quantity' => $quantity,
                'uom_id' => (int) $row['uom_id'],
                'uom_name' => $row['uom_name'],
                'uom_symbol' => $row['uom_symbol'],
                'uom_set_id' => (int) $row['ingredient_uom_set_id'],
                'qty_in_base' => $qtyInBase,
                'base_uom_id' => (int) $row['base_uom_id'],
                'base_uom_symbol' => $row['base_uom_symbol'],
                'cost_per_base_x10000' => $costPerBase,
                'cost_effective_at' => $row['cost_effective_at'],
                'line_cost_minor' => $lineCostMinor,
                'invalid_units' => $invalidUnits,
            ];
        }, $rows);
    }
}
