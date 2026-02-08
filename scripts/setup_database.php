<?php

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Missing config/config.php. Copy config/config.example.php first.\n");
    exit(1);
}

$config = require $configPath;

$host = $config['db_host'] ?? '127.0.0.1';
$port = (int) ($config['db_port'] ?? 3306);
$dbName = $config['db_name'] ?? 'sufura';
$user = $config['db_user'] ?? 'root';
$pass = $config['db_pass'] ?? '';

$mysqli = new mysqli($host, $user, $pass, '', $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connection failed: {$mysqli->connect_error}\n");
    exit(1);
}

$files = [
    __DIR__ . '/../sql/001_init.sql',
    __DIR__ . '/../sql/002_seed.sql',
];

$dbNameSql = str_replace('`', '``', $dbName);

foreach ($files as $file) {
    if (!file_exists($file)) {
        fwrite(STDERR, "Missing SQL file: {$file}\n");
        exit(1);
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Unable to read SQL file: {$file}\n");
        exit(1);
    }

    if (basename($file) === '001_init.sql') {
        $sql = preg_replace(
            '/CREATE DATABASE IF NOT EXISTS\s+[^;]+;/i',
            "CREATE DATABASE IF NOT EXISTS `{$dbNameSql}`;",
            $sql
        );
        $sql = preg_replace('/USE\s+[^;]+;/i', "USE `{$dbNameSql}`;", $sql);
    }

    if (!$mysqli->multi_query($sql)) {
        fwrite(STDERR, "Failed running {$file}: {$mysqli->error}\n");
        exit(1);
    }

    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }

        if ($mysqli->errno) {
            fwrite(STDERR, "SQL error in {$file}: {$mysqli->error}\n");
            exit(1);
        }
    } while ($mysqli->more_results() && $mysqli->next_result());
}

$mysqli->close();

echo "Database setup complete.\n";
