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
                            <table class="table table-striped align-middle mb-0" data-uom-table>
                                <thead class="table-secondary">
                                    <tr>
                                        <th style="width: 30%;">Name</th>
                                        <th style="width: 20%;">Symbol</th>
                                        <th style="width: 30%;">Factor to Base</th>
                                        <th style="width: 20%;">Base UoM</th>
                                        <th style="width: 1%;"></th>
                                    </tr>
                                </thead>
                                <tbody data-uom-rows>
                                    <?php foreach ($set['uoms'] as $uom): ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" data-uom-id-input name="uoms[<?= (int) $uom['id'] ?>][id]" value="<?= (int) $uom['id'] ?>">
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
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-uom>&times;</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-between gap-2">
                            <button type="button" class="btn btn-outline-primary" data-add-uom>Add UoM</button>
                            <button type="submit" class="btn btn-primary">Save UoM Set</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<template id="uom-row-template">
    <tr>
        <td>
            <input type="hidden" data-uom-id-input name="uom_key_placeholder[id]" value="">
            <input class="form-control form-control-sm" type="text" name="uom_key_placeholder[name]" required>
        </td>
        <td>
            <input class="form-control form-control-sm" type="text" name="uom_key_placeholder[symbol]" required>
        </td>
        <td>
            <input class="form-control form-control-sm" type="number" step="0.000001" min="0.000001" name="uom_key_placeholder[factor_to_base]" required>
        </td>
        <td class="text-center">
            <input class="form-check-input" type="radio" name="base_uom_id" value="" required>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-uom>&times;</button>
        </td>
    </tr>
</template>

<script>
document.querySelectorAll('form[action^="/admin/uoms/"]').forEach((form) => {
    const rowsContainer = form.querySelector('[data-uom-rows]');
    const addButton = form.querySelector('[data-add-uom]');
    const template = document.getElementById('uom-row-template');
    let newRowIndex = 1;

    if (!rowsContainer || !addButton || !template) {
        return;
    }

    const updateRemovableState = () => {
        const rows = rowsContainer.querySelectorAll('tr');
        rows.forEach((row) => {
            const removeButton = row.querySelector('[data-remove-uom]');
            if (removeButton) {
                removeButton.disabled = rows.length <= 1;
            }
        });
    };

    const wireRow = (row) => {
        const removeButton = row.querySelector('[data-remove-uom]');
        const baseRadio = row.querySelector('input[type="radio"][name="base_uom_id"]');
        const idInput = row.querySelector('input[data-uom-id-input]');

        if (baseRadio && idInput && !baseRadio.value) {
            baseRadio.value = idInput.value || row.dataset.uomKey || '';
        }

        if (removeButton) {
            removeButton.addEventListener('click', () => {
                const wasChecked = !!baseRadio?.checked;
                row.remove();

                if (wasChecked) {
                    const firstRadio = rowsContainer.querySelector('input[type="radio"][name="base_uom_id"]');
                    if (firstRadio) {
                        firstRadio.checked = true;
                    }
                }

                updateRemovableState();
            });
        }
    };

    rowsContainer.querySelectorAll('tr').forEach((row) => wireRow(row));

    addButton.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('tr');
        const rowKey = `new_${newRowIndex++}`;
        row.dataset.uomKey = rowKey;
        row.querySelectorAll('[name^="uom_key_placeholder"]').forEach((input) => {
            input.name = input.name.replace('uom_key_placeholder', `uoms[${rowKey}]`);
        });
        wireRow(row);
        rowsContainer.appendChild(row);
        updateRemovableState();
    });

    updateRemovableState();
});
</script>
