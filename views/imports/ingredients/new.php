<?php
use App\Core\Csrf;

$summary = $_SESSION['form_summary'] ?? null;
unset($_SESSION['form_summary']);
$errors = $summary['errors'] ?? [];
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="bg-body-secondary p-4 rounded shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0">Bulk Upload Ingredients</h1>
                    <p class="text-muted mb-0">Upload a CSV to add ingredients in one step.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="/ingredients">Back</a>
            </div>

            <div class="alert alert-info">
                Download the template, fill in your data, then upload the completed CSV.
                <a href="/ingredients/template" class="alert-link">Download ingredient template</a>.
            </div>

            <?php if ($summary): ?>
                <div class="mb-3">
                    <div class="fw-semibold">Upload summary</div>
                    <div class="text-muted small">File: <?= htmlspecialchars($summary['file_name'] ?? 'upload.csv', ENT_QUOTES) ?></div>
                    <div class="d-flex gap-3 mt-2">
                        <span class="badge bg-success">Created: <?= (int) ($summary['created'] ?? 0) ?></span>
                        <span class="badge bg-secondary">Skipped: <?= (int) ($summary['skipped'] ?? 0) ?></span>
                        <span class="badge bg-danger">Errors: <?= count($errors) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2">Please fix the following issues:</div>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="/ingredients/import" enctype="multipart/form-data">
                <?= Csrf::input() ?>
                <div class="mb-3">
                    <label class="form-label">CSV file</label>
                    <input class="form-control" type="file" name="csv_file" accept=".csv" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Upload ingredients</button>
            </form>

            <div class="mt-4">
                <div class="fw-semibold">Required columns</div>
                <ul class="mb-0 text-muted small">
                    <li><code>name</code></li>
                    <li><code>uom_set</code> (must match an existing UoM set name)</li>
                </ul>
                <div class="fw-semibold mt-3">Optional columns</div>
                <ul class="mb-0 text-muted small">
                    <li><code>notes</code></li>
                    <li><code>active</code> (1/0, yes/no, active/inactive)</li>
                </ul>
            </div>
        </div>
    </div>
</div>
