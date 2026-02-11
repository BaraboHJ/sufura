<?php
use App\Core\Csrf;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">User Management</h1>
        <p class="text-muted mb-0">Manage roles and access for your organization.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/admin/uoms">Manage UoMs</a>
        <a class="btn btn-primary" href="/admin/users/new">Add User</a>
    </div>
</div>

<div class="alert alert-info d-flex justify-content-between align-items-center">
    <div>
        <strong>System update:</strong> Fetch the latest code from GitHub and apply file updates on this server.
    </div>
    <form method="post" action="/admin/system/update" class="m-0" onsubmit="return confirm('This will download the latest release files and overwrite application code. Continue?')">
        <?= Csrf::input() ?>
        <button class="btn btn-sm btn-outline-info" type="submit">Update from GitHub</button>
    </form>
</div>

<div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div>
        <strong>Danger zone:</strong> Reset all ingredient, dish, menu, and cost data for this organization.
    </div>
    <form method="post" action="/admin/reset-data" class="m-0" onsubmit="return confirm('This will permanently delete all ingredients, dishes, menus, and cost data. Continue?')">
        <?= Csrf::input() ?>
        <button class="btn btn-sm btn-danger" type="submit">Reset data</button>
    </form>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead class="table-secondary">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No users yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $userRow): ?>
                            <?php $formId = 'user-update-' . (int) $userRow['id']; ?>
                            <tr>
                                <td><?= htmlspecialchars($userRow['name'], ENT_QUOTES) ?></td>
                                <td><?= htmlspecialchars($userRow['email'], ENT_QUOTES) ?></td>
                                <td>
                                    <select name="role" form="<?= $formId ?>" class="form-select form-select-sm">
                                        <?php foreach (['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $userRow['role'] === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="status" form="<?= $formId ?>" class="form-select form-select-sm">
                                        <?php foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $userRow['status'] === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?= htmlspecialchars($userRow['created_at'], ENT_QUOTES) ?></td>
                                <td>
                                    <form method="post" action="/admin/users/<?= (int) $userRow['id'] ?>/update" id="<?= $formId ?>">
                                        <?= Csrf::input() ?>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
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
