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
    $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    if (!empty($config['db_port'])) {
        $dsn = 'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    }
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $sslCa = (string) ($config['db_ssl_ca'] ?? '');
    if ($sslCa !== '' && is_readable($sslCa) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $sslCa;
    }
    if (!empty($config['db_ssl_verify']) && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
    }
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
} catch (PDOException $exception) {
    exit('Database connection failed. Check config.php and XAMPP MySQL.');
}

require_once __DIR__ . '/helpers.php';
