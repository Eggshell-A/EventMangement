<?php
// Copy this file to config.php and set the real values there. Never commit config.php.
// Aiven uses database defaultdb, port 14574, SSL REQUIRED, and a ca.pem file.
// Point db_ssl_ca at that ca.pem and set db_ssl_verify to true.
// Local XAMPP can use db_name eventlink, an empty db_port, an empty db_ssl_ca, and db_ssl_verify false.
return [
    'db_host' => 'your-host.example',
    'db_port' => '14574',
    'db_name' => 'defaultdb',
    'db_user' => 'your-db-user',
    'db_pass' => 'replace-with-password',
    'db_ssl_ca' => '/absolute/path/to/ca.pem',
    'db_ssl_verify' => true,
    'base_url' => 'http://localhost/eventlink',
    // Local HTTP: false. Production HTTPS (including TLS proxies): true.
    'session_secure' => false,
    'session_idle_timeout' => 1800,
    'session_absolute_timeout' => 28800,
    // Enable only after HTTPS is working for the whole site.
    'hsts_max_age' => 0,
];
