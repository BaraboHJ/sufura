<?php
$formValues = $_SESSION['form_values'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);
$values = array_merge($dish ?? [], $formValues);
$currency = $currency ?? 'USD';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Edit dish</h1>
        <p class="text-muted mb-0">Update dish details and review recipe lines.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/dishes/<?= (int) $dish['id'] ?>">View</a>
        <a class="btn btn-outline-secondary" href="/dishes">Back</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars((string) $error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" action="/dishes/<?= (int) $dish['id'] ?>/update" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input class="form-control" type="text" name="name" value="<?= htmlspecialchars((string) ($values['name'] ?? '')) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category_id" required>
                            <?php foreach (($categories ?? []) as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= ((int) ($values['category_id'] ?? 0) === (int) $category['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Yield servings</label>
                        <input class="form-control" type="number" min="1" name="yield_servings" value="<?= (int) ($values['yield_servings'] ?? 1) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" rows="3" name="description"><?= htmlspecialchars((string) ($values['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input class="form-check-input" type="checkbox" name="active" id="active" value="1" <?= ((int) ($values['active'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Active</label>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" type="submit">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="text-muted small">Total cost</div>
                <div class="h4 mb-0"><?= htmlspecialchars($currency) ?> <?= number_format(((int) ($summary['total_cost_x10000'] ?? 0)) / 10000, 2) ?></div>
                <div class="text-muted small mt-2">Cost per serving: <?= htmlspecialchars($currency) ?> <?= number_format(((int) ($summary['cost_per_serving_x10000'] ?? 0)) / 10000, 2) ?></div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Recipe lines</strong>
                <small class="text-muted">Manage lines in the API-enabled editor if needed</small>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Ingredient</th><th>Qty</th><th>UOM</th><th>Cost</th></tr></thead>
                    <tbody>
                    <?php if (empty($breakdown)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No lines added yet.</td></tr>
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

        <form class="mt-3" method="post" action="/dishes/<?= (int) $dish['id'] ?>/delete" onsubmit="return confirm('Delete this dish?');">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-outline-danger w-100" type="submit">Delete dish</button>
        </form>
    </div>
</div>
