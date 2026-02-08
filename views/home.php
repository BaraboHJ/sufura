<?php
use App\Core\Auth;

$user = Auth::user();
?>
<div class="bg-body-secondary text-body p-4 rounded shadow-sm">
    <h1 class="h4">Welcome to Sufura</h1>
    <p class="text-body-secondary">Start by adding ingredients, building recipes, and assembling menus. Costing remains transparent with audit trails and lockable snapshots.</p>
    <div class="row g-3">
        <div class="col-md-4">
            <a class="btn btn-outline-primary w-100" href="/?r=ingredients">View Ingredients</a>
        </div>
        <div class="col-md-4">
            <a class="btn btn-outline-primary w-100" href="/?r=dishes">View Dishes</a>
        </div>
        <div class="col-md-4">
            <a class="btn btn-outline-primary w-100" href="/?r=menus">View Menus</a>
        </div>
    </div>
</div>
