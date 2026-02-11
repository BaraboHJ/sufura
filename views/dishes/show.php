<?php
$currency = $currency ?? 'USD';
$summary = $summary ?? [];
$breakdown = $breakdown ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars((string) ($dish['name'] ?? 'Dish')) ?></h1>
        <p class="text-muted mb-0"><?= htmlspecialchars((string) ($dish['category_name'] ?? '')) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/dishes">Back</a>
        <a class="btn btn-primary" href="/dishes/<?= (int) $dish['id'] ?>/edit">Edit</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Yield</div><div class="h5 mb-0"><?= (int) ($dish['yield_servings'] ?? 1) ?> servings</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Status</div><div class="h5 mb-0"><?= ((int) ($dish['active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Total cost</div><div class="h5 mb-0"><?= htmlspecialchars($currency) ?> <?= number_format(((int) ($summary['total_cost_x10000'] ?? 0)) / 10000, 2) ?></div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Cost per serving</div><div class="h5 mb-0"><?= htmlspecialchars($currency) ?> <?= number_format(((int) ($summary['cost_per_serving_x10000'] ?? 0)) / 10000, 2) ?></div></div></div></div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h6">Description</h2>
        <p class="mb-0"><?= nl2br(htmlspecialchars((string) ($dish['description'] ?? 'No description.'))) ?></p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><strong>Ingredient cost breakdown</strong></div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Ingredient</th><th>Qty</th><th>UOM</th><th>Line cost</th></tr></thead>
            <tbody>
            <?php if (empty($breakdown)): ?>
                <tr><td colspan="4" class="text-center text-muted py-4">No ingredients added yet.</td></tr>
            <?php else: ?>
                <?php foreach ($breakdown as $line): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($line['ingredient_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($line['quantity'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($line['uom_name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($currency) ?> <?= number_format(((int) ($line['line_cost_x10000'] ?? 0)) / 10000, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
