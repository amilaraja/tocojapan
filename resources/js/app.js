import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.vehicleGallery = (photos) => ({
    photos,
    index: 0,
    lightbox: false,
    goTo(i) {
        const n = this.photos.length;
        this.index = ((i % n) + n) % n;
    },
    prev() {
        this.goTo(this.index - 1);
    },
    next() {
        this.goTo(this.index + 1);
    },
});

Alpine.start();
