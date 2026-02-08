<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Dish;
use App\Models\Ingredient;
use PDO;

class BulkImportController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function ingredientForm(): void
    {
        Auth::requireRole(['admin', 'editor']);
        $pageTitle = 'Bulk Upload Ingredients';
        $view = __DIR__ . '/../../views/imports/ingredients/new.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function ingredientTemplate(): void
    {
        Auth::requireRole(['admin', 'editor']);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="ingredients_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['name', 'uom_set', 'notes', 'active']);
        fputcsv($output, ['Example Ingredient', 'Weight', 'Optional notes', '1']);
        fclose($output);
    }

    public function ingredientUpload(): void
    {
        Auth::requireRole(['admin', 'editor']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => ['CSV file is required.'],
            ];
            header('Location: /ingredients/import');
            exit;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $file = $_FILES['csv_file'];
        $handle = fopen($file['tmp_name'], 'r');

        if (!$handle) {
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => ['Unable to read uploaded file.'],
            ];
            header('Location: /ingredients/import');
            exit;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => ['CSV header row is required.'],
            ];
            header('Location: /ingredients/import');
            exit;
        }

        $columnMap = $this->mapCsvHeader($header);
        $required = ['name', 'uom_set'];
        $errors = [];
        foreach ($required as $column) {
            if (!isset($columnMap[$column])) {
                $errors[] = 'Missing required column: ' . $column;
            }
        }

        if (!empty($errors)) {
            fclose($handle);
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => $errors,
            ];
            header('Location: /ingredients/import');
            exit;
        }

        $uomSets = Ingredient::listUomSets($this->pdo, $orgId);
        $uomMap = [];
        foreach ($uomSets as $uomSet) {
            $uomMap[$this->normalizeName($uomSet['name'])] = (int) $uomSet['id'];
        }

        $created = 0;
        $skipped = 0;
        $rowNum = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $name = trim((string) ($data[$columnMap['name']] ?? ''));
            $uomSetName = trim((string) ($data[$columnMap['uom_set']] ?? ''));
            $notes = trim((string) ($this->getColumnValue($data, $columnMap, 'notes') ?? ''));
            $activeRaw = $this->getColumnValue($data, $columnMap, 'active');

            if ($name === '') {
                $errors[] = "Row {$rowNum}: Name is required.";
                $skipped++;
                continue;
            }

            if ($uomSetName === '') {
                $errors[] = "Row {$rowNum}: UoM set is required.";
                $skipped++;
                continue;
            }

            $uomSetId = $uomMap[$this->normalizeName($uomSetName)] ?? null;
            if (!$uomSetId) {
                $errors[] = "Row {$rowNum}: UoM set \"{$uomSetName}\" not found.";
                $skipped++;
                continue;
            }

            if (Ingredient::nameExists($this->pdo, $orgId, $name)) {
                $errors[] = "Row {$rowNum}: Ingredient name \"{$name}\" already exists.";
                $skipped++;
                continue;
            }

            $active = $this->parseBoolean($activeRaw, true) ? 1 : 0;

            Ingredient::create($this->pdo, $orgId, $actor['id'] ?? 0, [
                'name' => $name,
                'uom_set_id' => $uomSetId,
                'notes' => $notes,
                'active' => $active,
            ]);
            $created++;
        }

        fclose($handle);

        $_SESSION['form_summary'] = [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'file_name' => $file['name'] ?? 'upload.csv',
        ];

        if ($created > 0 && empty($errors)) {
            $_SESSION['flash_success'] = "Uploaded {$created} ingredients.";
        }

        header('Location: /ingredients/import');
        exit;
    }

    public function dishForm(): void
    {
        Auth::requireRole(['admin', 'editor']);
        $pageTitle = 'Bulk Upload Dishes';
        $view = __DIR__ . '/../../views/imports/dishes/new.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function dishTemplate(): void
    {
        Auth::requireRole(['admin', 'editor']);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dishes_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['name', 'description', 'yield_servings', 'active']);
        fputcsv($output, ['Example Dish', 'Optional description', '10', '1']);
        fclose($output);
    }

    public function dishUpload(): void
    {
        Auth::requireRole(['admin', 'editor']);
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        if (empty($_FILES['csv_file']['tmp_name'])) {
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => ['CSV file is required.'],
            ];
            header('Location: /dishes/import');
            exit;
        }

        $orgId = Auth::currentOrgId();
        $actor = Auth::currentUser();
        $file = $_FILES['csv_file'];
        $handle = fopen($file['tmp_name'], 'r');

        if (!$handle) {
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => ['Unable to read uploaded file.'],
            ];
            header('Location: /dishes/import');
            exit;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => ['CSV header row is required.'],
            ];
            header('Location: /dishes/import');
            exit;
        }

        $columnMap = $this->mapCsvHeader($header);
        $errors = [];
        if (!isset($columnMap['name'])) {
            $errors[] = 'Missing required column: name';
        }

        if (!empty($errors)) {
            fclose($handle);
            $_SESSION['form_summary'] = [
                'created' => 0,
                'skipped' => 0,
                'errors' => $errors,
            ];
            header('Location: /dishes/import');
            exit;
        }

        $created = 0;
        $skipped = 0;
        $rowNum = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $name = trim((string) ($data[$columnMap['name']] ?? ''));
            $description = trim((string) ($this->getColumnValue($data, $columnMap, 'description') ?? ''));
            $yieldRaw = $this->getColumnValue($data, $columnMap, 'yield_servings');
            $activeRaw = $this->getColumnValue($data, $columnMap, 'active');

            if ($name === '') {
                $errors[] = "Row {$rowNum}: Name is required.";
                $skipped++;
                continue;
            }

            $yieldServings = $this->parsePositiveInt($yieldRaw);
            if ($yieldServings === null) {
                $errors[] = "Row {$rowNum}: Yield servings must be a positive number.";
                $skipped++;
                continue;
            }

            $active = $this->parseBoolean($activeRaw, true) ? 1 : 0;

            Dish::create($this->pdo, $orgId, $actor['id'] ?? 0, [
                'name' => $name,
                'description' => $description,
                'yield_servings' => $yieldServings,
                'active' => $active,
            ]);
            $created++;
        }

        fclose($handle);

        $_SESSION['form_summary'] = [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'file_name' => $file['name'] ?? 'upload.csv',
        ];

        if ($created > 0 && empty($errors)) {
            $_SESSION['flash_success'] = "Uploaded {$created} dishes.";
        }

        header('Location: /dishes/import');
        exit;
    }

    private function mapCsvHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $column) {
            $normalized = $this->normalizeHeader((string) $column);
            if ($normalized !== '') {
                $map[$normalized] = $index;
            }
        }
        return $map;
    }

    private function getColumnValue(array $row, array $columnMap, string $key)
    {
        if (!isset($columnMap[$key])) {
            return null;
        }

        $index = $columnMap[$key];
        return $row[$index] ?? null;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
        return trim((string) $normalized, '_');
    }

    private function normalizeName(string $value): string
    {
        return strtolower(trim($value));
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    private function parseBoolean($value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        $truthy = ['1', 'true', 'yes', 'y', 'active'];
        $falsy = ['0', 'false', 'no', 'n', 'inactive'];

        if (in_array($normalized, $truthy, true)) {
            return true;
        }

        if (in_array($normalized, $falsy, true)) {
            return false;
        }

        return $default;
    }

    private function parsePositiveInt($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return 1;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }
}
