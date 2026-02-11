<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\DishCategory;
use PDO;

class AdminCategoryController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        Auth::requireRole(['admin']);
        $orgId = Auth::currentOrgId();
        $categories = DishCategory::listByOrg($this->pdo, $orgId);
        $pageTitle = 'Dish Categories';
        $view = __DIR__ . '/../../views/admin/categories/index.php';
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

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_error'] = 'Category name is required.';
            header('Location: /admin/categories');
            exit;
        }

        $orgId = Auth::currentOrgId();
        if (DishCategory::nameExists($this->pdo, $orgId, $name)) {
            $_SESSION['flash_error'] = 'Category name already exists.';
            header('Location: /admin/categories');
            exit;
        }

        $actor = Auth::currentUser();
        DishCategory::create($this->pdo, $orgId, $actor['id'] ?? 0, ['name' => $name]);
        $_SESSION['flash_success'] = 'Category created.';
        header('Location: /admin/categories');
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
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $category = DishCategory::findById($this->pdo, $orgId, $id);
        if (!$category) {
            $_SESSION['flash_error'] = 'Category not found.';
            header('Location: /admin/categories');
            exit;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['flash_error'] = 'Category name is required.';
            header('Location: /admin/categories');
            exit;
        }

        if (DishCategory::nameExists($this->pdo, $orgId, $name, $id)) {
            $_SESSION['flash_error'] = 'Category name already exists.';
            header('Location: /admin/categories');
            exit;
        }

        $actor = Auth::currentUser();
        DishCategory::update($this->pdo, $orgId, $actor['id'] ?? 0, $id, ['name' => $name]);
        $_SESSION['flash_success'] = 'Category updated.';
        header('Location: /admin/categories');
        exit;
    }

    public function delete(array $params): void
    {
        Auth::requireRole(['admin']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $category = DishCategory::findById($this->pdo, $orgId, $id);
        if (!$category) {
            $_SESSION['flash_error'] = 'Category not found.';
            header('Location: /admin/categories');
            exit;
        }

        if (DishCategory::hasDishes($this->pdo, $orgId, $id)) {
            $_SESSION['flash_error'] = 'Category cannot be deleted because dishes are assigned to it.';
            header('Location: /admin/categories');
            exit;
        }

        $actor = Auth::currentUser();
        DishCategory::delete($this->pdo, $orgId, $actor['id'] ?? 0, $id);
        $_SESSION['flash_success'] = 'Category deleted.';
        header('Location: /admin/categories');
        exit;
    }
}
