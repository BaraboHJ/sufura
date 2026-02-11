<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishLine;
use App\Models\Ingredient;
use App\Services\DishCost;
use PDO;

class DishController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        Auth::requireLogin();
        $user = Auth::currentUser();
        $canBulkDelete = ($user['role'] ?? '') === 'admin';
        $orgId = Auth::currentOrgId();
        $org = $this->loadOrg($orgId);
        $currency = $org['default_currency'] ?? 'USD';
        $dishes = Dish::listByOrg($this->pdo, $orgId);

        $dishRows = array_map(function (array $dish) use ($orgId): array {
            $summary = DishCost::summary(
                $this->pdo,
                $orgId,
                (int) $dish['id'],
                (int) $dish['yield_servings']
            );
            return array_merge($dish, $summary);
        }, $dishes);

        $pageTitle = 'Dishes';
        $view = __DIR__ . '/../../views/dishes/index.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function createForm(): void
    {
        Auth::requireRole(['admin', 'editor']);
        $orgId = Auth::currentOrgId();
        $categories = DishCategory::listByOrg($this->pdo, $orgId);
        $pageTitle = 'New Dish';
        $view = __DIR__ . '/../../views/dishes/new.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function create(): void
    {
        Auth::requireRole(['admin', 'editor']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $payload = $this->payloadFromRequest();
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_values'] = $payload;
            header('Location: /dishes/new');
            exit;
        }

        $dish = Dish::create($this->pdo, $orgId, $actor['id'] ?? 0, $payload);

        $_SESSION['flash_success'] = 'Dish created.';
        header('Location: /dishes/' . (int) ($dish['id'] ?? 0) . '/edit');
        exit;
    }

    public function show(array $params): void
    {
        Auth::requireLogin();
        $orgId = Auth::currentOrgId();
        $org = $this->loadOrg($orgId);
        $currency = $org['default_currency'] ?? 'USD';
        $dishId = isset($params['id']) ? (int) $params['id'] : 0;
        $dish = Dish::findById($this->pdo, $orgId, $dishId);

        if (!$dish) {
            $_SESSION['flash_error'] = 'Dish not found.';
            header('Location: /dishes');
            exit;
        }

        $summary = DishCost::summary($this->pdo, $orgId, $dishId, (int) $dish['yield_servings']);
        $breakdown = DishCost::breakdown($this->pdo, $orgId, $dishId);
        $missingIngredients = array_values(array_filter($breakdown, function (array $line): bool {
            return $line['cost_per_base_x10000'] === null;
        }));

        $pageTitle = $dish['name'];
        $view = __DIR__ . '/../../views/dishes/show.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function editForm(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        $orgId = Auth::currentOrgId();
        $dishId = isset($params['id']) ? (int) $params['id'] : 0;
        $dish = Dish::findById($this->pdo, $orgId, $dishId);

        if (!$dish) {
            $_SESSION['flash_error'] = 'Dish not found.';
            header('Location: /dishes');
            exit;
        }

        $lines = DishLine::listByDish($this->pdo, $orgId, $dishId);
        $categories = DishCategory::listByOrg($this->pdo, $orgId);
        $uomSets = Ingredient::listUomSets($this->pdo, $orgId);
        $org = $this->loadOrg($orgId);
        $currency = $org['default_currency'] ?? 'USD';
        $summary = DishCost::summary($this->pdo, $orgId, $dishId, (int) $dish['yield_servings']);
        $breakdown = DishCost::breakdown($this->pdo, $orgId, $dishId);
        $pageTitle = 'Edit Dish';
        $view = __DIR__ . '/../../views/dishes/edit.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function update(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $dishId = isset($params['id']) ? (int) $params['id'] : 0;
        $dish = Dish::findById($this->pdo, $orgId, $dishId);

        if (!$dish) {
            $_SESSION['flash_error'] = 'Dish not found.';
            header('Location: /dishes');
            exit;
        }

        $payload = $this->payloadFromRequest();
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_values'] = $payload;
            header('Location: /dishes/' . $dishId . '/edit');
            exit;
        }

        Dish::update($this->pdo, $orgId, $actor['id'] ?? 0, $dishId, $payload);

        $_SESSION['flash_success'] = 'Dish updated.';
        header('Location: /dishes/' . $dishId . '/edit');
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
        $actor = Auth::currentUser();
        $dishId = isset($params['id']) ? (int) $params['id'] : 0;
        $dish = Dish::findById($this->pdo, $orgId, $dishId);

        if (!$dish) {
            $_SESSION['flash_error'] = 'Dish not found.';
            header('Location: /dishes');
            exit;
        }

        if (Dish::hasMenuItems($this->pdo, $orgId, $dishId) || Dish::hasMenuSnapshots($this->pdo, $orgId, $dishId)) {
            $_SESSION['flash_error'] = 'Dish cannot be deleted because it is used in menus or menu snapshots.';
            header('Location: /dishes/' . $dishId);
            exit;
        }

        $lines = DishLine::listByDish($this->pdo, $orgId, $dishId);
        foreach ($lines as $line) {
            DishLine::delete($this->pdo, $orgId, $actor['id'] ?? 0, (int) $line['id']);
        }

        Dish::delete($this->pdo, $orgId, $actor['id'] ?? 0, $dishId);
        $_SESSION['flash_success'] = 'Dish deleted.';
        header('Location: /dishes');
        exit;
    }


    public function bulkDelete(): void
    {
        Auth::requireRole(['admin']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $selectedIds = $_POST['selected_ids'] ?? [];
        $dishIds = is_array($selectedIds) ? array_values(array_unique(array_map('intval', $selectedIds))) : [];
        $dishIds = array_values(array_filter($dishIds, function (int $id): bool {
            return $id > 0;
        }));

        if (empty($dishIds)) {
            $_SESSION['flash_error'] = 'Select at least one dish to delete.';
            header('Location: /dishes');
            exit;
        }

        $deleted = 0;
        $skipped = 0;
        foreach ($dishIds as $dishId) {
            $dish = Dish::findById($this->pdo, $orgId, $dishId);
            if (!$dish) {
                $skipped++;
                continue;
            }

            if (Dish::hasMenuItems($this->pdo, $orgId, $dishId) || Dish::hasMenuSnapshots($this->pdo, $orgId, $dishId)) {
                $skipped++;
                continue;
            }

            $lines = DishLine::listByDish($this->pdo, $orgId, $dishId);
            foreach ($lines as $line) {
                DishLine::delete($this->pdo, $orgId, $actor['id'] ?? 0, (int) $line['id']);
            }

            Dish::delete($this->pdo, $orgId, $actor['id'] ?? 0, $dishId);
            $deleted++;
        }

        if ($deleted > 0) {
            $_SESSION['flash_success'] = "Deleted {$deleted} dish(es).";
        }
        if ($skipped > 0) {
            $_SESSION['flash_error'] = "Skipped {$skipped} dish(es) because they are referenced or missing.";
        }

        header('Location: /dishes');
        exit;
    }

    public function addLine(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        header('Content-Type: application/json');
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $dishId = isset($params['id']) ? (int) $params['id'] : 0;
        $dish = Dish::findById($this->pdo, $orgId, $dishId);
        if (!$dish) {
            http_response_code(404);
            echo json_encode(['error' => 'Dish not found.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid payload.']);
            return;
        }

        $ingredientId = (int) ($payload['ingredient_id'] ?? 0);
        $quantity = isset($payload['quantity']) ? (float) $payload['quantity'] : 1.0;
        $uomId = (int) ($payload['uom_id'] ?? 0);
        $sortOrder = (int) ($payload['sort_order'] ?? 0);

        if ($ingredientId <= 0 || $quantity <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Ingredient and quantity are required.']);
            return;
        }

        $ingredient = Ingredient::findById($this->pdo, $orgId, $ingredientId);
        if (!$ingredient) {
            http_response_code(404);
            echo json_encode(['error' => 'Ingredient not found.']);
            return;
        }

        if ($uomId <= 0) {
            $uoms = Ingredient::listUomsBySet($this->pdo, $orgId, (int) $ingredient['uom_set_id']);
            foreach ($uoms as $uom) {
                if ((int) $uom['is_base'] === 1) {
                    $uomId = (int) $uom['id'];
                    break;
                }
            }
        }

        $uom = Ingredient::findUomById($this->pdo, $orgId, $uomId);
        if (!$uom || (int) $uom['uom_set_id'] !== (int) $ingredient['uom_set_id']) {
            http_response_code(422);
            echo json_encode(['error' => 'Select a valid unit of measure.']);
            return;
        }

        $line = DishLine::create($this->pdo, $orgId, $actor['id'] ?? 0, $dishId, [
            'ingredient_id' => $ingredientId,
            'quantity' => $quantity,
            'uom_id' => $uomId,
            'sort_order' => $sortOrder,
        ]);

        $summary = DishCost::summary($this->pdo, $orgId, $dishId, (int) $dish['yield_servings']);
        $breakdown = DishCost::breakdown($this->pdo, $orgId, $dishId);
        $lineBreakdown = null;
        foreach ($breakdown as $item) {
            if ((int) $item['id'] === (int) ($line['id'] ?? 0)) {
                $lineBreakdown = $item;
                break;
            }
        }
        echo json_encode([
            'line' => $line,
            'line_breakdown' => $lineBreakdown,
            'summary' => $summary,
        ]);
    }

    public function updateLine(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        header('Content-Type: application/json');
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $lineId = isset($params['id']) ? (int) $params['id'] : 0;
        $line = DishLine::findById($this->pdo, $orgId, $lineId);
        if (!$line) {
            http_response_code(404);
            echo json_encode(['error' => 'Line not found.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid payload.']);
            return;
        }

        $ingredientId = (int) ($payload['ingredient_id'] ?? $line['ingredient_id']);
        $quantity = isset($payload['quantity']) ? (float) $payload['quantity'] : (float) $line['quantity'];
        $uomId = (int) ($payload['uom_id'] ?? $line['uom_id']);
        $sortOrder = (int) ($payload['sort_order'] ?? $line['sort_order']);

        if ($ingredientId <= 0 || $quantity <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Ingredient and quantity are required.']);
            return;
        }

        $ingredient = Ingredient::findById($this->pdo, $orgId, $ingredientId);
        if (!$ingredient) {
            http_response_code(404);
            echo json_encode(['error' => 'Ingredient not found.']);
            return;
        }

        $uom = Ingredient::findUomById($this->pdo, $orgId, $uomId);
        if (!$uom || (int) $uom['uom_set_id'] !== (int) $ingredient['uom_set_id']) {
            http_response_code(422);
            echo json_encode(['error' => 'Select a valid unit of measure.']);
            return;
        }

        $updated = DishLine::update($this->pdo, $orgId, $actor['id'] ?? 0, $lineId, [
            'ingredient_id' => $ingredientId,
            'quantity' => $quantity,
            'uom_id' => $uomId,
            'sort_order' => $sortOrder,
        ]);

        $dish = Dish::findById($this->pdo, $orgId, (int) $line['dish_id']);
        $summary = $dish
            ? DishCost::summary($this->pdo, $orgId, (int) $line['dish_id'], (int) $dish['yield_servings'])
            : [];
        $breakdown = $dish ? DishCost::breakdown($this->pdo, $orgId, (int) $line['dish_id']) : [];
        $lineBreakdown = null;
        foreach ($breakdown as $item) {
            if ((int) $item['id'] === $lineId) {
                $lineBreakdown = $item;
                break;
            }
        }

        echo json_encode([
            'line' => $updated,
            'line_breakdown' => $lineBreakdown,
            'summary' => $summary,
        ]);
    }

    public function deleteLine(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        header('Content-Type: application/json');
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $lineId = isset($params['id']) ? (int) $params['id'] : 0;
        $line = DishLine::findById($this->pdo, $orgId, $lineId);
        if (!$line) {
            http_response_code(404);
            echo json_encode(['error' => 'Line not found.']);
            return;
        }

        DishLine::delete($this->pdo, $orgId, $actor['id'] ?? 0, $lineId);
        $dish = Dish::findById($this->pdo, $orgId, (int) $line['dish_id']);
        $summary = $dish
            ? DishCost::summary($this->pdo, $orgId, (int) $line['dish_id'], (int) $dish['yield_servings'])
            : [];

        echo json_encode(['summary' => $summary]);
    }

    public function costSummary(array $params): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $orgId = Auth::currentOrgId();
        $dishId = isset($params['id']) ? (int) $params['id'] : 0;
        $dish = Dish::findById($this->pdo, $orgId, $dishId);
        if (!$dish) {
            http_response_code(404);
            echo json_encode(['error' => 'Dish not found.']);
            return;
        }

        $summary = DishCost::summary($this->pdo, $orgId, $dishId, (int) $dish['yield_servings']);
        echo json_encode($summary);
    }

    public function costBreakdown(array $params): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $orgId = Auth::currentOrgId();
        $dishId = isset($params['id']) ? (int) $params['id'] : 0;
        $dish = Dish::findById($this->pdo, $orgId, $dishId);
        if (!$dish) {
            http_response_code(404);
            echo json_encode(['error' => 'Dish not found.']);
            return;
        }

        $breakdown = DishCost::breakdown($this->pdo, $orgId, $dishId);
        echo json_encode($breakdown);
    }

    public function search(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $orgId = Auth::currentOrgId();
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $query = trim($_GET['query'] ?? '');

        if ($categoryId > 0) {
            $category = DishCategory::findById($this->pdo, $orgId, $categoryId);
            if (!$category) {
                echo json_encode(['results' => []]);
                return;
            }
        }

        $results = Dish::searchByOrg($this->pdo, $orgId, $categoryId, $query);
        echo json_encode(['results' => $results]);
    }

    public function createFromApi(): void
    {
        Auth::requireRole(['admin', 'editor']);
        header('Content-Type: application/json');
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid payload.']);
            return;
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $categoryId = (int) ($payload['category_id'] ?? 0);

        if ($categoryId <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Category is required.']);
            return;
        }

        $orgId = Auth::currentOrgId();
        $category = DishCategory::findById($this->pdo, $orgId, $categoryId);
        if (!$category) {
            http_response_code(422);
            echo json_encode(['error' => 'Select a valid category.']);
            return;
        }

        if ($name === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Name is required.']);
            return;
        }

        $actor = Auth::currentUser();
        $dish = Dish::create($this->pdo, $orgId, $actor['id'] ?? 0, [
            'name' => $name,
            'category_id' => $categoryId,
            'description' => trim((string) ($payload['description'] ?? '')),
            'yield_servings' => isset($payload['yield_servings']) ? (int) $payload['yield_servings'] : 1,
            'active' => 1,
        ]);

        echo json_encode(['dish' => $dish]);
    }

    private function payloadFromRequest(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'category_id' => isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0,
            'description' => trim($_POST['description'] ?? ''),
            'yield_servings' => isset($_POST['yield_servings']) ? (int) $_POST['yield_servings'] : 0,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ($payload['name'] === '') {
            $errors[] = 'Name is required.';
        }

        if ((int) ($payload['category_id'] ?? 0) <= 0) {
            $errors[] = 'Category is required.';
        } else {
            $orgId = Auth::currentOrgId();
            $category = DishCategory::findById($this->pdo, $orgId, (int) $payload['category_id']);
            if (!$category) {
                $errors[] = 'Select a valid category.';
            }
        }

        if ((int) $payload['yield_servings'] <= 0) {
            $errors[] = 'Yield servings must be greater than 0.';
        }

        return $errors;
    }

    private function loadOrg(?int $orgId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, default_currency FROM organizations WHERE id = :id');
        $stmt->execute(['id' => $orgId]);
        return $stmt->fetch() ?: [];
    }
}
