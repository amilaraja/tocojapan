<?php

// JapaneseCarTrade.com (JCT) automatic stock import feed. JCT pulls the file
// once a day from the URL we give them; format per JCT's "Automatic Stock
// Data Import" spec (XML, 8 Jan 2013, and the JSON variant on their blog).
// Separate from the Partner Stock API: no partner records, keys or quotas.

return [
    // JCT issues the token; they call ?token=… (param name below). No token = feed answers 404.
    'token' => env('JCT_FEED_TOKEN'),
    'token_param' => env('JCT_FEED_TOKEN_PARAM', 'token'),

    // JCT membership cap: Premium 1500, Gold 500. Newest cars first when over.
    'max_vehicles' => (int) env('JCT_FEED_MAX_VEHICLES', 1500),

    // JCT accepts at most 10 photos per car, 1024 x 800 px, 512 KB each.
    'max_photos' => 10,
    'image_max_width' => 1024,
    'image_max_height' => 800,
    'image_max_bytes' => 512 * 1024,
    'image_cache_path' => 'private/jct-feed/images',

    // Generated feed kept for a few minutes: JCT pulls daily, staff previews should not rebuild 1500 rows each time.
    'cache_seconds' => 600,

    'location' => env('JCT_FEED_LOCATION', 'Japan'),
    'port' => env('JCT_FEED_PORT', 'Yokohama'),

    // The site masks chassis numbers; JCT is public too, so masked by default.
    'full_chassis' => (bool) env('JCT_FEED_FULL_CHASSIS', false),

    'timezone' => 'Asia/Tokyo',
];
