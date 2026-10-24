<?php
// Load optional local .env file (KEY=VALUE, one per line).
$envFile = __DIR__ . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

if (!function_exists('linky_env')) {
    function linky_env($key, $default = null) {
        $value = getenv($key);
        return ($value === false || $value === '') ? $default : $value;
    }
}

// Database Configuration (override via .env or real environment variables)
define('DB_HOST', linky_env('DB_HOST', 'localhost'));
define('DB_NAME', linky_env('DB_NAME', ''));
define('DB_USER', linky_env('DB_USER', ''));
define('DB_PASS', linky_env('DB_PASS', ''));

// Application Configuration — auto-detects domain and base path
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

// Compute the base URL from the physical location of this config file
// so it always works regardless of which script is running
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$configDir = str_replace('\\', '/', realpath(__DIR__));
$baseDir = '';
if ($docRoot && strpos($configDir, $docRoot) === 0) {
    $baseDir = substr($configDir, strlen($docRoot));
}
if ($baseDir === '/')
    $baseDir = '';

// Optional BASE_URL override (e.g. https://yourdomain.com)
define('BASE_URL', linky_env('BASE_URL', $protocol . '://' . $host . $baseDir));
define('SITE_NAME', linky_env('SITE_NAME', 'Linky Dz'));
define('SITE_SLUG', linky_env('SITE_SLUG', 'linky-dz'));

// Footer branding / social links (optional; hidden when empty)
define('STUDIO_NAME', linky_env('STUDIO_NAME', ''));
define('STUDIO_URL', linky_env('STUDIO_URL', ''));
define('SOCIAL_INSTAGRAM_URL', linky_env('SOCIAL_INSTAGRAM_URL', ''));

// Locale and third-party service settings (set in .env)
define('PHONE_COUNTRY_CODE', linky_env('PHONE_COUNTRY_CODE', ''));
define('QR_API_URL', linky_env('QR_API_URL', ''));
define('IP_API_URL', linky_env('IP_API_URL', ''));

// Session Configuration
define('SESSION_LIFETIME', 3600 * 24); // 24 hours

// File Paths
define('STORAGE_DIR', __DIR__ . '/qr-codes/');
define('STORAGE_URL', BASE_URL . '/qr-codes/');

define('QR_RAW_DIR', STORAGE_DIR . 'raw/');
define('QR_RAW_URL', STORAGE_URL . 'raw/');

define('QR_CARD_DIR', STORAGE_DIR . 'cards/');
define('QR_CARD_URL', STORAGE_URL . 'cards/');

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('PFP_DIR', UPLOAD_DIR . 'profiles/');
define('PFP_URL', UPLOAD_URL . 'profiles/');
define('PFP_UPLOAD_DIR', PFP_DIR); // Alias for portal API
define('BG_DIR', UPLOAD_DIR . 'backgrounds/');
define('BG_URL', UPLOAD_URL . 'backgrounds/');
define('BG_UPLOAD_DIR', BG_DIR); // Alias for portal API

// Strong Password
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 10);

// Admin recovery credentials (used by reset-admin.php; set in .env)
define('RECOVERY_USER', linky_env('RECOVERY_USER', 'admin'));
define('RECOVERY_EMAIL', linky_env('RECOVERY_EMAIL', 'admin@example.com'));
define('RECOVERY_PASS', linky_env('RECOVERY_PASS', ''));
define('RECOVERY_KEY', linky_env('RECOVERY_KEY', ''));

// Telegram Notification Settings (optional; set in .env)
define('TELEGRAM_BOT_TOKEN', linky_env('TELEGRAM_BOT_TOKEN', ''));
define('TELEGRAM_CHAT_ID', linky_env('TELEGRAM_CHAT_ID', ''));

// Token Configuration
define('TOKEN_LENGTH', 6);
define('TOKEN_CHARS', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789');

// Timezone
date_default_timezone_set('UTC');

// Error Reporting (disabled in production for security)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable public error display
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Session Security Hardening (only if session not yet active)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if ($protocol === 'https') {
        ini_set('session.cookie_secure', 1);
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global Rate Limiting (60 requests per minute)
require_once 'rate-limiter.php';
RateLimiter::handleGlobalLimit();

// Global Security & Sanitization
require_once 'security.php';
Security::sanitizeInputs();
Security::blockSuspicious();

// Protect against CSRF on state-changing requests
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if (!in_array($currentScript, ['api-track-pageview.php', 'api-track-click.php'])) {
    Security::verifyCSRF();
}
