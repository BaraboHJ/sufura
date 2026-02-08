<?php
use App\Core\Csrf;
?>
<div class="mb-4">
    <h1 class="h3 mb-2">New Cost Import</h1>
    <p class="text-muted mb-0">Upload a CSV and preview costs before applying updates.</p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form id="cost-import-form" enctype="multipart/form-data">
                    <?= Csrf::input() ?>
                    <div class="mb-3">
                        <label class="form-label">CSV file</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Upload and preview</button>
                    <a class="btn btn-outline-secondary" href="/imports/costs">Cancel</a>
                </form>
                <div class="alert alert-danger mt-3 d-none" id="import-error"></div>
                <div class="alert alert-success mt-3 d-none" id="import-success"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6">Expected columns</h2>
                <ul class="mb-3">
                    <li><code>ingredient_name</code></li>
                    <li><code>purchase_qty</code></li>
                    <li><code>purchase_uom</code></li>
                    <li><code>total_cost</code></li>
                </ul>
                <p class="text-muted small mb-0">Headers are case-insensitive and may include spaces. Total cost should be in the currency set for the organization.</p>
            </div>
        </div>
    </div>
</div>

<script>
const form = document.getElementById('cost-import-form');
const errorBox = document.getElementById('import-error');
const successBox = document.getElementById('import-success');

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.classList.add('d-none');
    successBox.classList.add('d-none');

    const formData = new FormData(form);
    try {
        const response = await fetch('/api/imports/costs/upload', {
            method: 'POST',
            body: formData,
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Upload failed.');
        }
        const summary = data.summary || {};
        successBox.textContent = `Parsed ${summary.total_rows || 0} rows with ${summary.matched_ok || 0} matches. Redirecting to preview...`;
        successBox.classList.remove('d-none');
        if (data.redirect_url) {
            window.location = data.redirect_url;
        }
    } catch (error) {
        errorBox.textContent = error.message;
        errorBox.classList.remove('d-none');
    }
});
</script>
