<?php
use App\Core\Csrf;

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
    <div class="card-body">
        <form method="post" action="/menus/bulk-delete" id="menu-bulk-delete-form">
            <?= Csrf::input() ?>
            <?php if ($canBulkDelete): ?>
                <div class="mb-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Delete selected menus and all their snapshot data?')">Delete selected</button>
                </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <?php if ($canBulkDelete): ?>
                            <th><input type="checkbox" id="menu-select-all"></th>
                        <?php endif; ?>
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
                            <td colspan="<?= $canBulkDelete ? '7' : '6' ?>" class="text-muted">No menus yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($menuRows as $menu): ?>
                            <tr>
                                <?php if ($canBulkDelete): ?>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= (int) $menu['id'] ?>" class="menu-select-row"></td>
                                <?php endif; ?>
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
                                    <div class="d-inline-flex gap-2">
                                        <a class="btn btn-sm btn-outline-primary" href="/menus/<?= (int) $menu['id'] ?>/edit?mode=view">View</a>
                                        <a class="btn btn-sm btn-primary" href="/menus/<?= (int) $menu['id'] ?>/edit">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<?php if ($canBulkDelete): ?>
<script>
const menuSelectAll = document.getElementById('menu-select-all');
menuSelectAll?.addEventListener('change', () => {
    document.querySelectorAll('.menu-select-row').forEach((checkbox) => {
        checkbox.checked = menuSelectAll.checked;
    });
});
</script>
<?php endif; ?>
