<?php
use App\Core\Csrf;

function format_money(?int $minor, string $currency): string
{
    if ($minor === null) {
        return '—';
    }
    return sprintf('%s %s', $currency, number_format($minor / 100, 2));
}

function format_money_input(?int $minor): string
{
    if ($minor === null) {
        return '';
    }
    return number_format($minor / 100, 2, '.', '');
}

$readOnly = ($menu['cost_mode'] ?? 'live') === 'locked';
$menuType = $menu['menu_type'] ?? 'package';
$paxCount = $report['totals']['pax_count'] ?? null;
?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><?= htmlspecialchars($menu['name'], ENT_QUOTES) ?></h1>
        <p class="text-muted mb-0">
            Menu builder with costing and revenue analytics.
            <?= $readOnly ? 'Costs are locked.' : 'Live costs.' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="/menus/<?= (int) $menu['id'] ?>/duplicate">
            <?= Csrf::input() ?>
            <button class="btn btn-outline-secondary" type="submit">Duplicate menu</button>
        </form>
        <?php if ($readOnly): ?>
            <button class="btn btn-warning" id="unlock-menu" type="button">Unlock</button>
        <?php else: ?>
            <button class="btn btn-dark" id="lock-menu" type="button" <?= $canLock ? '' : 'disabled' ?>>Lock costs for quoting</button>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="post" action="/menus/<?= (int) $menu['id'] ?>/update">
            <?= Csrf::input() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Menu name</label>
                    <input class="form-control" name="name" value="<?= htmlspecialchars($menu['name'], ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : 'required' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Menu type</label>
                    <select class="form-select" name="menu_type" <?= $readOnly ? 'disabled' : '' ?>>
                        <option value="package" <?= $menuType === 'package' ? 'selected' : '' ?>>Package</option>
                        <option value="per_item" <?= $menuType === 'per_item' ? 'selected' : '' ?>>Per item</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Currency</label>
                    <input class="form-control" name="currency" value="<?= htmlspecialchars($menu['currency'], ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price min (per pax)</label>
                    <input class="form-control" name="price_min_major" placeholder="0.00" value="<?= htmlspecialchars(format_money_input($menu['price_min_minor'] ?? null), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price max (per pax)</label>
                    <input class="form-control" name="price_max_major" placeholder="0.00" value="<?= htmlspecialchars(format_money_input($menu['price_max_minor'] ?? null), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Price label suffix</label>
                    <input class="form-control" name="price_label_suffix" placeholder="++" value="<?= htmlspecialchars($menu['price_label_suffix'] ?? '', ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Minimum pax (package)</label>
                    <input class="form-control" name="min_pax" type="number" min="1" value="<?= htmlspecialchars((string) ($menu['min_pax'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Default waste %</label>
                    <input class="form-control" name="default_waste_pct" placeholder="0.05" value="<?= htmlspecialchars((string) ($menu['default_waste_pct'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Show descriptions</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_descriptions" id="show-descriptions" <?= !empty($menu['show_descriptions']) ? 'checked' : '' ?> <?= $readOnly ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="show-descriptions">Display dish descriptions</label>
                    </div>
                </div>
            </div>
            <?php if (!$readOnly): ?>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit">Save menu</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Cost summary</h5>
                <div class="mb-2">
                    <span class="text-muted">Cost per pax</span>
                    <div class="h4" id="menu-cost-per-pax"><?= format_money($report['menu_cost_per_pax_minor'] ?? null, $currency) ?></div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Pax count</label>
                    <input class="form-control" type="number" min="1" id="pax-count" value="<?= htmlspecialchars((string) ($paxCount ?? ''), ENT_QUOTES) ?>">
                </div>
                <div>
                    <span class="text-muted">Total cost</span>
                    <div class="h5" id="menu-total-cost"><?= format_money($report['totals']['total_cost_minor'] ?? null, $currency) ?></div>
                </div>
                <?php if ($report['missing_dish_costs'] > 0): ?>
                    <div class="alert alert-warning mt-3 mb-0">Some dishes are missing cost data.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Revenue &amp; profit</h5>
                <?php if ($menuType === 'package'): ?>
                    <div class="mb-2"><span class="text-muted">Revenue min</span>
                        <div id="revenue-min"><?= format_money($report['totals']['revenue_min_minor'] ?? null, $currency) ?></div>
                    </div>
                    <div class="mb-2"><span class="text-muted">Revenue max</span>
                        <div id="revenue-max"><?= format_money($report['totals']['revenue_max_minor'] ?? null, $currency) ?></div>
                    </div>
                    <div class="mb-2"><span class="text-muted">Profit min</span>
                        <div id="profit-min"><?= format_money($report['totals']['profit_min_minor'] ?? null, $currency) ?></div>
                    </div>
                    <div class="mb-2"><span class="text-muted">Profit max</span>
                        <div id="profit-max"><?= format_money($report['totals']['profit_max_minor'] ?? null, $currency) ?></div>
                    </div>
                    <div class="mb-2"><span class="text-muted">Food cost % min</span>
                        <div id="food-cost-min"><?= $report['totals']['food_cost_pct_min'] !== null ? number_format($report['totals']['food_cost_pct_min'] * 100, 1) . '%' : '—' ?></div>
                    </div>
                    <div><span class="text-muted">Food cost % max</span>
                        <div id="food-cost-max"><?= $report['totals']['food_cost_pct_max'] !== null ? number_format($report['totals']['food_cost_pct_max'] * 100, 1) . '%' : '—' ?></div>
                    </div>
                <?php else: ?>
                    <div class="mb-2"><span class="text-muted">Revenue total</span>
                        <div id="per-item-revenue"><?= format_money($report['totals']['per_item_total_revenue_minor'] ?? null, $currency) ?></div>
                    </div>
                    <div class="mb-2"><span class="text-muted">Cost total</span>
                        <div id="per-item-cost"><?= format_money($report['totals']['per_item_total_cost_minor'] ?? null, $currency) ?></div>
                    </div>
                    <div><span class="text-muted">Food cost %</span>
                        <div id="per-item-food-cost"><?= $report['totals']['per_item_food_cost_pct'] !== null ? number_format($report['totals']['per_item_food_cost_pct'] * 100, 1) . '%' : '—' ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Price recommendation</h5>
                <label class="form-label">Target food cost %</label>
                <input class="form-range" type="range" min="20" max="45" step="1" value="30" id="target-food-cost">
                <div class="d-flex justify-content-between">
                    <span class="text-muted">20%</span>
                    <span class="text-muted">45%</span>
                </div>
                <div class="mt-3">
                    <span class="text-muted">Recommended price per pax</span>
                    <div class="h5" id="recommended-price"><?= format_money(null, $currency) ?></div>
                </div>
                <div>
                    <span class="text-muted">Recommended total</span>
                    <div class="h6" id="recommended-total"><?= format_money(null, $currency) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach ($groups as $group): ?>
    <div class="card shadow-sm mb-3" data-group-id="<?= (int) $group['id'] ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Group name</label>
                            <input class="form-control group-name" value="<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Uptake %</label>
                            <input class="form-control group-uptake" value="<?= htmlspecialchars((string) ($group['uptake_pct'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Portion</label>
                            <input class="form-control group-portion" value="<?= htmlspecialchars((string) ($group['portion'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Waste %</label>
                            <input class="form-control group-waste" value="<?= htmlspecialchars((string) ($group['waste_pct'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Group cost</label>
                            <div class="form-control-plaintext">
                                <?php
                                $groupCost = 0;
                                foreach (($report['items'] ?? []) as $itemReport) {
                                    if ($itemReport['menu_group_id'] === $group['id']) {
                                        $groupCost += $itemReport['item_cost_per_pax_minor'];
                                    }
                                }
                                ?>
                                <?= format_money($groupCost, $currency) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!$readOnly): ?>
                    <div class="ms-3">
                        <button class="btn btn-outline-primary btn-sm save-group" type="button">Save</button>
                        <button class="btn btn-outline-danger btn-sm delete-group" type="button">Delete</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Dish</th>
                        <th>Overrides</th>
                        <th>Uptake</th>
                        <th>Portion</th>
                        <th>Waste</th>
                        <?php if ($menuType === 'per_item'): ?>
                            <th>Selling price</th>
                        <?php endif; ?>
                        <th>Cost per pax</th>
                        <?php if ($menuType === 'per_item'): ?>
                            <th>Expected qty</th>
                            <th>Revenue</th>
                            <th>Cost</th>
                        <?php endif; ?>
                        <?php if (!$readOnly): ?>
                            <th></th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($itemsByGroup[$group['id']] ?? []) as $item): ?>
                        <?php
                        $itemReport = null;
                        foreach ($report['items'] as $candidate) {
                            if ($candidate['id'] === $item['id']) {
                                $itemReport = $candidate;
                                break;
                            }
                        }
                        $itemReport = $itemReport ?? [];
                        ?>
                        <tr data-item-id="<?= (int) $item['id'] ?>">
                            <td>
                                <strong><?= htmlspecialchars($item['dish_name'], ENT_QUOTES) ?></strong>
                                <?php if (!empty($menu['show_descriptions'])): ?>
                                    <div class="text-muted small"><?= htmlspecialchars($item['dish_description'] ?? '', ENT_QUOTES) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input class="form-control form-control-sm mb-1 item-name" placeholder="Display name" value="<?= htmlspecialchars($item['display_name'] ?? '', ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                                <input class="form-control form-control-sm item-description" placeholder="Display description" value="<?= htmlspecialchars($item['display_description'] ?? '', ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>>
                            </td>
                            <td><input class="form-control form-control-sm item-uptake" value="<?= htmlspecialchars((string) ($item['uptake_pct'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>></td>
                            <td><input class="form-control form-control-sm item-portion" value="<?= htmlspecialchars((string) ($item['portion'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>></td>
                            <td><input class="form-control form-control-sm item-waste" value="<?= htmlspecialchars((string) ($item['waste_pct'] ?? ''), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>></td>
                            <?php if ($menuType === 'per_item'): ?>
                                <td><input class="form-control form-control-sm item-selling-price" placeholder="0.00" value="<?= htmlspecialchars(format_money_input($item['selling_price_minor'] ?? null), ENT_QUOTES) ?>" <?= $readOnly ? 'disabled' : '' ?>></td>
                            <?php endif; ?>
                            <td class="item-cost-per-pax"><?= format_money($itemReport['item_cost_per_pax_minor'] ?? null, $currency) ?></td>
                            <?php if ($menuType === 'per_item'): ?>
                                <?php
                                $line = null;
                                foreach ($report['totals']['per_item_lines'] ?? [] as $lineCandidate) {
                                    if ($lineCandidate['id'] === $item['id']) {
                                        $line = $lineCandidate;
                                        break;
                                    }
                                }
                                $line = $line ?? ['expected_qty' => null, 'revenue_minor' => null, 'cost_minor' => null];
                                ?>
                                <td class="item-expected-qty"><?= $line['expected_qty'] !== null ? number_format($line['expected_qty'], 2) : '—' ?></td>
                                <td class="item-revenue"><?= format_money($line['revenue_minor'], $currency) ?></td>
                                <td class="item-cost"><?= format_money($line['cost_minor'], $currency) ?></td>
                            <?php endif; ?>
                            <?php if (!$readOnly): ?>
                                <td class="text-end">
                                    <button class="btn btn-outline-primary btn-sm save-item" type="button">Save</button>
                                    <button class="btn btn-outline-danger btn-sm delete-item" type="button">Delete</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$readOnly): ?>
                <div class="border rounded p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select class="form-select dish-category">
                                <option value="">Select category</option>
                                <?php foreach ($dishCategories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name'], ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 position-relative">
                            <label class="form-label">Dish name</label>
                            <input class="form-control dish-search" placeholder="Select category first" disabled>
                            <input type="hidden" class="dish-id">
                            <div class="list-group position-absolute w-100 dish-results" style="z-index: 10;"></div>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary add-item" type="button">Add item</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!$readOnly): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title">Add group</h5>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Group name</label>
                    <input class="form-control" id="new-group-name" placeholder="Group name">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Uptake %</label>
                    <input class="form-control" id="new-group-uptake">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Portion</label>
                    <input class="form-control" id="new-group-portion">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Waste %</label>
                    <input class="form-control" id="new-group-waste">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary" id="create-group" type="button">Add group</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="createDishModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create dish</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" id="modal-dish-category">
                        <option value="">Select category</option>
                        <?php foreach ($dishCategories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name'], ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dish name</label>
                    <input class="form-control" id="modal-dish-name">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="modal-dish-description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Yield servings</label>
                    <input class="form-control" id="modal-dish-yield" type="number" min="1" value="1">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="button" id="create-dish-submit">Create dish</button>
            </div>
        </div>
    </div>
</div>

<script>
const menuId = <?= (int) $menu['id'] ?>;
const csrfToken = '<?= Csrf::token() ?>';
const currency = '<?= htmlspecialchars($currency, ENT_QUOTES) ?>';
const menuType = '<?= htmlspecialchars($menuType, ENT_QUOTES) ?>';
let lastMenuCostPerPax = <?= (int) ($report['menu_cost_per_pax_minor'] ?? 0) ?>;
const motion = window.SufuraMotion;

const formatMoney = (minor) => {
    if (minor === null || typeof minor === 'undefined') {
        return '—';
    }
    return `${currency} ${(minor / 100).toFixed(2)}`;
};

const updateRecommendations = () => {
    const targetEl = document.getElementById('target-food-cost');
    const targetPct = parseFloat(targetEl.value) / 100;
    const recommendedPerPax = targetPct > 0 ? Math.round(lastMenuCostPerPax / targetPct) : null;
    document.getElementById('recommended-price').textContent = formatMoney(recommendedPerPax);
    const paxCount = parseInt(document.getElementById('pax-count').value || '0', 10);
    if (paxCount > 0 && recommendedPerPax !== null) {
        document.getElementById('recommended-total').textContent = formatMoney(recommendedPerPax * paxCount);
    } else {
        document.getElementById('recommended-total').textContent = '—';
    }
};

const refreshSummary = async () => {
    const paxCount = document.getElementById('pax-count').value;
    const query = paxCount ? `?pax_count=${encodeURIComponent(paxCount)}` : '';
    const res = await fetch(`/api/menus/${menuId}/compute${query}`);
    if (!res.ok) {
        return;
    }
    const data = await res.json();
    lastMenuCostPerPax = data.menu_cost_per_pax_minor || 0;
    const menuCostPerPaxEl = document.getElementById('menu-cost-per-pax');
    const menuTotalCostEl = document.getElementById('menu-total-cost');
    menuCostPerPaxEl.textContent = formatMoney(data.menu_cost_per_pax_minor);
    menuTotalCostEl.textContent = formatMoney(data.totals.total_cost_minor);
    if (menuType === 'package') {
        document.getElementById('revenue-min').textContent = formatMoney(data.totals.revenue_min_minor);
        document.getElementById('revenue-max').textContent = formatMoney(data.totals.revenue_max_minor);
        document.getElementById('profit-min').textContent = formatMoney(data.totals.profit_min_minor);
        document.getElementById('profit-max').textContent = formatMoney(data.totals.profit_max_minor);
        document.getElementById('food-cost-min').textContent = data.totals.food_cost_pct_min !== null
            ? `${(data.totals.food_cost_pct_min * 100).toFixed(1)}%`
            : '—';
        document.getElementById('food-cost-max').textContent = data.totals.food_cost_pct_max !== null
            ? `${(data.totals.food_cost_pct_max * 100).toFixed(1)}%`
            : '—';
    } else {
        document.getElementById('per-item-revenue').textContent = formatMoney(data.totals.per_item_total_revenue_minor);
        document.getElementById('per-item-cost').textContent = formatMoney(data.totals.per_item_total_cost_minor);
        document.getElementById('per-item-food-cost').textContent = data.totals.per_item_food_cost_pct !== null
            ? `${(data.totals.per_item_food_cost_pct * 100).toFixed(1)}%`
            : '—';
        (data.totals.per_item_lines || []).forEach((line) => {
            const row = document.querySelector(`tr[data-item-id="${line.id}"]`);
            if (!row) return;
            row.querySelector('.item-expected-qty').textContent = line.expected_qty !== null
                ? Number(line.expected_qty).toFixed(2)
                : '—';
            row.querySelector('.item-revenue').textContent = formatMoney(line.revenue_minor);
            row.querySelector('.item-cost').textContent = formatMoney(line.cost_minor);
        });
    }
    updateRecommendations();
    motion?.animateMany([menuCostPerPaxEl, menuTotalCostEl]);
};

document.getElementById('pax-count').addEventListener('input', refreshSummary);
document.getElementById('target-food-cost').addEventListener('input', updateRecommendations);
updateRecommendations();

const postJson = async (url, payload) => {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify(payload),
    });
    if (!res.ok) {
        const data = await res.json();
        alert(data.error || 'Request failed.');
        return null;
    }
    return res.json();
};

