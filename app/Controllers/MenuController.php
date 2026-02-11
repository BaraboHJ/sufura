<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Menu;
use App\Models\MenuGroup;
use App\Models\MenuItem;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Services\DishCost;
use App\Services\MenuCost;
use PDO;

class MenuController
{
    private PDO $pdo;
    private array $compareCache = [];

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

        $viewOnly = ($_GET['mode'] ?? '') === 'view';
        $canLock = !$viewOnly && $menu['cost_mode'] === 'live' && $this->canLockMenu($report, $groups, $itemsWithCosts);
        $dishCategories = DishCategory::listByOrg($this->pdo, $orgId);

        $pageTitle = $viewOnly ? 'View Menu' : 'Edit Menu';
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

    public function compareForm(): void
    {
        Auth::requireLogin();
        $orgId = Auth::currentOrgId();
        $menus = Menu::listByOrg($this->pdo, $orgId);
        $pageTitle = 'Compare Menus';
        $view = __DIR__ . '/../../views/menus/compare.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function compareView(): void
    {
        Auth::requireLogin();
        $idsParam = trim($_GET['ids'] ?? '');
        $menuIds = $this->parseMenuIds($idsParam);
        if (count($menuIds) < 2 || count($menuIds) > 4) {
            $_SESSION['flash_error'] = 'Select between 2 and 4 menus to compare.';
            header('Location: /menus/compare');
            exit;
        }
        $pageTitle = 'Menu Comparison';
        $view = __DIR__ . '/../../views/menus/compare_view.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function compareApi(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            return;
        }

        $payload = $this->jsonPayload();
        if (!$payload) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid payload.']);
            return;
        }

        $menuIds = $payload['menu_ids'] ?? [];
        if (!is_array($menuIds)) {
            http_response_code(422);
            echo json_encode(['error' => 'menu_ids must be an array.']);
            return;
        }

        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
        if (count($menuIds) < 2 || count($menuIds) > 4) {
            http_response_code(422);
            echo json_encode(['error' => 'Select between 2 and 4 menus.']);
            return;
        }

        $orgId = Auth::currentOrgId();
        $payload = $this->buildComparePayload($orgId, $menuIds);
        if (isset($payload['error'])) {
            http_response_code(404);
        }
        echo json_encode($payload);
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
        $menuId = isset($params['id']) ? (int) $params['id'] : 0;
        $menu = Menu::findById($this->pdo, $orgId, $menuId);

        if (!$menu) {
            $_SESSION['flash_error'] = 'Menu not found.';
            header('Location: /menus');
            exit;
        }

        $this->deleteMenuWithDependencies($orgId, $actor['id'] ?? 0, $menuId);
        $_SESSION['flash_success'] = 'Menu deleted.';
        header('Location: /menus');
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
        $menuIds = is_array($selectedIds) ? array_values(array_unique(array_map('intval', $selectedIds))) : [];
        $menuIds = array_values(array_filter($menuIds, function (int $id): bool {
            return $id > 0;
        }));

        if (empty($menuIds)) {
            $_SESSION['flash_error'] = 'Select at least one menu to delete.';
            header('Location: /menus');
            exit;
        }

        $deleted = 0;
        foreach ($menuIds as $menuId) {
            $menu = Menu::findById($this->pdo, $orgId, $menuId);
            if (!$menu) {
                continue;
            }
            $this->deleteMenuWithDependencies($orgId, $actor['id'] ?? 0, $menuId);
            $deleted++;
        }

        $_SESSION['flash_success'] = "Deleted {$deleted} menu(s).";
        header('Location: /menus');
        exit;
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
                    (org_id, menu_id, menu_item_id, dish_id, dish_cost_per_serving_minor, uptake_pct, `portion`, waste_pct, item_cost_per_pax_minor, created_at)
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
                    s.`portion` AS effective_portion,
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


