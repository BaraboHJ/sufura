<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Audit;
use App\Models\CostImport;
use PDO;

class ImportController
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
        $imports = CostImport::listWithSummary($this->pdo, $orgId);
        $pageTitle = 'Cost Imports';
        $view = __DIR__ . '/../../views/imports/costs/index.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function createForm(): void
    {
        Auth::requireRole(['admin', 'editor']);
        $pageTitle = 'New Cost Import';
        $view = __DIR__ . '/../../views/imports/costs/new.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function show(array $params): void
    {
        Auth::requireLogin();
        $orgId = Auth::currentOrgId();
        $importId = isset($params['id']) ? (int) $params['id'] : 0;
        $import = CostImport::findById($this->pdo, $orgId, $importId);

        if (!$import) {
            $_SESSION['flash_error'] = 'Import not found.';
            header('Location: /imports/costs');
            exit;
        }

        $rows = CostImport::rowsForImport($this->pdo, $orgId, $importId);
        $ingredientIds = array_values(array_unique(array_filter(array_map(function (array $row): ?int {
            return $row['matched_ingredient_id'] ? (int) $row['matched_ingredient_id'] : null;
        }, $rows))));
        $latestCosts = $this->latestCostsByIngredient($orgId, $ingredientIds);
        $baseUoms = $this->baseUomsByIngredient($orgId, $ingredientIds);
        $recentUpdates = $this->recentCostUpdates($orgId, $ingredientIds);
        $org = $this->loadOrg($orgId);

        $summary = $this->summarizeRows($rows);
        $currency = $org['default_currency'] ?? 'USD';

        $pageTitle = 'Cost Import Preview';
        $view = __DIR__ . '/../../views/imports/costs/show.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function upload(): void
    {
        Auth::requireRole(['admin', 'editor']);
        header('Content-Type: application/json');
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token.']);
            return;
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            http_response_code(422);
            echo json_encode(['error' => 'CSV file is required.']);
            return;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $file = $_FILES['csv_file'];
        $originalName = $file['name'] ?? 'import.csv';

        $import = CostImport::create($this->pdo, $orgId, $actor['id'] ?? 0, $originalName);
        $importId = (int) ($import['id'] ?? 0);

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to read uploaded file.']);
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            http_response_code(422);
            echo json_encode(['error' => 'CSV header row is required.']);
            return;
        }

        $columnMap = $this->mapCsvHeader($header);
        $required = ['ingredient_name', 'purchase_qty', 'purchase_uom', 'total_cost'];
        foreach ($required as $column) {
            if (!isset($columnMap[$column])) {
                fclose($handle);
                http_response_code(422);
                echo json_encode(['error' => 'Missing required column: ' . $column]);
                return;
            }
        }

        $ingredients = $this->ingredientMap($orgId);
        $uomMap = $this->uomMapBySet($orgId);

        $rowNum = 1;
        $rowsToInsert = [];
        $summary = [
            'matched_ok' => 0,
            'missing_ingredient' => 0,
            'invalid_uom' => 0,
            'invalid_number' => 0,
            'total_rows' => 0,
        ];

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $summary['total_rows']++;
            $rawName = (string) ($data[$columnMap['ingredient_name']] ?? '');
            $normalizedName = $this->normalizeName($rawName);
            $ingredient = $ingredients[$normalizedName] ?? null;

            $purchaseQty = $this->parseDecimal($data[$columnMap['purchase_qty']] ?? null);
            $purchaseUomSymbol = trim((string) ($data[$columnMap['purchase_uom']] ?? ''));
            $totalCostMinor = $this->parseCurrencyMinor($data[$columnMap['total_cost']] ?? null);

            $parseStatus = 'matched_ok';
            $errorMessage = null;
            $matchedIngredientId = $ingredient['id'] ?? null;
            $purchaseUomId = null;
            $computedCostPerBase = null;

            if (!$ingredient) {
                $parseStatus = 'missing_ingredient';
                $errorMessage = 'Ingredient not found.';
                $summary['missing_ingredient']++;
            } elseif ($purchaseQty === null || $purchaseQty <= 0 || $totalCostMinor === null || $totalCostMinor <= 0) {
                $parseStatus = 'invalid_number';
                $errorMessage = 'Invalid quantity or total cost.';
                $summary['invalid_number']++;
            } else {
                $uomSetId = (int) $ingredient['uom_set_id'];
                $uom = $this->findUomBySymbol($uomMap, $uomSetId, $purchaseUomSymbol);
                if (!$uom) {
                    $parseStatus = 'invalid_uom';
                    $errorMessage = 'Unit not in ingredient UOM set.';
                    $summary['invalid_uom']++;
                } else {
                    $purchaseUomId = (int) $uom['id'];
                    $baseQty = $purchaseQty * (float) $uom['factor_to_base'];
                    if ($baseQty <= 0) {
                        $parseStatus = 'invalid_number';
                        $errorMessage = 'Invalid unit conversion.';
                        $summary['invalid_number']++;
                    } else {
                        $computedCostPerBase = (int) round(($totalCostMinor * 10000) / $baseQty);
                        $summary['matched_ok']++;
                    }
                }
            }

            $rowsToInsert[] = [
                'import_id' => $importId,
                'org_id' => $orgId,
                'row_num' => $rowNum,
                'ingredient_name_raw' => $rawName !== '' ? $rawName : $normalizedName,
                'matched_ingredient_id' => $matchedIngredientId,
                'purchase_qty' => $purchaseQty,
                'purchase_uom_symbol' => $purchaseUomSymbol !== '' ? $purchaseUomSymbol : null,
                'purchase_uom_id' => $purchaseUomId,
                'total_cost_minor' => $totalCostMinor,
                'parse_status' => $parseStatus,
                'error_message' => $errorMessage,
                'computed_cost_per_base_x10000' => $computedCostPerBase,
            ];
        }

        fclose($handle);

        $this->insertImportRows($rowsToInsert);

        echo json_encode([
            'summary' => $summary,
            'redirect_url' => '/imports/costs/' . $importId,
        ]);
    }

    public function confirm(array $params): void
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
        $importId = isset($params['id']) ? (int) $params['id'] : 0;
        $import = CostImport::findById($this->pdo, $orgId, $importId);

        if (!$import) {
            http_response_code(404);
            echo json_encode(['error' => 'Import not found.']);
            return;
        }

        if ($import['status'] !== 'uploaded') {
            http_response_code(409);
            echo json_encode(['error' => 'Import already applied.']);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $force = is_array($payload) && !empty($payload['force']);

        $rows = CostImport::rowsForImport($this->pdo, $orgId, $importId);
        $matchedRows = array_values(array_filter($rows, function (array $row): bool {
            return $row['parse_status'] === 'matched_ok';
        }));

        if (empty($matchedRows)) {
            http_response_code(422);
            echo json_encode(['error' => 'No valid rows to apply.']);
            return;
        }

        $ingredientIds = array_values(array_unique(array_map(function (array $row): int {
            return (int) $row['matched_ingredient_id'];
        }, $matchedRows)));

        $recentUpdates = $this->recentCostUpdates($orgId, $ingredientIds);
        if ($recentUpdates['count'] > 0 && !$force) {
            http_response_code(409);
            echo json_encode([
                'error' => 'Some ingredients were updated today. Confirm force to overwrite.',
                'recent_count' => $recentUpdates['count'],
            ]);
            return;
        }

        $org = $this->loadOrg($orgId);
        $currency = $org['default_currency'] ?? 'USD';
        $effectiveAt = date('Y-m-d H:i:s');
        $beforeCosts = $this->latestCostsByIngredient($orgId, $ingredientIds);

        try {
            $this->pdo->beginTransaction();
            $this->batchInsertIngredientCosts($orgId, $matchedRows, $currency, $effectiveAt);
            CostImport::updateStatus($this->pdo, $orgId, $importId, 'applied');

            foreach ($matchedRows as $row) {
                $ingredientId = (int) $row['matched_ingredient_id'];
                $after = [
                    'ingredient_id' => $ingredientId,
                    'purchase_qty' => $row['purchase_qty'],
                    'purchase_uom_id' => $row['purchase_uom_id'],
                    'total_cost_minor' => $row['total_cost_minor'],
                    'cost_per_base_x10000' => $row['computed_cost_per_base_x10000'],
                    'currency' => $currency,
                    'effective_at' => $effectiveAt,
                ];
                Audit::log(
                    $this->pdo,
                    $orgId,
                    $actor['id'] ?? 0,
                    'ingredient_cost',
                    $ingredientId,
                    'bulk_import',
                    $beforeCosts[$ingredientId] ?? null,
                    $after
                );
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to apply import.']);
            return;
        }

        echo json_encode(['status' => 'ok']);
    }

    private function mapCsvHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized === 'ingredientname' || $normalized === 'ingredient_name') {
                $map['ingredient_name'] = $index;
            } elseif ($normalized === 'purchaseqty' || $normalized === 'purchase_qty' || $normalized === 'quantity') {
                $map['purchase_qty'] = $index;
            } elseif ($normalized === 'purchaseuom' || $normalized === 'purchase_uom' || $normalized === 'uom') {
                $map['purchase_uom'] = $index;
            } elseif ($normalized === 'totalcost' || $normalized === 'total_cost' || $normalized === 'cost') {
                $map['total_cost'] = $index;
            }
        }
        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim($value, '_');
    }

    private function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        return strtolower($value);
    }

    private function ingredientMap(int $orgId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, uom_set_id
             FROM ingredients
             WHERE org_id = :org_id'
        );
        $stmt->execute(['org_id' => $orgId]);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$this->normalizeName($row['name'])] = [
                'id' => (int) $row['id'],
                'uom_set_id' => (int) $row['uom_set_id'],
            ];
        }
        return $map;
    }

    private function uomMapBySet(int $orgId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, uom_set_id, symbol, factor_to_base
             FROM uoms
             WHERE org_id = :org_id'
        );
        $stmt->execute(['org_id' => $orgId]);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $setId = (int) $row['uom_set_id'];
            $symbolKey = strtolower(trim((string) $row['symbol']));
            $map[$setId][$symbolKey] = $row;
        }
        return $map;
    }

    private function findUomBySymbol(array $map, int $uomSetId, string $symbol): ?array
    {
        $key = strtolower(trim($symbol));
        return $map[$uomSetId][$key] ?? null;
    }

    private function parseDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^-?\d+(\.\d{1,6})?$/', $value)) {
            return null;
        }
        return (float) $value;
    }

    private function parseCurrencyMinor($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^-?\d+(\.\d{1,4})?$/', $value)) {
            return null;
        }
        $parts = explode('.', $value, 2);
        $major = (int) $parts[0];
        $minor = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
        $minor = substr($minor, 0, 2);
        $minorValue = (int) $minor;
        return ($major * 100) + ($major < 0 ? -$minorValue : $minorValue);
    }

    private function rowIsEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function insertImportRows(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $chunkSize = 200;
        $columns = [
            'import_id',
            'org_id',
            'row_num',
            'ingredient_name_raw',
            'matched_ingredient_id',
            'purchase_qty',
            'purchase_uom_symbol',
            'purchase_uom_id',
            'total_cost_minor',
            'parse_status',
            'error_message',
            'computed_cost_per_base_x10000',
        ];

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $placeholders = [];
            $values = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
                foreach ($columns as $column) {
                    $values[] = $row[$column] ?? null;
                }
            }

            $sql = 'INSERT INTO cost_import_rows (' . implode(',', $columns) . ') VALUES ' . implode(',', $placeholders);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
        }
    }

    private function batchInsertIngredientCosts(int $orgId, array $rows, string $currency, string $effectiveAt): void
    {
        $chunkSize = 200;
        $columns = [
            'org_id',
            'ingredient_id',
            'purchase_qty',
            'purchase_uom_id',
            'total_cost_minor',
            'cost_per_base_x10000',
            'currency',
            'effective_at',
        ];

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $placeholders = [];
            $values = [];
            foreach ($chunk as $row) {
                $placeholders[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
                $values[] = $orgId;
                $values[] = (int) $row['matched_ingredient_id'];
                $values[] = $row['purchase_qty'];
                $values[] = $row['purchase_uom_id'];
                $values[] = $row['total_cost_minor'];
                $values[] = $row['computed_cost_per_base_x10000'];
                $values[] = $currency;
                $values[] = $effectiveAt;
            }

            $sql = 'INSERT INTO ingredient_costs (' . implode(',', $columns) . ') VALUES ' . implode(',', $placeholders);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
        }
    }

    private function latestCostsByIngredient(int $orgId, array $ingredientIds): array
    {
        if (empty($ingredientIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ingredientIds), '?'));
        $sql = 'SELECT ic.ingredient_id,
                       ic.purchase_qty,
                       ic.purchase_uom_id,
                       ic.total_cost_minor,
                       ic.cost_per_base_x10000,
                       ic.currency,
                       ic.effective_at
                FROM ingredient_costs ic
                JOIN (
                    SELECT ingredient_id, MAX(effective_at) AS max_effective
                    FROM ingredient_costs
                    WHERE org_id = ?
                        AND ingredient_id IN (' . $placeholders . ')
                    GROUP BY ingredient_id
                ) latest
                    ON latest.ingredient_id = ic.ingredient_id
                    AND latest.max_effective = ic.effective_at
                WHERE ic.org_id = ?';
        $params = array_merge([$orgId], $ingredientIds, [$orgId]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['ingredient_id']] = $row;
        }
        return $map;
    }

    private function baseUomsByIngredient(int $orgId, array $ingredientIds): array
    {
        if (empty($ingredientIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ingredientIds), '?'));
        $sql = 'SELECT i.id AS ingredient_id,
                       u.symbol AS base_uom_symbol
                FROM ingredients i
                JOIN uoms u ON u.uom_set_id = i.uom_set_id AND u.org_id = i.org_id AND u.is_base = 1
                WHERE i.org_id = ? AND i.id IN (' . $placeholders . ')';
        $params = array_merge([$orgId], $ingredientIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['ingredient_id']] = $row['base_uom_symbol'];
        }
        return $map;
    }

    private function recentCostUpdates(int $orgId, array $ingredientIds): array
    {
        if (empty($ingredientIds)) {
            return ['count' => 0, 'ingredients' => []];
        }
        $placeholders = implode(',', array_fill(0, count($ingredientIds), '?'));
        $sql = 'SELECT ingredient_id, effective_at
                FROM ingredient_costs
                WHERE org_id = ?
                    AND ingredient_id IN (' . $placeholders . ')
                    AND effective_at >= CURDATE()';
        $params = array_merge([$orgId], $ingredientIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return [
            'count' => count($rows),
            'ingredients' => $rows,
        ];
    }

    private function summarizeRows(array $rows): array
    {
        $summary = [
            'matched_ok' => 0,
            'missing_ingredient' => 0,
            'invalid_uom' => 0,
            'invalid_number' => 0,
            'total_rows' => count($rows),
        ];
        foreach ($rows as $row) {
            if (isset($summary[$row['parse_status']])) {
                $summary[$row['parse_status']]++;
            }
        }
        return $summary;
    }

    private function loadOrg(?int $orgId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, default_currency FROM organizations WHERE id = :id');
        $stmt->execute(['id' => $orgId]);
        return $stmt->fetch() ?: [];
    }
}
