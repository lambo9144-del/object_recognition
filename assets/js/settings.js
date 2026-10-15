/**
 * settings.js - saves settings, tests connections and draws the QR code.
 */
(function () {
    'use strict';

    const form = document.getElementById('settingsForm');
    const message = document.getElementById('message');
    const saveBtn = document.getElementById('saveBtn');
    const testBtn = document.getElementById('testBtn');
    const testResults = document.getElementById('testResults');
    const csrf = form.dataset.csrf;

    function showMessage(text, kind) {
        message.innerHTML = '';
        if (!text) {
            return;
        }
        const box = document.createElement('div');
        box.className = 'notice ' + kind;
        box.textContent = text;
        message.appendChild(box);
        box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    async function callApi(payload) {
        const response = await fetch('api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf }, payload)),
            credentials: 'same-origin',
        });
        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            data = null;
        }
        if (response.status === 401) {
            window.location.reload();
            return { ok: false, error: 'Please sign in again.' };
        }
        return data || { ok: false, error: 'Server error. Please try again.' };
    }

    /* ---- Show / Hide on key fields ---- */
    document.querySelectorAll('[data-toggle-key]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.toggleKey);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-label', (show ? 'Hide ' : 'Show ') + (button.dataset.toggleKey.startsWith('gemini') ? 'Gemini' : 'Agnes') + ' API key');
        });
    });

    function updateKeyStatus(id, saved) {
        const el = document.getElementById(id);
        el.textContent = saved ? 'Key saved' : 'Not configured';
        el.className = 'key-status ' + (saved ? 'saved' : 'missing');
    }

    /* ---- Save ---- */
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showMessage('', '');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const result = await callApi({
                action: 'save',
                provider: form.provider.value,
                timeout: form.timeout.value,
                web_lookup: form.web_lookup.checked,
                gemini_model: form.gemini_model.value,
                gemini_fallback_models: form.gemini_fallback_models.value,
                gemini_api_key: form.gemini_api_key.value,
                clear_gemini_key: form.clear_gemini_key.checked,
                agnes_base_url: form.agnes_base_url.value,
                agnes_model: form.agnes_model.value,
                agnes_fallback_models: form.agnes_fallback_models.value,
                agnes_api_key: form.agnes_api_key.value,
                clear_agnes_key: form.clear_agnes_key.checked,
            });

            if (result.ok) {
                // Never keep a typed key in the page after saving.
                form.gemini_api_key.value = '';
                form.agnes_api_key.value = '';
                form.clear_gemini_key.checked = false;
                form.clear_agnes_key.checked = false;
                form.gemini_api_key.type = 'password';
                form.agnes_api_key.type = 'password';
                document.querySelectorAll('[data-toggle-key]').forEach((b) => { b.textContent = 'Show'; });
                updateKeyStatus('geminiKeyStatus', result.data.gemini_key_saved);
                updateKeyStatus('agnesKeyStatus', result.data.agnes_key_saved);
                form.gemini_api_key.placeholder = result.data.gemini_key_saved ? 'Leave blank to keep the saved key' : 'Paste your key';
                form.agnes_api_key.placeholder = result.data.agnes_key_saved ? 'Leave blank to keep the saved key' : 'Paste your key';
                form.gemini_fallback_models.value = result.data.gemini_fallback_models;
                form.agnes_fallback_models.value = result.data.agnes_fallback_models;
                showMessage('Settings saved.', 'ok');
            } else {
                showMessage(result.error || 'Could not save settings.', 'error');
            }
        } catch (error) {
            showMessage('Network error. Please try again.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save settings';
        }
    });

    /* ---- Test connection (tests the SAVED settings) ---- */
    testBtn.addEventListener('click', async () => {
        testBtn.disabled = true;
        testBtn.textContent = 'Testing...';
        testResults.innerHTML = '';

        try {
            const result = await callApi({ action: 'test' });
            if (!result.ok) {
                showMessage(result.error || 'Test failed.', 'error');
                return;
            }
            const names = { gemini: 'Gemini', agnes: 'Agnes' };
            Object.keys(names).forEach((id) => {
                const outcome = result.data[id];
                const item = document.createElement('div');
                item.className = 'test-item ' + (outcome.ok ? 'ok' : 'fail');
                const title = document.createElement('strong');
                title.textContent = names[id];
                const text = document.createElement('span');
                text.textContent = (outcome.ok ? '✓ ' : '✗ ') + outcome.message;
                item.append(title, text);
                testResults.appendChild(item);
            });
        } catch (error) {
            showMessage('Network error. Please try again.', 'error');
        } finally {
            testBtn.disabled = false;
            testBtn.textContent = 'Test Connection';
        }
    });

    /* ---- QR code for the scanner page (uses the current address, so it works in sub-folders) ---- */
    const scannerUrl = new URL('index.php', window.location.href).href;
    document.getElementById('qrUrl').textContent = scannerUrl;
    const host = window.location.hostname;
    const isLocal = host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
    if (window.location.protocol !== 'https:' && !isLocal) {
        document.getElementById('qrWarning').hidden = false;
    }
    if (typeof QRCode === 'function') {
        new QRCode(document.getElementById('qrCode'), {
            text: scannerUrl,
            width: 176,
            height: 176,
            correctLevel: QRCode.CorrectLevel.M,
        });
    } else {
        document.getElementById('qrCode').textContent = 'QR code library could not load. Use the link below.';
    }
})();
