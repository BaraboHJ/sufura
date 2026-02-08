<?php
use App\Core\Csrf;

$formErrors = $_SESSION['form_errors'] ?? [];
$formValues = $_SESSION['form_values'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="bg-body-secondary p-4 rounded shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0">Add User</h1>
                    <p class="text-muted mb-0">Invite someone to your organization.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="/admin/users">Back</a>
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

            <form method="post" action="/admin/users/create">
                <?= Csrf::input() ?>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($formValues['name'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($formValues['email'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <?php foreach (['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($formValues['role'] ?? 'viewer') === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($formValues['status'] ?? 'active') === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Temporary Password</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                    <div class="form-text">Ask the user to change this after first login.</div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Create User</button>
            </form>
        </div>
    </div>
</div>
