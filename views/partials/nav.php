<?php
use App\Core\Auth;

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
                    <li class="nav-item"><a class="nav-link" href="/?r=ingredients">Ingredients</a></li>
                    <li class="nav-item"><a class="nav-link" href="/?r=dishes">Dishes</a></li>
                    <li class="nav-item"><a class="nav-link" href="/?r=menus">Menus</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if ($user): ?>
                    <li class="nav-item"><span class="navbar-text me-3">Hello, <?= htmlspecialchars($user['name'], ENT_QUOTES) ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="/?r=logout">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/?r=login">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
