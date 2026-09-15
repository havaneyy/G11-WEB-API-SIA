<?php


define('DB_HOST', 'localhost');
define('DB_NAME', 'paymongo_shop');
define('DB_USER', 'root');
define('DB_PASS', '');


define('PAYMONGO_SECRET_KEY', 'sk_test_vTqrGYu15sy8JkKgw8bTDhA8');


define('APP_BASE_URL', 'http://localhost/paymongo-shop');


function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}


function format_pesos($centavos) {
    return '₱' . number_format($centavos / 100, 2);
}


session_start();


