/**
 * scanner.js - main controller for index.php
 * Flow: camera -> capture -> api/recognize.php -> api/lookup.php -> AR overlay
 */
(function () {
    'use strict';

    // Relative URLs keep the app working in any sub-folder (public_html/ai-ar/, htdocs/xyz/ ...)
    const API_RECOGNIZE = 'api/recognize.php';
    const API_LOOKUP = 'api/lookup.php';
    const RECOGNIZE_TIMEOUT_MS = 180000;
    const LOOKUP_TIMEOUT_MS = 10000;

    const els = {
        stage: document.getElementById('stage'),
        video: document.getElementById('video'),
        viewfinder: document.getElementById('viewfinder'),
        focusRing: document.getElementById('focusRing'),
        status: document.getElementById('status'),
        scanBtn: document.getElementById('scanBtn'),
        progress: document.getElementById('progress'),
        progressSteps: document.querySelectorAll('#progressSteps li'),
        cameraMessage: document.getElementById('cameraMessage'),
        cameraMessageTitle: document.getElementById('cameraMessageTitle'),
        cameraMessageText: document.getElementById('cameraMessageText'),
        retryCamera: document.getElementById('retryCamera'),
    };

    // 'starting' | 'ready' | 'busy' | 'result' | 'no-camera'
    let state = 'starting';

    CameraController.init(els.video);
    AROverlay.init();

    /* ---------- small UI helpers ---------- */

    function setStatus(text, kind) {
        els.status.textContent = text;
        els.status.classList.toggle('is-error', kind === 'error');
        els.status.classList.toggle('is-success', kind === 'success');
    }

    function setButton(label, disabled) {
        els.scanBtn.textContent = label;
        els.scanBtn.disabled = disabled;
    }

    function setStep(name) {
        const order = ['capture', 'ai', 'lookup', 'display'];
        const activeIndex = order.indexOf(name);
        els.progressSteps.forEach((item) => {
            const index = order.indexOf(item.dataset.step);
            item.classList.toggle('done', index < activeIndex);
            item.classList.toggle('active', index === activeIndex);
        });
    }

    function showProgress(show) {
        els.progress.hidden = !show;
        els.viewfinder.classList.toggle('is-scanning', show);
        els.stage.classList.toggle('is-scanning', show);
    }

    /* ---------- camera ---------- */

    async function startCamera() {
        state = 'starting';
        setButton('SCAN', true);
        setStatus('Starting camera...');
        els.cameraMessage.hidden = true;

        const outcome = await CameraController.start();
        if (!outcome.ok) {
            state = 'no-camera';
            els.cameraMessageTitle.textContent = outcome.title;
            els.cameraMessageText.textContent = outcome.message;
            els.cameraMessage.hidden = false;
            setStatus(outcome.message, 'error');
            return;
        }
        state = 'ready';
        setButton('SCAN', false);
        setStatus('Point camera at an object');
    }

    /* ---------- network ---------- */

    async function postJson(url, body, timeoutMs) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                signal: controller.signal,
                credentials: 'same-origin',
            });
            let payload = null;
            try {
                payload = await response.json();
            } catch (parseError) {
                payload = null;
            }
            return { status: response.status, payload };
        } finally {
            clearTimeout(timer);
        }
    }

    function describeFailure(error, response) {
        if (error) {
            if (error.name === 'AbortError') {
                return 'The request took too long. Please try again.';
            }
            return 'Network error. Check your connection and try again.';
        }
        if (response && response.payload && typeof response.payload.error === 'string') {
            return response.payload.error;
        }
        return 'Server error. Please try again.';
    }

    /* ---------- the scan ---------- */

    async function runScan() {
        state = 'busy';
        setButton('SCANNING...', true);
        AROverlay.clear();
        showProgress(true);

        // 1. capture
        setStep('capture');
        setStatus('Capturing image...');
        let image;
        try {
            image = CameraController.captureVisibleFrame(1280, 0.7);
        } catch (captureError) {
            return failScan('Could not capture the camera image. Please try again.');
        }
        CameraController.freeze(); // keep the picture still so the AR box stays aligned

        // 2. AI recognition
        setStep('ai');
        setStatus('AI identifying object...');
        let recognizeResponse;
        try {
            recognizeResponse = await postJson(API_RECOGNIZE, { image: image.dataUrl }, RECOGNIZE_TIMEOUT_MS);
        } catch (networkError) {
            return failScan(describeFailure(networkError, null));
        }
        if (!recognizeResponse.payload || recognizeResponse.payload.ok !== true || !recognizeResponse.payload.data) {
            return failScan(describeFailure(null, recognizeResponse));
        }
        const result = recognizeResponse.payload.data;

        // 3. optional web lookup (never fatal)
        let reference = null;
        setStep('lookup');
        if (result.manufacturer && result.manufacturer !== 'Unknown') {
            setStatus('Checking product information...');
            try {
                const lookup = await postJson(
                    API_LOOKUP,
                    { productName: result.productName, manufacturer: result.manufacturer },
                    LOOKUP_TIMEOUT_MS
                );
                if (lookup.payload && lookup.payload.ok === true && lookup.payload.data) {
                    reference = lookup.payload.data.reference || null;
                }
            } catch (lookupError) {
                reference = null;
            }
        }

        // 4. display
        setStep('display');
        AROverlay.show(result, reference);
        showProgress(false);
        state = 'result';
        setStatus('Object identified', 'success');
        setButton('SCAN AGAIN', false);
    }

    async function failScan(message) {
        showProgress(false);
        await CameraController.unfreeze();
        state = 'ready';
        setStatus(message, 'error');
        setButton('SCAN', false);
    }

    async function scanAgain() {
        AROverlay.clear();
        await CameraController.unfreeze();
        state = 'ready';
        setStatus('Point camera at an object');
        setButton('SCAN', false);
    }

    /* ---------- events ---------- */

    els.scanBtn.addEventListener('click', () => {
        if (state === 'ready') {
            runScan();
        } else if (state === 'result') {
            scanAgain();
        }
    });

    els.retryCamera.addEventListener('click', startCamera);

    // Tap to focus (only while the live preview is running)
    els.stage.addEventListener('click', (event) => {
        if (state !== 'ready' || event.target.closest('.info-card, a, button')) {
            return;
        }
        const rect = els.stage.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;
        els.focusRing.style.left = event.clientX - rect.left + 'px';
        els.focusRing.style.top = event.clientY - rect.top + 'px';
        els.focusRing.classList.remove('show');
        void els.focusRing.offsetWidth; // restart the CSS animation
        els.focusRing.classList.add('show');
        CameraController.focusAt(x, y);
    });

    // Release the camera when leaving the page or hiding the tab
    window.addEventListener('pagehide', () => CameraController.stop());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && (state === 'ready' || state === 'starting') && !CameraController.stream) {
            startCamera();
        }
    });

    startCamera();
})();
