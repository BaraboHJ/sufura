<?php
use App\Core\Auth;
use App\Core\Csrf;

$user = Auth::user();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="/">Sufura</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link" href="/ingredients">Ingredients</a></li>
                    <li class="nav-item"><a class="nav-link" href="/dishes">Dishes</a></li>
                    <li class="nav-item"><a class="nav-link" href="/menus">Menus</a></li>
                    <li class="nav-item"><a class="nav-link" href="/menus/compare">Compare Menus</a></li>
                    <li class="nav-item"><a class="nav-link" href="/imports/costs">Cost Imports</a></li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="/admin/users">Admin Portal</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if ($user): ?>
                    <li class="nav-item"><span class="navbar-text me-3">Hello, <?= htmlspecialchars($user['name'], ENT_QUOTES) ?></span></li>
                    <li class="nav-item">
                        <button class="btn btn-outline-light btn-sm me-3" type="button" id="themeToggle">Light mode</button>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="/logout" class="d-inline">
                            <?= Csrf::input() ?>
                            <button class="btn btn-link nav-link" type="submit">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <button class="btn btn-outline-light btn-sm me-3" type="button" id="themeToggle">Light mode</button>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/login">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
