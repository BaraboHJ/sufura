<?php

namespace App\Services;

use PDO;

class MenuCost
{
    public static function computeLive(PDO $pdo, array $menu, array $groups, array $items, ?int $paxCount): array
    {
        $itemsByGroup = [];
        foreach ($items as $item) {
            $itemsByGroup[$item['menu_group_id']][] = $item;
        }

        $menuCostPerPax = 0;
        $missingDishCosts = 0;
        $groupRows = [];
        $itemRows = [];
        $currency = $menu['currency'] ?? 'USD';
        $defaultWaste = isset($menu['default_waste_pct']) ? (float) $menu['default_waste_pct'] : 0.0;

        foreach ($groups as $group) {
            $groupCost = 0;
            $groupItems = $itemsByGroup[$group['id']] ?? [];

            foreach ($groupItems as $item) {
                $dishCostPerServing = $item['dish_cost_per_serving_minor'];
                if ($dishCostPerServing === null) {
                    $missingDishCosts++;
                    $dishCostPerServing = 0;
                }

                $uptake = $item['uptake_pct'] ?? $group['uptake_pct'] ?? 1.0;
                $portion = $item['portion'] ?? $group['portion'] ?? 1.0;
                $waste = $item['waste_pct'] ?? $group['waste_pct'] ?? $defaultWaste ?? 0.0;

                $itemCostPerPax = (int) round($dishCostPerServing * $uptake * $portion * (1 + $waste));
                $groupCost += $itemCostPerPax;

                $itemRows[] = [
                    'id' => (int) $item['id'],
                    'menu_group_id' => (int) $item['menu_group_id'],
                    'dish_id' => (int) $item['dish_id'],
                    'dish_name' => $item['dish_name'],
                    'display_name' => $item['display_name'],
                    'display_description' => $item['display_description'],
                    'effective_uptake_pct' => (float) $uptake,
                    'effective_portion' => (float) $portion,
                    'effective_waste_pct' => (float) $waste,
                    'dish_cost_per_serving_minor' => $item['dish_cost_per_serving_minor'],
                    'item_cost_per_pax_minor' => $itemCostPerPax,
                    'selling_price_minor' => $item['selling_price_minor'],
                ];
            }

            $menuCostPerPax += $groupCost;
            $groupRows[] = [
                'id' => (int) $group['id'],
                'name' => $group['name'],
                'cost_per_pax_minor' => $groupCost,
            ];
        }

        $totals = self::computeRevenueTotals($menu, $itemRows, $menuCostPerPax, $paxCount);

        return [
            'menu_cost_per_pax_minor' => $menuCostPerPax,
            'currency' => $currency,
            'groups' => $groupRows,
            'items' => $itemRows,
            'missing_dish_costs' => $missingDishCosts,
            'totals' => $totals,
        ];
    }

    public static function computeLocked(PDO $pdo, array $menu, array $groups, array $items, ?int $paxCount): array
    {
        $itemsByGroup = [];
        foreach ($items as $item) {
            $itemsByGroup[$item['menu_group_id']][] = $item;
        }

        $menuCostPerPax = 0;
        $groupRows = [];
        $itemRows = [];
        $currency = $menu['currency'] ?? 'USD';

        foreach ($groups as $group) {
            $groupCost = 0;
            foreach ($itemsByGroup[$group['id']] ?? [] as $item) {
                $groupCost += (int) $item['item_cost_per_pax_minor'];
                $itemRows[] = [
                    'id' => (int) $item['id'],
                    'menu_group_id' => (int) $item['menu_group_id'],
                    'dish_id' => (int) $item['dish_id'],
                    'dish_name' => $item['dish_name'],
                    'display_name' => $item['display_name'],
                    'display_description' => $item['display_description'],
                    'effective_uptake_pct' => (float) $item['effective_uptake_pct'],
                    'effective_portion' => (float) $item['effective_portion'],
                    'effective_waste_pct' => (float) $item['effective_waste_pct'],
                    'dish_cost_per_serving_minor' => $item['dish_cost_per_serving_minor'],
                    'item_cost_per_pax_minor' => (int) $item['item_cost_per_pax_minor'],
                    'selling_price_minor' => $item['selling_price_minor'],
                ];
            }

            $menuCostPerPax += $groupCost;
            $groupRows[] = [
                'id' => (int) $group['id'],
                'name' => $group['name'],
                'cost_per_pax_minor' => $groupCost,
            ];
        }

        $totals = self::computeRevenueTotals($menu, $itemRows, $menuCostPerPax, $paxCount);

        return [
            'menu_cost_per_pax_minor' => $menuCostPerPax,
            'currency' => $currency,
            'groups' => $groupRows,
            'items' => $itemRows,
            'missing_dish_costs' => 0,
            'totals' => $totals,
        ];
    }

