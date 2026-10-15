<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
page_headers();

if (!Auth::check()) {
    header('Location: settings.php');
    exit;
}
$csrf = Auth::csrfToken();

$query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$enabled = Database::enabled();
$rows = [];
$total = 0;
$dbError = '';

if ($enabled) {
    try {
        $data = ScanHistory::search($query, $page);
        $rows = $data['rows'];
        $total = $data['total'];
    } catch (Throwable $e) {
        log_error('History page error: ' . $e->getMessage());
        $dbError = 'Could not read the scan history. Check the database settings in config/config.php and that database.sql was imported.';
    }
}

$pages = max(1, (int) ceil($total / ScanHistory::PER_PAGE));
$link = static fn (int $p): string => 'history.php?' . http_build_query(array_filter(['q' => $query, 'page' => $p > 1 ? $p : null]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Scan History - AI + AR Object Scanner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="admin-page">
<div class="admin-wrap" style="width:min(100%, 960px);">

    <div class="admin-head">
        <h1>Scan History</h1>
        <nav class="admin-nav" aria-label="Admin">
            <a class="btn-secondary" href="index.php" style="display:inline-flex;align-items:center;">Scanner</a>
            <a class="btn-secondary" href="settings.php" style="display:inline-flex;align-items:center;">Settings</a>
        </nav>
    </div>

    <div id="message" role="status" aria-live="polite"></div>

    <?php if (!$enabled): ?>
        <div class="card">
            <p>Scan history is turned off. To enable it, import <code>database/database.sql</code> and set <code>'db_enabled' =&gt; true</code> (plus your database details) in <code>config/config.php</code>.</p>
        </div>
    <?php elseif ($dbError !== ''): ?>
        <div class="notice error" role="alert"><?= e($dbError) ?></div>
    <?php else: ?>
        <div class="card" id="historyCard" data-csrf="<?= e($csrf) ?>">
            <form class="search-row" method="get" action="history.php" role="search">
                <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search product, object or manufacturer" aria-label="Search history">
                <button type="submit" class="btn-secondary">Search</button>
                <button type="button" class="btn-danger" id="clearBtn" <?= $total === 0 && $query === '' ? 'disabled' : '' ?>>Clear history</button>
            </form>

            <?php if ($rows === []): ?>
                <p style="color:var(--muted);">No scans found.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="history">
                        <thead>
                            <tr><th>Product</th><th>Provider</th><th>Confidence</th><th>Date</th><th><span class="sr-only">Actions</span></th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) $row['product_name']) ?></strong>
                                    <details>
                                        <summary>View details</summary>
                                        <dl>
                                            <dt>Object</dt><dd><?= e((string) $row['object_label']) ?></dd>
                                            <dt>Manufacturer</dt><dd><?= e((string) $row['manufacturer']) ?></dd>
                                            <dt>Specification</dt><dd><?= e((string) $row['specification']) ?></dd>
                                            <dt>Description</dt><dd><?= e((string) $row['description']) ?></dd>
                                        </dl>
                                    </details>
                                </td>
                                <td><?= e(ucfirst((string) $row['provider'])) ?></td>
                                <td><?= (int) round((float) $row['confidence'] * 100) ?>%</td>
                                <td><?= e((string) $row['created_at']) ?></td>
                                <td><button type="button" class="btn-danger delete-btn" data-id="<?= (int) $row['id'] ?>" aria-label="Delete <?= e((string) $row['product_name']) ?>">Delete</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pager">
                    <span><?= $total ?> scan<?= $total === 1 ? '' : 's' ?> &middot; page <?= $page ?> of <?= $pages ?></span>
                    <span>
                        <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>">&larr; Newer</a><?php endif; ?>
                        <?php if ($page > 1 && $page < $pages): ?> &nbsp;|&nbsp; <?php endif; ?>
                        <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>">Older &rarr;</a><?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<style>.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}</style>
<?php if ($enabled && $dbError === ''): ?>
<script src="assets/js/history.js"></script>
<?php endif; ?>
</body>
</html>
