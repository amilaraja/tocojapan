<?php

/*
 * Local Filament config overrides — Filament's service provider mergeConfigFrom's
 * the vendor default at vendor/filament/support/config/filament.php under it, so
 * we only need to specify the keys we want to override here.
 */

return [

    /*
     * Filament's FileUpload (including SpatieMediaLibraryFileUpload) defaults to
     * env('FILESYSTEM_DISK', 'local'). On Laravel 11+ the `local` disk maps to
     * storage/app/private — which is NOT covered by `public/storage` symlink, so
     * uploads silently 404 on the public site.
     *
     * Pin Filament uploads to the `public` disk by default. Individual fields can
     * still override per-call with ->disk('something-else') when needed.
     */
    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),

];
