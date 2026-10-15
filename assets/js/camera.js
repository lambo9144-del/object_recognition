/**
 * camera.js - live camera preview and frame capture.
 * Exposes: window.CameraController
 */
(function () {
    'use strict';

    const CameraController = {
        video: null,
        stream: null,
        track: null,

        init(videoElement) {
            this.video = videoElement;
        },

        /**
         * Start the rear camera.
         * @returns {Promise<{ok: boolean, title?: string, message?: string}>}
         */
        async start() {
            if (!window.isSecureContext) {
                return {
                    ok: false,
                    title: 'Secure connection needed',
                    message: 'Please use HTTPS to access the camera.',
                };
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                return {
                    ok: false,
                    title: 'Camera unavailable',
                    message: 'This browser does not support camera access. Try Chrome, Edge or Safari.',
                };
            }

            this.stop();

            try {
                this.stream = await this.openStream({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
                    audio: false,
                });
            } catch (error) {
                // A strict constraint can fail on some devices: retry with plain video.
                if (error && (error.name === 'OverconstrainedError' || error.name === 'ConstraintNotSatisfiedError')) {
                    try {
                        this.stream = await this.openStream({ video: true, audio: false });
                    } catch (retryError) {
                        return this.describeError(retryError);
                    }
                } else {
                    return this.describeError(error);
                }
            }

            this.track = this.stream.getVideoTracks()[0] || null;
            this.video.srcObject = this.stream;
            try {
                await this.video.play();
            } catch (playError) {
                return { ok: false, title: 'Camera unavailable', message: 'The camera preview could not start.' };
            }
            return { ok: true };
        },

        openStream(constraints) {
            return navigator.mediaDevices.getUserMedia(constraints);
        },

        describeError(error) {
            const name = error && error.name ? error.name : '';
            if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
                return {
                    ok: false,
                    title: 'Camera permission needed',
                    message: 'Camera permission is required. Allow camera access in your browser settings, then try again.',
                };
            }
            if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
                return { ok: false, title: 'No camera found', message: 'Camera is unavailable on this device.' };
            }
            if (name === 'NotReadableError' || name === 'TrackStartError') {
                return {
                    ok: false,
                    title: 'Camera is busy',
                    message: 'Camera is unavailable. Close other apps that may be using it, then try again.',
                };
            }
            return { ok: false, title: 'Camera unavailable', message: 'Camera is unavailable right now.' };
        },

        stop() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
            }
            this.stream = null;
            this.track = null;
            if (this.video) {
                this.video.srcObject = null;
            }
        },

        freeze() {
            if (this.video) {
                this.video.pause();
            }
        },

        async unfreeze() {
            if (this.video) {
                try {
                    await this.video.play();
                } catch (e) {
                    /* ignore: the stream is still attached */
                }
            }
        },

        /** Tap-to-focus where the camera supports it. x and y are 0-1 within the preview. */
        async focusAt(x, y) {
            if (!this.track || typeof this.track.getCapabilities !== 'function') {
                return;
            }
            try {
                const caps = this.track.getCapabilities();
                const modes = caps.focusMode || [];
                if (!modes.includes('single-shot') && !modes.includes('continuous')) {
                    return;
                }
                const advanced = {};
                advanced.focusMode = modes.includes('single-shot') ? 'single-shot' : 'continuous';
                if (caps.pointsOfInterest !== undefined) {
                    advanced.pointsOfInterest = [{ x, y }];
                }
                await this.track.applyConstraints({ advanced: [advanced] });
            } catch (e) {
                /* focus is optional */
            }
        },

        /**
         * Capture exactly what the user sees. The preview uses object-fit: cover,
         * so the video is cropped; we crop the same area, then shrink it.
         * Because the crop equals the visible area, the AI's 0-1000 bounding box
         * maps straight onto the preview.
         *
         * @returns {{dataUrl: string, width: number, height: number}}
         */
        captureVisibleFrame(maxDimension = 1280, quality = 0.7) {
            const video = this.video;
            const videoWidth = video.videoWidth;
            const videoHeight = video.videoHeight;
            const boxWidth = video.clientWidth;
            const boxHeight = video.clientHeight;

            if (!videoWidth || !videoHeight || !boxWidth || !boxHeight) {
                throw new Error('Camera is not ready yet.');
            }

            // object-fit: cover scale, then the part of the video that is visible
            const scale = Math.max(boxWidth / videoWidth, boxHeight / videoHeight);
            const sourceWidth = boxWidth / scale;
            const sourceHeight = boxHeight / scale;
            const sourceX = (videoWidth - sourceWidth) / 2;
            const sourceY = (videoHeight - sourceHeight) / 2;

            const shrink = Math.min(1, maxDimension / Math.max(sourceWidth, sourceHeight));
            const width = Math.max(1, Math.round(sourceWidth * shrink));
            const height = Math.max(1, Math.round(sourceHeight * shrink));

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const context = canvas.getContext('2d');
            context.drawImage(video, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, width, height);

            return { dataUrl: canvas.toDataURL('image/jpeg', quality), width, height };
        },
    };

    window.CameraController = CameraController;
})();
