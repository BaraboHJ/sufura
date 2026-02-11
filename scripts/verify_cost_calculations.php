<?php

declare(strict_types=1);

require __DIR__ . '/../app/Services/MenuCost.php';

use App\Services\MenuCost;

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function assertSameValue($actual, $expected, string $label): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "[FAIL] {$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');

$menuPackage = [
    'currency' => 'MVR',
    'menu_type' => 'package',
    'default_waste_pct' => 0.10,
    'price_min_minor' => 25000,
    'price_max_minor' => 30000,
];
$groups = [
    ['id' => 10, 'name' => 'Mains', 'uptake_pct' => 0.8, 'portion' => 1.0, 'waste_pct' => 0.05],
    ['id' => 11, 'name' => 'Dessert', 'uptake_pct' => 0.5, 'portion' => 1.0, 'waste_pct' => 0.10],
];
$items = [
    [
        'id' => 1,
        'menu_group_id' => 10,
        'dish_id' => 101,
        'dish_name' => 'Chicken',
        'display_name' => 'Chicken',
        'display_description' => '',
        'dish_cost_per_serving_minor' => 1000,
        'uptake_pct' => null,
        'portion' => null,
        'waste_pct' => null,
        'selling_price_minor' => 3500,
    ],
    [
        'id' => 2,
        'menu_group_id' => 10,
        'dish_id' => 102,
        'dish_name' => 'Fish',
        'display_name' => 'Fish',
        'display_description' => '',
        'dish_cost_per_serving_minor' => 750,
        'uptake_pct' => 0.3,
        'portion' => 1.5,
        'waste_pct' => 0.2,
        'selling_price_minor' => 3200,
    ],
    [
        'id' => 3,
        'menu_group_id' => 11,
        'dish_id' => 103,
        'dish_name' => 'Cake',
        'display_name' => 'Cake',
        'display_description' => '',
        'dish_cost_per_serving_minor' => null,
        'uptake_pct' => 0.5,
        'portion' => 1.0,
        'waste_pct' => 0.1,
        'selling_price_minor' => 1500,
    ],
];

$live = MenuCost::computeLive($pdo, $menuPackage, $groups, $items, 100);

$expectedItem1 = (int) round(1000 * 0.8 * 1.0 * (1 + 0.05));
$expectedItem2 = (int) round(750 * 0.3 * 1.5 * (1 + 0.2));
$expectedItem3 = 0;
$expectedGroup10 = $expectedItem1 + $expectedItem2;
$expectedGroup11 = $expectedItem3;
$expectedMenuPerPax = $expectedGroup10 + $expectedGroup11;
$expectedTotalCost = $expectedMenuPerPax * 100;

assertSameValue($live['items'][0]['item_cost_per_pax_minor'], $expectedItem1, 'live item1 cost/pax');
assertSameValue($live['items'][1]['item_cost_per_pax_minor'], $expectedItem2, 'live item2 cost/pax');
assertSameValue($live['groups'][0]['cost_per_pax_minor'], $expectedGroup10, 'live group 10 cost/pax');
assertSameValue($live['groups'][1]['cost_per_pax_minor'], $expectedGroup11, 'live group 11 cost/pax');
assertSameValue($live['menu_cost_per_pax_minor'], $expectedMenuPerPax, 'live menu cost/pax');
assertSameValue($live['missing_dish_costs'], 1, 'live missing dish count');
assertSameValue($live['totals']['total_cost_minor'], $expectedTotalCost, 'live total cost');
assertSameValue($live['totals']['revenue_min_minor'], 2500000, 'package min revenue');
assertSameValue($live['totals']['revenue_max_minor'], 3000000, 'package max revenue');
assertSameValue($live['totals']['profit_min_minor'], 2500000 - $expectedTotalCost, 'package min profit');

$lockedItems = [
    [
        'id' => 1,
        'menu_group_id' => 10,
        'dish_id' => 101,
        'dish_name' => 'Chicken',
        'display_name' => 'Chicken',
        'display_description' => '',
        'effective_uptake_pct' => 0.8,
        'effective_portion' => 1.0,
        'effective_waste_pct' => 0.05,
        'dish_cost_per_serving_minor' => 1000,
        'item_cost_per_pax_minor' => $expectedItem1,
        'selling_price_minor' => 3500,
    ],
    [
        'id' => 2,
        'menu_group_id' => 10,
        'dish_id' => 102,
        'dish_name' => 'Fish',
        'display_name' => 'Fish',
        'display_description' => '',
        'effective_uptake_pct' => 0.3,
        'effective_portion' => 1.5,
        'effective_waste_pct' => 0.2,
        'dish_cost_per_serving_minor' => 750,
        'item_cost_per_pax_minor' => $expectedItem2,
        'selling_price_minor' => 3200,
    ],
];
$locked = MenuCost::computeLocked($pdo, $menuPackage, [$groups[0]], $lockedItems, 100);
assertSameValue($locked['menu_cost_per_pax_minor'], $expectedGroup10, 'locked menu cost/pax');
assertSameValue($locked['totals']['total_cost_minor'], $expectedGroup10 * 100, 'locked total cost');

$menuPerItem = [
    'currency' => 'MVR',
    'menu_type' => 'per_item',
    'default_waste_pct' => 0.10,
];
$perItem = MenuCost::computeLive($pdo, $menuPerItem, [$groups[0]], [$items[0], $items[1]], 120);
$expectedQty1 = 120 * 0.8 * 1.0;
$expectedQty2 = 120 * 0.3 * 1.5;
$expectedRevenue = (int) round($expectedQty1 * 3500) + (int) round($expectedQty2 * 3200);
$expectedCost = (int) round($expectedQty1 * 1000 * 1.05) + (int) round($expectedQty2 * 750 * 1.2);
assertSameValue($perItem['totals']['per_item_total_revenue_minor'], $expectedRevenue, 'per-item total revenue');
assertSameValue($perItem['totals']['per_item_total_cost_minor'], $expectedCost, 'per-item total cost');

fwrite(STDOUT, "All cost calculation checks passed.\n");
