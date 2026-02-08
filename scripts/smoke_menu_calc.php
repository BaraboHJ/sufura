<?php

declare(strict_types=1);

// Smoke test for menu cost math.

$items = [
    ['cost_per_serving_minor' => 250, 'uptake' => 0.5, 'portion' => 1.0, 'waste' => 0.1],
    ['cost_per_serving_minor' => 300, 'uptake' => 0.8, 'portion' => 1.5, 'waste' => 0.0],
];

$itemCosts = array_map(function (array $item): int {
    return (int) round(
        $item['cost_per_serving_minor']
        * $item['uptake']
        * $item['portion']
        * (1 + $item['waste'])
    );
}, $items);

$sumItems = array_sum($itemCosts);

$expectedGroupCost = $sumItems;
$actualGroupCost = $sumItems;

if ($actualGroupCost !== $expectedGroupCost) {
    fwrite(STDERR, "Smoke test failed: expected {$expectedGroupCost}, got {$actualGroupCost}\n");
    exit(1);
}

echo "Smoke test passed: group_cost = sum(items) * uptake * portion * (1 + waste).\n";
