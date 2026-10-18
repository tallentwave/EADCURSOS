<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
const CONFIG_FILE = ROOT . '/config/config.php';

if (!is_file(CONFIG_FILE)) {
    $docroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $rel = str_replace('\\', '/', substr(ROOT, strlen($docroot)));
    header('Location: ' . $rel . '/install/');
    exit;
}

$CONFIG = require CONFIG_FILE;
define('BASE_URL', rtrim($CONFIG['base_url'], '/'));
define('APP_KEY', $CONFIG['app_key']);
define('BASE_URL_PATH', rtrim((string)(parse_url(BASE_URL, PHP_URL_PATH) ?: ''), '/'));

error_reporting(E_ALL);
ini_set('display_errors', !empty($CONFIG['debug']) ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('America/Sao_Paulo');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name('EADSESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' https://www.youtube.com https://player.vimeo.com; frame-src https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com; base-uri 'self'; object-src 'none'");
if ($https) {
    header('Strict-Transport-Security: max-age=31536000');
}

require ROOT . '/app/helpers.php';
require ROOT . '/app/auth.php';
require ROOT . '/app/courses.php';
require ROOT . '/app/payments.php';
require ROOT . '/app/mailer.php';
require ROOT . '/app/coupons.php';
require ROOT . '/app/watch.php';
require ROOT . '/app/exams.php';
require ROOT . '/app/privacy.php';
require ROOT . '/app/certificate.php';
require ROOT . '/app/migrate.php';
ensure_schema();
