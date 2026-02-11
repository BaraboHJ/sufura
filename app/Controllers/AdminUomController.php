<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Uom;
use PDO;

class AdminUomController
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
        $uomSets = Uom::listSetsWithUoms($this->pdo, $orgId);

        $pageTitle = 'UoM Management';
        $view = __DIR__ . '/../../views/admin/uoms/index.php';
        require __DIR__ . '/../../views/layout.php';
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
        $setId = isset($params['id']) ? (int) $params['id'] : 0;
        $existingSet = Uom::findSet($this->pdo, $orgId, $setId);

        if (!$existingSet) {
            $_SESSION['flash_error'] = 'UoM set not found.';
            header('Location: /admin/uoms');
            exit;
        }

        $setName = trim((string) ($_POST['set_name'] ?? ''));
        $baseUomId = isset($_POST['base_uom_id']) ? (int) $_POST['base_uom_id'] : 0;
        $submittedUoms = $_POST['uoms'] ?? [];

        $errors = [];
        if ($setName === '') {
            $errors[] = 'UoM set name is required.';
        }

        if (!is_array($submittedUoms) || empty($submittedUoms)) {
            $errors[] = 'No UoMs were submitted.';
        }

        $uoms = [];
        $seenSymbols = [];
        foreach ($submittedUoms as $uomId => $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) $uomId;
            $name = trim((string) ($row['name'] ?? ''));
            $symbol = trim((string) ($row['symbol'] ?? ''));
            $factor = isset($row['factor_to_base']) ? (float) $row['factor_to_base'] : 0.0;

            if ($name === '') {
                $errors[] = 'Each UoM requires a name.';
            }

            if ($symbol === '') {
                $errors[] = 'Each UoM requires a symbol.';
            }

            $symbolKey = mb_strtolower($symbol);
            if ($symbol !== '' && isset($seenSymbols[$symbolKey])) {
                $errors[] = 'UoM symbols must be unique within a set.';
            }
            $seenSymbols[$symbolKey] = true;

            if ($factor <= 0) {
                $errors[] = 'Each UoM factor must be greater than zero.';
            }

            $uoms[] = [
                'id' => $id,
                'name' => $name,
                'symbol' => $symbol,
                'factor_to_base' => $factor,
            ];
        }

        $validIds = array_map(static fn (array $uom): int => (int) $uom['id'], $uoms);
        if (!in_array($baseUomId, $validIds, true)) {
            $errors[] = 'Please choose a valid base UoM.';
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', array_unique($errors));
            header('Location: /admin/uoms');
            exit;
        }

        try {
            Uom::updateSetAndUoms($this->pdo, $orgId, $setId, $setName, $uoms, $baseUomId);
            $_SESSION['flash_success'] = sprintf('Updated UoM set "%s".', $setName);
        } catch (\Throwable $exception) {
            $_SESSION['flash_error'] = 'Could not update UoMs. Ensure symbols are unique.';
        }

        header('Location: /admin/uoms');
        exit;
    }
}
