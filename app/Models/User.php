<?php

namespace App\Models;

use PDO;

class User
{
    public static function listByOrg(PDO $pdo, ?int $orgId): array
    {
        $stmt = $pdo->prepare('SELECT id, name, email, role, status, created_at FROM users WHERE org_id = :org_id ORDER BY created_at DESC');
        $stmt->execute(['org_id' => $orgId]);
        return $stmt->fetchAll();
    }

    public static function findById(PDO $pdo, ?int $orgId, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT id, org_id, name, email, role, status, created_at FROM users WHERE org_id = :org_id AND id = :id LIMIT 1');
        $stmt->execute(['org_id' => $orgId, 'id' => $userId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(PDO $pdo, ?int $orgId, array $payload): array
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (org_id, name, email, role, status, password_hash) VALUES (:org_id, :name, :email, :role, :status, :password_hash)'
        );
        $stmt->execute([
            'org_id' => $orgId,
            'name' => $payload['name'],
            'email' => $payload['email'],
            'role' => $payload['role'],
            'status' => $payload['status'],
            'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
        ]);

        $id = (int) $pdo->lastInsertId();
        return self::findById($pdo, $orgId, $id) ?? [];
    }

    public static function updateRoleStatus(PDO $pdo, ?int $orgId, int $userId, string $role, string $status): array
    {
        $stmt = $pdo->prepare(
            'UPDATE users SET role = :role, status = :status WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'role' => $role,
            'status' => $status,
            'org_id' => $orgId,
            'id' => $userId,
        ]);

        return self::findById($pdo, $orgId, $userId) ?? [];
    }

    public static function updatePassword(PDO $pdo, ?int $orgId, int $userId, string $password): void
    {
        $stmt = $pdo->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE org_id = :org_id AND id = :id'
        );
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'org_id' => $orgId,
            'id' => $userId,
        ]);
    }

    public static function validatePayload(array $payload, bool $requirePassword): array
    {
        $errors = [];

        if (!isset($payload['name']) || trim($payload['name']) === '') {
            $errors[] = 'Name is required.';
        }

        if (!isset($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }

        $roles = ['admin', 'editor', 'viewer'];
        if (!isset($payload['role']) || !in_array($payload['role'], $roles, true)) {
            $errors[] = 'Role must be admin, editor, or viewer.';
        }

        $statuses = ['active', 'inactive'];
        if (!isset($payload['status']) || !in_array($payload['status'], $statuses, true)) {
            $errors[] = 'Status must be active or inactive.';
        }

        if ($requirePassword && (!isset($payload['password']) || strlen($payload['password']) < 8)) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        return $errors;
    }
}
