<?php
// ENV-driven configuration with optional SSL/TLS for MySQL

function env_bool(string $name, bool $default = false): bool {
	$val = getenv($name);
	if ($val === false) return $default;
	$val = strtolower(trim($val));
	return in_array($val, ['1','true','yes','on'], true);
}

function env_int(string $name, int $default): int {
	$val = getenv($name);
	if ($val === false || $val === '') return $default;
	return (int)$val;
}

// Database credentials via ENV (with safe defaults)
$servername = getenv('DB_HOST') ?: (getenv('MYSQL_HOST') ?: 'db:3306');
$dbport = env_int('DB_PORT', env_int('MYSQL_PORT', 3306));
$dbname = getenv('DB_NAME') ?: (getenv('MYSQL_DATABASE') ?: 'recipedb');
$username = getenv('DB_USER') ?: (getenv('MYSQL_USER') ?: 'root');
$password = getenv('DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: 'root');

// SSL / TLS settings
$sslEnable = env_bool('DB_SSL_ENABLE', false);
$sslCA = getenv('DB_SSL_CA') ?: '';
$sslCert = getenv('DB_SSL_CERT') ?: '';
$sslKey = getenv('DB_SSL_KEY') ?: '';
$sslVerify = env_bool('DB_SSL_VERIFY_SERVER_CERT', true);

// PDO options
$pdoOptions = [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES => false,
	PDO::ATTR_PERSISTENT => env_bool('DB_PERSISTENT', false),
];

// Add MySQL-specific options if available
if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
	$pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
}

// Apply SSL if enabled
if ($sslEnable) {
	if ($sslCA && is_file($sslCA)) {
		$pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $sslCA;
	}
	if ($sslCert && is_file($sslCert)) {
		$pdoOptions[PDO::MYSQL_ATTR_SSL_CERT] = $sslCert;
	}
	if ($sslKey && is_file($sslKey)) {
		$pdoOptions[PDO::MYSQL_ATTR_SSL_KEY] = $sslKey;
	}
	$pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $sslVerify;
}

// Build DSN (use port if provided)
$dsn = "mysql:host={$servername};dbname={$dbname}";
if (!empty($dbport)) {
	$dsn .= ";port={$dbport}";
}

try {
	$conn = new PDO($dsn, $username, $password, $pdoOptions);
} catch (PDOException $e) {
	http_response_code(500);
	die('Datenbankverbindung fehlgeschlagen.');
}

// App settings
define('APP_NAME', 'RecipeHub');
// Logo settings - can be set via environment variable or use default
define('APP_LOGO_PATH', getenv('APP_LOGO_PATH') ?: 'images/logos/recipehub-logo.svg');
define('APP_LOGO_ALT', getenv('APP_LOGO_ALT') ?: APP_NAME);
// BASE_URL for email links (full domain) - must be set via environment variable
define('BASE_URL', getenv('BASE_URL') ?: '');
// SITE_URL for static assets and internal links (relative or full domain)
define('SITE_URL', getenv('SITE_URL') ?: '/');
define('UPLOAD_DIR', __DIR__ . '/public/uploads');
define('UPLOAD_BASE_URL', SITE_URL . 'uploads');

if (!is_dir(UPLOAD_DIR)) {
	@mkdir(UPLOAD_DIR, 0775, true);
}

// Logo helper function
function get_app_logo_html(): string {
	$logoPath = SITE_URL . APP_LOGO_PATH;
	$logoAlt = APP_LOGO_ALT;
	$fullLogoPath = __DIR__ . '/public/' . APP_LOGO_PATH;
	
	if (file_exists($fullLogoPath)) {
		return '<img src="' . htmlspecialchars($logoPath) . '" alt="' . htmlspecialchars($logoAlt) . '" class="h-8 w-auto" />';
	} else {
		return '<span class="font-semibold text-xl">' . htmlspecialchars(APP_NAME) . '</span>';
	}
}

// Email settings for PHPMailer
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'hallo@recipe-hub.de');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'mail.manitu.de');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USER', getenv('MAIL_USER') ?: 'hallo@recipe-hub.de');
define('MAIL_PASS', getenv('MAIL_PASS') ?: 'jr19KHa241??!!');
define('MAIL_SECURE', getenv('MAIL_SECURE') ?: 'tls'); // 'ssl' or 'tls'

// Helper: ensure paths like "uploads/..." become "/uploads/...", keep absolute http(s) and root paths
function absolute_url_from_path(string $path): string {
    if ($path === '') return '';
    if (preg_match('#^https?://#i', $path) === 1) return $path;
    if ($path[0] === '/') return $path;
    return '/' . ltrim($path, '/');
}

