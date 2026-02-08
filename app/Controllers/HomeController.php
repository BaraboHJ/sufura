<?php

namespace App\Controllers;

use App\Core\Auth;

class HomeController
{
    public function index(): void
    {
        Auth::requireLogin();
        $pageTitle = 'Dashboard';
        $view = __DIR__ . '/../../views/home.php';
        require __DIR__ . '/../../views/layout.php';
    }
}
