<?php
use App\Core\Csrf;

$idsParam = implode(',', array_map('intval', $menuIds ?? []));
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Menu Comparison</h1>
        <p class="text-muted mb-0">Side-by-side summary and item breakdown.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/menus/compare">Edit selection</a>
</div>

<div id="compare-error" class="alert alert-danger d-none"></div>

<div id="compare-summary" class="row g-3 mb-4"></div>

<div id="compare-breakdown"></div>

<script>
const menuIds = '<?= htmlspecialchars($idsParam, ENT_QUOTES) ?>'.split(',').filter(Boolean).map(Number);
const errorBox = document.getElementById('compare-error');
const summaryEl = document.getElementById('compare-summary');
const breakdownEl = document.getElementById('compare-breakdown');

const formatMoney = (minor, currency) => {
    if (minor === null || minor === undefined) return '—';
    return `${currency} ${(minor / 100).toFixed(2)}`;
};

const formatPercent = (value) => {
    if (value === null || value === undefined) return '—';
    return `${(value * 100).toFixed(1)}%`;
};

const normalizeItemKey = (item) => {
    return `${(item.name || '').toLowerCase().trim()}::${item.dish_id || ''}`;
};

const renderSummary = (menus) => {
    summaryEl.innerHTML = menus.map(menu => {
        const priceBand = menu.price_min_minor !== null
            ? `${formatMoney(menu.price_min_minor, menu.currency)}${menu.price_max_minor && menu.price_max_minor !== menu.price_min_minor ? ' - ' + formatMoney(menu.price_max_minor, menu.currency) : ''}`
            : '—';
        const profitBand = menu.profit_min_minor !== null
            ? `${formatMoney(menu.profit_min_minor, menu.currency)}${menu.profit_max_minor !== null && menu.profit_max_minor !== menu.profit_min_minor ? ' - ' + formatMoney(menu.profit_max_minor, menu.currency) : ''}`
            : '—';
        return `
            <div class="col-md-${Math.max(3, Math.floor(12 / menus.length))}">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="h6 mb-1">${menu.name}</h2>
                                <div class="text-muted small">${menu.menu_type}</div>
                            </div>
                            <span class="badge ${menu.cost_mode === 'locked' ? 'bg-secondary' : 'bg-success'}">${menu.cost_mode}</span>
                        </div>
                        <hr>
                        <div class="mb-2"><span class="text-muted">Price band</span><div>${priceBand}</div></div>
                        <div class="mb-2"><span class="text-muted">Cost per pax</span><div>${formatMoney(menu.menu_cost_per_pax_minor, menu.currency)}</div></div>
                        <div class="mb-2"><span class="text-muted">Gross profit/pax</span><div>${profitBand}</div></div>
                        <div><span class="text-muted">Food cost %</span>
                            <div>${formatPercent(menu.food_cost_pct_min)}${menu.food_cost_pct_max !== null && menu.food_cost_pct_max !== menu.food_cost_pct_min ? ' - ' + formatPercent(menu.food_cost_pct_max) : ''}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    window.SufuraMotion?.animateMany(summaryEl.querySelectorAll('.card'));
};

const renderBreakdown = (groups, items, menus) => {
    const menuMap = Object.fromEntries(menus.map(menu => [menu.id, menu]));
    breakdownEl.innerHTML = groups.map(group => {
        const groupItems = items[group.key] || {};
        const keyCounts = {};
        Object.values(groupItems).forEach(list => {
            list.forEach(item => {
                const key = normalizeItemKey(item);
                keyCounts[key] = (keyCounts[key] || 0) + 1;
            });
        });

        const columns = menus.map(menu => {
            const list = groupItems[menu.id] || [];
            return `
                <div class="col">
                    <h3 class="h6 mb-2">${menu.name}</h3>
                    ${list.length === 0 ? '<div class="text-muted small">No items.</div>' : `
                        <ul class="list-group list-group-flush">
                            ${list.map(item => {
                                const key = normalizeItemKey(item);
                                const missingElsewhere = (keyCounts[key] || 0) < menus.length;
                                const badge = missingElsewhere ? '<span class="badge bg-warning text-dark ms-2">Diff</span>' : '';
                                const cost = item.cost_per_pax_minor !== null ? formatMoney(item.cost_per_pax_minor, menu.currency) : '—';
                                return `
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold">${item.name}</div>
                                            <div class="text-muted small">${item.dish_name || ''}</div>
                                        </div>
                                        <div class="text-end">
                                            <div>${cost}</div>
                                            ${badge}
                                        </div>
                                    </li>
                                `;
                            }).join('')}
                        </ul>
                    `}
                </div>
            `;
        }).join('');

        return `
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">${group.name}</h2>
                    </div>
                    <div class="row g-3">${columns}</div>
                </div>
            </div>
        `;
    }).join('');
    window.SufuraMotion?.animateMany(breakdownEl.querySelectorAll('.card, .list-group-item'));
};

const loadCompare = async () => {
    try {
        const response = await fetch('/api/menus/compare', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>',
            },
            body: JSON.stringify({ menu_ids: menuIds }),
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Unable to load comparison.');
        }
        renderSummary(data.menus || []);
        renderBreakdown(data.groups || [], data.items || {}, data.menus || []);
    } catch (error) {
        errorBox.textContent = error.message;
        errorBox.classList.remove('d-none');
    }
};

loadCompare();
</script>
