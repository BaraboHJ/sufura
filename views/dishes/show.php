<?php
function format_money(?int $minor, string $currency): string
{
    if ($minor === null) {
        return '—';
    }
    return sprintf('%s %s', $currency, number_format($minor / 100, 2));
}

function format_cost_per_base(?int $costX10000, string $currency): string
{
    if ($costX10000 === null) {
        return '—';
    }
    return sprintf('%s %s', $currency, number_format($costX10000 / 1000000, 4));
}

$statusLabels = [
    'complete' => ['label' => 'Complete', 'class' => 'bg-success'],
    'incomplete_missing_ingredient_cost' => ['label' => 'Missing costs', 'class' => 'bg-warning text-dark'],
    'invalid_units' => ['label' => 'Invalid units', 'class' => 'bg-danger'],
];

$status = $summary['status'] ?? 'incomplete_missing_ingredient_cost';
$statusConfig = $statusLabels[$status] ?? $statusLabels['incomplete_missing_ingredient_cost'];
$knownCount = (int) ($summary['lines_count'] ?? 0) - (int) ($summary['unknown_cost_lines_count'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h4 mb-1"><?= htmlspecialchars($dish['name'], ENT_QUOTES) ?></h1>
        <div class="text-muted">Yield servings: <?= (int) $dish['yield_servings'] ?></div>
        <?php if (!empty($dish['description'])): ?>
            <div class="text-muted mt-2"><?= nl2br(htmlspecialchars($dish['description'], ENT_QUOTES)) ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/dishes/<?= (int) $dish['id'] ?>/edit">Edit</a>
        <a class="btn btn-outline-secondary" href="/dishes">Back</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Status</h2>
                    <span class="badge <?= $statusConfig['class'] ?>"><?= $statusConfig['label'] ?></span>
                </div>
                <div class="mt-3 text-muted small">
                    <?php if ((int) ($summary['lines_count'] ?? 0) > 0): ?>
                        <?= $knownCount ?>/<?= (int) $summary['lines_count'] ?> ingredients costed
                    <?php else: ?>
                        No recipe lines yet.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6">Total cost</h2>
                <div class="display-6 fw-semibold">
                    <?= format_money($summary['total_cost_minor'] ?? null, $currency) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6">Cost per serving</h2>
                <div class="display-6 fw-semibold">
                    <?= format_money($summary['cost_per_serving_minor'] ?? null, $currency) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($missingIngredients)): ?>
    <div class="alert alert-warning">
        <div class="fw-semibold mb-2">Missing ingredient costs</div>
        <ul class="mb-0">
            <?php foreach ($missingIngredients as $line): ?>
                <li>
                    <a href="/ingredients/<?= (int) $line['ingredient_id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($line['ingredient_name'], ENT_QUOTES) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Cost breakdown</h2>
        <a class="btn btn-sm btn-outline-secondary" href="/api/dishes/<?= (int) $dish['id'] ?>/cost_breakdown">Download JSON</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th>Ingredient</th>
                    <th>Qty</th>
                    <th>UoM</th>
                    <th>Qty in base</th>
                    <th>Cost per base</th>
                    <th>Line cost</th>
                    <th>Last cost update</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($breakdown)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No recipe lines yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($breakdown as $line): ?>
                        <tr>
                            <td><?= htmlspecialchars($line['ingredient_name'], ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars((string) $line['quantity'], ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($line['uom_symbol'], ENT_QUOTES) ?></td>
                            <td>
                                <?= number_format($line['qty_in_base'], 4) ?>
                                <?= htmlspecialchars($line['base_uom_symbol'], ENT_QUOTES) ?>
                            </td>
                            <td><?= format_cost_per_base($line['cost_per_base_x10000'], $currency) ?></td>
                            <td><?= $line['line_cost_minor'] !== null ? format_money((int) $line['line_cost_minor'], $currency) : '—' ?></td>
                            <td><?= $line['cost_effective_at'] ? htmlspecialchars($line['cost_effective_at'], ENT_QUOTES) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
