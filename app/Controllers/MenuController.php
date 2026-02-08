<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Menu;
use App\Models\MenuGroup;
use App\Models\MenuItem;
use App\Models\Dish;
use App\Services\DishCost;
use App\Services\MenuCost;
use PDO;

class MenuController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        Auth::requireLogin();
        $orgId = Auth::currentOrgId();
        $org = $this->loadOrg($orgId);
        $menus = Menu::listByOrg($this->pdo, $orgId);

        $menuRows = [];
        foreach ($menus as $menu) {
            $groups = MenuGroup::listByMenu($this->pdo, $orgId, (int) $menu['id']);
            $items = MenuItem::listByMenu($this->pdo, $orgId, (int) $menu['id']);
            $itemsWithCosts = $this->attachDishCosts($orgId, $items);
            $report = MenuCost::computeLive($this->pdo, $menu, $groups, $itemsWithCosts, null);

            if ($menu['cost_mode'] === 'locked') {
                $report = $this->lockedReport($orgId, $menu, $groups);
            }

            $menuRows[] = array_merge($menu, [
                'menu_cost_per_pax_minor' => $report['menu_cost_per_pax_minor'],
            ]);
        }

        $pageTitle = 'Menus';
        $view = __DIR__ . '/../../views/menus/index.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function createForm(): void
    {
        Auth::requireRole(['admin', 'editor']);
        $orgId = Auth::currentOrgId();
        $org = $this->loadOrg($orgId);
        $currency = $org['default_currency'] ?? 'USD';
        $pageTitle = 'New Menu';
        $view = __DIR__ . '/../../views/menus/new.php';
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
            header('Location: /menus/new');
            exit;
        }

        $menu = Menu::create($this->pdo, $orgId, $actor['id'] ?? 0, $payload);

        $_SESSION['flash_success'] = 'Menu created.';
        header('Location: /menus/' . (int) ($menu['id'] ?? 0) . '/edit');
        exit;
    }

    public function editForm(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        $orgId = Auth::currentOrgId();
        $org = $this->loadOrg($orgId);
        $currency = $org['default_currency'] ?? 'USD';
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);

        if (!$menu) {
            $_SESSION['flash_error'] = 'Menu not found.';
            header('Location: /menus');
            exit;
        }

        $currency = $menu['currency'] ?? ($org['default_currency'] ?? 'USD');
        $groups = MenuGroup::listByMenu($this->pdo, $orgId, $menuId);
        $items = MenuItem::listByMenu($this->pdo, $orgId, $menuId);
        $itemsWithCosts = $this->attachDishCosts($orgId, $items);
        $paxCount = isset($_GET['pax_count']) ? (int) $_GET['pax_count'] : null;

        if ($menu['cost_mode'] === 'locked') {
            $report = $this->lockedReport($orgId, $menu, $groups, $paxCount);
        } else {
            $report = MenuCost::computeLive($this->pdo, $menu, $groups, $itemsWithCosts, $paxCount);
        }

        $itemsByGroup = [];
        foreach ($itemsWithCosts as $item) {
            $itemsByGroup[$item['menu_group_id']][] = $item;
        }

        $canLock = $menu['cost_mode'] === 'live' && $this->canLockMenu($report, $groups, $itemsWithCosts);

        $pageTitle = 'Edit Menu';
        $view = __DIR__ . '/../../views/menus/edit.php';
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
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);

        if (!$menu) {
            $_SESSION['flash_error'] = 'Menu not found.';
            header('Location: /menus');
            exit;
        }

        if ($menu['cost_mode'] === 'locked') {
            $_SESSION['flash_error'] = 'Locked menus cannot be edited.';
            header('Location: /menus/' . $menuId . '/edit');
            exit;
        }

        $payload = $this->payloadFromRequest();
        $errors = $this->validatePayload($payload, $menuId);

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_values'] = $payload;
            header('Location: /menus/' . $menuId . '/edit');
            exit;
        }

        Menu::update($this->pdo, $orgId, $actor['id'] ?? 0, $menuId, $payload);

        $_SESSION['flash_success'] = 'Menu updated.';
        header('Location: /menus/' . $menuId . '/edit');
        exit;
    }

    public function updateFromApi(array $params): void
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
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);
        if (!$menu) {
            http_response_code(404);
            echo json_encode(['error' => 'Menu not found.']);
            return;
        }
        if ($menu['cost_mode'] === 'locked') {
            http_response_code(409);
            echo json_encode(['error' => 'Menu is locked.']);
            return;
        }

        $payload = $this->jsonPayload();
        if (!$payload) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid payload.']);
            return;
        }

        $showDescriptions = array_key_exists('show_descriptions', $payload)
            ? (int) (bool) $payload['show_descriptions']
            : (int) $menu['show_descriptions'];
        $data = [
            'name' => trim((string) ($payload['name'] ?? $menu['name'])),
            'menu_type' => $payload['menu_type'] ?? $menu['menu_type'],
            'price_min_minor' => $this->parseCurrencyToMinor($payload['price_min_major'] ?? null),
            'price_max_minor' => $this->parseCurrencyToMinor($payload['price_max_major'] ?? null),
            'currency' => strtoupper(trim((string) ($payload['currency'] ?? $menu['currency']))),
            'price_label_suffix' => trim((string) ($payload['price_label_suffix'] ?? $menu['price_label_suffix'])),
            'min_pax' => $this->parseNullableInt($payload['min_pax'] ?? $menu['min_pax']),
            'default_waste_pct' => $this->parseNullableDecimal($payload['default_waste_pct'] ?? $menu['default_waste_pct']) ?? 0,
            'show_descriptions' => $showDescriptions,
            'servings' => 1,
        ];

        $errors = $this->validatePayload($data);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['error' => implode(' ', $errors)]);
            return;
        }

        $updated = Menu::update($this->pdo, $orgId, $actor['id'] ?? 0, $menuId, $data);
        echo json_encode(['menu' => $updated]);
    }

    public function compute(array $params): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $orgId = Auth::currentOrgId();
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);
        if (!$menu) {
            http_response_code(404);
            echo json_encode(['error' => 'Menu not found.']);
            return;
        }

        $groups = MenuGroup::listByMenu($this->pdo, $orgId, $menuId);
        $paxCount = isset($_GET['pax_count']) ? (int) $_GET['pax_count'] : null;

        if ($menu['cost_mode'] === 'locked') {
            $report = $this->lockedReport($orgId, $menu, $groups, $paxCount);
        } else {
            $items = MenuItem::listByMenu($this->pdo, $orgId, $menuId);
            $itemsWithCosts = $this->attachDishCosts($orgId, $items);
            $report = MenuCost::computeLive($this->pdo, $menu, $groups, $itemsWithCosts, $paxCount);
        }

        echo json_encode($report);
    }

    public function createGroup(array $params): void
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
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);
        if (!$menu) {
            http_response_code(404);
            echo json_encode(['error' => 'Menu not found.']);
            return;
        }
        if ($menu['cost_mode'] === 'locked') {
            http_response_code(409);
            echo json_encode(['error' => 'Menu is locked.']);
            return;
        }

        $payload = $this->jsonPayload();
        if (!$payload || trim((string) ($payload['name'] ?? '')) === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Group name is required.']);
            return;
        }

        $actor = Auth::currentUser();
        $group = MenuGroup::create($this->pdo, $orgId, $actor['id'] ?? 0, $menuId, [
            'name' => trim($payload['name']),
            'uptake_pct' => $this->parseNullableDecimal($payload['uptake_pct'] ?? null),
            'portion' => $this->parseNullableDecimal($payload['portion'] ?? null),
            'waste_pct' => $this->parseNullableDecimal($payload['waste_pct'] ?? null),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);

        echo json_encode(['group' => $group]);
    }

    public function updateGroup(array $params): void
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
        $groupId = isset($params['id']) ? (int) $params['id'] : 0;
        $group = MenuGroup::findById($this->pdo, $orgId, $groupId);
        if (!$group) {
            http_response_code(404);
            echo json_encode(['error' => 'Group not found.']);
            return;
        }

        $menu = Menu::findById($this->pdo, $orgId, (int) $group['menu_id']);
        if (!$menu || $menu['cost_mode'] === 'locked') {
            http_response_code(409);
            echo json_encode(['error' => 'Menu is locked.']);
            return;
        }

        $payload = $this->jsonPayload();
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Group name is required.']);
            return;
        }

        $actor = Auth::currentUser();
        $updated = MenuGroup::update($this->pdo, $orgId, $actor['id'] ?? 0, $groupId, [
            'name' => $name,
            'uptake_pct' => $this->parseNullableDecimal($payload['uptake_pct'] ?? null),
            'portion' => $this->parseNullableDecimal($payload['portion'] ?? null),
            'waste_pct' => $this->parseNullableDecimal($payload['waste_pct'] ?? null),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);

        echo json_encode(['group' => $updated]);
    }

    public function deleteGroup(array $params): void
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
        $groupId = isset($params['id']) ? (int) $params['id'] : 0;
        $group = MenuGroup::findById($this->pdo, $orgId, $groupId);
        if (!$group) {
            http_response_code(404);
            echo json_encode(['error' => 'Group not found.']);
            return;
        }

        $menu = Menu::findById($this->pdo, $orgId, (int) $group['menu_id']);
        if (!$menu || $menu['cost_mode'] === 'locked') {
            http_response_code(409);
            echo json_encode(['error' => 'Menu is locked.']);
            return;
        }

        $actor = Auth::currentUser();
        $stmt = $this->pdo->prepare('DELETE FROM menu_items WHERE org_id = :org_id AND menu_group_id = :group_id');
        $stmt->execute(['org_id' => $orgId, 'group_id' => $groupId]);
        MenuGroup::delete($this->pdo, $orgId, $actor['id'] ?? 0, $groupId);

        echo json_encode(['ok' => true]);
    }

    public function createItem(array $params): void
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
        $groupId = isset($params['group_id']) ? (int) $params['group_id'] : 0;
        $group = MenuGroup::findById($this->pdo, $orgId, $groupId);
        if (!$group) {
            http_response_code(404);
            echo json_encode(['error' => 'Group not found.']);
            return;
        }

        $menu = Menu::findById($this->pdo, $orgId, (int) $group['menu_id']);
        if (!$menu || $menu['cost_mode'] === 'locked') {
            http_response_code(409);
            echo json_encode(['error' => 'Menu is locked.']);
            return;
        }

        $payload = $this->jsonPayload();
        $dishId = (int) ($payload['dish_id'] ?? 0);
        $dish = Dish::findById($this->pdo, $orgId, $dishId);
        if (!$dish) {
            http_response_code(422);
            echo json_encode(['error' => 'Dish not found.']);
            return;
        }

        $actor = Auth::currentUser();
        $item = MenuItem::create($this->pdo, $orgId, $actor['id'] ?? 0, $groupId, [
            'dish_id' => $dishId,
            'display_name' => $payload['display_name'] ?? null,
            'display_description' => $payload['display_description'] ?? null,
            'uptake_pct' => $this->parseNullableDecimal($payload['uptake_pct'] ?? null),
            'portion' => $this->parseNullableDecimal($payload['portion'] ?? null),
            'waste_pct' => $this->parseNullableDecimal($payload['waste_pct'] ?? null),
            'selling_price_minor' => $this->parseCurrencyToMinor($payload['selling_price_major'] ?? null),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);

        echo json_encode(['item' => $item]);
    }

    public function updateItem(array $params): void
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
        $itemId = isset($params['id']) ? (int) $params['id'] : 0;
        $item = MenuItem::findById($this->pdo, $orgId, $itemId);
        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found.']);
            return;
        }

        $group = MenuGroup::findById($this->pdo, $orgId, (int) $item['menu_group_id']);
        $menu = $group ? Menu::findById($this->pdo, $orgId, (int) $group['menu_id']) : null;
        if (!$menu || $menu['cost_mode'] === 'locked') {
            http_response_code(409);
            echo json_encode(['error' => 'Menu is locked.']);
            return;
        }

        $payload = $this->jsonPayload();
        $actor = Auth::currentUser();
        $updated = MenuItem::update($this->pdo, $orgId, $actor['id'] ?? 0, $itemId, [
            'display_name' => $payload['display_name'] ?? null,
            'display_description' => $payload['display_description'] ?? null,
            'uptake_pct' => $this->parseNullableDecimal($payload['uptake_pct'] ?? null),
            'portion' => $this->parseNullableDecimal($payload['portion'] ?? null),
            'waste_pct' => $this->parseNullableDecimal($payload['waste_pct'] ?? null),
            'selling_price_minor' => $this->parseCurrencyToMinor($payload['selling_price_major'] ?? null),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);

        echo json_encode(['item' => $updated]);
    }

    public function deleteItem(array $params): void
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
        $itemId = isset($params['id']) ? (int) $params['id'] : 0;
        $item = MenuItem::findById($this->pdo, $orgId, $itemId);
        if (!$item) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found.']);
            return;
        }

        $group = MenuGroup::findById($this->pdo, $orgId, (int) $item['menu_group_id']);
        $menu = $group ? Menu::findById($this->pdo, $orgId, (int) $group['menu_id']) : null;
        if (!$menu || $menu['cost_mode'] === 'locked') {
            http_response_code(409);
            echo json_encode(['error' => 'Menu is locked.']);
            return;
        }

        $actor = Auth::currentUser();
        MenuItem::delete($this->pdo, $orgId, $actor['id'] ?? 0, $itemId);

        echo json_encode(['ok' => true]);
    }

    public function lock(array $params): void
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
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);
        if (!$menu) {
            http_response_code(404);
            echo json_encode(['error' => 'Menu not found.']);
            return;
        }
        if ($menu['cost_mode'] === 'locked') {
            echo json_encode(['ok' => true]);
            return;
        }

        $groups = MenuGroup::listByMenu($this->pdo, $orgId, $menuId);
        $items = MenuItem::listByMenu($this->pdo, $orgId, $menuId);
        $itemsWithCosts = $this->attachDishCosts($orgId, $items);
        $report = MenuCost::computeLive($this->pdo, $menu, $groups, $itemsWithCosts, null);

        if (!$this->canLockMenu($report, $groups, $itemsWithCosts)) {
            http_response_code(422);
            echo json_encode(['error' => 'Menu is incomplete for locking.']);
            return;
        }

        $actor = Auth::currentUser();

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO menu_cost_snapshots (org_id, menu_id, menu_cost_per_pax_minor, currency, locked_by_user_id, created_at)
                 VALUES (:org_id, :menu_id, :menu_cost_per_pax_minor, :currency, :locked_by_user_id, NOW())'
            );
            $stmt->execute([
                'org_id' => $orgId,
                'menu_id' => $menuId,
                'menu_cost_per_pax_minor' => $report['menu_cost_per_pax_minor'],
                'currency' => $menu['currency'],
                'locked_by_user_id' => $actor['id'] ?? null,
            ]);

            $itemStmt = $this->pdo->prepare(
                'INSERT INTO menu_item_cost_snapshots
                    (org_id, menu_id, menu_item_id, dish_id, dish_cost_per_serving_minor, uptake_pct, portion, waste_pct, item_cost_per_pax_minor, created_at)
                 VALUES
                    (:org_id, :menu_id, :menu_item_id, :dish_id, :dish_cost_per_serving_minor, :uptake_pct, :portion, :waste_pct, :item_cost_per_pax_minor, NOW())'
            );

            foreach ($report['items'] as $item) {
                $itemStmt->execute([
                    'org_id' => $orgId,
                    'menu_id' => $menuId,
                    'menu_item_id' => $item['id'],
                    'dish_id' => $item['dish_id'],
                    'dish_cost_per_serving_minor' => $item['dish_cost_per_serving_minor'],
                    'uptake_pct' => $item['effective_uptake_pct'],
                    'portion' => $item['effective_portion'],
                    'waste_pct' => $item['effective_waste_pct'],
                    'item_cost_per_pax_minor' => $item['item_cost_per_pax_minor'],
                ]);
            }

            $ingredientIds = $this->menuIngredientIds($orgId, $menuId);
            $ingStmt = $this->pdo->prepare(
                'INSERT INTO menu_ingredient_cost_snapshots
                    (org_id, menu_id, ingredient_id, ingredient_cost_id, cost_per_base_x10000, base_uom_id, currency, captured_at)
                 VALUES
                    (:org_id, :menu_id, :ingredient_id, :ingredient_cost_id, :cost_per_base_x10000, :base_uom_id, :currency, :captured_at)'
            );
            foreach ($ingredientIds as $ingredientId) {
                $costRow = $this->latestIngredientCost($orgId, $ingredientId);
                if (!$costRow) {
                    continue;
                }
                $ingStmt->execute([
                    'org_id' => $orgId,
                    'menu_id' => $menuId,
                    'ingredient_id' => $ingredientId,
                    'ingredient_cost_id' => $costRow['ingredient_cost_id'],
                    'cost_per_base_x10000' => $costRow['cost_per_base_x10000'],
                    'base_uom_id' => $costRow['base_uom_id'],
                    'currency' => $costRow['currency'],
                    'captured_at' => $costRow['effective_at'],
                ]);
            }

            Menu::lock($this->pdo, $orgId, $actor['id'] ?? 0, $menuId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to lock menu.']);
            return;
        }

        echo json_encode(['ok' => true]);
    }

    public function unlock(array $params): void
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
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);
        if (!$menu) {
            http_response_code(404);
            echo json_encode(['error' => 'Menu not found.']);
            return;
        }

        $actor = Auth::currentUser();
        Menu::unlock($this->pdo, $orgId, $actor['id'] ?? 0, $menuId);

        echo json_encode(['ok' => true]);
    }

    public function duplicate(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);
        if (!$menu) {
            $_SESSION['flash_error'] = 'Menu not found.';
            header('Location: /menus');
            exit;
        }

        $groups = MenuGroup::listByMenu($this->pdo, $orgId, $menuId);
        $items = MenuItem::listByMenu($this->pdo, $orgId, $menuId);

        $this->pdo->beginTransaction();
        try {
            $newMenu = Menu::create($this->pdo, $orgId, $actor['id'] ?? 0, [
                'name' => $menu['name'] . ' (Copy)',
                'menu_type' => $menu['menu_type'],
                'price_min_minor' => $menu['price_min_minor'],
                'price_max_minor' => $menu['price_max_minor'],
                'currency' => $menu['currency'],
                'price_label_suffix' => $menu['price_label_suffix'],
                'min_pax' => $menu['min_pax'],
                'default_waste_pct' => $menu['default_waste_pct'],
                'show_descriptions' => $menu['show_descriptions'],
                'cost_mode' => 'live',
            ]);

            $groupMap = [];
            foreach ($groups as $group) {
                $newGroup = MenuGroup::create($this->pdo, $orgId, $actor['id'] ?? 0, (int) $newMenu['id'], [
                    'name' => $group['name'],
                    'uptake_pct' => $group['uptake_pct'],
                    'portion' => $group['portion'],
                    'waste_pct' => $group['waste_pct'],
                    'sort_order' => $group['sort_order'],
                ]);
                $groupMap[$group['id']] = $newGroup['id'];
            }

            foreach ($items as $item) {
                $newGroupId = $groupMap[$item['menu_group_id']] ?? null;
                if (!$newGroupId) {
                    continue;
                }
                MenuItem::create($this->pdo, $orgId, $actor['id'] ?? 0, (int) $newGroupId, [
                    'dish_id' => $item['dish_id'],
                    'display_name' => $item['display_name'],
                    'display_description' => $item['display_description'],
                    'uptake_pct' => $item['uptake_pct'],
                    'portion' => $item['portion'],
                    'waste_pct' => $item['waste_pct'],
                    'selling_price_minor' => $item['selling_price_minor'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            $this->pdo->commit();
            $before = $menu;
            $after = $newMenu;
            \App\Models\Audit::log($this->pdo, $orgId, $actor['id'] ?? 0, 'menu', (int) $newMenu['id'], 'duplicate', $before, $after);
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $_SESSION['flash_error'] = 'Failed to duplicate menu.';
            header('Location: /menus/' . $menuId . '/edit');
            exit;
        }

        $_SESSION['flash_success'] = 'Menu duplicated.';
        header('Location: /menus/' . (int) $newMenu['id'] . '/edit');
        exit;
    }

    private function lockedReport(int $orgId, array $menu, array $groups, ?int $paxCount = null): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mi.id,
                    mi.menu_group_id,
                    mi.dish_id,
                    mi.display_name,
                    mi.display_description,
                    mi.selling_price_minor,
                    d.name AS dish_name,
                    s.dish_cost_per_serving_minor,
                    s.uptake_pct AS effective_uptake_pct,
                    s.portion AS effective_portion,
                    s.waste_pct AS effective_waste_pct,
                    s.item_cost_per_pax_minor
             FROM menu_item_cost_snapshots s
             JOIN (
                SELECT menu_item_id, MAX(created_at) AS latest_created
                FROM menu_item_cost_snapshots
                WHERE org_id = :org_id AND menu_id = :menu_id
                GROUP BY menu_item_id
             ) latest
                ON latest.menu_item_id = s.menu_item_id AND latest.latest_created = s.created_at
             JOIN menu_items mi ON mi.id = s.menu_item_id AND mi.org_id = s.org_id
             JOIN menu_groups mg ON mg.id = mi.menu_group_id AND mg.org_id = s.org_id
             JOIN dishes d ON d.id = s.dish_id AND d.org_id = s.org_id
             WHERE s.org_id = :org_id AND s.menu_id = :menu_id
             ORDER BY mg.sort_order ASC, mi.sort_order ASC, mi.id ASC'
        );
        $stmt->execute(['org_id' => $orgId, 'menu_id' => $menu['id']]);
        $items = $stmt->fetchAll();

        return MenuCost::computeLocked($this->pdo, $menu, $groups, $items, $paxCount);
    }

    private function attachDishCosts(int $orgId, array $items): array
    {
        return array_map(function (array $item) use ($orgId): array {
            $summary = DishCost::summary(
                $this->pdo,
                $orgId,
                (int) $item['dish_id'],
                (int) $item['dish_yield_servings']
            );
            $item['dish_cost_per_serving_minor'] = $summary['cost_per_serving_minor'];
            return $item;
        }, $items);
    }

    private function canLockMenu(array $report, array $groups, array $items): bool
    {
        if (empty($groups) || empty($items)) {
            return false;
        }
        foreach ($report['items'] as $item) {
            if ($item['dish_cost_per_serving_minor'] === null) {
                return false;
            }
        }
        return true;
    }

    private function menuIngredientIds(int $orgId, int $menuId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT dl.ingredient_id
             FROM menu_items mi
             JOIN menu_groups mg ON mg.id = mi.menu_group_id AND mg.org_id = mi.org_id
             JOIN dish_lines dl ON dl.dish_id = mi.dish_id AND dl.org_id = mi.org_id
             WHERE mi.org_id = :org_id AND mg.menu_id = :menu_id'
        );
        $stmt->execute(['org_id' => $orgId, 'menu_id' => $menuId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'ingredient_id'));
    }

    private function latestIngredientCost(int $orgId, int $ingredientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ic.id AS ingredient_cost_id,
                    ic.cost_per_base_x10000,
                    ic.currency,
                    ic.effective_at,
                    base_uom.id AS base_uom_id
             FROM ingredient_costs ic
             JOIN ingredients i ON i.id = ic.ingredient_id AND i.org_id = ic.org_id
             JOIN uoms base_uom ON base_uom.uom_set_id = i.uom_set_id AND base_uom.org_id = i.org_id AND base_uom.is_base = 1
             WHERE ic.org_id = :org_id AND ic.ingredient_id = :ingredient_id
             ORDER BY ic.effective_at DESC, ic.id DESC
             LIMIT 1'
        );
        $stmt->execute(['org_id' => $orgId, 'ingredient_id' => $ingredientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function payloadFromRequest(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'menu_type' => $_POST['menu_type'] ?? 'package',
            'price_min_minor' => $this->parseCurrencyToMinor($_POST['price_min_major'] ?? null),
            'price_max_minor' => $this->parseCurrencyToMinor($_POST['price_max_major'] ?? null),
            'currency' => strtoupper(trim($_POST['currency'] ?? 'USD')),
            'price_label_suffix' => trim($_POST['price_label_suffix'] ?? ''),
            'min_pax' => $this->parseNullableInt($_POST['min_pax'] ?? null),
            'default_waste_pct' => $this->parseNullableDecimal($_POST['default_waste_pct'] ?? null) ?? 0,
            'show_descriptions' => isset($_POST['show_descriptions']) ? 1 : 0,
            'servings' => 1,
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];
        if ($payload['name'] === '') {
            $errors[] = 'Name is required.';
        }
        if (!in_array($payload['menu_type'], ['package', 'per_item'], true)) {
            $errors[] = 'Menu type is invalid.';
        }
        if ($payload['menu_type'] === 'package' && (!$payload['min_pax'] || $payload['min_pax'] <= 0)) {
            $errors[] = 'Minimum pax is required for package menus.';
        }
        if (!preg_match('/^[A-Z]{3}$/', $payload['currency'])) {
            $errors[] = 'Currency is invalid.';
        }
        return $errors;
    }

    private function loadOrg(?int $orgId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, default_currency FROM organizations WHERE id = :id');
        $stmt->execute(['id' => $orgId]);
        return $stmt->fetch() ?: [];
    }

    private function jsonPayload(): ?array
    {
        $payload = json_decode(file_get_contents('php://input'), true);
        return is_array($payload) ? $payload : null;
    }

    private function parseNullableDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^-?\d+(\.\d{1,4})?$/', $value)) {
            return null;
        }
        return (float) $value;
    }

    private function parseNullableInt($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^-?\d+$/', $value)) {
            return null;
        }
        return (int) $value;
    }

    private function parseCurrencyToMinor($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^-?\d+(\.\d{1,4})?$/', $value)) {
            return null;
        }
        $parts = explode('.', $value, 2);
        $major = (int) $parts[0];
        $minor = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
        $minor = substr($minor, 0, 2);
        $minorValue = (int) $minor;

        return ($major * 100) + ($major < 0 ? -$minorValue : $minorValue);
    }
}
