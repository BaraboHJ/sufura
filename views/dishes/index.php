<?php
use App\Core\Csrf;

function format_money(?int $minor, string $currency): string
{
    if ($minor === null) {
        return '—';
    }
    return sprintf('%s %s', $currency, number_format($minor / 100, 2));
}

$statusLabels = [
    'complete' => ['label' => 'Complete', 'class' => 'bg-success'],
    'incomplete_missing_ingredient_cost' => ['label' => 'Missing costs', 'class' => 'bg-warning text-dark'],
    'invalid_units' => ['label' => 'Invalid units', 'class' => 'bg-danger'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1">Dishes</h1>
        <p class="text-muted mb-0">Manage recipe yields, costs, and completeness.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/dishes/template">Download template</a>
        <a class="btn btn-outline-primary" href="/dishes/import">Bulk upload</a>
        <a class="btn btn-primary" href="/dishes/new">New Dish</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="/dishes/bulk-delete" id="dish-bulk-delete-form">
            <?= Csrf::input() ?>
            <?php if ($canBulkDelete): ?>
                <div class="mb-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete selected dishes? Dishes in menus will be skipped.')">Delete selected</button>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead class="table-secondary">
                        <tr>
                            <?php if ($canBulkDelete): ?>
                                <th><input type="checkbox" id="dish-select-all"></th>
                            <?php endif; ?>
                            <th>Name</th>
                            <th>Yield servings</th>
                            <th>Cost per serving</th>
                            <th>Completeness</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dishRows)): ?>
                            <tr>
                                <td colspan="<?= $canBulkDelete ? '7' : '6' ?>" class="text-center text-muted py-4">No dishes yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dishRows as $dish): ?>
                                <?php
                                $knownCount = (int) ($dish['lines_count'] ?? 0) - (int) ($dish['unknown_cost_lines_count'] ?? 0);
                                $status = $dish['status'] ?? 'incomplete_missing_ingredient_cost';
                                $statusConfig = $statusLabels[$status] ?? $statusLabels['incomplete_missing_ingredient_cost'];
                                ?>
                                <tr>
                                    <?php if ($canBulkDelete): ?>
                                        <td><input type="checkbox" name="selected_ids[]" value="<?= (int) $dish['id'] ?>" class="dish-select-row"></td>
                                    <?php endif; ?>
                                    <td>
                                        <a href="/dishes/<?= (int) $dish['id'] ?>" class="fw-semibold text-decoration-none">
                                            <?= htmlspecialchars($dish['name'], ENT_QUOTES) ?>
                                        </a>
                                        <?php if ((int) $dish['active'] !== 1): ?>
                                            <span class="badge bg-secondary ms-2">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) $dish['yield_servings'] ?></td>
                                    <td><?= format_money($dish['cost_per_serving_minor'] ?? null, $currency) ?></td>
                                    <td>
                                        <?php if ((int) ($dish['lines_count'] ?? 0) > 0): ?>
                                            <div class="small text-muted">
                                                <?= $knownCount ?>/<?= (int) $dish['lines_count'] ?> ingredients costed
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= $statusConfig['class'] ?>"><?= $statusConfig['label'] ?></span></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-secondary" href="/dishes/<?= (int) $dish['id'] ?>/edit">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<?php if ($canBulkDelete): ?>
<script>
const dishSelectAll = document.getElementById('dish-select-all');
dishSelectAll?.addEventListener('change', () => {
    document.querySelectorAll('.dish-select-row').forEach((checkbox) => {
        checkbox.checked = dishSelectAll.checked;
    });
});
</script>
<?php endif; ?>
