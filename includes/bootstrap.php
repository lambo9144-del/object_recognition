<?php

declare(strict_types=1);

/**
 * Shared bootstrap: autoloader, error handling and small helper functions.
 * Every page and API endpoint includes this file first.
 */

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Never show raw PHP errors to visitors. Errors go to storage/error.log instead.
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    log_error("PHP error [$severity]: $message in " . basename($file) . ":$line");
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    log_error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
    if (defined('APP_API')) {
        json_out(['ok' => false, 'error' => 'Server error. Please try again.'], 500);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Server error. Please try again later.';
    exit;
});

/** Escape text for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Write a line to storage/error.log. API keys are redacted first.
 */
function log_error(string $message): void
{
    $message = AppConfig::redact($message);
    $dir = APP_ROOT . '/storage';
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    @file_put_contents($dir . '/error.log', '[' . gmdate('Y-m-d H:i:s') . "] $message\n", FILE_APPEND | LOCK_EX);
}

/** Send a JSON response and stop. */
function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Read and decode a JSON request body, refusing anything larger than $maxBytes.
 */
function read_json_body(int $maxBytes): array
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > $maxBytes) {
        json_out(['ok' => false, 'error' => 'The request is too large.'], 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || $raw === '') {
        json_out(['ok' => false, 'error' => 'Empty request.'], 400);
    }
    if (strlen($raw) > $maxBytes) {
        json_out(['ok' => false, 'error' => 'The request is too large.'], 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_out(['ok' => false, 'error' => 'Invalid JSON request.'], 400);
    }
    return $data;
}

/** Require a POST request for API endpoints. */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        json_out(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
}

/** Basic security headers for HTML pages. */
function page_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
}
