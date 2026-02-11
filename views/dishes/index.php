<?php
$canBulkDelete = $canBulkDelete ?? false;
$dishRows = $dishRows ?? [];
$currency = $currency ?? 'USD';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Dishes</h1>
        <p class="text-muted mb-0">Manage dish catalog and quickly review food cost totals.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/dishes/template">Download template</a>
        <a class="btn btn-outline-primary" href="/dishes/import">Bulk upload</a>
        <a class="btn btn-primary" href="/dishes/new">New dish</a>
    </div>
</div>

<form method="post" action="/dishes/bulk-delete">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <?php if ($canBulkDelete): ?>
                        <th style="width: 48px;"><input type="checkbox" onclick="document.querySelectorAll('.js-dish-checkbox').forEach(cb => cb.checked = this.checked)"></th>
                    <?php endif; ?>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Yield</th>
                    <th>Status</th>
                    <th>Total cost</th>
                    <th>Cost/serving</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($dishRows)): ?>
                    <tr>
                        <td colspan="<?= $canBulkDelete ? 8 : 7 ?>" class="text-center text-muted py-4">No dishes found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($dishRows as $dish): ?>
                        <tr>
                            <?php if ($canBulkDelete): ?>
                                <td><input class="js-dish-checkbox" type="checkbox" name="selected_ids[]" value="<?= (int) $dish['id'] ?>"></td>
                            <?php endif; ?>
                            <td><a class="text-decoration-none fw-semibold" href="/dishes/<?= (int) $dish['id'] ?>"><?= htmlspecialchars((string) $dish['name']) ?></a></td>
                            <td><?= htmlspecialchars((string) ($dish['category_name'] ?? '-')) ?></td>
                            <td><?= (int) ($dish['yield_servings'] ?? 1) ?></td>
                            <td>
                                <span class="badge text-bg-<?= ((int) ($dish['active'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                                    <?= ((int) ($dish['active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($currency) ?> <?= number_format(((int) ($dish['total_cost_x10000'] ?? 0)) / 10000, 2) ?></td>
                            <td><?= htmlspecialchars($currency) ?> <?= number_format(((int) ($dish['cost_per_serving_x10000'] ?? 0)) / 10000, 2) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="/dishes/<?= (int) $dish['id'] ?>">View</a>
                                <a class="btn btn-sm btn-outline-primary" href="/dishes/<?= (int) $dish['id'] ?>/edit">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($canBulkDelete && !empty($dishRows)): ?>
            <div class="card-footer bg-white text-end">
                <button class="btn btn-outline-danger" type="submit" onclick="return confirm('Delete selected dishes? Dishes used in menus will be skipped.')">Delete selected</button>
            </div>
        <?php endif; ?>
    </div>
</form>
