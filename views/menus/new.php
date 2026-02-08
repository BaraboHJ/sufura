<?php
$values = $_SESSION['form_values'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_values'], $_SESSION['form_errors']);
?>

<div class="mb-3">
    <h1 class="h3">New Menu</h1>
    <p class="text-muted mb-0">Start a package or per-item menu and build out groups and dishes.</p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="/menus/create">
            <?= \App\Core\Csrf::input() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Menu name</label>
                    <input class="form-control" name="name" required value="<?= htmlspecialchars($values['name'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Menu type</label>
                    <select class="form-select" name="menu_type">
                        <?php $menuType = $values['menu_type'] ?? 'package'; ?>
                        <option value="package" <?= $menuType === 'package' ? 'selected' : '' ?>>Package</option>
                        <option value="per_item" <?= $menuType === 'per_item' ? 'selected' : '' ?>>Per item</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Currency</label>
                    <input class="form-control" name="currency" value="<?= htmlspecialchars($values['currency'] ?? $currency, ENT_QUOTES) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price min (per pax)</label>
                    <input class="form-control" name="price_min_major" placeholder="0.00" value="<?= htmlspecialchars($values['price_min_major'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price max (per pax)</label>
                    <input class="form-control" name="price_max_major" placeholder="0.00" value="<?= htmlspecialchars($values['price_max_major'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price label suffix</label>
                    <input class="form-control" name="price_label_suffix" placeholder="++" value="<?= htmlspecialchars($values['price_label_suffix'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Minimum pax (package)</label>
                    <input class="form-control" name="min_pax" type="number" min="1" value="<?= htmlspecialchars($values['min_pax'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Default waste %</label>
                    <input class="form-control" name="default_waste_pct" placeholder="0.05" value="<?= htmlspecialchars($values['default_waste_pct'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Show descriptions</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_descriptions" id="show-descriptions" <?= isset($values['show_descriptions']) || empty($values) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="show-descriptions">Display dish descriptions</label>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary" type="submit">Create menu</button>
                <a class="btn btn-outline-secondary" href="/menus">Cancel</a>
            </div>
        </form>
    </div>
</div>
