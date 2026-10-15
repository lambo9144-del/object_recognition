/**
 * ar-overlay.js - draws the detection box and the information card on top of the preview.
 * Exposes: window.AROverlay
 *
 * The bounding box uses 0-1000 coordinates relative to the image that was captured.
 * That image is exactly the visible preview area, so percentages line up directly.
 */
(function () {
    'use strict';

    const MARGIN = 8;

    const AROverlay = {
        layer: null,
        box: null,
        card: null,
        toggle: null,
        details: null,
        hint: null,
        current: null,

        init() {
            this.layer = document.getElementById('arLayer');
            this.box = document.getElementById('detectionBox');
            this.card = document.getElementById('infoCard');
            this.toggle = document.getElementById('infoToggle');
            this.details = document.getElementById('infoDetails');
            this.hint = document.getElementById('infoHint');

            this.toggle.addEventListener('click', () => this.setExpanded(this.toggle.getAttribute('aria-expanded') !== 'true'));
            window.addEventListener('resize', () => this.position());
            window.addEventListener('orientationchange', () => setTimeout(() => this.position(), 250));
        },

        /**
         * @param {object} result  validated result from api/recognize.php
         * @param {{title: string, url: string}|null} reference optional web reference
         */
        show(result, reference) {
            this.current = result;

            // Everything is written with textContent, so AI text can never inject HTML.
            document.getElementById('infoTitle').textContent = result.productName;
            document.getElementById('infoMaker').textContent = result.manufacturer;
            document.getElementById('infoSpec').textContent = result.specification;
            document.getElementById('infoDesc').textContent = result.description;
            document.getElementById('infoConf').textContent = Math.round(result.confidence * 100) + '%';
            document.getElementById('infoProvider').textContent = this.providerLabel(result.provider);

            const refWrap = document.getElementById('infoRefWrap');
            const refLink = document.getElementById('infoRef');
            if (reference && typeof reference.url === 'string' && reference.url.startsWith('https://en.wikipedia.org/wiki/')) {
                refLink.href = reference.url;
                refLink.textContent = reference.title;
                refWrap.hidden = false;
            } else {
                refWrap.hidden = true;
            }

            this.setExpanded(false, false);
            this.layer.hidden = false;
            this.position();
        },

        clear() {
            this.current = null;
            this.layer.hidden = true;
            this.setExpanded(false, false);
        },

        setExpanded(expanded, reposition = true) {
            this.toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            this.details.hidden = !expanded;
            if (reposition) {
                this.position();
            }
        },

        providerLabel(id) {
            if (id === 'gemini') return 'Gemini';
            if (id === 'agnes') return 'Agnes';
            return 'AI';
        },

        /** Place the box from the bounding box, then put the card next to it. */
        position() {
            if (!this.current || this.layer.hidden) {
                return;
            }
            const stageWidth = this.layer.clientWidth;
            const stageHeight = this.layer.clientHeight;
            const bbox = this.current.boundingBox;

            let boxTop = 0;
            let boxBottom = 0;
            let boxLeft = 0;

            if (bbox) {
                const left = (bbox.xmin / 1000) * stageWidth;
                const top = (bbox.ymin / 1000) * stageHeight;
                const width = ((bbox.xmax - bbox.xmin) / 1000) * stageWidth;
                const height = ((bbox.ymax - bbox.ymin) / 1000) * stageHeight;
                this.box.hidden = false;
                this.box.style.left = left + 'px';
                this.box.style.top = top + 'px';
                this.box.style.width = width + 'px';
                this.box.style.height = height + 'px';
                boxTop = top;
                boxBottom = top + height;
                boxLeft = left;
            } else {
                // The AI named the object but gave no usable box: show the card only.
                this.box.hidden = true;
                boxTop = stageHeight * 0.35;
                boxBottom = stageHeight * 0.35;
                boxLeft = (stageWidth - this.card.offsetWidth) / 2;
            }

            const cardWidth = this.card.offsetWidth;
            const cardHeight = this.card.offsetHeight;

            let cardTop = boxBottom + MARGIN;                       // preferred: below the box
            if (cardTop + cardHeight > stageHeight - MARGIN) {
                cardTop = boxTop - cardHeight - MARGIN;             // otherwise above it
            }
            if (cardTop < MARGIN + 56) {                            // keep clear of the header
                cardTop = Math.max(MARGIN + 56, stageHeight - cardHeight - MARGIN);
            }

            let cardLeft = boxLeft;
            cardLeft = Math.min(cardLeft, stageWidth - cardWidth - MARGIN);
            cardLeft = Math.max(MARGIN, cardLeft);

            this.card.style.top = cardTop + 'px';
            this.card.style.left = cardLeft + 'px';
        },
    };

    window.AROverlay = AROverlay;
})();
