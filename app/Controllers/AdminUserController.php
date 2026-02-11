<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\User;
use App\Models\Audit;
use App\Services\SystemUpdater;
use PDO;

class AdminUserController
{
    private PDO $pdo;
    /** @var array<string, mixed> */
    private array $config;
    private string $projectRoot;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(PDO $pdo, array $config, string $projectRoot)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    public function index(): void
    {
        Auth::requireRole(['admin']);
        $orgId = Auth::currentOrgId();
        $users = User::listByOrg($this->pdo, $orgId);
        $pageTitle = 'User Management';
        $view = __DIR__ . '/../../views/admin/users/index.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function createForm(): void
    {
        Auth::requireRole(['admin']);
        $pageTitle = 'Add User';
        $view = __DIR__ . '/../../views/admin/users/new.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function create(): void
    {
        Auth::requireRole(['admin']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'role' => $_POST['role'] ?? 'viewer',
            'status' => $_POST['status'] ?? 'active',
            'password' => $_POST['password'] ?? '',
        ];

        $errors = User::validatePayload($payload, true);
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_values'] = $payload;
            header('Location: /admin/users/new');
            exit;
        }

        $created = User::create($this->pdo, $orgId, $payload);
        Audit::log(
            $this->pdo,
            $orgId,
            $actor['id'] ?? null,
            'user',
            $created['id'] ?? null,
            'create',
            null,
            $created
        );

        $_SESSION['flash_success'] = 'User created.';
        header('Location: /admin/users');
        exit;
    }

    public function update(array $params): void
    {
        Auth::requireRole(['admin']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $userId = isset($params['id']) ? (int) $params['id'] : 0;
        $existing = User::findById($this->pdo, $orgId, $userId);

        if (!$existing) {
            $_SESSION['flash_error'] = 'User not found.';
            header('Location: /admin/users');
            exit;
        }

        $payload = [
            'role' => $_POST['role'] ?? $existing['role'],
            'status' => $_POST['status'] ?? $existing['status'],
            'name' => $existing['name'],
            'email' => $existing['email'],
        ];

        $errors = User::validatePayload($payload, false);
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: /admin/users');
            exit;
        }

        $updated = User::updateRoleStatus($this->pdo, $orgId, $userId, $payload['role'], $payload['status']);
        Audit::log(
            $this->pdo,
            $orgId,
            $actor['id'] ?? null,
            'user',
            $userId,
            $payload['status'] === 'inactive' && $existing['status'] !== 'inactive' ? 'deactivate' : 'update',
            $existing,
            $updated
        );

        $_SESSION['flash_success'] = 'User updated.';
        header('Location: /admin/users');
        exit;
    }
    public function resetData(): void
    {
        Auth::requireRole(['admin']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();

        $this->pdo->beginTransaction();
        try {
            $tables = [
                'menu_item_cost_snapshots',
                'menu_ingredient_cost_snapshots',
                'menu_cost_snapshots',
                'menu_items',
                'menu_groups',
                'menus',
                'dish_lines',
                'dishes',
                'cost_import_rows',
                'cost_imports',
                'ingredient_costs',
                'ingredients',
            ];

            foreach ($tables as $table) {
                $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE org_id = :org_id");
                $stmt->execute(['org_id' => $orgId]);
            }

            $this->pdo->commit();
            $_SESSION['flash_success'] = 'All ingredient, dish, menu, and cost data has been reset.';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $_SESSION['flash_error'] = 'Could not reset data. Please try again.';
        }

        header('Location: /admin/users');
        exit;
    }

    public function runSystemUpdate(): void
    {
        Auth::requireRole(['admin']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $repoZipUrl = (string) ($this->config['update_zip_url'] ?? 'https://github.com/BaraboHJ/sufura/archive/refs/heads/main.zip');
        $excludePaths = $this->config['update_exclude_paths'] ?? [];
        if (!is_array($excludePaths)) {
            $excludePaths = [];
        }

        try {
            $updater = new SystemUpdater($this->projectRoot, $repoZipUrl, $excludePaths);
            $result = $updater->run();
            $_SESSION['flash_success'] = sprintf(
                'Update complete. %d files synchronized from %s.',
                (int) ($result['files_updated'] ?? 0),
                $repoZipUrl
            );
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'System update failed: ' . $e->getMessage();
        }

        header('Location: /admin/users');
        exit;
    }

}
