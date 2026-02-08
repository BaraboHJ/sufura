<?php
function status_badge(string $status): string
{
    $map = [
        'uploaded' => 'bg-info',
        'applied' => 'bg-success',
        'failed' => 'bg-danger',
    ];
    $class = $map[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($status, ENT_QUOTES) . '</span>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Cost Imports</h1>
        <p class="text-muted mb-0">Upload CSV files and track parsing results.</p>
    </div>
    <a class="btn btn-primary" href="/imports/costs/new">New Import</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>File</th>
                    <th>Status</th>
                    <th>Rows</th>
                    <th>Matched</th>
                    <th>Errors</th>
                    <th>Uploaded</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($imports)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No imports yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($imports as $import): ?>
                        <?php
                        $errorCount = (int) ($import['missing_ingredient'] ?? 0)
                            + (int) ($import['invalid_uom'] ?? 0)
                            + (int) ($import['invalid_number'] ?? 0);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($import['original_filename'], ENT_QUOTES) ?></td>
                            <td><?= status_badge($import['status']) ?></td>
                            <td><?= (int) ($import['total_rows'] ?? 0) ?></td>
                            <td><?= (int) ($import['matched_ok'] ?? 0) ?></td>
                            <td><?= $errorCount ?></td>
                            <td><?= htmlspecialchars($import['created_at'] ?? '', ENT_QUOTES) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="/imports/costs/<?= (int) $import['id'] ?>">Open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
