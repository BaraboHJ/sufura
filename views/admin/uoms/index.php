<?php
use App\Core\Csrf;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">UoM Management</h1>
        <p class="text-muted mb-0">Review and update UoM sets, conversion factors, and base units.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/users">Back to Admin</a>
</div>

<?php if (empty($uomSets)): ?>
    <div class="alert alert-info">No UoM sets found for this organization.</div>
<?php else: ?>
    <div class="vstack gap-3">
        <?php foreach ($uomSets as $set): ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    <strong><?= htmlspecialchars($set['name'], ENT_QUOTES) ?></strong>
                </div>
                <div class="card-body">
                    <form method="post" action="/admin/uoms/<?= (int) $set['id'] ?>/update">
                        <?= Csrf::input() ?>
                        <div class="mb-3">
                            <label class="form-label" for="set-name-<?= (int) $set['id'] ?>">UoM Set Name</label>
                            <input
                                id="set-name-<?= (int) $set['id'] ?>"
                                class="form-control"
                                type="text"
                                name="set_name"
                                value="<?= htmlspecialchars($set['name'], ENT_QUOTES) ?>"
                                required
                            >
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-secondary">
                                    <tr>
                                        <th style="width: 30%;">Name</th>
                                        <th style="width: 20%;">Symbol</th>
                                        <th style="width: 30%;">Factor to Base</th>
                                        <th style="width: 20%;">Base UoM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($set['uoms'] as $uom): ?>
                                        <tr>
                                            <td>
                                                <input
                                                    class="form-control form-control-sm"
                                                    type="text"
                                                    name="uoms[<?= (int) $uom['id'] ?>][name]"
                                                    value="<?= htmlspecialchars($uom['name'], ENT_QUOTES) ?>"
                                                    required
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    class="form-control form-control-sm"
                                                    type="text"
                                                    name="uoms[<?= (int) $uom['id'] ?>][symbol]"
                                                    value="<?= htmlspecialchars($uom['symbol'], ENT_QUOTES) ?>"
                                                    required
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    class="form-control form-control-sm"
                                                    type="number"
                                                    step="0.000001"
                                                    min="0.000001"
                                                    name="uoms[<?= (int) $uom['id'] ?>][factor_to_base]"
                                                    value="<?= htmlspecialchars(number_format((float) $uom['factor_to_base'], 6, '.', ''), ENT_QUOTES) ?>"
                                                    required
                                                >
                                            </td>
                                            <td class="text-center">
                                                <input
                                                    class="form-check-input"
                                                    type="radio"
                                                    name="base_uom_id"
                                                    value="<?= (int) $uom['id'] ?>"
                                                    <?= $uom['is_base'] ? 'checked' : '' ?>
                                                    required
                                                    aria-label="Set <?= htmlspecialchars($uom['name'], ENT_QUOTES) ?> as base UoM"
                                                >
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Save UoM Set</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
