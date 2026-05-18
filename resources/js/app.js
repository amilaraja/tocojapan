import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.vehicleGallery = (photos) => ({
    photos,
    index: 0,
    lightbox: false,
    zoom: 1,
    panX: 0,
    panY: 0,
    dragging: false,
    dragStartX: 0,
    dragStartY: 0,
    dragOriginX: 0,
    dragOriginY: 0,
    minZoom: 1,
    maxZoom: 5,
    // Pointer/pinch tracking. activePointerCount is reactive so style
    // bindings (e.g. disabling transitions during pinch) update; the Map
    // itself is mutated without reactivity.
    pointers: new Map(),
    activePointerCount: 0,
    pinchStartDist: 0,
    pinchStartZoom: 1,
    // Horizontal swipe → prev/next (hero image + lightbox-at-zoom-1).
    swipeStartX: 0,
    swipeStartY: 0,
    swipeTracking: false,
    swipeJustFired: false,
    // Hero filmstrip live drag — translates the strip with the finger.
    heroDragging: false,
    heroDragOffsetPx: 0,
    goTo(i) {
        const n = this.photos.length;
        this.index = ((i % n) + n) % n;
        this.resetZoom();
    },
    prev() {
        this.goTo(this.index - 1);
    },
    next() {
        this.goTo(this.index + 1);
    },
    openLightbox(i = null) {
        if (i !== null) this.index = i;
        this.resetZoom();
        this.lightbox = true;
    },
    closeLightbox() {
        this.lightbox = false;
        this.resetZoom();
    },
    resetZoom() {
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
        this.dragging = false;
        this.pointers.clear();
        this.activePointerCount = 0;
        this.pinchStartDist = 0;
    },
    setZoom(next, originX = null, originY = null) {
        const clamped = Math.min(this.maxZoom, Math.max(this.minZoom, next));
        if (clamped === this.zoom) return;
        const ratio = clamped / this.zoom;
        // Anchor zoom at the cursor / pinch midpoint: shift pan so that
        // image point stays put. Uses the current (post-transform) rect.
        if (originX !== null && originY !== null && this.$refs && this.$refs.zoomImage) {
            const rect = this.$refs.zoomImage.getBoundingClientRect();
            const u = (originX - rect.left) / rect.width;
            const v = (originY - rect.top) / rect.height;
            this.panX += (originX - u * rect.width * ratio) - rect.left;
            this.panY += (originY - v * rect.height * ratio) - rect.top;
        }
        this.zoom = clamped;
        if (clamped === 1) {
            this.panX = 0;
            this.panY = 0;
        }
    },
    zoomIn() {
        this.setZoom(this.zoom * 1.4);
    },
    zoomOut() {
        this.setZoom(this.zoom / 1.4);
    },
    toggleZoom(e) {
        if (this.zoom > 1) {
            this.resetZoom();
        } else {
            this.setZoom(2.5, e.clientX, e.clientY);
        }
    },
    onWheel(e) {
        e.preventDefault();
        const delta = e.deltaY < 0 ? 1.2 : 1 / 1.2;
        this.setZoom(this.zoom * delta, e.clientX, e.clientY);
    },
    // ---- Hero swipe (mouse drag on desktop, touch on mobile) ----
    heroSwipeStart(e) {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        if (this.photos.length < 2) return;
        this.swipeStartX = e.clientX;
        this.swipeStartY = e.clientY;
        this.swipeTracking = true;
        this.swipeJustFired = false;
        this.heroDragging = false;
        this.heroDragOffsetPx = 0;
        if (e.target.setPointerCapture) {
            try { e.target.setPointerCapture(e.pointerId); } catch (_) {}
        }
    },
    heroSwipeMove(e) {
        if (!this.swipeTracking) return;
        const dx = e.clientX - this.swipeStartX;
        const dy = e.clientY - this.swipeStartY;
        // Lock in horizontal intent once the gesture crosses a small threshold;
        // before that, let the browser keep the gesture for native vertical scroll.
        if (!this.heroDragging && Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy)) {
            this.heroDragging = true;
        }
        if (this.heroDragging) {
            this.heroDragOffsetPx = dx;
        }
    },
    heroSwipeEnd(e) {
        if (!this.swipeTracking) return;
        this.swipeTracking = false;
        const dx = this.heroDragging ? this.heroDragOffsetPx : (e.clientX - this.swipeStartX);
        const dy = e.clientY - this.swipeStartY;
        this.heroDragging = false;
        this.heroDragOffsetPx = 0;
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.3) {
            if (dx < 0) this.next();
            else this.prev();
            this.swipeJustFired = true;
            // Suppress the click that would otherwise open the lightbox
            // when the user's pointer-up lands on the same image.
            setTimeout(() => { this.swipeJustFired = false; }, 300);
        }
    },
    maybeOpenLightbox() {
        if (this.swipeJustFired) return;
        this.openLightbox();
    },
    // ---- Pointer handlers (mouse + touch + pen via Pointer Events) ----
    pinchDistance() {
        const pts = Array.from(this.pointers.values());
        if (pts.length < 2) return 0;
        const dx = pts[0].x - pts[1].x;
        const dy = pts[0].y - pts[1].y;
        return Math.hypot(dx, dy);
    },
    pinchMidpoint() {
        const pts = Array.from(this.pointers.values());
        return { x: (pts[0].x + pts[1].x) / 2, y: (pts[0].y + pts[1].y) / 2 };
    },
    startDrag(e) {
        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        this.activePointerCount = this.pointers.size;

        if (this.activePointerCount === 2) {
            // Pinch begins — capture baseline and stop any single-finger drag.
            this.dragging = false;
            this.swipeTracking = false;
            this.pinchStartDist = this.pinchDistance();
            this.pinchStartZoom = this.zoom;
        } else if (this.activePointerCount === 1 && this.zoom > 1) {
            // Single-finger / mouse drag only meaningful when zoomed in.
            this.dragging = true;
            this.dragStartX = e.clientX;
            this.dragStartY = e.clientY;
            this.dragOriginX = this.panX;
            this.dragOriginY = this.panY;
        } else if (this.activePointerCount === 1 && this.zoom === 1 && this.photos.length > 1) {
            // Not zoomed → drag becomes a swipe between photos.
            this.swipeStartX = e.clientX;
            this.swipeStartY = e.clientY;
            this.swipeTracking = true;
        }
    },
    onDrag(e) {
        if (this.pointers.has(e.pointerId)) {
            this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        }

        if (this.activePointerCount >= 2 && this.pinchStartDist > 0) {
            // Pinch zoom — scale by the ratio of current to starting distance,
            // anchored at the midpoint between the two fingers.
            const dist = this.pinchDistance();
            const mid = this.pinchMidpoint();
            const target = this.pinchStartZoom * (dist / this.pinchStartDist);
            this.setZoom(target, mid.x, mid.y);
        } else if (this.dragging && this.activePointerCount === 1) {
            this.panX = this.dragOriginX + (e.clientX - this.dragStartX);
            this.panY = this.dragOriginY + (e.clientY - this.dragStartY);
        }
    },
    endDrag(e) {
        if (e && this.pointers.has(e.pointerId)) {
            this.pointers.delete(e.pointerId);
        } else if (!e) {
            this.pointers.clear();
        }
        this.activePointerCount = this.pointers.size;

        if (this.activePointerCount < 2) {
            this.pinchStartDist = 0;
        }

        // Lightbox swipe completion — only triggers when zoom=1 and pointer up.
        if (this.activePointerCount === 0 && this.swipeTracking && e) {
            this.swipeTracking = false;
            const dx = e.clientX - this.swipeStartX;
            const dy = e.clientY - this.swipeStartY;
            if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.3) {
                if (dx < 0) this.next();
                else this.prev();
            }
        }

        if (this.activePointerCount === 0) {
            this.dragging = false;
        } else if (this.activePointerCount === 1 && this.zoom > 1) {
            // One finger lifted during a pinch → switch to pan with the
            // remaining finger from its current spot.
            const remaining = Array.from(this.pointers.values())[0];
            this.dragging = true;
            this.dragStartX = remaining.x;
            this.dragStartY = remaining.y;
            this.dragOriginX = this.panX;
            this.dragOriginY = this.panY;
        } else {
            this.dragging = false;
        }
    },
    get transform() {
        return `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`;
    },
});

Alpine.start();
