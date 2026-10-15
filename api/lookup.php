<?php

declare(strict_types=1);

/**
 * POST /api/lookup.php
 * Body: {"productName": "...", "manufacturer": "..."}
 * Optional second step that looks for a matching web reference.
 * Always answers ok:true; "reference" is null when nothing trustworthy is found.
 */

define('APP_API', true);
require __DIR__ . '/../includes/bootstrap.php';

require_post();

if (!(bool) AppConfig::get('web_lookup', true)) {
    json_out(['ok' => true, 'data' => ['reference' => null]]);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!RateLimiter::allow('lookup-' . $ip, 30, 60)) {
    json_out(['ok' => true, 'data' => ['reference' => null]]);
}

$body = read_json_body(4096);
$product = is_string($body['productName'] ?? null) ? mb_substr(trim($body['productName']), 0, 120) : '';
$maker = is_string($body['manufacturer'] ?? null) ? mb_substr(trim($body['manufacturer']), 0, 80) : '';

$reference = null;
if ($product !== '' && $maker !== '') {
    try {
        $reference = (new ProductLookup())->find($product, $maker);
    } catch (Throwable $e) {
        log_error('Lookup failed: ' . $e->getMessage());
    }
}

json_out(['ok' => true, 'data' => ['reference' => $reference]]);