    private static function computeRevenueTotals(array $menu, array $items, int $menuCostPerPax, ?int $paxCount): array
    {
        $pax = $paxCount ?? 0;
        $menuType = $menu['menu_type'] ?? 'package';
        $totals = [
            'pax_count' => $paxCount,
            'menu_cost_per_pax_minor' => $menuCostPerPax,
            'total_cost_minor' => $paxCount !== null ? $menuCostPerPax * $pax : null,
            'revenue_min_minor' => null,
            'revenue_max_minor' => null,
            'profit_min_minor' => null,
            'profit_max_minor' => null,
            'food_cost_pct_min' => null,
            'food_cost_pct_max' => null,
            'per_item_total_revenue_minor' => null,
            'per_item_total_cost_minor' => null,
            'per_item_food_cost_pct' => null,
            'per_item_lines' => [],
        ];

        if ($paxCount === null) {
            return $totals;
        }

        if ($menuType === 'package') {
            $priceMin = $menu['price_min_minor'] ?? null;
            $priceMax = $menu['price_max_minor'] ?? null;
            $priceMax = $priceMax ?? $priceMin;
            $revenueMin = $priceMin !== null ? $pax * $priceMin : null;
            $revenueMax = $priceMax !== null ? $pax * $priceMax : null;
            $totalCost = $menuCostPerPax * $pax;

            $totals['total_cost_minor'] = $totalCost;
            $totals['revenue_min_minor'] = $revenueMin;
            $totals['revenue_max_minor'] = $revenueMax;
            $totals['profit_min_minor'] = $revenueMin !== null ? $revenueMin - $totalCost : null;
            $totals['profit_max_minor'] = $revenueMax !== null ? $revenueMax - $totalCost : null;
            $totals['food_cost_pct_min'] = ($revenueMin && $revenueMin > 0) ? ($totalCost / $revenueMin) : null;
            $totals['food_cost_pct_max'] = ($revenueMax && $revenueMax > 0) ? ($totalCost / $revenueMax) : null;
        } else {
            $totalRevenue = 0;
            $totalCost = 0;
            foreach ($items as $item) {
                $uptake = (float) $item['effective_uptake_pct'];
                $portion = (float) $item['effective_portion'];
                $waste = (float) $item['effective_waste_pct'];
                $expectedQty = $pax * $uptake * $portion;
                $sellingPrice = $item['selling_price_minor'] ?? 0;
                $dishCostPerServing = $item['dish_cost_per_serving_minor'] ?? 0;
                $itemRevenue = (int) round($expectedQty * $sellingPrice);
                $itemCost = (int) round($expectedQty * $dishCostPerServing * (1 + $waste));
                $totalRevenue += $itemRevenue;
                $totalCost += $itemCost;
                $totals['per_item_lines'][] = [
                    'id' => $item['id'],
                    'expected_qty' => $expectedQty,
                    'revenue_minor' => $itemRevenue,
                    'cost_minor' => $itemCost,
                ];
            }
            $totals['per_item_total_revenue_minor'] = $totalRevenue;
            $totals['per_item_total_cost_minor'] = $totalCost;
            $totals['per_item_food_cost_pct'] = $totalRevenue > 0 ? ($totalCost / $totalRevenue) : null;
        }

        return $totals;
    }
}
