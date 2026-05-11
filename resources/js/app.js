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
    },
    setZoom(next, originX = null, originY = null) {
        const clamped = Math.min(this.maxZoom, Math.max(this.minZoom, next));
        if (clamped === this.zoom) return;
        const ratio = clamped / this.zoom;
        // Anchor zoom at the cursor: shift pan so the image point under the
        // cursor stays put. Uses the current (post-transform) bounding rect.
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
    startDrag(e) {
        if (this.zoom <= 1) return;
        this.dragging = true;
        this.dragStartX = e.clientX;
        this.dragStartY = e.clientY;
        this.dragOriginX = this.panX;
        this.dragOriginY = this.panY;
    },
    onDrag(e) {
        if (!this.dragging) return;
        this.panX = this.dragOriginX + (e.clientX - this.dragStartX);
        this.panY = this.dragOriginY + (e.clientY - this.dragStartY);
    },
    endDrag() {
        this.dragging = false;
    },
    get transform() {
        return `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`;
    },
});

Alpine.start();
