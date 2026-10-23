<?php
/**
 * CodeCraft Shop configuration.
 * Keep your PayMongo TEST secret key on the server only.
 */
// Load local .env if present
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($envKey, $envVal) = explode('=', $line, 2);
            $envKey = trim($envKey);
            $envVal = trim($envVal, " \t\n\r\0\x0B\"'");
            putenv("$envKey=$envVal");
            $_ENV[$envKey] = $envVal;
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'codecraft_shop');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

// PayMongo API Keys (Admin Central Gateway)
// Get your keys from PayMongo Dashboard: Developers -> API Keys
define('PAYMONGO_SECRET_KEY', getenv('PAYMONGO_SECRET_KEY') ?: 'REPLACE_WITH_YOUR_PAYMONGO_SECRET_KEY');
define('PAYMONGO_PUBLIC_KEY', getenv('PAYMONGO_PUBLIC_KEY') ?: 'REPLACE_WITH_YOUR_PAYMONGO_PUBLIC_KEY');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = '/codecraft-shop';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if ($scriptDir !== '') {
        $parts = explode('/', $scriptDir);
        $basePath = '/' . $parts[0];
    }
}
define('APP_BASE_URL', $scheme . '://' . $host . $basePath);
define('DEFAULT_AVATAR_URL', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRsYYYmIOuieWvw2LUapfA7Catq3e-NyzpWMPjVoZ32pQ&s=10');

function get_db(): PDO {
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
function format_pesos($centavos): string {
    return '₱' . number_format(((int)$centavos) / 100, 2);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
