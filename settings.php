<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
page_headers();
Auth::start();

$loginError = '';

// ---- sign in / sign out (plain form posts) ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null;
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!Auth::verifyCsrf($token)) {
        $loginError = 'Your session expired. Please try again.';
    } elseif ($action === 'logout') {
        Auth::logout();
        header('Location: settings.php');
        exit;
    } elseif ($action === 'login') {
        $user = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
        $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (Auth::login($user, $pass)) {
            header('Location: settings.php');
            exit;
        }
        $loginError = 'Wrong username or password.';
    }
}

$loggedIn = Auth::check();
$csrf = Auth::csrfToken();
$noPasswordSet = (string) AppConfig::get('admin_password_hash', '') === '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Settings - AI + AR Object Scanner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="admin-page">
<div class="admin-wrap">

<?php if (!$loggedIn): ?>

    <div class="card login-card">
        <h2>Admin sign in</h2>
        <?php if ($noPasswordSet): ?>
            <div class="notice error">No admin password is set. Add <code>admin_password_hash</code> to <code>config/config.php</code> (see README).</div>
        <?php endif; ?>
        <?php if ($loginError !== ''): ?>
            <div class="notice error" role="alert"><?= e($loginError) ?></div>
        <?php endif; ?>
        <form method="post" action="settings.php" autocomplete="on">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="actions">
                <button type="submit" class="btn-primary">Sign in</button>
                <a class="btn-secondary" href="index.php" style="display:inline-flex;align-items:center;text-decoration:none;">Back to scanner</a>
            </div>
        </form>
    </div>

