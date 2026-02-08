<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use PDO;

class AuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function showLogin(): void
    {
        $pageTitle = 'Login';
        $view = __DIR__ . '/../../views/login.php';
        require __DIR__ . '/../../views/layout.php';
    }

    public function login(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!Auth::login($this->pdo, $email, $password)) {
            $_SESSION['flash_error'] = 'Invalid credentials or inactive account.';
            $_SESSION['form_values'] = ['email' => $email];
            header('Location: /login');
            exit;
        }

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        Auth::logout();
        header('Location: /login');
        exit;
    }
}
