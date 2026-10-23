<?php
/**
 * CodeCraft Shop configuration.
 * Keep your PayMongo TEST secret key on the server only.
 */
// Load local .env if present (without overwriting existing environment variables)
if (file_exists(__DIR__ . '/.env')) {
    $envLines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($envKey, $envVal) = explode('=', $line, 2);
            $envKey = trim($envKey);
            $envVal = trim($envVal, " \t\n\r\0\x0B\"'");
            if (getenv($envKey) === false) {
                putenv("$envKey=$envVal");
                $_ENV[$envKey] = $envVal;
            }
        }
    }
}

// Support DATABASE_URL or MYSQL_URL connection string from cloud providers (e.g. Aiven, TiDB, Railway)
$cloudDbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if ($cloudDbUrl && strpos($cloudDbUrl, '://') !== false) {
    $parsedUrl = parse_url($cloudDbUrl);
    if (!empty($parsedUrl['host'])) {
        if (!getenv('DB_HOST')) putenv('DB_HOST=' . $parsedUrl['host']);
        if (!empty($parsedUrl['port']) && !getenv('DB_PORT')) putenv('DB_PORT=' . $parsedUrl['port']);
        if (!empty($parsedUrl['user']) && !getenv('DB_USER')) putenv('DB_USER=' . $parsedUrl['user']);
        if (isset($parsedUrl['pass']) && getenv('DB_PASS') === false) putenv('DB_PASS=' . $parsedUrl['pass']);
        if (!empty($parsedUrl['path']) && !getenv('DB_NAME')) putenv('DB_NAME=' . ltrim($parsedUrl['path'], '/'));
    }
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'codecraft_shop');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: null);

// PayMongo API Keys (Admin Central Gateway)
// Get your keys from PayMongo Dashboard: Developers -> API Keys
define('PAYMONGO_SECRET_KEY', getenv('PAYMONGO_SECRET_KEY') ?: 'REPLACE_WITH_YOUR_PAYMONGO_SECRET_KEY');
define('PAYMONGO_PUBLIC_KEY', getenv('PAYMONGO_PUBLIC_KEY') ?: 'REPLACE_WITH_YOUR_PAYMONGO_PUBLIC_KEY');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');

// Detect Base URL (support cloud root domain or local subdirectory)
if (getenv('APP_BASE_URL')) {
    define('APP_BASE_URL', rtrim(getenv('APP_BASE_URL'), '/'));
} elseif (getenv('APP_URL')) {
    define('APP_BASE_URL', rtrim(getenv('APP_URL'), '/'));
} else {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($scriptDir !== '') {
            $parts = explode('/', $scriptDir);
            $basePath = '/' . $parts[0];
        }
    }
    define('APP_BASE_URL', $scheme . '://' . $host . $basePath);
}

define('DEFAULT_AVATAR_URL', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRsYYYmIOuieWvw2LUapfA7Catq3e-NyzpWMPjVoZ32pQ&s=10');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];
        if (defined('DB_SSL_CA') && DB_SSL_CA && file_exists(DB_SSL_CA)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        } elseif (getenv('DB_SSL') === 'true' || getenv('MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') === 'false') {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
function format_pesos($centavos): string {
    return '₱' . number_format(((int)$centavos) / 100, 2);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
