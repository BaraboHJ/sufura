<?php
use App\Core\Csrf;
?>
<div class="mb-4">
    <h1 class="h4 mb-1">Admin Portal</h1>
    <p class="text-muted mb-0">Use admin actions and manage organization users from one place.</p>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h2 class="h6 mb-0">Admin Actions</h2>
            </div>
            <div class="card-body d-grid gap-3">
                <a class="btn btn-primary" href="/admin/users/new">Add User</a>

                <div class="alert alert-info mb-0">
                    <p class="mb-2"><strong>System update:</strong> Fetch latest code from GitHub and apply updates on this server.</p>
                    <form method="post" action="/admin/system/update" class="m-0" onsubmit="return confirm('This will download the latest release files and overwrite application code. Continue?')">
                        <?= Csrf::input() ?>
                        <button class="btn btn-sm btn-outline-info" type="submit">Update from GitHub</button>
                    </form>
                </div>

                <div class="alert alert-warning mb-0">
                    <p class="mb-2"><strong>Danger zone:</strong> Reset all ingredient, dish, menu, and cost data for this organization.</p>
                    <form method="post" action="/admin/reset-data" class="m-0" onsubmit="return confirm('This will permanently delete all ingredients, dishes, menus, and cost data. Continue?')">
                        <?= Csrf::input() ?>
                        <button class="btn btn-sm btn-danger" type="submit">Reset data</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">User Management</h2>
                <span class="text-muted small">Admins can update role, status, and password for any user.</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>New Password</th>
                                <th>Created</th>
                                <th>Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No users yet.</td>
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
                                        <td>
                                            <input
                                                type="password"
                                                name="password"
                                                form="<?= $formId ?>"
                                                class="form-control form-control-sm"
                                                minlength="8"
                                                autocomplete="new-password"
                                                placeholder="Leave blank"
                                            >
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
    </div>
</div>
