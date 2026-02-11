<?php
use App\Core\Csrf;

$formErrors = $_SESSION['form_errors'] ?? [];
$formValues = $_SESSION['form_values'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="bg-body-secondary p-4 rounded shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0">New Dish</h1>
                    <p class="text-muted mb-0">Set the recipe yield and status.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="/dishes">Back</a>
            </div>

            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($formErrors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/dishes/create">
                <?= Csrf::input() ?>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($formValues['name'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) ($formValues['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name'], ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($formValues['description'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Yield servings</label>
                    <input type="number" name="yield_servings" min="1" class="form-control" required value="<?= htmlspecialchars((string) ($formValues['yield_servings'] ?? 1), ENT_QUOTES) ?>">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="active" id="dish-active" value="1" <?= ((int) ($formValues['active'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="dish-active">Active</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Create Dish</button>
            </form>
        </div>
    </div>
</div>
