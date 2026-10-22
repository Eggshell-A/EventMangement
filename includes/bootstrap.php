<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    exit('Configuration missing. Copy config.example.php to config.php and update it.');
}
$config = require $configPath;

ini_set('display_errors', '0');
require_once __DIR__ . '/security.php';
startSecureSession($config);
sendSecurityHeaders($config);
// A single guard protects every POST, including login, registration and logout.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireValidCsrf();
} elseif (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

try {
    $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    if (!empty($config['db_port'])) {
        $dsn = 'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    }
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
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
    serverError($exception);
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/booking.php';
require_once __DIR__ . '/auth.php';

// Refresh roles on each request so a database role change takes effect immediately.
if (isLoggedIn()) {
    try {
        $statement = $pdo->prepare('SELECT user_id,name,email,role FROM users WHERE user_id=?');
        $statement->execute([currentUser()['user_id']]);
        $user = $statement->fetch();
        if (!$user) {
            resetSession();
        } else {
            $_SESSION['user'] = $user;
        }
    } catch (Throwable $exception) {
        serverError($exception);
    }
}
