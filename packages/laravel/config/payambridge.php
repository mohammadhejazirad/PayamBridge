<?php

declare(strict_types=1);

return [
    'default' => 'bale',

    // Environment variable NAMES only. With config:cache, set these in the actual
    // process environment, or override CredentialResolver for your secret store.
    'connections' => [
        'bale' => ['provider' => 'bale', 'surface' => 'bot', 'tenant' => 'default', 'token_env' => 'PAYAMBRIDGE_BALE_TOKEN'],
        'rubika' => ['provider' => 'rubika', 'surface' => 'bot', 'tenant' => 'default', 'token_env' => 'PAYAMBRIDGE_RUBIKA_TOKEN'],
        'eitaa-app' => ['provider' => 'eitaa', 'surface' => 'app', 'tenant' => 'default', 'token_env' => 'PAYAMBRIDGE_EITAA_APP_TOKEN'],
    ],

    'timeouts' => ['connect' => 5.0, 'read' => 30.0, 'total' => 35.0],

    'inbox' => [
        'driver' => env('PAYAMBRIDGE_INBOX_DRIVER', 'sqlite'),
        'paths' => [
            'sqlite' => storage_path('app/private/payambridge/inbox.sqlite'),
            'file' => storage_path('app/private/payambridge/inbox.txt'),
        ],
        'lease_seconds' => 60.0,
    ],
];
