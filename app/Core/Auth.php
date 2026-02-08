<?php

namespace App\Core;

use PDO;

class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function login(PDO $pdo, string $email, string $password): bool
    {
        $stmt = $pdo->prepare('SELECT id, org_id, name, email, role, status, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        unset($user['password_hash']);
        $_SESSION['user'] = $user;

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
    }

    public static function requireLogin(): void
    {
        if (!self::user()) {
            header('Location: /?r=login');
            exit;
        }
    }
}
