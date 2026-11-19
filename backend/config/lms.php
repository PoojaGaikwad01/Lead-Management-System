<?php

return [
    'timezone' => env('LMS_TIMEZONE', 'Asia/Kolkata'),

    'ai' => [
        'enabled' => env('AI_EXTRACTION_ENABLED', false),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],
];
