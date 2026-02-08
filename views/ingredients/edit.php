<?php
use App\Core\Csrf;

$formErrors = $_SESSION['form_errors'] ?? [];
$formValues = $_SESSION['form_values'] ?? $ingredient ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="bg-body-secondary p-4 rounded shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0">Edit Ingredient</h1>
                    <p class="text-muted mb-0">Update unit sets and notes.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="/ingredients/<?= (int) $ingredient['id'] ?>">Back</a>
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

            <form method="post" action="/ingredients/<?= (int) $ingredient['id'] ?>/update">
                <?= Csrf::input() ?>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($formValues['name'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">UoM Set</label>
                    <select name="uom_set_id" class="form-select" required>
                        <option value="">Select a unit set</option>
                        <?php foreach ($uomSets as $uomSet): ?>
                            <option value="<?= (int) $uomSet['id'] ?>" <?= ((int) ($formValues['uom_set_id'] ?? 0) === (int) $uomSet['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($uomSet['name'], ENT_QUOTES) ?> (base: <?= htmlspecialchars($uomSet['base_uom_symbol'], ENT_QUOTES) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($formValues['notes'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="active" id="ingredient-active" value="1" <?= ((int) ($formValues['active'] ?? 1) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ingredient-active">Active</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Save Changes</button>
            </form>
        </div>
    </div>
</div>
