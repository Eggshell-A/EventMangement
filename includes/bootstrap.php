<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    exit('Configuration missing. Copy config.example.php to config.php and update it.');
}
$config = require $configPath;

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $exception) {
    exit('Database connection failed. Check config.php and XAMPP MySQL.');
}

require_once __DIR__ . '/helpers.php';
