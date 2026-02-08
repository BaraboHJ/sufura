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

$pdo = DB::conn($config);
$router = new Router();

$homeController = new HomeController();
$authController = new AuthController($pdo);
$adminUserController = new AdminUserController($pdo);

$router->get('/', [$homeController, 'index']);
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/admin/users', [$adminUserController, 'index']);
$router->get('/admin/users/new', [$adminUserController, 'createForm']);
$router->post('/admin/users/create', [$adminUserController, 'create']);
$router->post('/admin/users/:id/update', [$adminUserController, 'update']);

$route = $_GET['r'] ?? null;
$path = $route ? '/' . ltrim($route, '/') : parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $path ?: '/';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->dispatch($path, $method);