    private function deleteMenuWithDependencies(int $orgId, int $actorUserId, int $menuId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM menu_item_cost_snapshots WHERE org_id = :org_id AND menu_id = :menu_id');
            $stmt->execute(['org_id' => $orgId, 'menu_id' => $menuId]);

            $stmt = $this->pdo->prepare('DELETE FROM menu_ingredient_cost_snapshots WHERE org_id = :org_id AND menu_id = :menu_id');
            $stmt->execute(['org_id' => $orgId, 'menu_id' => $menuId]);

            $stmt = $this->pdo->prepare('DELETE FROM menu_cost_snapshots WHERE org_id = :org_id AND menu_id = :menu_id');
            $stmt->execute(['org_id' => $orgId, 'menu_id' => $menuId]);

            $items = MenuItem::listByMenu($this->pdo, $orgId, $menuId);
            foreach ($items as $item) {
                MenuItem::delete($this->pdo, $orgId, $actorUserId, (int) $item['id']);
            }

            $groups = MenuGroup::listByMenu($this->pdo, $orgId, $menuId);
            foreach ($groups as $group) {
                MenuGroup::delete($this->pdo, $orgId, $actorUserId, (int) $group['id']);
            }

            $stmt = $this->pdo->prepare('DELETE FROM menus WHERE org_id = :org_id AND id = :id');
            $stmt->execute(['org_id' => $orgId, 'id' => $menuId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function parseMenuIds(string $idsParam): array
    {
        if ($idsParam === '') {
            return [];
        }
        $ids = array_filter(array_map('trim', explode(',', $idsParam)));
        $ids = array_values(array_unique(array_map('intval', $ids)));
        return array_values(array_filter($ids, function (int $id): bool {
            return $id > 0;
        }));
    }

    private function buildComparePayload(int $orgId, array $menuIds): array
    {
        $menus = $this->menusByIds($orgId, $menuIds);
        if (count($menus) !== count($menuIds)) {
            return ['error' => 'One or more menus could not be found.'];
        }

        $groups = $this->menuGroupsByMenuIds($orgId, $menuIds);
        $items = $this->menuItemsByMenuIds($orgId, $menuIds);
        $dishIds = array_values(array_unique(array_map(function (array $item): int {
            return (int) $item['dish_id'];
        }, $items)));
        $dishCosts = $this->dishCostsByIds($orgId, $dishIds);

        $groupsByMenu = [];
        foreach ($groups as $group) {
            $groupsByMenu[(int) $group['menu_id']][] = $group;
        }

        $itemsByMenu = [];
        foreach ($items as $item) {
            $itemsByMenu[(int) $item['menu_id']][] = $item;
        }

        $groupKeys = [];
        foreach ($groups as $group) {
            $key = $this->normalizeGroupKey($group['name']);
            if (!isset($groupKeys[$key])) {
                $groupKeys[$key] = $group['name'];
            }
        }

        $menuSummaries = [];
        $itemsByGroup = [];

        foreach ($menuIds as $menuId) {
            $menu = $menus[$menuId];
            $menuGroups = $groupsByMenu[$menuId] ?? [];
            $menuItems = $itemsByMenu[$menuId] ?? [];

            if ($menu['cost_mode'] === 'locked') {
                $report = $this->lockedReport($orgId, $menu, $menuGroups, null);
            } else {
                $menuItems = array_map(function (array $item) use ($dishCosts): array {
                    $dishId = (int) $item['dish_id'];
                    $item['dish_cost_per_serving_minor'] = $dishCosts[$dishId]['cost_per_serving_minor'] ?? null;
                    return $item;
                }, $menuItems);
                $report = $this->computeMenuReportCached($menuId, $menu, $menuGroups, $menuItems);
            }

            $menuSummaries[] = $this->buildMenuSummary($menu, $menuGroups, $report);

            $reportItems = [];
            foreach ($report['items'] as $reportItem) {
                $reportItems[(int) $reportItem['id']] = $reportItem;
            }

            foreach ($menuItems as $item) {
                $groupId = (int) $item['menu_group_id'];
                $groupName = $this->groupNameById($menuGroups, $groupId);
                $groupKey = $this->normalizeGroupKey($groupName);
                $name = $item['display_name'] ?: $item['dish_name'];
                $itemReport = $reportItems[(int) $item['id']] ?? null;
                $itemsByGroup[$groupKey][$menuId][] = [
                    'id' => (int) $item['id'],
                    'dish_id' => (int) $item['dish_id'],
                    'name' => $name,
                    'display_description' => $item['display_description'],
                    'dish_name' => $item['dish_name'],
                    'cost_per_pax_minor' => $itemReport['item_cost_per_pax_minor'] ?? null,
                    'selling_price_minor' => $item['selling_price_minor'],
                    'uptake_pct' => $itemReport['effective_uptake_pct'] ?? $item['uptake_pct'],
                    'portion' => $itemReport['effective_portion'] ?? $item['portion'],
                ];
            }
        }

        $groupList = [];
        foreach ($groupKeys as $key => $name) {
            $groupList[] = ['key' => $key, 'name' => $name];
        }

        return [
            'menus' => $menuSummaries,
            'groups' => $groupList,
            'items' => $itemsByGroup,
        ];
    }

    private function computeMenuReportCached(int $menuId, array $menu, array $groups, array $items): array
    {
        if (isset($this->compareCache[$menuId])) {
            return $this->compareCache[$menuId];
        }
        $report = MenuCost::computeLive($this->pdo, $menu, $groups, $items, null);
        $this->compareCache[$menuId] = $report;
        return $report;
    }

    private function buildMenuSummary(array $menu, array $groups, array $report): array
    {
        $priceMin = $menu['price_min_minor'] ?? null;
        $priceMax = $menu['price_max_minor'] ?? null;
        $priceMax = $priceMax ?? $priceMin;
        $costPerPax = $report['menu_cost_per_pax_minor'] ?? null;
        $pricePerPax = $menu['menu_type'] === 'per_item'
            ? $this->perItemPricePerPax($report['items'])
            : $priceMin;

        $pricePerPaxMax = $menu['menu_type'] === 'per_item'
            ? $pricePerPax
            : $priceMax;

        $profitMin = ($pricePerPax !== null && $costPerPax !== null) ? $pricePerPax - $costPerPax : null;
        $profitMax = ($pricePerPaxMax !== null && $costPerPax !== null) ? $pricePerPaxMax - $costPerPax : null;

        $foodCostMin = ($pricePerPax && $costPerPax !== null) ? ($costPerPax / $pricePerPax) : null;
        $foodCostMax = ($pricePerPaxMax && $costPerPax !== null) ? ($costPerPax / $pricePerPaxMax) : null;

        return [
            'id' => (int) $menu['id'],
            'name' => $menu['name'],
            'menu_type' => $menu['menu_type'],
            'currency' => $menu['currency'],
            'price_min_minor' => $priceMin,
            'price_max_minor' => $priceMax,
            'cost_mode' => $menu['cost_mode'],
            'menu_cost_per_pax_minor' => $costPerPax,
            'profit_min_minor' => $profitMin,
            'profit_max_minor' => $profitMax,
            'food_cost_pct_min' => $foodCostMin,
            'food_cost_pct_max' => $foodCostMax,
        ];
    }

    private function perItemPricePerPax(array $reportItems): ?int
    {
        if (empty($reportItems)) {
            return null;
        }
        $total = 0;
        foreach ($reportItems as $item) {
            $uptake = $item['effective_uptake_pct'] ?? 0;
            $portion = $item['effective_portion'] ?? 0;
            $price = $item['selling_price_minor'] ?? 0;
            $total += (int) round($price * $uptake * $portion);
        }
        return $total;
    }

    private function groupNameById(array $groups, int $groupId): string
    {
        foreach ($groups as $group) {
            if ((int) $group['id'] === $groupId) {
                return $group['name'];
            }
        }
        return 'Ungrouped';
    }

    private function normalizeGroupKey(string $name): string
    {
        return strtolower(trim($name));
    }

    private function menusByIds(int $orgId, array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT id, org_id, name, menu_type, price_min_minor, price_max_minor, currency, price_label_suffix, min_pax,
                    default_waste_pct, show_descriptions, servings, cost_mode, locked_at, locked_by_user_id, created_at, updated_at
             FROM menus
             WHERE org_id = ? AND id IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([$orgId], $menuIds));
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = $row;
        }
        return $map;
    }

    private function menuGroupsByMenuIds(int $orgId, array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT id, menu_id, name, uptake_pct, `portion`, waste_pct, sort_order, created_at, updated_at
             FROM menu_groups
             WHERE org_id = ? AND menu_id IN (' . $placeholders . ')
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(array_merge([$orgId], $menuIds));
        return $stmt->fetchAll();
    }

    private function menuItemsByMenuIds(int $orgId, array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($menuIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT mi.id,
                    mi.menu_group_id,
                    mg.menu_id,
                    mi.dish_id,
                    mi.display_name,
                    mi.display_description,
                    mi.uptake_pct,
                    mi.`portion`,
                    mi.waste_pct,
                    mi.selling_price_minor,
                    mi.sort_order,
                    d.name AS dish_name,
                    d.description AS dish_description,
                    d.yield_servings AS dish_yield_servings
             FROM menu_items mi
             JOIN menu_groups mg ON mg.id = mi.menu_group_id AND mg.org_id = mi.org_id
             JOIN dishes d ON d.id = mi.dish_id AND d.org_id = mi.org_id
             WHERE mi.org_id = ? AND mg.menu_id IN (' . $placeholders . ')
             ORDER BY mg.sort_order ASC, mi.sort_order ASC, mi.id ASC'
        );
        $stmt->execute(array_merge([$orgId], $menuIds));
        return $stmt->fetchAll();
    }

    private function dishCostsByIds(int $orgId, array $dishIds): array
    {
        if (empty($dishIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($dishIds), '?'));
        $sql = 'SELECT d.id AS dish_id,
                       d.yield_servings,
                       dl.quantity,
                       u.factor_to_base,
                       u.uom_set_id AS uom_set_id,
                       i.uom_set_id AS ingredient_uom_set_id,
                       ic.cost_per_base_x10000
                FROM dishes d
                LEFT JOIN dish_lines dl ON dl.dish_id = d.id AND dl.org_id = d.org_id
                LEFT JOIN ingredients i ON i.id = dl.ingredient_id AND i.org_id = dl.org_id
                LEFT JOIN uoms u ON u.id = dl.uom_id AND u.org_id = dl.org_id
                LEFT JOIN (
                    SELECT ic1.ingredient_id, ic1.cost_per_base_x10000, ic1.effective_at
                    FROM ingredient_costs ic1
                    JOIN (
                        SELECT ingredient_id, MAX(effective_at) AS max_effective
                        FROM ingredient_costs
                        WHERE org_id = ?
                        GROUP BY ingredient_id
                    ) latest
                        ON latest.ingredient_id = ic1.ingredient_id AND latest.max_effective = ic1.effective_at
                    WHERE ic1.org_id = ?
                ) ic ON ic.ingredient_id = dl.ingredient_id
                WHERE d.org_id = ? AND d.id IN (' . $placeholders . ')';
        $params = array_merge([$orgId, $orgId, $orgId], $dishIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $summary = [];
        foreach ($rows as $row) {
            $dishId = (int) $row['dish_id'];
            if (!isset($summary[$dishId])) {
                $summary[$dishId] = [
                    'yield_servings' => (int) $row['yield_servings'],
                    'total_cost_minor' => 0,
                    'missing' => false,
                    'lines' => 0,
                ];
            }
            if ($row['quantity'] === null) {
                continue;
            }
            $summary[$dishId]['lines']++;
            $costPerBase = $row['cost_per_base_x10000'] !== null ? (int) $row['cost_per_base_x10000'] : null;
            $invalidUnits = $row['uom_set_id'] !== null
                && $row['ingredient_uom_set_id'] !== null
                && (int) $row['uom_set_id'] !== (int) $row['ingredient_uom_set_id'];
            if ($costPerBase === null || $invalidUnits) {
                $summary[$dishId]['missing'] = true;
                continue;
            }
            $qtyInBase = (float) $row['quantity'] * (float) $row['factor_to_base'];
            $summary[$dishId]['total_cost_minor'] += (int) round(($qtyInBase * $costPerBase) / 10000);
        }

        $costs = [];
        foreach ($summary as $dishId => $data) {
            $costPerServing = null;
            if (!$data['missing']) {
                $yield = $data['yield_servings'];
                $costPerServing = $yield > 0 ? (int) round($data['total_cost_minor'] / $yield) : null;
            }
            $costs[$dishId] = [
                'cost_per_serving_minor' => $costPerServing,
            ];
        }

        return $costs;
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