document.querySelectorAll('.save-group').forEach((button) => {
    button.addEventListener('click', async () => {
        const card = button.closest('[data-group-id]');
        const groupId = card.dataset.groupId;
        const payload = {
            name: card.querySelector('.group-name').value,
            uptake_pct: card.querySelector('.group-uptake').value,
            portion: card.querySelector('.group-portion').value,
            waste_pct: card.querySelector('.group-waste').value,
        };
        const data = await postJson(`/api/menu-groups/${groupId}/update`, payload);
        if (data) {
            location.reload();
        }
    });
});

document.querySelectorAll('.delete-group').forEach((button) => {
    button.addEventListener('click', async () => {
        if (!confirm('Delete this group and its items?')) return;
        const card = button.closest('[data-group-id]');
        const groupId = card.dataset.groupId;
        const data = await postJson(`/api/menu-groups/${groupId}/delete`, {});
        if (data) {
            location.reload();
        }
    });
});

document.getElementById('create-group')?.addEventListener('click', async () => {
    const payload = {
        name: document.getElementById('new-group-name').value,
        uptake_pct: document.getElementById('new-group-uptake').value,
        portion: document.getElementById('new-group-portion').value,
        waste_pct: document.getElementById('new-group-waste').value,
    };
    const data = await postJson(`/api/menus/${menuId}/groups/create`, payload);
    if (data) {
        location.reload();
    }
});

