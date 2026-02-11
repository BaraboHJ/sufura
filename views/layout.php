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
    <style>
        :root {
            --sufura-ease: cubic-bezier(.22, 1, .36, 1);
            --sufura-fast: 160ms;
            --sufura-base: 220ms;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0ms !important;
                scroll-behavior: auto !important;
            }
        }

        .btn,
        .card,
        .alert,
        .form-control,
        .form-select,
        .table tbody tr,
        .list-group-item,
        .badge,
        .nav-link {
            transition: background-color var(--sufura-fast) var(--sufura-ease),
                        border-color var(--sufura-fast) var(--sufura-ease),
                        color var(--sufura-fast) var(--sufura-ease),
                        box-shadow var(--sufura-fast) var(--sufura-ease),
                        transform var(--sufura-fast) var(--sufura-ease),
                        opacity var(--sufura-fast) var(--sufura-ease);
        }

        .card:hover {
            transform: translateY(-1px);
        }

        @keyframes sufuraFadeSlide {
            from {
                opacity: 0;
                transform: translateY(6px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .sufura-animate-in {
            animation: sufuraFadeSlide var(--sufura-base) var(--sufura-ease);
        }
    </style>
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

    (() => {
        const animateIn = (element) => {
            if (!element || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            element.classList.remove('sufura-animate-in');
            void element.offsetWidth;
            element.classList.add('sufura-animate-in');
            window.setTimeout(() => {
                element.classList.remove('sufura-animate-in');
            }, 280);
        };

        const animateMany = (elements) => {
            (elements || []).forEach((element, index) => {
                window.setTimeout(() => animateIn(element), index * 18);
            });
        };

        window.SufuraMotion = {
            animateIn,
            animateMany,
        };

        document.addEventListener('DOMContentLoaded', () => {
            animateMany(document.querySelectorAll('main .card, main .alert, main .table tbody tr'));
        });
    })();
</script>
</body>
</html>
