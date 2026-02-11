<?php
use App\Core\Csrf;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Dish Categories</h1>
        <p class="text-muted mb-0">Create and manage dish categories used in menu builder.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">Add category</h2>
        <form method="post" action="/admin/categories/create" class="row g-2 align-items-end">
            <?= Csrf::input() ?>
            <div class="col-md-6">
                <label class="form-label">Category name</label>
                <input class="form-control" name="name" required>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary" type="submit">Add</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>Name</th>
                        <th>Created</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No categories yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/update" class="d-flex gap-2 align-items-center">
                                        <?= Csrf::input() ?>
                                        <input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>" required>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                    </form>
                                </td>
                                <td><?= htmlspecialchars($category['created_at'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($category['updated_at'], ENT_QUOTES) ?></td>
                                <td class="text-end">
                                    <form method="post" action="/admin/categories/<?= (int) $category['id'] ?>/delete" onsubmit="return confirm('Delete this category?')" class="d-inline">
                                        <?= Csrf::input() ?>
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
