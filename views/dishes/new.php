<?php
$formValues = $_SESSION['form_values'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Create dish</h1>
        <p class="text-muted mb-0">Add a new dish to your catalog.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/dishes">Back</a>
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

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="/dishes/create" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="col-md-6">
                <label class="form-label">Name</label>
                <input class="form-control" type="text" name="name" value="<?= htmlspecialchars((string) ($formValues['name'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select" name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach (($categories ?? []) as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= ((int) ($formValues['category_id'] ?? 0) === (int) $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Yield servings</label>
                <input class="form-control" type="number" min="1" name="yield_servings" value="<?= (int) ($formValues['yield_servings'] ?? 1) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars((string) ($formValues['description'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 form-check ms-2">
                <input class="form-check-input" type="checkbox" name="active" id="active" value="1" <?= ((int) ($formValues['active'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="active">Active</label>
            </div>
            <div class="col-12 text-end">
                <button class="btn btn-primary" type="submit">Create dish</button>
            </div>
        </form>
    </div>
</div>
