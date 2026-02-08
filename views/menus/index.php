<?php
function format_money(?int $minor, string $currency): string
{
    if ($minor === null) {
        return '—';
    }
    return sprintf('%s %s', $currency, number_format($minor / 100, 2));
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Menus</h1>
        <p class="text-muted mb-0">Track package and per-item menus, live costs, and locked quotes.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="/menus/compare">Compare Menus</a>
        <a class="btn btn-primary" href="/menus/new">New Menu</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Cost per pax</th>
                <th>Status</th>
                <th>Updated</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($menuRows)): ?>
                <tr>
                    <td colspan="6" class="text-muted">No menus yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($menuRows as $menu): ?>
                    <tr>
                        <td><?= htmlspecialchars($menu['name'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $menu['menu_type'] ?? 'package')), ENT_QUOTES) ?></td>
                        <td><?= format_money($menu['menu_cost_per_pax_minor'] ?? null, $menu['currency'] ?? $currency) ?></td>
                        <td>
                            <span class="badge <?= ($menu['cost_mode'] ?? 'live') === 'locked' ? 'bg-secondary' : 'bg-success' ?>">
                                <?= htmlspecialchars($menu['cost_mode'] ?? 'live', ENT_QUOTES) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($menu['updated_at'] ?? $menu['created_at'] ?? '', ENT_QUOTES) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="/menus/<?= (int) $menu['id'] ?>/edit">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
