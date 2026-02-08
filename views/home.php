<?php
use App\Core\Auth;

$user = Auth::user();
?>
<div class="bg-white p-4 rounded shadow-sm">
    <h1 class="h4">Welcome to Sufura</h1>
    <p class="text-muted">Start by adding ingredients, building recipes, and assembling menus. Costing remains transparent with audit trails and lockable snapshots.</p>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <h2 class="h6">Transparency by default</h2>
                <p class="mb-0">Every cost update is stored with history, so teams can understand how each menu price is derived.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <h2 class="h6">Lock costs when needed</h2>
                <p class="mb-0">Snapshot menu costs to keep reporting stable even as ingredient prices change.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <h2 class="h6">Bulk cost updates</h2>
                <p class="mb-0">Import CSV files to update ingredient costs quickly and review parsing errors.</p>
            </div>
        </div>
    </div>
</div>
