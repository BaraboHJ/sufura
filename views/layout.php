<?php
use App\Core\Auth;

$user = Auth::user();
$flashError = $_SESSION['flash_error'] ?? null;
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_error']);
unset($_SESSION['flash_success']);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Sufura', ENT_QUOTES) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body text-body">
<?php require __DIR__ . '/partials/nav.php'; ?>
<main class="container py-4">
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($flashError, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flashSuccess, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <?php if (isset($view) && file_exists($view)) {
        require $view;
    } ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (() => {
        const html = document.documentElement;
        const storedTheme = localStorage.getItem('theme');
        const theme = storedTheme === 'light' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', theme);

        const toggle = document.getElementById('themeToggle');
        if (!toggle) {
            return;
        }

        const setToggleLabel = (currentTheme) => {
            toggle.textContent = currentTheme === 'dark' ? 'Light mode' : 'Dark mode';
        };

        setToggleLabel(theme);

        toggle.addEventListener('click', () => {
            const nextTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', nextTheme);
            localStorage.setItem('theme', nextTheme);
            setToggleLabel(nextTheme);
        });
    })();
</script>
</body>
</html>
