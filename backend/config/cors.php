<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Aquí le decimos que permita peticiones desde nuestro Angular local
    'allowed_origins' => ['http://localhost:4200', 'http://127.0.0.1:4200'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // IMPORTANTE: Si usamos tokens JWT en cookies o sesiones, esto debe ser true
    'supports_credentials' => true,
];