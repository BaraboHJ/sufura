<?php
use App\Core\Auth;
use App\Core\Csrf;

function format_cost_per_base(?int $costX10000, string $currency): string
{
    if (!$costX10000) {
        return '—';
    }
    $major = $costX10000 / 1000000;
    return sprintf('%s %s', $currency, number_format($major, 4));
}

$statusLabels = [
    'missing' => ['label' => 'Missing', 'class' => 'bg-danger'],
    'stale' => ['label' => 'Stale', 'class' => 'bg-warning text-dark'],
    'ok' => ['label' => 'OK', 'class' => 'bg-success'],
];
$statusConfig = $statusLabels[$status] ?? $statusLabels['missing'];
$csrfToken = Csrf::token();
$user = Auth::user();
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h4 mb-1"><?= htmlspecialchars($ingredient['name'], ENT_QUOTES) ?></h1>
        <div class="text-muted">UoM Set: <?= htmlspecialchars($ingredient['uom_set_name'], ENT_QUOTES) ?> (base: <?= htmlspecialchars($ingredient['base_uom_symbol'], ENT_QUOTES) ?>)</div>
        <?php if (!empty($ingredient['notes'])): ?>
            <div class="text-muted mt-2"><?= nl2br(htmlspecialchars($ingredient['notes'], ENT_QUOTES)) ?></div>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if ($user && ($user['role'] ?? '') === 'admin'): ?>
            <form method="post" action="/ingredients/<?= (int) $ingredient['id'] ?>/delete" onsubmit="return confirm('Delete this ingredient? This cannot be undone.');">
                <?= Csrf::input() ?>
                <button class="btn btn-outline-danger" type="submit">Delete</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="/ingredients/<?= (int) $ingredient['id'] ?>/edit">Edit</a>
        <a class="btn btn-outline-secondary" href="/ingredients">Back</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">Current Cost</h2>
                    <span id="status-badge" class="badge <?= $statusConfig['class'] ?>"><?= $statusConfig['label'] ?></span>
                </div>
                <div class="display-6 fw-semibold" id="current-cost-value">
                    <?= format_cost_per_base($currentCost['cost_per_base_x10000'] ?? null, $currency) ?>
                    <small class="text-muted">/ <?= htmlspecialchars($ingredient['base_uom_symbol'], ENT_QUOTES) ?></small>
                </div>
                <div class="text-muted mt-2">
                    Last updated:
                    <span id="last-updated">
                        <?= $currentCost['effective_at'] ? htmlspecialchars($currentCost['effective_at'], ENT_QUOTES) : '—' ?>
                    </span>
                </div>
                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#quickCostModal">Quick update cost</button>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6">Details</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8"><?= (int) $ingredient['active'] === 1 ? 'Active' : 'Inactive' ?></dd>
                    <dt class="col-sm-4">Created</dt>
                    <dd class="col-sm-8"><?= htmlspecialchars($ingredient['created_at'], ENT_QUOTES) ?></dd>
                    <dt class="col-sm-4">Updated</dt>
                    <dd class="col-sm-8"><?= $ingredient['updated_at'] ? htmlspecialchars($ingredient['updated_at'], ENT_QUOTES) : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-body-secondary">
        <h2 class="h6 mb-0">Cost History</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead class="table-secondary">
                <tr>
                    <th>Effective Date</th>
                    <th>Cost per <?= htmlspecialchars($ingredient['base_uom_symbol'], ENT_QUOTES) ?></th>
                    <th>Recorded</th>
                </tr>
            </thead>
            <tbody id="cost-history-body">
                <?php if (empty($costHistory)): ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">No cost history yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($costHistory as $cost): ?>
                        <tr>
                            <td><?= htmlspecialchars($cost['effective_at'], ENT_QUOTES) ?></td>
                            <td><?= format_cost_per_base((int) $cost['cost_per_base_x10000'], $cost['currency']) ?></td>
                            <td><?= htmlspecialchars($cost['created_at'], ENT_QUOTES) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="quickCostModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="quick-cost-form" data-endpoint="/api/ingredients/<?= (int) $ingredient['id'] ?>/costs" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Quick update cost</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="quick-cost-error"></div>
                    <div class="mb-3">
                        <label class="form-label">Purchase quantity</label>
                        <input type="number" step="0.0001" min="0" class="form-control" name="purchase_qty" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purchase unit</label>
                        <select class="form-select" name="purchase_uom_id" required>
                            <option value="">Select unit</option>
                            <?php foreach ($uoms as $uom): ?>
                                <option value="<?= (int) $uom['id'] ?>">
                                    <?= htmlspecialchars($uom['name'], ENT_QUOTES) ?> (<?= htmlspecialchars($uom['symbol'], ENT_QUOTES) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total cost (<?= htmlspecialchars($currency, ENT_QUOTES) ?>)</label>
                        <input type="text" class="form-control" name="total_cost_major" placeholder="12.50" required>
                    </div>
                    <div class="text-muted small">Cost will be converted to base unit: <?= htmlspecialchars($ingredient['base_uom_symbol'], ENT_QUOTES) ?>.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save cost</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('quick-cost-form');
    const errorBox = document.getElementById('quick-cost-error');
    const currentCostEl = document.getElementById('current-cost-value');
    const lastUpdatedEl = document.getElementById('last-updated');
    const statusBadge = document.getElementById('status-badge');
    const historyBody = document.getElementById('cost-history-body');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorBox.classList.add('d-none');
        errorBox.textContent = '';

        const payload = {
            purchase_qty: form.purchase_qty.value,
            purchase_uom_id: form.purchase_uom_id.value,
            total_cost_major: form.total_cost_major.value,
        };

        try {
            const response = await fetch(form.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': form.dataset.csrf,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Unable to update cost.');
            }

            const costMajor = (data.cost_per_base_x10000 / 1000000).toFixed(4);
            currentCostEl.innerHTML = `${data.currency} ${costMajor} <small class="text-muted">/ <?= htmlspecialchars($ingredient['base_uom_symbol'], ENT_QUOTES) ?></small>`;
            lastUpdatedEl.textContent = data.last_updated_at;

            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = 'OK';

            window.SufuraMotion?.animateMany([currentCostEl, lastUpdatedEl, statusBadge]);

            if (historyBody) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${data.last_updated_at}</td>
                    <td>${data.currency} ${costMajor}</td>
                    <td>${data.last_updated_at}</td>
                `;
                if (historyBody.querySelector('td[colspan]')) {
                    historyBody.innerHTML = '';
                }
                historyBody.prepend(row);
                window.SufuraMotion?.animateIn(row);
            }

            form.reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('quickCostModal'));
            if (modal) {
                modal.hide();
            }
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
        }
    });
})();
</script>
