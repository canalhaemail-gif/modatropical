<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

function normalize_app_url(string $value): string
{
    $value = trim(str_replace('\\', '/', $value));

    if ($value === '' || $value === '/') {
        return '';
    }

    return '/' . trim($value, '/');
}

function detect_app_url_from_document_root(string $basePath): ?string
{
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';

    if ($documentRoot === '') {
        return null;
    }

    $baseRealPath = realpath($basePath) ?: $basePath;
    $documentRootRealPath = realpath($documentRoot) ?: $documentRoot;

    $baseNormalized = rtrim(str_replace('\\', '/', $baseRealPath), '/');
    $documentRootNormalized = rtrim(str_replace('\\', '/', $documentRootRealPath), '/');

    if ($documentRootNormalized === '') {
        return null;
    }

    if ($baseNormalized === $documentRootNormalized) {
        return '';
    }

    $prefix = $documentRootNormalized . '/';

    if (!str_starts_with($baseNormalized, $prefix)) {
        return null;
    }

    return normalize_app_url(substr($baseNormalized, strlen($documentRootNormalized)));
}

function resolve_app_url(string $basePath): string
{
    $candidates = [
        $_SERVER['APP_URL'] ?? null,
        getenv('APP_URL') !== false ? (string) getenv('APP_URL') : null,
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== null && trim((string) $candidate) !== '') {
            return normalize_app_url((string) $candidate);
        }
    }

    $detected = detect_app_url_from_document_root($basePath);

    if ($detected !== null) {
        return $detected;
    }

    return normalize_app_url('/' . basename($basePath));
}

define('BASE_PATH', dirname(__DIR__));
define('APP_NAME', 'Moda Tropical');
define('APP_URL', resolve_app_url(BASE_PATH));

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/mail.php';
require_once BASE_PATH . '/config/asaas.php';
require_once BASE_PATH . '/config/pagbank.php';
require_once BASE_PATH . '/config/google.php';
require_once BASE_PATH . '/config/facebook.php';
require_once BASE_PATH . '/config/apple.php';
require_once BASE_PATH . '/config/tiktok.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/mailer.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/google_auth.php';
require_once BASE_PATH . '/includes/social_auth.php';
require_once BASE_PATH . '/includes/customer_verification.php';
require_once BASE_PATH . '/includes/customer_addresses.php';
require_once BASE_PATH . '/includes/notifications.php';
require_once BASE_PATH . '/includes/message_queue.php';
require_once BASE_PATH . '/includes/coupons.php';
require_once BASE_PATH . '/includes/storefront.php';
require_once BASE_PATH . '/includes/customer_favorites.php';
require_once BASE_PATH . '/includes/asaas.php';
require_once BASE_PATH . '/includes/pagbank.php';
require_once BASE_PATH . '/includes/orders.php';

attempt_admin_remember_login();
attempt_customer_remember_login();

ensure_upload_directories();