document.querySelectorAll('.save-item').forEach((button) => {
    button.addEventListener('click', async () => {
        const row = button.closest('tr[data-item-id]');
        const itemId = row.dataset.itemId;
        const payload = {
            display_name: row.querySelector('.item-name').value,
            display_description: row.querySelector('.item-description').value,
            uptake_pct: row.querySelector('.item-uptake').value,
            portion: row.querySelector('.item-portion').value,
            waste_pct: row.querySelector('.item-waste').value,
            selling_price_major: row.querySelector('.item-selling-price')?.value || null,
        };
        const data = await postJson(`/api/menu-items/${itemId}/update`, payload);
        if (data) {
            location.reload();
        }
    });
});

document.querySelectorAll('.delete-item').forEach((button) => {
    button.addEventListener('click', async () => {
        if (!confirm('Delete this item?')) return;
        const row = button.closest('tr[data-item-id]');
        const itemId = row.dataset.itemId;
        const data = await postJson(`/api/menu-items/${itemId}/delete`, {});
        if (data) {
            location.reload();
        }
    });
});

document.querySelectorAll('.add-item').forEach((button) => {
    button.addEventListener('click', async () => {
        const card = button.closest('[data-group-id]');
        const dishIdInput = card.querySelector('.dish-id');
        const dishSearchInput = card.querySelector('.dish-search');
        const categorySelect = card.querySelector('.dish-category');
        const categoryId = parseInt(categorySelect.value || '0', 10);
        if (!categoryId) {
            alert('Please select a category first.');
            return;
        }
        const dishId = dishIdInput.value;
        if (!dishId) {
            openCreateDishModal(categoryId, dishSearchInput.value, async (createdDish) => {
                if (!createdDish) return;
                await postJson(`/api/menu-groups/${card.dataset.groupId}/items/create`, {
                    dish_id: createdDish.id,
                });
                location.reload();
            });
            return;
        }
        const data = await postJson(`/api/menu-groups/${card.dataset.groupId}/items/create`, {
            dish_id: parseInt(dishId, 10),
        });
        if (data) {
            location.reload();
        }
    });
});

