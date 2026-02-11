<?php
use App\Core\Csrf;

$formErrors = $_SESSION['form_errors'] ?? [];
$formValues = $_SESSION['form_values'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_values']);
$csrfToken = Csrf::token();

function format_money(?int $minor, string $currency): string
{
    if ($minor === null) {
        return '—';
    }
    return sprintf('%s %s', $currency, number_format($minor / 100, 2));
}

$statusLabels = [
    'complete' => ['label' => 'Complete', 'class' => 'bg-success'],
    'incomplete_missing_ingredient_cost' => ['label' => 'Missing costs', 'class' => 'bg-warning text-dark'],
    'invalid_units' => ['label' => 'Invalid units', 'class' => 'bg-danger'],
];

$lineBreakdown = [];
foreach ($breakdown as $line) {
    $lineBreakdown[(int) $line['id']] = $line;
}

$status = $summary['status'] ?? 'incomplete_missing_ingredient_cost';
$statusConfig = $statusLabels[$status] ?? $statusLabels['incomplete_missing_ingredient_cost'];
$knownCount = (int) ($summary['lines_count'] ?? 0) - (int) ($summary['unknown_cost_lines_count'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h4 mb-1">Edit Dish</h1>
        <p class="text-muted mb-0">Build the recipe and keep costs up to date.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/dishes/<?= (int) $dish['id'] ?>">View</a>
        <a class="btn btn-outline-secondary" href="/dishes">Back</a>
    </div>
</div>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($formErrors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="bg-body-secondary p-4 rounded shadow-sm mb-4">
            <h2 class="h6">Dish details</h2>
            <form method="post" action="/dishes/<?= (int) $dish['id'] ?>/update">
                <?= Csrf::input() ?>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($formValues['name'] ?? $dish['name'], ENT_QUOTES) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) ($formValues['category_id'] ?? $dish['category_id']) === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name'], ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($formValues['description'] ?? $dish['description'] ?? '', ENT_QUOTES) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Yield servings</label>
                    <input type="number" name="yield_servings" min="1" class="form-control" required value="<?= htmlspecialchars((string) ($formValues['yield_servings'] ?? $dish['yield_servings']), ENT_QUOTES) ?>">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="active" id="dish-active" value="1" <?= ((int) ($formValues['active'] ?? $dish['active']) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="dish-active">Active</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Save dish</button>
            </form>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="h6 mb-1">Cost summary</h2>
                        <div class="text-muted small">Auto-updates as you edit lines.</div>
                    </div>
                    <span id="dish-status" class="badge <?= $statusConfig['class'] ?>"><?= $statusConfig['label'] ?></span>
                </div>
                <div class="mt-3">
                    <div class="fw-semibold">Total cost</div>
                    <div class="display-6" id="total-cost" data-currency="<?= htmlspecialchars($currency, ENT_QUOTES) ?>">
                        <?= format_money($summary['total_cost_minor'] ?? null, $currency) ?>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="fw-semibold">Cost per serving</div>
                    <div class="h4" id="cost-per-serving"><?= format_money($summary['cost_per_serving_minor'] ?? null, $currency) ?></div>
                </div>
                <div class="mt-3 text-muted small" id="completeness">
                    <?php if ((int) ($summary['lines_count'] ?? 0) > 0): ?>
                        <?= $knownCount ?>/<?= (int) $summary['lines_count'] ?> ingredients costed
                    <?php else: ?>
                        No recipe lines yet.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <style>
            .recipe-table-container {
                overflow: visible;
            }

            .recipe-table-container .ingredient-dropdown {
                z-index: 1100;
            }
        </style>
        <div class="card shadow-sm">
            <div class="card-header bg-body-secondary d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 mb-1">Recipe builder</h2>
                    <div class="text-muted small">Search ingredients, set quantities, and update costs fast.</div>
                </div>
                <button class="btn btn-sm btn-outline-primary" id="add-line" type="button">Add line</button>
            </div>
            <div class="table-responsive recipe-table-container">
                <table class="table align-middle mb-0" id="recipe-table" data-dish-id="<?= (int) $dish['id'] ?>" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <thead class="table-secondary">
                        <tr>
                            <th style="width: 30%">Ingredient</th>
                            <th style="width: 12%">Qty</th>
                            <th style="width: 18%">Unit</th>
                            <th style="width: 18%">Cost/base</th>
                            <th style="width: 12%">Line cost</th>
                            <th style="width: 10%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lines)): ?>
                            <tr class="empty-row">
                                <td colspan="6" class="text-center text-muted py-4">Add ingredients to start costing.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lines as $line): ?>
                                <?php $lineMeta = $lineBreakdown[(int) $line['id']] ?? null; ?>
                                <tr data-line-id="<?= (int) $line['id'] ?>" data-ingredient-id="<?= (int) $line['ingredient_id'] ?>" data-uom-set-id="<?= (int) $line['uom_set_id'] ?>" data-base-uom-id="<?= (int) ($lineMeta['base_uom_id'] ?? 0) ?>" data-base-uom-symbol="<?= htmlspecialchars($lineMeta['base_uom_symbol'] ?? '', ENT_QUOTES) ?>" data-sort-order="<?= (int) $line['sort_order'] ?>">
                                    <td class="position-relative">
                                        <input type="text" class="form-control ingredient-input" value="<?= htmlspecialchars($line['ingredient_name'], ENT_QUOTES) ?>" autocomplete="off">
                                        <div class="list-group position-absolute w-100 shadow-sm d-none ingredient-dropdown"></div>
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.0001" class="form-control qty-input" value="<?= htmlspecialchars((string) $line['quantity'], ENT_QUOTES) ?>">
                                    </td>
                                    <td>
                                        <select class="form-select uom-select" data-selected="<?= (int) $line['uom_id'] ?>">
                                            <option value="<?= (int) $line['uom_id'] ?>"><?= htmlspecialchars($line['uom_name'], ENT_QUOTES) ?> (<?= htmlspecialchars($line['uom_symbol'], ENT_QUOTES) ?>)</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" min="0" step="0.0001" class="form-control cost-per-base-input" value="<?= $lineMeta && $lineMeta['cost_per_base_x10000'] !== null ? htmlspecialchars(number_format(((int) $lineMeta['cost_per_base_x10000']) / 10000, 4, '.', ''), ENT_QUOTES) : '' ?>" placeholder="—">
                                            <span class="input-group-text base-uom-symbol"><?= htmlspecialchars($lineMeta['base_uom_symbol'] ?? '', ENT_QUOTES) ?></span>
                                        </div>
                                    </td>
                                    <td class="line-cost text-muted">
                                        <?= $lineMeta && $lineMeta['line_cost_minor'] !== null ? format_money((int) $lineMeta['line_cost_minor'], $currency) : '—' ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-secondary move-up" type="button">↑</button>
                                            <button class="btn btn-outline-secondary move-down" type="button">↓</button>
                                            <button class="btn btn-outline-danger delete-line" type="button">Remove</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ingredientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="ingredient-create-form">
                <div class="modal-header">
                    <h5 class="modal-title">Create ingredient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="ingredient-error"></div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">UoM set</label>
                        <select class="form-select" name="uom_set_id" required>
                            <option value="">Select a unit set</option>
                            <?php foreach ($uomSets as $set): ?>
                                <option value="<?= (int) $set['id'] ?>">
                                    <?= htmlspecialchars($set['name'], ENT_QUOTES) ?> (base: <?= htmlspecialchars($set['base_uom_symbol'], ENT_QUOTES) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial cost per base unit (optional)</label>
                        <input type="number" class="form-control" name="cost_per_base_major" min="0" step="0.0001" placeholder="e.g. 2.50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create ingredient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('recipe-table');
    const addLineBtn = document.getElementById('add-line');
    const modalEl = document.getElementById('ingredientModal');
    const modal = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;
    const modalForm = document.getElementById('ingredient-create-form');
    const modalError = document.getElementById('ingredient-error');
    const statusBadge = document.getElementById('dish-status');
    const totalCostEl = document.getElementById('total-cost');
    const costPerServingEl = document.getElementById('cost-per-serving');
    const completenessEl = document.getElementById('completeness');
    const currency = totalCostEl?.dataset.currency || 'USD';
    let activeRow = null;

    if (!table) {
        return;
    }

    const csrfToken = table.dataset.csrf;
    const motion = window.SufuraMotion;
    const dishId = table.dataset.dishId;

    const statusMap = {
        complete: { label: 'Complete', className: 'bg-success' },
        incomplete_missing_ingredient_cost: { label: 'Missing costs', className: 'bg-warning text-dark' },
        invalid_units: { label: 'Invalid units', className: 'bg-danger' },
    };

    const formatMoney = (minor) => {
        if (minor === null || typeof minor === 'undefined') {
            return '—';
        }
        return `${currency} ${(minor / 100).toFixed(2)}`;
    };

    const syncCostPerBaseUi = (row, lineBreakdown) => {
        if (!lineBreakdown) {
            return;
        }
        if (lineBreakdown.base_uom_id) {
            row.dataset.baseUomId = lineBreakdown.base_uom_id;
        }
        if (lineBreakdown.base_uom_symbol) {
            row.dataset.baseUomSymbol = lineBreakdown.base_uom_symbol;
        }

        const costInput = row.querySelector('.cost-per-base-input');
        if (costInput && lineBreakdown.cost_per_base_x10000 !== null && typeof lineBreakdown.cost_per_base_x10000 !== 'undefined') {
            costInput.value = (lineBreakdown.cost_per_base_x10000 / 10000).toFixed(4);
        }

        const baseSymbolEl = row.querySelector('.base-uom-symbol');
        if (baseSymbolEl) {
            baseSymbolEl.textContent = row.dataset.baseUomSymbol || '';
        }
    };

    const updateIngredientCost = async (row) => {
        const ingredientId = Number(row.dataset.ingredientId || 0);
        const baseUomId = Number(row.dataset.baseUomId || 0);
        const costInput = row.querySelector('.cost-per-base-input');

        if (!ingredientId || !baseUomId || !costInput) {
            return;
        }

        const rawValue = costInput.value.trim();
        if (!rawValue) {
            return;
        }

        await fetchJson(`/api/ingredients/${ingredientId}/costs`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({
                purchase_qty: 1,
                purchase_uom_id: baseUomId,
                total_cost_major: rawValue,
            }),
        });

        await updateLine(row);
    };

    const updateSummary = (summary) => {
        if (!summary) {
            return;
        }
        totalCostEl.textContent = formatMoney(summary.total_cost_minor);
        costPerServingEl.textContent = formatMoney(summary.cost_per_serving_minor);

        const known = summary.lines_count - summary.unknown_cost_lines_count;
        if (summary.lines_count > 0) {
            completenessEl.textContent = `${known}/${summary.lines_count} ingredients costed`;
        } else {
            completenessEl.textContent = 'No recipe lines yet.';
        }

        const statusConfig = statusMap[summary.status] || statusMap.incomplete_missing_ingredient_cost;
        statusBadge.className = `badge ${statusConfig.className}`;
        statusBadge.textContent = statusConfig.label;
        motion?.animateMany([totalCostEl, costPerServingEl, completenessEl, statusBadge]);
    };

    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, options);
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Request failed.');
        }
        return data;
    };

    const ensureUoms = async (row, uomSetId) => {
        const select = row.querySelector('.uom-select');
        if (!select) {
            return null;
        }
        select.innerHTML = '<option value="">Loading...</option>';
        motion?.animateIn(select);
        const uoms = await fetchJson(`/api/uoms?uom_set_id=${uomSetId}`);
        select.innerHTML = '';
        let selectedId = null;
        uoms.forEach((uom) => {
            const option = document.createElement('option');
            option.value = uom.id;
            option.textContent = `${uom.name} (${uom.symbol})`;
            if (uom.is_base && !selectedId) {
                selectedId = uom.id;
            }
            select.appendChild(option);
        });
        motion?.animateIn(select);
        const dataSelected = select.dataset.selected;
        if (dataSelected) {
            select.value = dataSelected;
        } else if (selectedId) {
            select.value = selectedId;
        }
        return select.value;
    };

    const buildPayload = (row) => {
        return {
            ingredient_id: Number(row.dataset.ingredientId || 0),
            quantity: Number(row.querySelector('.qty-input')?.value || 0),
            uom_id: Number(row.querySelector('.uom-select')?.value || 0),
            sort_order: Number(row.dataset.sortOrder || 0),
        };
    };

    const updateLine = async (row) => {
        const lineId = row.dataset.lineId;
        if (!lineId) {
            return;
        }
        const payload = buildPayload(row);
        const data = await fetchJson(`/api/dish-lines/${lineId}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(payload),
        });
        if (data.line_breakdown) {
            syncCostPerBaseUi(row, data.line_breakdown);
            const lineCostEl = row.querySelector('.line-cost');
            if (lineCostEl) {
                lineCostEl.textContent = data.line_breakdown.line_cost_minor === null
                    ? '—'
                    : formatMoney(data.line_breakdown.line_cost_minor);
                motion?.animateIn(lineCostEl);
            }
        }
        updateSummary(data.summary);
    };

    const addLineRow = () => {
        const tbody = table.querySelector('tbody');
        const emptyRow = tbody.querySelector('.empty-row');
        if (emptyRow) {
            emptyRow.remove();
        }
        const row = document.createElement('tr');
        row.dataset.sortOrder = table.querySelectorAll('tbody tr').length + 1;
        row.innerHTML = `
            <td class="position-relative">
                <input type="text" class="form-control ingredient-input" placeholder="Search ingredient" autocomplete="off">
                <div class="list-group position-absolute w-100 shadow-sm d-none ingredient-dropdown"></div>
            </td>
            <td><input type="number" min="0" step="0.0001" class="form-control qty-input" value="1"></td>
            <td><select class="form-select uom-select" disabled><option value="">Select unit</option></select></td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" min="0" step="0.0001" class="form-control cost-per-base-input" value="" placeholder="—">
                    <span class="input-group-text base-uom-symbol"></span>
                </div>
            </td>
            <td class="line-cost text-muted">—</td>
            <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary move-up" type="button">↑</button>
                    <button class="btn btn-outline-secondary move-down" type="button">↓</button>
                    <button class="btn btn-outline-danger delete-line" type="button">Remove</button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
        motion?.animateIn(row);
        attachRowEvents(row);
        row.querySelector('.ingredient-input')?.focus();
    };

    const applySortOrders = async () => {
        const rows = Array.from(table.querySelectorAll('tbody tr')).filter((row) => !row.classList.contains('empty-row'));
        rows.forEach((row, index) => {
            row.dataset.sortOrder = index + 1;
        });
        await Promise.all(rows.map(async (row) => {
            if (row.dataset.lineId) {
                await updateLine(row);
            }
        }));
    };

    const handleIngredientSelect = async (row, ingredient) => {
        row.dataset.ingredientId = ingredient.id;
        row.dataset.uomSetId = ingredient.uom_set_id;
        row.dataset.baseUomId = ingredient.base_uom_id || '';
        row.dataset.baseUomSymbol = ingredient.base_uom_symbol || '';
        const baseSymbolEl = row.querySelector('.base-uom-symbol');
        if (baseSymbolEl) {
            baseSymbolEl.textContent = ingredient.base_uom_symbol || '';
        }
        const costInput = row.querySelector('.cost-per-base-input');
        if (costInput) {
            costInput.value = ingredient.cost_per_base_x10000 ? (ingredient.cost_per_base_x10000 / 10000).toFixed(4) : '';
        }
        row.querySelector('.ingredient-input').value = ingredient.name;
        const select = row.querySelector('.uom-select');
        if (select) {
            select.disabled = false;
        }
        const uomId = await ensureUoms(row, ingredient.uom_set_id);
        const payload = buildPayload(row);
        payload.uom_id = Number(uomId || payload.uom_id);

        if (!row.dataset.lineId) {
            const data = await fetchJson(`/api/dishes/${dishId}/lines/add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            row.dataset.lineId = data.line?.id || '';
            updateSummary(data.summary);
            if (data.line_breakdown) {
                syncCostPerBaseUi(row, data.line_breakdown);
                const lineCostEl = row.querySelector('.line-cost');
                lineCostEl.textContent = data.line_breakdown.line_cost_minor === null
                    ? '—'
                    : formatMoney(data.line_breakdown.line_cost_minor);
            }
        } else {
            await updateLine(row);
        }
    };

    const renderDropdown = (row, results, query) => {
        const dropdown = row.querySelector('.ingredient-dropdown');
        if (!dropdown) {
            return;
        }
        dropdown.innerHTML = '';
        if (query) {
            const createBtn = document.createElement('button');
            createBtn.type = 'button';
            createBtn.className = 'list-group-item list-group-item-action text-primary';
            createBtn.textContent = `Create new ingredient "${query}"`;
            createBtn.addEventListener('click', () => {
                activeRow = row;
                modalForm.name.value = query;
                modalForm.notes.value = '';
                modalForm.cost_per_base_major.value = '';
                modalForm.uom_set_id.value = '';
                modalError.classList.add('d-none');
                modalError.textContent = '';
                modal?.show();
                dropdown.classList.add('d-none');
            });
            dropdown.appendChild(createBtn);
        }

        results.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = item.name;
            if (!item.has_cost) {
                button.classList.add('text-warning');
            } else if (item.is_stale) {
                button.classList.add('text-muted');
            }
            button.addEventListener('click', () => {
                dropdown.classList.add('d-none');
                handleIngredientSelect(row, item).catch((error) => {
                    console.error(error);
                });
            });
            dropdown.appendChild(button);
        });

        dropdown.classList.toggle('d-none', dropdown.childElementCount === 0);
    };

    const handleIngredientInput = (row) => {
        const input = row.querySelector('.ingredient-input');
        if (!input) {
            return;
        }
        let timeout;
        input.addEventListener('input', () => {
            const query = input.value.trim();
            clearTimeout(timeout);
            timeout = setTimeout(async () => {
                if (!query) {
                    renderDropdown(row, [], query);
                    return;
                }
                try {
                    const results = await fetchJson(`/api/ingredients/search?q=${encodeURIComponent(query)}`);
                    renderDropdown(row, results, query);
                } catch (error) {
                    console.error(error);
                }
            }, 200);
        });
    };

    const attachRowEvents = (row) => {
        handleIngredientInput(row);
        const qtyInput = row.querySelector('.qty-input');
        const uomSelect = row.querySelector('.uom-select');
        const deleteBtn = row.querySelector('.delete-line');
        const costPerBaseInput = row.querySelector('.cost-per-base-input');
        const moveUp = row.querySelector('.move-up');
        const moveDown = row.querySelector('.move-down');

        if (qtyInput) {
            qtyInput.addEventListener('change', () => updateLine(row));
        }
        if (uomSelect) {
            uomSelect.addEventListener('change', () => updateLine(row));
        }
        if (costPerBaseInput) {
            costPerBaseInput.addEventListener('change', () => {
                updateIngredientCost(row).catch((error) => console.error(error));
            });
        }
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async () => {
                if (row.dataset.lineId) {
                    await fetchJson(`/api/dish-lines/${row.dataset.lineId}/delete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken,
                        },
                    });
                }
                row.remove();
                const tbody = table.querySelector('tbody');
                if (tbody && tbody.querySelectorAll('tr').length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.className = 'empty-row';
                    emptyRow.innerHTML = '<td colspan="6" class="text-center text-muted py-4">Add ingredients to start costing.</td>';
                    tbody.appendChild(emptyRow);
                    motion?.animateIn(emptyRow);
                }
                await applySortOrders();
            });
        }
        if (moveUp) {
            moveUp.addEventListener('click', async () => {
                const prev = row.previousElementSibling;
                if (prev) {
                    row.parentNode.insertBefore(row, prev);
                    await applySortOrders();
                }
            });
        }
        if (moveDown) {
            moveDown.addEventListener('click', async () => {
                const next = row.nextElementSibling;
                if (next) {
                    row.parentNode.insertBefore(next, row);
                    await applySortOrders();
                }
            });
        }
    };

    table.querySelectorAll('tbody tr').forEach((row) => {
        if (!row.classList.contains('empty-row')) {
            attachRowEvents(row);
            const uomSetId = row.dataset.uomSetId;
            const baseSymbolEl = row.querySelector('.base-uom-symbol');
            if (baseSymbolEl) {
                baseSymbolEl.textContent = row.dataset.baseUomSymbol || '';
            }
            if (uomSetId) {
                ensureUoms(row, uomSetId).catch((error) => console.error(error));
            }
        }
    });

    addLineBtn?.addEventListener('click', addLineRow);

    table.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addLineRow();
        }
    });

    modalForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        modalError.classList.add('d-none');
        modalError.textContent = '';

        try {
            const payload = {
                name: modalForm.name.value.trim(),
                uom_set_id: modalForm.uom_set_id.value,
                notes: modalForm.notes.value.trim(),
                cost_per_base_major: modalForm.cost_per_base_major.value.trim(),
                active: 1,
            };
            const data = await fetchJson('/api/ingredients/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            if (activeRow) {
                await handleIngredientSelect(activeRow, {
                    id: data.id,
                    name: data.name,
                    uom_set_id: data.uom_set_id,
                    base_uom_id: data.base_uom_id,
                    base_uom_symbol: data.base_uom_symbol,
                    cost_per_base_x10000: data.cost_per_base_x10000,
                });
            }
            modal?.hide();
        } catch (error) {
            modalError.textContent = error.message;
            modalError.classList.remove('d-none');
        }
    });
});
</script>
