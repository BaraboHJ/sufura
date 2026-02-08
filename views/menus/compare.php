<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Compare Menus</h1>
        <p class="text-muted mb-0">Select 2–4 menus to compare side-by-side.</p>
    </div>
    <a class="btn btn-outline-secondary" href="/menus">Back to menus</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form id="compare-form">
            <div class="mb-3">
                <label class="form-label">Menus</label>
                <select class="form-select" name="menu_ids[]" multiple size="6" required>
                    <?php foreach ($menus as $menu): ?>
                        <option value="<?= (int) $menu['id'] ?>">
                            <?= htmlspecialchars($menu['name'], ENT_QUOTES) ?>
                            (<?= htmlspecialchars($menu['menu_type'], ENT_QUOTES) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Hold ⌘/Ctrl to select multiple menus.</div>
            </div>
            <button class="btn btn-primary" type="submit">Compare</button>
        </form>
        <div class="alert alert-danger mt-3 d-none" id="compare-error"></div>
    </div>
</div>

<script>
const compareForm = document.getElementById('compare-form');
const errorBox = document.getElementById('compare-error');

compareForm.addEventListener('submit', (event) => {
    event.preventDefault();
    errorBox.classList.add('d-none');
    const selected = Array.from(compareForm.querySelectorAll('option:checked')).map(opt => opt.value);
    if (selected.length < 2 || selected.length > 4) {
        errorBox.textContent = 'Please select 2 to 4 menus.';
        errorBox.classList.remove('d-none');
        return;
    }
    window.location = `/menus/compare/view?ids=${selected.join(',')}`;
});
</script>
