<?php
function format_cost_per_base(?int $costX10000, string $currency, string $baseSymbol): string
{
    if (!$costX10000) {
        return '—';
    }
    $major = $costX10000 / 1000000;
    return sprintf('%s %s / %s', $currency, number_format($major, 4), $baseSymbol);
}

$statusLabels = [
    'missing' => ['label' => 'Missing', 'class' => 'bg-danger'],
    'stale' => ['label' => 'Stale', 'class' => 'bg-warning text-dark'],
    'ok' => ['label' => 'OK', 'class' => 'bg-success'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Ingredients</h1>
        <p class="text-muted mb-0">Manage ingredient units and costs.</p>
    </div>
    <a class="btn btn-primary" href="/ingredients/new">New Ingredient</a>
</div>

<?php if ($missingCount > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <div>
            <strong><?= (int) $missingCount ?></strong> ingredients missing costs.
        </div>
        <a class="btn btn-sm btn-outline-dark" href="/ingredients?status=missing">Review missing</a>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/ingredients">All</a>
            <a class="btn btn-sm <?= $statusFilter === 'missing' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/ingredients?status=missing">Missing</a>
            <a class="btn btn-sm <?= $statusFilter === 'stale' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/ingredients?status=stale">Stale</a>
            <a class="btn btn-sm <?= $statusFilter === 'ok' ? 'btn-primary' : 'btn-outline-primary' ?>" href="/ingredients?status=ok">OK</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>UoM Set</th>
                        <th>Status</th>
                        <th>Current Cost</th>
                        <th>Last Updated</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ingredients)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No ingredients found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ingredients as $ingredient): ?>
                            <?php
                            $status = $ingredient['status'] ?? 'missing';
                            $statusConfig = $statusLabels[$status] ?? $statusLabels['missing'];
                            ?>
                            <tr>
                                <td>
                                    <a class="text-decoration-none" href="/ingredients/<?= (int) $ingredient['id'] ?>">
                                        <?= htmlspecialchars($ingredient['name'], ENT_QUOTES) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ingredient['uom_set_name'], ENT_QUOTES) ?>
                                    <div class="text-muted small">Base: <?= htmlspecialchars($ingredient['base_uom_symbol'], ENT_QUOTES) ?></div>
                                </td>
                                <td><span class="badge <?= $statusConfig['class'] ?>"><?= $statusConfig['label'] ?></span></td>
                                <td><?= format_cost_per_base($ingredient['cost_per_base_x10000'] ?? null, $currency, $ingredient['base_uom_symbol']) ?></td>
                                <td><?= $ingredient['cost_effective_at'] ? htmlspecialchars($ingredient['cost_effective_at'], ENT_QUOTES) : '—' ?></td>
                                <td><?= (int) $ingredient['active'] === 1 ? 'Yes' : 'No' ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="/ingredients/<?= (int) $ingredient['id'] ?>/edit">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
