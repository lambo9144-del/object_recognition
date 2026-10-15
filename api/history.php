<?php

declare(strict_types=1);

/**
 * Admin-only history API.
 *   POST {"action":"delete","id":5,"csrf":"..."}
 *   POST {"action":"clear","csrf":"..."}
 */

define('APP_API', true);
require __DIR__ . '/../includes/bootstrap.php';

Auth::requireApi();
require_post();

$body = read_json_body(4096);
if (!Auth::verifyCsrf(is_string($body['csrf'] ?? null) ? $body['csrf'] : null)) {
    json_out(['ok' => false, 'error' => 'Your session expired. Reload the page and try again.'], 403);
}
if (!Database::enabled()) {
    json_out(['ok' => false, 'error' => 'Scan history is not enabled.'], 409);
}

try {
    switch ($body['action'] ?? '') {
        case 'delete':
            $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                json_out(['ok' => false, 'error' => 'Invalid record.'], 422);
            }
            ScanHistory::delete($id);
            json_out(['ok' => true, 'data' => ['deleted' => $id]]);
        case 'clear':
            ScanHistory::clear();
            json_out(['ok' => true, 'data' => ['cleared' => true]]);
        default:
            json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (PDOException $e) {
    log_error('History API database error: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Database error.'], 500);
}
