<?php
declare(strict_types=1);

return [
    'app_name' => 'Livestock Carcass Management System',
    'base_url' => '', // e.g. https://example.com ; leave blank for auto-detection
    'timezone' => 'Asia/Kathmandu',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'deadbody_map',
        'user' => 'deadbody_user',
        'pass' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'emergency' => [
        'police_control' => '100',
        'police_toll_free' => '16600141516',
    ],
    'security' => [
        'session_name' => 'DBMAPSESSID',
        'max_upload_mb' => 8,
        'max_photos' => 5,
        'public_coordinate_decimals' => 3,
        'setup_key' => 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET',
    ],
];
