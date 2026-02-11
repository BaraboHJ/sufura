<?php

namespace App\Core;

use PDO;
use PDOException;

class DB
{
    private static ?PDO $pdo = null;
    private static bool $schemaEnsured = false;

    public static function conn(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_port'],
            $config['db_name']
        );

        try {
            self::$pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo 'Database connection failed.';
            exit;
        }

        self::ensureSchema(self::$pdo);

        return self::$pdo;
    }

    private static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        $schemaPath = dirname(__DIR__, 2) . '/sql/001_init.sql';
        if (!is_file($schemaPath) || !is_readable($schemaPath)) {
            self::$schemaEnsured = true;
            return;
        }

        $schemaSql = file_get_contents($schemaPath);
        if ($schemaSql === false || trim($schemaSql) === '') {
            self::$schemaEnsured = true;
            return;
        }

        $statements = preg_split('/;\s*(?:\r\n|\r|\n)/', $schemaSql) ?: [];
        foreach ($statements as $statement) {
            $sql = trim($statement);
            if ($sql === '') {
                continue;
            }

            $pdo->exec($sql);
        }

        self::$schemaEnsured = true;
    }
}
