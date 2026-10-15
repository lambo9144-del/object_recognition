/**
 * history.js - delete one scan or clear the whole history.
 */
(function () {
    'use strict';

    const card = document.getElementById('historyCard');
    const message = document.getElementById('message');
    if (!card) {
        return;
    }
    const csrf = card.dataset.csrf;

    function showMessage(text) {
        message.innerHTML = '';
        const box = document.createElement('div');
        box.className = 'notice error';
        box.textContent = text;
        message.appendChild(box);
    }

    async function send(payload) {
        const response = await fetch('api/history.php', {
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
            window.location.href = 'settings.php';
            return null;
        }
        return data;
    }

    async function run(payload) {
        try {
            const result = await send(payload);
            if (result && result.ok) {
                window.location.reload();
            } else if (result) {
                showMessage(result.error || 'Something went wrong.');
            }
        } catch (error) {
            showMessage('Network error. Please try again.');
        }
    }

    card.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('.delete-btn');
        if (deleteButton) {
            if (window.confirm('Delete this scan?')) {
                run({ action: 'delete', id: Number(deleteButton.dataset.id) });
            }
            return;
        }
        if (event.target.closest('#clearBtn')) {
            if (window.confirm('Delete ALL scan history? This cannot be undone.')) {
                run({ action: 'clear' });
            }
        }
    });
})();
