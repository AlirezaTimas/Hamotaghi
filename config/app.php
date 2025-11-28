<?php
/**
 * Application Configuration
 */

return [
    'name' => 'هم‌اتاقی',
    'version' => '2.0.0',
    'timezone' => 'Asia/Tehran',
    'locale' => 'fa',
    
    'database' => [
        'host' => 'localhost',
        'dbname' => 'hamotaghi_db',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ],

    'paths' => [
        'base' => __DIR__ . '/..',
        'uploads' => __DIR__ . '/../assets/uploads',
        'avatars' => __DIR__ . '/../assets/uploads/avatars',
        'houses' => __DIR__ . '/../assets/uploads/houses'
    ],

    'upload' => [
        'max_size' => 5 * 1024 * 1024, // 5MB
        'allowed_types' => ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif']
    ],

    'session' => [
        'lifetime' => 7200, // 2 hours
        'name' => 'hamotaghi_session'
    ],

    'pagination' => [
        'per_page' => 12
    ]
];

