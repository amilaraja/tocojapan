<?php

// Only the keys that differ from Livewire's defaults; the rest comes from
// vendor/livewire/livewire/config/livewire.php (merged per top-level key).
return [
    // Admin uploads up to 128 MB (supplier stock CSVs ≈ 90 MB, walkaround
    // videos ≤ 100 MB). Matches PHP's upload_max_filesize in the vhost
    // phpIniOverride; Livewire's default cap is 12 MB.
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:131072'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 15,
        'cleanup' => true,
    ],
];
