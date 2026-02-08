<?php
use App\Core\Csrf;

$summary = $_SESSION['form_summary'] ?? null;
unset($_SESSION['form_summary']);
$errors = $summary['errors'] ?? [];
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="bg-white p-4 rounded shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 mb-0">Bulk Upload Dishes</h1>
                    <p class="text-muted mb-0">Upload a CSV to add dishes in one step.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="/dishes">Back</a>
            </div>

            <div class="alert alert-info">
                Download the template, fill in your data, then upload the completed CSV.
                <a href="/dishes/template" class="alert-link">Download dish template</a>.
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

            <form method="post" action="/dishes/import" enctype="multipart/form-data">
                <?= Csrf::input() ?>
                <div class="mb-3">
                    <label class="form-label">CSV file</label>
                    <input class="form-control" type="file" name="csv_file" accept=".csv" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Upload dishes</button>
            </form>

            <div class="mt-4">
                <div class="fw-semibold">Required columns</div>
                <ul class="mb-0 text-muted small">
                    <li><code>name</code></li>
                </ul>
                <div class="fw-semibold mt-3">Optional columns</div>
                <ul class="mb-0 text-muted small">
                    <li><code>description</code></li>
                    <li><code>yield_servings</code> (defaults to 1)</li>
                    <li><code>active</code> (1/0, yes/no, active/inactive)</li>
                </ul>
            </div>
        </div>
    </div>
</div>
