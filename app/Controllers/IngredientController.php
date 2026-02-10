<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Ingredient;
use PDO;

class IngredientController
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
        $ingredients = Ingredient::listByOrgWithCosts($this->pdo, $orgId);

        $staleDays = (int) ($org['stale_cost_days'] ?? 30);
        $allIngredients = array_map(function (array $row) use ($staleDays): array {
            $row['status'] = $this->determineStatus($row['cost_effective_at'] ?? null, $staleDays);
            return $row;
        }, $ingredients);

        $missingCount = count(array_filter($allIngredients, function (array $row): bool {
            return $row['status'] === 'missing';
        }));

        $statusFilter = $_GET['status'] ?? '';
        $validFilters = ['missing', 'stale', 'ok'];
        if (in_array($statusFilter, $validFilters, true)) {
            $ingredients = array_values(array_filter($allIngredients, function (array $row) use ($statusFilter): bool {
                return $row['status'] === $statusFilter;
            }));
        } else {
            $ingredients = $allIngredients;
            $statusFilter = '';
        }

        $pageTitle = 'Ingredients';
        $view = __DIR__ . '/../../views/ingredients/index.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function createForm(): void
    {
        Auth::requireRole(['admin', 'editor']);
        $orgId = Auth::currentOrgId();
        $uomSets = Ingredient::listUomSets($this->pdo, $orgId);
        $pageTitle = 'New Ingredient';
        $view = __DIR__ . '/../../views/ingredients/new.php';
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

        $errors = $this->validatePayload($orgId, $payload);
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_values'] = $payload;
            header('Location: /ingredients/new');
            exit;
        }

        Ingredient::create($this->pdo, $orgId, $actor['id'] ?? 0, $payload);

        $_SESSION['flash_success'] = 'Ingredient created.';
        header('Location: /ingredients');
        exit;
    }

    public function show(array $params): void
    {
        Auth::requireLogin();
        $orgId = Auth::currentOrgId();
        $org = $this->loadOrg($orgId);
        $ingredientId = isset($params['id']) ? (int) $params['id'] : 0;
        $ingredient = Ingredient::findByIdWithMeta($this->pdo, $orgId, $ingredientId);

        if (!$ingredient) {
            $_SESSION['flash_error'] = 'Ingredient not found.';
            header('Location: /ingredients');
            exit;
        }

        $currentCost = Ingredient::latestCost($this->pdo, $orgId, $ingredientId) ?? [];
        $costHistory = Ingredient::costHistory($this->pdo, $orgId, $ingredientId);
        $status = $this->determineStatus($currentCost['effective_at'] ?? null, (int) ($org['stale_cost_days'] ?? 30));
        $uoms = Ingredient::listUomsBySet($this->pdo, $orgId, (int) $ingredient['uom_set_id']);
        $currency = $org['default_currency'] ?? 'USD';

        $pageTitle = $ingredient['name'];
        $view = __DIR__ . '/../../views/ingredients/show.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function editForm(array $params): void
    {
        Auth::requireRole(['admin', 'editor']);
        $orgId = Auth::currentOrgId();
        $ingredientId = isset($params['id']) ? (int) $params['id'] : 0;
        $ingredient = Ingredient::findById($this->pdo, $orgId, $ingredientId);

        if (!$ingredient) {
            $_SESSION['flash_error'] = 'Ingredient not found.';
            header('Location: /ingredients');
            exit;
        }

        $uomSets = Ingredient::listUomSets($this->pdo, $orgId);
        $pageTitle = 'Edit Ingredient';
        $view = __DIR__ . '/../../views/ingredients/edit.php';
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
        $ingredientId = isset($params['id']) ? (int) $params['id'] : 0;
        $existing = Ingredient::findById($this->pdo, $orgId, $ingredientId);

        if (!$existing) {
            $_SESSION['flash_error'] = 'Ingredient not found.';
            header('Location: /ingredients');
            exit;
        }

        $payload = $this->payloadFromRequest();
        $errors = $this->validatePayload($orgId, $payload, $ingredientId);
        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_values'] = $payload;
            header('Location: /ingredients/' . $ingredientId . '/edit');
            exit;
        }

        Ingredient::update($this->pdo, $orgId, $actor['id'] ?? 0, $ingredientId, $payload);

        $_SESSION['flash_success'] = 'Ingredient updated.';
        header('Location: /ingredients/' . $ingredientId);
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
        $ingredientId = isset($params['id']) ? (int) $params['id'] : 0;
        $ingredient = Ingredient::findById($this->pdo, $orgId, $ingredientId);

        if (!$ingredient) {
            $_SESSION['flash_error'] = 'Ingredient not found.';
            header('Location: /ingredients');
            exit;
        }

        if (
            Ingredient::hasDishLines($this->pdo, $orgId, $ingredientId)
            || Ingredient::hasMenuSnapshots($this->pdo, $orgId, $ingredientId)
            || Ingredient::hasCostImportRows($this->pdo, $orgId, $ingredientId)
        ) {
            $_SESSION['flash_error'] = 'Ingredient cannot be deleted because it is referenced by dishes, menus, or cost imports.';
            header('Location: /ingredients/' . $ingredientId);
            exit;
        }

        Ingredient::delete($this->pdo, $orgId, $actor['id'] ?? 0, $ingredientId);
        $_SESSION['flash_success'] = 'Ingredient deleted.';
        header('Location: /ingredients');
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
        $ingredientIds = is_array($selectedIds) ? array_values(array_unique(array_map('intval', $selectedIds))) : [];
        $ingredientIds = array_values(array_filter($ingredientIds, function (int $id): bool {
            return $id > 0;
        }));

        if (empty($ingredientIds)) {
            $_SESSION['flash_error'] = 'Select at least one ingredient to delete.';
            header('Location: /ingredients');
            exit;
        }

        $deleted = 0;
        $skipped = 0;
        foreach ($ingredientIds as $ingredientId) {
            $ingredient = Ingredient::findById($this->pdo, $orgId, $ingredientId);
            if (!$ingredient) {
                $skipped++;
                continue;
            }

            if (
                Ingredient::hasDishLines($this->pdo, $orgId, $ingredientId)
                || Ingredient::hasMenuSnapshots($this->pdo, $orgId, $ingredientId)
                || Ingredient::hasCostImportRows($this->pdo, $orgId, $ingredientId)
            ) {
                $skipped++;
                continue;
            }

            Ingredient::delete($this->pdo, $orgId, $actor['id'] ?? 0, $ingredientId);
            $deleted++;
        }

        if ($deleted > 0) {
            $_SESSION['flash_success'] = "Deleted {$deleted} ingredient(s).";
        }
        if ($skipped > 0) {
            $_SESSION['flash_error'] = "Skipped {$skipped} ingredient(s) because they are referenced or missing.";
        }

        header('Location: /ingredients');
        exit;
    }

    public function addCost(array $params): void
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
        $ingredientId = isset($params['id']) ? (int) $params['id'] : 0;
        $ingredient = Ingredient::findByIdWithMeta($this->pdo, $orgId, $ingredientId);

        if (!$ingredient) {
            http_response_code(404);
            echo json_encode(['error' => 'Ingredient not found.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid payload.']);
            return;
        }

        $purchaseQty = isset($payload['purchase_qty']) ? (float) $payload['purchase_qty'] : 0.0;
        $purchaseUomId = isset($payload['purchase_uom_id']) ? (int) $payload['purchase_uom_id'] : 0;
        $totalCostMinor = $this->extractTotalCostMinor($payload);

        if ($purchaseQty <= 0 || $purchaseUomId <= 0 || $totalCostMinor === null) {
            http_response_code(422);
            echo json_encode(['error' => 'Purchase quantity, unit, and total cost are required.']);
            return;
        }

        $uom = Ingredient::findUomById($this->pdo, $orgId, $purchaseUomId);
        if (!$uom || (int) $uom['uom_set_id'] !== (int) $ingredient['uom_set_id']) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid unit for this ingredient.']);
            return;
        }

        $baseQty = $purchaseQty * (float) $uom['factor_to_base'];
        if ($baseQty <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid conversion quantity.']);
            return;
        }

        $costPerBaseX10000 = (int) round(($totalCostMinor * 10000) / $baseQty);
        $org = $this->loadOrg($orgId);

        $newCost = Ingredient::addCost($this->pdo, $orgId, $actor['id'] ?? 0, $ingredientId, [
            'cost_per_base_x10000' => $costPerBaseX10000,
            'currency' => $org['default_currency'] ?? 'USD',
            'effective_at' => date('Y-m-d H:i:s'),
            'purchase_qty' => $purchaseQty,
            'purchase_uom_id' => $purchaseUomId,
            'total_cost_minor' => $totalCostMinor,
        ]);

        echo json_encode([
            'cost_per_base_x10000' => (int) ($newCost['cost_per_base_x10000'] ?? $costPerBaseX10000),
            'last_updated_at' => $newCost['effective_at'] ?? date('Y-m-d H:i:s'),
            'currency' => $newCost['currency'] ?? ($org['default_currency'] ?? 'USD'),
        ]);
    }

    public function search(): void
    {
        Auth::requireLogin();
        $orgId = Auth::currentOrgId();
        $org = $this->loadOrg($orgId);
        $query = trim($_GET['q'] ?? '');
        $results = $this->searchIngredients($orgId, $query, (int) ($org['stale_cost_days'] ?? 30));
        header('Content-Type: application/json');
        echo json_encode($results);
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

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $data = [
            'name' => trim((string) ($payload['name'] ?? '')),
            'uom_set_id' => isset($payload['uom_set_id']) ? (int) $payload['uom_set_id'] : 0,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'active' => isset($payload['active']) ? (int) $payload['active'] : 1,
        ];

        $errors = $this->validatePayload($orgId, $data);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['error' => implode(' ', $errors)]);
            return;
        }

        $ingredient = Ingredient::create($this->pdo, $orgId, $actor['id'] ?? 0, $data);
        echo json_encode([
            'id' => (int) ($ingredient['id'] ?? 0),
            'name' => $ingredient['name'] ?? $data['name'],
            'uom_set_id' => (int) ($ingredient['uom_set_id'] ?? $data['uom_set_id']),
        ]);
    }

    public function listUoms(): void
    {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $orgId = Auth::currentOrgId();
        $uomSetId = isset($_GET['uom_set_id']) ? (int) $_GET['uom_set_id'] : 0;

        if ($uomSetId <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'uom_set_id is required.']);
            return;
        }

        $uoms = Ingredient::listUomsBySet($this->pdo, $orgId, $uomSetId);
        echo json_encode(array_map(function (array $uom): array {
            return [
                'id' => (int) $uom['id'],
                'name' => $uom['name'],
                'symbol' => $uom['symbol'],
                'factor_to_base' => (float) $uom['factor_to_base'],
                'is_base' => (int) $uom['is_base'] === 1,
            ];
        }, $uoms));
    }

    private function payloadFromRequest(): array
    {
        return [
            'name' => trim($_POST['name'] ?? ''),
            'uom_set_id' => isset($_POST['uom_set_id']) ? (int) $_POST['uom_set_id'] : 0,
            'notes' => trim($_POST['notes'] ?? ''),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function validatePayload(int $orgId, array $payload, ?int $excludeId = null): array
    {
        $errors = [];

        if ($payload['name'] === '') {
            $errors[] = 'Name is required.';
        }

        if ($payload['uom_set_id'] <= 0 || !Ingredient::uomSetExists($this->pdo, $orgId, $payload['uom_set_id'])) {
            $errors[] = 'Select a valid unit of measure set.';
        }

        if ($payload['name'] !== '' && Ingredient::nameExists($this->pdo, $orgId, $payload['name'], $excludeId)) {
            $errors[] = 'Ingredient name must be unique within the organization.';
        }

        return $errors;
    }

    private function loadOrg(?int $orgId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, default_currency, stale_cost_days FROM organizations WHERE id = :id');
        $stmt->execute(['id' => $orgId]);
        return $stmt->fetch() ?: [];
    }

    private function determineStatus(?string $effectiveAt, int $staleDays): string
    {
        if (!$effectiveAt) {
            return 'missing';
        }

        try {
            $effective = new \DateTime($effectiveAt);
            $threshold = (new \DateTime())->modify('-' . $staleDays . ' days');
        } catch (\Exception $e) {
            return 'missing';
        }

        return $effective < $threshold ? 'stale' : 'ok';
    }

    private function extractTotalCostMinor(array $payload): ?int
    {
        if (isset($payload['total_cost_minor'])) {
            return (int) $payload['total_cost_minor'];
        }

        if (!isset($payload['total_cost_major'])) {
            return null;
        }

        $value = trim((string) $payload['total_cost_major']);
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

    private function searchIngredients(int $orgId, string $query, int $staleDays): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->pdo->prepare(
            'SELECT i.id,
                    i.name,
                    i.uom_set_id,
                    base_uom.symbol AS base_uom_symbol,
                    (SELECT ic.cost_per_base_x10000
                     FROM ingredient_costs ic
                     WHERE ic.org_id = i.org_id AND ic.ingredient_id = i.id
                     ORDER BY ic.effective_at DESC, ic.id DESC
                     LIMIT 1) AS cost_per_base_x10000,
                    (SELECT ic.effective_at
                     FROM ingredient_costs ic
                     WHERE ic.org_id = i.org_id AND ic.ingredient_id = i.id
                     ORDER BY ic.effective_at DESC, ic.id DESC
                     LIMIT 1) AS cost_effective_at
             FROM ingredients i
             JOIN uoms base_uom ON base_uom.uom_set_id = i.uom_set_id
                 AND base_uom.org_id = i.org_id
                 AND base_uom.is_base = 1
             WHERE i.org_id = :org_id
                 AND i.active = 1
                 AND i.name LIKE :query
             ORDER BY i.name
             LIMIT 20'
        );
        $stmt->execute(['org_id' => $orgId, 'query' => $like]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row) use ($staleDays): array {
            $hasCost = !empty($row['cost_effective_at']);
            $isStale = $hasCost ? $this->determineStatus($row['cost_effective_at'], $staleDays) === 'stale' : false;

            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'uom_set_id' => (int) $row['uom_set_id'],
                'base_uom_symbol' => $row['base_uom_symbol'],
                'has_cost' => $hasCost,
                'is_stale' => $isStale,
            ];
        }, $rows);
    }
}
