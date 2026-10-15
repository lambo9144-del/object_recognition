/**
 * voice.js - speaks the recognized item's name using the browser's built-in speech.
 */
(function () {
    'use strict';

    const VoiceController = {
        supported: !!(window.speechSynthesis && window.SpeechSynthesisUtterance),
        itemName: '',

        speakName(name) {
            this.itemName = typeof name === 'string' ? name.trim() : '';
            if (!this.supported || !this.itemName) {
                return false;
            }

            const synth = window.speechSynthesis;
            if (synth.speaking || synth.pending) {
                synth.cancel();
            }
            if (synth.paused) {
                synth.resume();
            }

            const utterance = new SpeechSynthesisUtterance(this.itemName);
            const voices = synth.getVoices();
            const voice = voices.find((item) => item.default && /^en(-|_)/i.test(item.lang))
                || voices.find((item) => /^en(-|_)/i.test(item.lang));
            if (voice) {
                utterance.voice = voice;
                utterance.lang = voice.lang;
            } else {
                utterance.lang = 'en-US';
            }
            utterance.rate = 0.92;
            utterance.pitch = 1;
            utterance.volume = 1;
            utterance.onerror = (event) => {
                if (event && (event.error === 'canceled' || event.error === 'interrupted')) {
                    return;
                }
                const status = document.getElementById('status');
                if (status) {
                    const reason = event && event.error === 'not-allowed'
                        ? 'Tap Speak item name to allow speech in this browser.'
                        : 'Could not play speech. Check the device volume and browser voice settings.';
                    status.textContent = reason;
                    status.classList.add('is-error');
                }
            };

            synth.speak(utterance);
            return true;
        },
    };

    window.VoiceController = VoiceController;
})();
