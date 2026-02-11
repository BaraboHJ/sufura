<?php

declare(strict_types=1);

session_start();

ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    echo 'Missing config/config.php. Copy config/config.example.php to config.php and update values.';
    exit;
}

$config = require $configFile;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\DB;
use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\AdminUserController;
use App\Controllers\AdminCategoryController;
use App\Controllers\IngredientController;
use App\Controllers\DishController;
use App\Controllers\MenuController;
use App\Controllers\ImportController;
use App\Controllers\BulkImportController;

$pdo = DB::conn($config);
$router = new Router();

$homeController = new HomeController();
$authController = new AuthController($pdo);
$adminUserController = new AdminUserController($pdo, $config, $root);
$adminCategoryController = new AdminCategoryController($pdo);
$ingredientController = new IngredientController($pdo);
$dishController = new DishController($pdo);
$menuController = new MenuController($pdo);
$importController = new ImportController($pdo);
$bulkImportController = new BulkImportController($pdo);

$router->get('/', [$homeController, 'index']);
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/admin/users', [$adminUserController, 'index']);
$router->get('/admin/users/new', [$adminUserController, 'createForm']);
$router->post('/admin/users/create', [$adminUserController, 'create']);
$router->post('/admin/users/:id/update', [$adminUserController, 'update']);
$router->post('/admin/reset-data', [$adminUserController, 'resetData']);
$router->post('/admin/system/update', [$adminUserController, 'runSystemUpdate']);
$router->get('/admin/categories', [$adminCategoryController, 'index']);
$router->post('/admin/categories/create', [$adminCategoryController, 'create']);
$router->post('/admin/categories/:id/update', [$adminCategoryController, 'update']);
$router->post('/admin/categories/:id/delete', [$adminCategoryController, 'delete']);
$router->get('/ingredients', [$ingredientController, 'index']);
$router->get('/ingredients/new', [$ingredientController, 'createForm']);
$router->post('/ingredients/create', [$ingredientController, 'create']);
$router->get('/ingredients/import', [$bulkImportController, 'ingredientForm']);
$router->post('/ingredients/import', [$bulkImportController, 'ingredientUpload']);
$router->get('/ingredients/template', [$bulkImportController, 'ingredientTemplate']);
$router->get('/ingredients/:id', [$ingredientController, 'show']);
$router->get('/ingredients/:id/edit', [$ingredientController, 'editForm']);
$router->post('/ingredients/:id/update', [$ingredientController, 'update']);
$router->post('/ingredients/:id/delete', [$ingredientController, 'delete']);
$router->post('/ingredients/bulk-delete', [$ingredientController, 'bulkDelete']);
$router->post('/api/ingredients/:id/costs', [$ingredientController, 'addCost']);
$router->get('/api/ingredients/search', [$ingredientController, 'search']);
$router->post('/api/ingredients/create', [$ingredientController, 'createFromApi']);
$router->get('/api/uoms', [$ingredientController, 'listUoms']);
$router->get('/dishes', [$dishController, 'index']);
$router->get('/dishes/new', [$dishController, 'createForm']);
$router->post('/dishes/create', [$dishController, 'create']);
$router->get('/dishes/import', [$bulkImportController, 'dishForm']);
$router->post('/dishes/import', [$bulkImportController, 'dishUpload']);
$router->get('/dishes/template', [$bulkImportController, 'dishTemplate']);
$router->get('/dishes/:id', [$dishController, 'show']);
$router->get('/dishes/:id/edit', [$dishController, 'editForm']);
$router->post('/dishes/:id/update', [$dishController, 'update']);
$router->post('/dishes/:id/delete', [$dishController, 'delete']);
$router->post('/dishes/bulk-delete', [$dishController, 'bulkDelete']);
$router->post('/api/dishes/:id/lines/add', [$dishController, 'addLine']);
$router->post('/api/dish-lines/:id/update', [$dishController, 'updateLine']);
$router->post('/api/dish-lines/:id/delete', [$dishController, 'deleteLine']);
$router->get('/api/dishes/:id/cost_summary', [$dishController, 'costSummary']);
$router->get('/api/dishes/:id/cost_breakdown', [$dishController, 'costBreakdown']);
$router->get('/api/dishes/search', [$dishController, 'search']);
$router->post('/api/dishes/create', [$dishController, 'createFromApi']);
$router->get('/menus', [$menuController, 'index']);
$router->get('/menus/new', [$menuController, 'createForm']);
$router->post('/menus/create', [$menuController, 'create']);
$router->get('/menus/:id/edit', [$menuController, 'editForm']);
$router->post('/menus/:id/update', [$menuController, 'update']);
$router->post('/menus/:id/duplicate', [$menuController, 'duplicate']);
$router->post('/menus/:id/delete', [$menuController, 'delete']);
$router->post('/menus/bulk-delete', [$menuController, 'bulkDelete']);
$router->get('/api/menus/:id/compute', [$menuController, 'compute']);
$router->post('/api/menus/:id/update', [$menuController, 'updateFromApi']);
$router->post('/api/menus/:id/groups/create', [$menuController, 'createGroup']);
$router->post('/api/menu-groups/:id/update', [$menuController, 'updateGroup']);
$router->post('/api/menu-groups/:id/delete', [$menuController, 'deleteGroup']);
$router->post('/api/menu-groups/:group_id/items/create', [$menuController, 'createItem']);
$router->post('/api/menu-items/:id/update', [$menuController, 'updateItem']);
$router->post('/api/menu-items/:id/delete', [$menuController, 'deleteItem']);
$router->post('/api/menus/:id/lock', [$menuController, 'lock']);
$router->post('/api/menus/:id/unlock', [$menuController, 'unlock']);
$router->get('/menus/compare', [$menuController, 'compareForm']);
$router->get('/menus/compare/view', [$menuController, 'compareView']);
$router->post('/api/menus/compare', [$menuController, 'compareApi']);
$router->get('/imports/costs', [$importController, 'index']);
$router->get('/imports/costs/new', [$importController, 'createForm']);
$router->get('/imports/costs/:id', [$importController, 'show']);
$router->post('/api/imports/costs/upload', [$importController, 'upload']);
$router->post('/api/imports/costs/:id/confirm', [$importController, 'confirm']);

$route = $_GET['r'] ?? null;
$path = $route ? '/' . ltrim($route, '/') : parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $path ?: '/';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($path, $method);
