<?php

return [

    'paths' => ['api/*', 'login', 'logout', 'me', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:3000'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // true bila menggunakan Sanctum SPA (cookie). Untuk token Bearer murni boleh false.
    'supports_credentials' => true,

];