document.querySelectorAll('.dish-search').forEach((input) => {
    let activeResults = [];
    const wrapper = input.parentElement;
    const card = input.closest('[data-group-id]');
    const categorySelect = card.querySelector('.dish-category');

    const fetchResults = async () => {
        const categoryId = categorySelect.value;
        const query = input.value.trim();
        const container = wrapper.querySelector('.dish-results');
        const hidden = wrapper.querySelector('.dish-id');
        hidden.value = '';
        if (!categoryId) {
            container.innerHTML = '';
            motion?.animateIn(container);
            return;
        }
        const params = new URLSearchParams({ category_id: categoryId });
        if (query) {
            params.set('query', query);
        }
        const res = await fetch(`/api/dishes/search?${params.toString()}`);
        if (!res.ok) {
            return;
        }
        const data = await res.json();
        activeResults = data.results || [];
        container.innerHTML = '';
        activeResults.forEach((dish) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.textContent = dish.name;
            button.addEventListener('click', () => {
                input.value = dish.name;
                hidden.value = dish.id;
                container.innerHTML = '';
                motion?.animateIn(input);
            });
            container.appendChild(button);
        });
        motion?.animateMany(container.querySelectorAll('.list-group-item'));
    };

    categorySelect.addEventListener('change', () => {
        wrapper.querySelector('.dish-id').value = '';
        input.value = '';
        input.disabled = !categorySelect.value;
        input.placeholder = categorySelect.value ? 'Start typing dish name...' : 'Select category first';
        wrapper.querySelector('.dish-results').innerHTML = '';
        motion?.animateIn(input);
        if (categorySelect.value) {
            fetchResults();
        }
    });

    input.addEventListener('focus', () => {
        if (categorySelect.value) {
            fetchResults();
        }
    });

    input.addEventListener('input', fetchResults);
});

