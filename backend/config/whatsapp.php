<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'mock'),

    'chatwoot' => [
        'base_url' => env('CHATWOOT_BASE_URL'),
        'account_id' => env('CHATWOOT_ACCOUNT_ID'),
        'inbox_id' => env('CHATWOOT_INBOX_ID'),
        'api_token' => env('CHATWOOT_API_TOKEN'),
        'webhook_token' => env('CHATWOOT_WEBHOOK_TOKEN'),
    ],

    'periskope' => [
        'api_key' => env('PERISKOPE_API_KEY'),
        'phone' => env('PERISKOPE_PHONE'),
        'webhook_token' => env('PERISKOPE_WEBHOOK_TOKEN'),
    ],
];
