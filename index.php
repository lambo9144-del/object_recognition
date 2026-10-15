<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
page_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b0f14">
    <title>AI + AR Object Scanner</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="scanner-page">
<main class="app" id="app">

    <header class="topbar">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"></span>
            <h1>AI + AR Object Scanner</h1>
        </div>
        <a class="icon-btn" href="settings.php" aria-label="Settings">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
        </a>
    </header>

    <section class="stage" id="stage" aria-label="Camera view">
        <video id="video" playsinline muted autoplay></video>

        <div class="viewfinder" id="viewfinder" aria-hidden="true">
            <span class="corner tl"></span><span class="corner tr"></span>
            <span class="corner bl"></span><span class="corner br"></span>
            <span class="scan-line"></span>
        </div>
        <div class="focus-ring" id="focusRing" aria-hidden="true"></div>

        <!-- AR layer: detection box + information card (positioned by ar-overlay.js) -->
        <div class="ar-layer" id="arLayer" hidden>
            <div class="detection-box" id="detectionBox"></div>
            <div class="info-card" id="infoCard">
                <button type="button" class="info-toggle" id="infoToggle" aria-expanded="false" aria-controls="infoDetails">
                    <span class="info-title" id="infoTitle"></span>
                    <span class="info-hint" id="infoHint">Tap for details</span>
                </button>
                <dl class="info-details" id="infoDetails" hidden>
                    <div><dt>Manufacturer</dt><dd id="infoMaker"></dd></div>
                    <div><dt>Specification</dt><dd id="infoSpec"></dd></div>
                    <div><dt>Description</dt><dd id="infoDesc"></dd></div>
                    <div class="info-row">
                        <div><dt>Confidence</dt><dd id="infoConf"></dd></div>
                        <div><dt>AI provider</dt><dd id="infoProvider"></dd></div>
                    </div>
                    <div id="infoRefWrap" hidden><dt>Web reference</dt><dd><a id="infoRef" target="_blank" rel="noopener noreferrer"></a></dd></div>
                </dl>
            </div>
        </div>

        <!-- Camera problems (permission, HTTPS, no camera) -->
        <div class="camera-message" id="cameraMessage" hidden>
            <div class="glass-panel">
                <h2 id="cameraMessageTitle">Camera unavailable</h2>
                <p id="cameraMessageText"></p>
                <button type="button" class="btn-secondary" id="retryCamera">Try again</button>
            </div>
        </div>

        <!-- Scan progress: real stages, no fake percentage -->
        <div class="progress-panel glass-panel" id="progress" hidden>
            <h2>Identifying Object</h2>
            <div class="progress-bar" aria-hidden="true"><span></span></div>
            <ol class="progress-steps" id="progressSteps">
                <li data-step="capture">Capture image</li>
                <li data-step="ai">AI identification</li>
                <li data-step="lookup">Product lookup</li>
                <li data-step="display">Display result</li>
            </ol>
        </div>
    </section>

    <footer class="controls">
        <p class="status" id="status" role="status" aria-live="polite">Point camera at an object</p>
        <button type="button" class="btn-secondary" id="speakNameBtn" disabled>Speak item name</button>
        <button type="button" class="scan-btn" id="scanBtn" disabled>SCAN</button>
    </footer>

</main>

<script src="assets/js/camera.js"></script>
<script src="assets/js/voice.js"></script>
<script src="assets/js/ar-overlay.js"></script>
<script src="assets/js/scanner.js"></script>
</body>
</html>