const openCreateDishModal = (categoryId, name, onSuccess) => {
    const modalEl = document.getElementById('createDishModal');
    const modal = new bootstrap.Modal(modalEl);
    const categoryInput = document.getElementById('modal-dish-category');
    const nameInput = document.getElementById('modal-dish-name');
    const descInput = document.getElementById('modal-dish-description');
    const yieldInput = document.getElementById('modal-dish-yield');
    categoryInput.value = categoryId || '';
    nameInput.value = name || '';
    descInput.value = '';
    yieldInput.value = 1;
    modal.show();
    const submitBtn = document.getElementById('create-dish-submit');
    const handler = async () => {
        const payload = {
            category_id: parseInt(categoryInput.value || '0', 10),
            name: nameInput.value,
            description: descInput.value,
            yield_servings: parseInt(yieldInput.value || '1', 10),
        };
        const data = await postJson('/api/dishes/create', payload);
        modal.hide();
        if (data && onSuccess) {
            onSuccess(data.dish);
        }
    };
    submitBtn.addEventListener('click', handler, { once: true });
};

document.getElementById('lock-menu')?.addEventListener('click', async () => {
    const data = await postJson(`/api/menus/${menuId}/lock`, {});
    if (data) {
        location.reload();
    }
});

document.getElementById('unlock-menu')?.addEventListener('click', async () => {
    const data = await postJson(`/api/menus/${menuId}/unlock`, {});
    if (data) {
        location.reload();
    }
});
</script>
