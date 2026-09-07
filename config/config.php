<?php
declare(strict_types=1);

/* SCERRS - Smart Citizen Emergency Reporting & Response System
 * Global configuration.
 */

define('APP_NAME', 'SCERRS');
define('APP_TAGLINE', 'Smart Citizen Emergency Reporting & Response System');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'scerrs');
define('DB_USER', 'root');
define('DB_PASS', '');

// Project root (folder that contains this config folder)
define('APP_ROOT', dirname(__DIR__));

// Uploads
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'incidents');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5 MB

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Never let browsers cache HTML pages — ensures they always load the
// current CSS/JS asset links (?v= timestamps). Static assets themselves
// are still cached normally by Apache.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Base URL auto-detection (works under http://localhost/SCERRS or http://scerrs.test)
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$appRoot = str_replace('\\', '/', APP_ROOT);
$rel     = ($docRoot && str_starts_with($appRoot, $docRoot)) ? trim(substr($appRoot, strlen($docRoot)), '/') : '';
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host . '/' . ($rel !== '' ? $rel . '/' : ''));

require_once __DIR__ . '/db.php';
require_once APP_ROOT . '/includes/functions.php';