<?php else:
    $s = AppConfig::publicSettings();
    $storageOk = is_dir(__DIR__ . '/storage') && is_writable(__DIR__ . '/storage');
    ?>

    <div class="admin-head">
        <h1>Settings</h1>
        <nav class="admin-nav" aria-label="Admin">
            <a class="btn-secondary" href="index.php" style="display:inline-flex;align-items:center;">Scanner</a>
            <a class="btn-secondary" href="history.php" style="display:inline-flex;align-items:center;">History</a>
            <form method="post" action="settings.php" style="display:inline;">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="btn-secondary">Sign out</button>
            </form>
        </nav>
    </div>

    <?php if (!$storageOk): ?>
        <div class="notice error" role="alert">The <code>storage/</code> folder is not writable, so settings cannot be saved. Make it writable (chmod 755 or 775).</div>
    <?php endif; ?>

    <div id="message" role="status" aria-live="polite"></div>

    <form id="settingsForm" data-csrf="<?= e($csrf) ?>" novalidate>

        <section class="card">
            <h2>AI provider</h2>
            <div class="field">
                <label for="provider">Provider mode</label>
                <select id="provider" name="provider">
                    <option value="auto" <?= $s['provider'] === 'auto' ? 'selected' : '' ?>>Auto (Gemini first, then Agnes)</option>
                    <option value="gemini" <?= $s['provider'] === 'gemini' ? 'selected' : '' ?>>Gemini only</option>
                    <option value="agnes" <?= $s['provider'] === 'agnes' ? 'selected' : '' ?>>Agnes only</option>
                </select>
            </div>
            <div class="field">
                <label for="timeout">Request timeout (seconds, 10 - 120)</label>
                <input type="number" id="timeout" name="timeout" min="10" max="120" step="1" value="<?= (int) $s['timeout'] ?>">
            </div>
            <div class="field">
                <label class="check"><input type="checkbox" id="web_lookup" name="web_lookup" <?= !empty($s['web_lookup']) ? 'checked' : '' ?>> Look up a web reference after recognition</label>
            </div>
        </section>

        <section class="card">
            <h2>Gemini</h2>
            <div class="field">
                <label for="gemini_model">Gemini model</label>
                <input type="text" id="gemini_model" name="gemini_model" value="<?= e((string) $s['gemini_model']) ?>" autocomplete="off" spellcheck="false">
            </div>
            <div class="field">
                <label for="gemini_fallback_models">Gemini fallback models (one per line)</label>
                <textarea id="gemini_fallback_models" name="gemini_fallback_models" spellcheck="false"><?= e((string) $s['gemini_fallback_models']) ?></textarea>
            </div>
            <div class="field">
                <label for="gemini_api_key">Gemini API key</label>
                <div class="key-row">
                    <input type="password" id="gemini_api_key" name="gemini_api_key" autocomplete="new-password" spellcheck="false" placeholder="<?= $s['gemini_key_saved'] ? 'Leave blank to keep the saved key' : 'Paste your key' ?>">
                    <button type="button" class="btn-secondary" data-toggle-key="gemini_api_key" aria-label="Show Gemini API key">Show</button>
                </div>
                <span class="key-status <?= $s['gemini_key_saved'] ? 'saved' : 'missing' ?>" id="geminiKeyStatus"><?= $s['gemini_key_saved'] ? 'Key saved' : 'Not configured' ?></span>
                <label class="check"><input type="checkbox" id="clear_gemini_key" name="clear_gemini_key"> Remove saved key</label>
            </div>
        </section>

        <section class="card">
            <h2>Agnes</h2>
            <div class="field">
                <label for="agnes_base_url">Agnes API base URL</label>
                <input type="text" id="agnes_base_url" name="agnes_base_url" value="<?= e((string) $s['agnes_base_url']) ?>" placeholder="https://..." autocomplete="off" spellcheck="false">
                <div class="hint">OpenAI-compatible endpoint, without <code>/chat/completions</code>. Get it from your Agnes account.</div>
            </div>
            <div class="field">
                <label for="agnes_model">Agnes model</label>
                <input type="text" id="agnes_model" name="agnes_model" value="<?= e((string) $s['agnes_model']) ?>" autocomplete="off" spellcheck="false">
            </div>
            <div class="field">
                <label for="agnes_fallback_models">Agnes fallback models (one per line)</label>
                <textarea id="agnes_fallback_models" name="agnes_fallback_models" spellcheck="false"><?= e((string) $s['agnes_fallback_models']) ?></textarea>
            </div>
            <div class="field">
                <label for="agnes_api_key">Agnes API key</label>
                <div class="key-row">
                    <input type="password" id="agnes_api_key" name="agnes_api_key" autocomplete="new-password" spellcheck="false" placeholder="<?= $s['agnes_key_saved'] ? 'Leave blank to keep the saved key' : 'Paste your key' ?>">
                    <button type="button" class="btn-secondary" data-toggle-key="agnes_api_key" aria-label="Show Agnes API key">Show</button>
                </div>
                <span class="key-status <?= $s['agnes_key_saved'] ? 'saved' : 'missing' ?>" id="agnesKeyStatus"><?= $s['agnes_key_saved'] ? 'Key saved' : 'Not configured' ?></span>
                <label class="check"><input type="checkbox" id="clear_agnes_key" name="clear_agnes_key"> Remove saved key</label>
            </div>
        </section>

        <div class="actions" style="margin-bottom:16px;">
            <button type="submit" class="btn-primary" id="saveBtn">Save settings</button>
            <button type="button" class="btn-secondary" id="testBtn">Test Connection</button>
        </div>
        <div class="test-results" id="testResults" aria-live="polite"></div>
    </form>

    <section class="card" style="margin-top:16px;">
        <h2>Open on Phone</h2>
        <div class="qr-box">
            <div class="qr-code" id="qrCode" aria-label="QR code that opens the scanner"></div>
            <p>Scan this QR code using your phone.</p>
            <div class="qr-url" id="qrUrl"></div>
            <div class="notice error" id="qrWarning" hidden>This address is not HTTPS. Phone cameras only work on HTTPS (or localhost).</div>
        </div>
    </section>

<?php endif; ?>

</div>

<?php if ($loggedIn): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="assets/js/settings.js"></script>
<?php endif; ?>
</body>
</html>
