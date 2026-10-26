<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
configureSessionCookies();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (isset($_SESSION['user']) && sessionIsExpired($_SESSION)) {
    $_SESSION = [];
    $_SESSION['flash'][] = ['message' => 'Your session expired. Please log in again.', 'type' => 'error'];
}
touchSession();

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
    if (defined('Pdo\Mysql::ATTR_SSL_CA')) {
        $sslCaAttr = \Pdo\Mysql::ATTR_SSL_CA;
        $sslVerifyAttr = \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT;
    } elseif (defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $sslCaAttr = constant('PDO::MYSQL_ATTR_SSL_CA');
        $sslVerifyAttr = constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT');
    } else {
        $sslCaAttr = null;
        $sslVerifyAttr = null;
    }
    if ($sslCa !== '' && is_readable($sslCa) && $sslCaAttr !== null) {
        $options[$sslCaAttr] = $sslCa;
    }
    if (!empty($config['db_ssl_verify']) && $sslVerifyAttr !== null) {
        $options[$sslVerifyAttr] = true;
    }
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
} catch (PDOException $exception) {
    exit('Database connection failed. Check config.php and XAMPP MySQL.');
}

require_once __DIR__ . '/helpers.php';

if (isset($_SESSION['user']['user_id'])) {
    $activeStatement = $pdo->prepare('SELECT is_active FROM users WHERE user_id = ?');
    $activeStatement->execute([(int) $_SESSION['user']['user_id']]);
    $account = $activeStatement->fetch();
    if (!$account || !accountIsActive($account)) {
        $_SESSION = [];
        $_SESSION['flash'][] = ['message' => 'This account is not active.', 'type' => 'error'];
        touchSession();
    }
}
