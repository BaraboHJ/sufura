<?php
use App\Core\Csrf;

function format_cost_per_base(?int $costX10000, string $currency, ?string $baseSymbol): string
{
    if ($costX10000 === null) {
        return '—';
    }
    $major = $costX10000 / 1000000;
    $suffix = $baseSymbol ? ' / ' . htmlspecialchars($baseSymbol, ENT_QUOTES) : '';
    return sprintf('%s %s%s', htmlspecialchars($currency, ENT_QUOTES), number_format($major, 4), $suffix);
}

function format_money_minor(?int $minor, string $currency): string
{
    if ($minor === null) {
        return '—';
    }
    return htmlspecialchars($currency, ENT_QUOTES) . ' ' . number_format($minor / 100, 2);
}

$recentCount = (int) ($recentUpdates['count'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Import Preview</h1>
        <p class="text-muted mb-0"><?= htmlspecialchars($import['original_filename'], ENT_QUOTES) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="/imports/costs">Back to imports</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h6">Summary</h2>
                <ul class="list-unstyled mb-0">
                    <li><strong>Total rows:</strong> <?= (int) ($summary['total_rows'] ?? 0) ?></li>
                    <li><strong>Matched:</strong> <?= (int) ($summary['matched_ok'] ?? 0) ?></li>
                    <li><strong>Missing ingredient:</strong> <?= (int) ($summary['missing_ingredient'] ?? 0) ?></li>
                    <li><strong>Invalid UOM:</strong> <?= (int) ($summary['invalid_uom'] ?? 0) ?></li>
                    <li><strong>Invalid numbers:</strong> <?= (int) ($summary['invalid_number'] ?? 0) ?></li>
                </ul>
            </div>
        </div>
        <?php if ($recentCount > 0): ?>
            <div class="alert alert-warning">
                <strong>Heads up:</strong> <?= $recentCount ?> ingredients were updated today. Confirm that you want to overwrite recent costs.
            </div>
        <?php endif; ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <button class="btn btn-primary w-100" id="confirm-import" <?= $import['status'] !== 'uploaded' ? 'disabled' : '' ?>>Confirm import</button>
                <?php if ($import['status'] !== 'uploaded'): ?>
                    <p class="text-muted small mt-2 mb-0">This import has already been applied.</p>
                <?php endif; ?>
                <?php if ($recentCount > 0): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="force-confirm">
                        <label class="form-check-label" for="force-confirm">Overwrite costs updated today</label>
                    </div>
                <?php endif; ?>
                <div class="alert alert-danger mt-3 d-none" id="confirm-error"></div>
                <div class="alert alert-success mt-3 d-none" id="confirm-success">Import applied successfully.</div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Ingredient</th>
                            <th>Purchase</th>
                            <th>Total cost</th>
                            <th>Old cost/base</th>
                            <th>New cost/base</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No rows parsed.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $ingredientId = $row['matched_ingredient_id'] ? (int) $row['matched_ingredient_id'] : null;
                                $oldCost = $ingredientId ? ($latestCosts[$ingredientId]['cost_per_base_x10000'] ?? null) : null;
                                $baseSymbol = $ingredientId ? ($baseUoms[$ingredientId] ?? null) : null;
                                $status = $row['parse_status'];
                                $statusClass = $status === 'matched_ok' ? 'bg-success' : 'bg-danger';
                                ?>
                                <tr>
                                    <td><?= (int) $row['row_num'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['ingredient_name_raw'], ENT_QUOTES) ?>
                                        <?php if ($row['error_message']): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($row['error_message'], ENT_QUOTES) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $row['purchase_qty'] !== null ? number_format((float) $row['purchase_qty'], 4) : '—' ?>
                                        <?= htmlspecialchars($row['purchase_uom_symbol'] ?? '', ENT_QUOTES) ?>
                                    </td>
                                    <td><?= format_money_minor($row['total_cost_minor'] !== null ? (int) $row['total_cost_minor'] : null, $currency) ?></td>
                                    <td><?= format_cost_per_base($oldCost !== null ? (int) $oldCost : null, $currency, $baseSymbol) ?></td>
                                    <td><?= format_cost_per_base($row['computed_cost_per_base_x10000'] !== null ? (int) $row['computed_cost_per_base_x10000'] : null, $currency, $baseSymbol) ?></td>
                                    <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status, ENT_QUOTES) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const confirmBtn = document.getElementById('confirm-import');
const errorBox = document.getElementById('confirm-error');
const successBox = document.getElementById('confirm-success');
const forceCheckbox = document.getElementById('force-confirm');

if (confirmBtn) {
    confirmBtn.addEventListener('click', async () => {
        errorBox.classList.add('d-none');
        successBox.classList.add('d-none');

        try {
            const response = await fetch('/api/imports/costs/<?= (int) $import['id'] ?>/confirm', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>',
                },
                body: JSON.stringify({
                    force: forceCheckbox ? forceCheckbox.checked : false,
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Unable to apply import.');
            }
            successBox.classList.remove('d-none');
            confirmBtn.disabled = true;
            window.SufuraMotion?.animateIn(successBox);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
            window.SufuraMotion?.animateIn(errorBox);
        }
    });
}
</script>
