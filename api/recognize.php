<?php

declare(strict_types=1);

/**
 * POST /api/recognize.php
 * Body: {"image": "<base64 JPEG>"}
 * Returns: {"ok": true, "data": {...}} or {"ok": false, "error": "..."}
 */

define('APP_API', true);
require __DIR__ . '/../includes/bootstrap.php';

require_post();

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (!str_contains($contentType, 'application/json')) {
    json_out(['ok' => false, 'error' => 'Send the image as JSON.'], 415);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!RateLimiter::allow($ip, 15, 60)) {
    header('Retry-After: 60');
    json_out(['ok' => false, 'error' => 'Too many scans. Please wait a minute and try again.'], 429);
}

$body = read_json_body(ImageValidator::MAX_BYTES * 2);

try {
    [$binary, $mime] = ImageValidator::fromBase64($body['image'] ?? null);
} catch (InvalidArgumentException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 400);
}

set_time_limit(180);

try {
    $result = (new ObjectRecognizer())->recognize($binary, $mime);
} catch (ProviderException $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], $e->httpStatus());
}

// Optional history. A database problem must never break scanning.
if (Database::enabled()) {
    try {
        ScanHistory::add($result);
    } catch (Throwable $e) {
        log_error('History save failed: ' . $e->getMessage());
    }
}

json_out(['ok' => true, 'data' => $result]);
