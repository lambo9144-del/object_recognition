<?php

declare(strict_types=1);

/**
 * Admin-only settings API.
 *   GET  /api/settings.php            -> current settings (API keys never included)
 *   POST {"action":"save", ...}       -> save settings
 *   POST {"action":"test"}            -> test connection to each provider
 * Every POST needs the CSRF token from the settings page.
 */

define('APP_API', true);
require __DIR__ . '/../includes/bootstrap.php';

Auth::requireApi();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    json_out(['ok' => true, 'data' => AppConfig::publicSettings()]);
}

require_post();
$body = read_json_body(64 * 1024);

if (!Auth::verifyCsrf(is_string($body['csrf'] ?? null) ? $body['csrf'] : null)) {
    json_out(['ok' => false, 'error' => 'Your session expired. Reload the page and try again.'], 403);
}

$action = is_string($body['action'] ?? null) ? $body['action'] : '';

if ($action === 'test') {
    set_time_limit(60);
    json_out(['ok' => true, 'data' => (new ObjectRecognizer())->testAll()]);
}

if ($action !== 'save') {
    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
}

// ---- validate and clean every field ----
$provider = $body['provider'] ?? 'auto';
if (!in_array($provider, ['auto', 'gemini', 'agnes'], true)) {
    json_out(['ok' => false, 'error' => 'Choose Auto, Gemini or Agnes.'], 422);
}

$timeout = filter_var($body['timeout'] ?? null, FILTER_VALIDATE_INT);
if ($timeout === false || $timeout < 10 || $timeout > 120) {
    json_out(['ok' => false, 'error' => 'Timeout must be between 10 and 120 seconds.'], 422);
}

$modelName = static function (mixed $value): ?string {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }
    return preg_match('/^[A-Za-z0-9._\-\/:]{1,100}$/', $value) === 1 ? $value : null;
};
$geminiModel = $modelName($body['gemini_model'] ?? '');
$agnesModel = $modelName($body['agnes_model'] ?? '');
if ($geminiModel === null || $agnesModel === null) {
    json_out(['ok' => false, 'error' => 'Model names may only contain letters, numbers and . _ - / :'], 422);
}

$fallbacks = static fn (mixed $value): string => implode("\n", AppConfig::modelList(is_string($value) ? $value : ''));

$baseUrl = is_string($body['agnes_base_url'] ?? null) ? rtrim(trim($body['agnes_base_url']), '/') : '';
if ($baseUrl !== '' && (preg_match('#^https?://[^\s]+$#i', $baseUrl) !== 1 || strlen($baseUrl) > 200)) {
    json_out(['ok' => false, 'error' => 'The Agnes base URL must start with https://'], 422);
}

$values = [
    'provider' => $provider,
    'timeout' => $timeout,
    'gemini_model' => $geminiModel,
    'gemini_fallback_models' => $fallbacks($body['gemini_fallback_models'] ?? ''),
    'agnes_model' => $agnesModel,
    'agnes_fallback_models' => $fallbacks($body['agnes_fallback_models'] ?? ''),
    'agnes_base_url' => $baseUrl,
    'web_lookup' => (bool) ($body['web_lookup'] ?? false),
];

// API keys: a blank field means "keep the saved key".
foreach (['gemini_api_key', 'agnes_api_key'] as $keyName) {
    $submitted = is_string($body[$keyName] ?? null) ? trim($body[$keyName]) : '';
    if ($submitted !== '') {
        if (strlen($submitted) > 300 || preg_match('/\s/', $submitted) === 1) {
            json_out(['ok' => false, 'error' => 'That API key does not look valid.'], 422);
        }
        $values[$keyName] = $submitted;
    }
}
// Explicit removal of a saved key
foreach (['gemini' => 'gemini_api_key', 'agnes' => 'agnes_api_key'] as $short => $keyName) {
    if (!empty($body['clear_' . $short . '_key'])) {
        $values[$keyName] = '';
    }
}

try {
    AppConfig::save($values);
} catch (RuntimeException $e) {
    log_error('Settings save failed: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Could not save. Make sure the storage folder is writable.'], 500);
}

json_out(['ok' => true, 'data' => AppConfig::publicSettings()]);
