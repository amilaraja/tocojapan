<?php

/**
 * Canonical vehicle options schema.
 *
 * Used by:
 *  - the admin VehicleForm to render grouped toggle fields,
 *  - the migrate:wp-features command to pick the matching wp postmeta keys,
 *  - the vehicle detail page to render the Vehicle Options block.
 *
 * Each entry: ['key' => 'wp_postmeta_suffix', 'label' => 'Display label'].
 * The wp key looked up is "{group}_{key}" — matches the toco WP plugin.
 */
return [
    'comfort' => [
        'label' => 'Comfort',
        'options' => [
            ['key' => 'power_steering', 'label' => 'Power Steering'],
            ['key' => 'power_window', 'label' => 'Power Window'],
            ['key' => 'power_door_locks', 'label' => 'Power Door Locks'],
            ['key' => 'power_mirror', 'label' => 'Power Mirror'],
            ['key' => 'power_seat', 'label' => 'Power Seat'],
            ['key' => 'keyless_entry', 'label' => 'Keyless Entry'],
            ['key' => 'smart_key', 'label' => 'Smart Key'],
            ['key' => 'cruise_control', 'label' => 'Cruise Control'],
            ['key' => 'auto_air_conditioner', 'label' => 'Auto Air Conditioner'],
            ['key' => 'air_conditioner', 'label' => 'Air Conditioner'],
            ['key' => 'central_locking', 'label' => 'Central Locking'],
            ['key' => 'navigation', 'label' => 'Navigation'],
        ],
    ],
    'safety' => [
        'label' => 'Safety',
        'options' => [
            ['key' => 'abs', 'label' => 'ABS'],
            ['key' => 'driver_airbag', 'label' => 'Driver Airbag'],
            ['key' => 'passenger_airbag', 'label' => 'Passenger Airbag'],
            ['key' => 'side_airbag', 'label' => 'Side Airbag'],
            ['key' => 'alarm', 'label' => 'Alarm'],
            ['key' => 'central_locking', 'label' => 'Central Locking'],
            ['key' => 'back_camera', 'label' => 'Back Camera'],
            ['key' => 'side_camera', 'label' => 'Side Camera'],
            ['key' => 'bumper_guard', 'label' => 'Bumper Guard'],
            ['key' => 'fog_lamp', 'label' => 'Fog Lamp'],
            ['key' => 'gear_locks', 'label' => 'Gear Locks'],
        ],
    ],
    'sound_system' => [
        'label' => 'Sound System',
        'options' => [
            ['key' => 'cd_player', 'label' => 'CD Player'],
            ['key' => 'dvd', 'label' => 'DVD'],
            ['key' => 'mp3_player', 'label' => 'MP3 Player'],
            ['key' => 'radio', 'label' => 'Radio'],
            ['key' => 'satellite_radio', 'label' => 'Satellite Radio'],
            ['key' => 'equalizer', 'label' => 'Equalizer'],
            ['key' => 'stereo', 'label' => 'Stereo'],
        ],
    ],
    'seats' => [
        'label' => 'Seats',
        'options' => [
            ['key' => 'leather_seat', 'label' => 'Leather Seats'],
            ['key' => 'heated_seats', 'label' => 'Heated Seats'],
            ['key' => 'seat_cover', 'label' => 'Seat Cover'],
            ['key' => 'child_seats', 'label' => 'Child Seats'],
        ],
    ],
    'windows' => [
        'label' => 'Windows',
        'options' => [
            ['key' => 'power_window', 'label' => 'Power Window'],
            ['key' => 'tinted_glasses', 'label' => 'Tinted Glasses'],
            ['key' => 'rear_window_defroster', 'label' => 'Rear Window Defroster'],
            ['key' => 'rear_window_wiper', 'label' => 'Rear Window Wiper'],
        ],
    ],
    'other' => [
        'label' => 'Other',
        'options' => [
            ['key' => 'tv', 'label' => 'TV'],
            ['key' => 'sun_roof', 'label' => 'Sun Roof'],
            ['key' => 'wood_panel', 'label' => 'Wood Panel'],
            ['key' => 'spare_tire', 'label' => 'Spare Tire'],
        ],
    ],
];
