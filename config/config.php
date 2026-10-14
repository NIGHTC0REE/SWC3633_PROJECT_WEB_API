<?php

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'boom_plus');
define('DB_USER', 'root');
define('DB_PASS', '');

define('JWT_SECRET', 'boom-plus-change-me-7f3a9c1e5d2b8a40c6e1f9d3b7a5c2e8');
define('JWT_TTL', 3600);
define('RESET_TTL', 3600);

define('RATE_LIMIT_GLOBAL', 300);
define('RATE_LIMIT_AUTH', 15);
define('RATE_LIMIT_WINDOW', 60);

define('QR_SERVICE_URL', 'https://api.qrserver.com/v1/create-qr-code/');
define('QR_TIMEOUT', 6);

define('APP_URL', '/boom');
define('API_BASE', 'http://localhost/boom/api');
define('SHARED_URL', APP_URL . '/shared/assets');

date_default_timezone_set('Asia/Kuala_Lumpur');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $pdo->exec("SET time_zone = '+08:00'");
    }

    return $pdo;
}
