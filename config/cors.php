<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — المدوّنة
    |--------------------------------------------------------------------------
    | Autorise Next.js (localhost:3000) et l'app mobile à interroger l'API.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',    // Next.js dev
        'http://localhost:3001',    // Next.js dev (alternate port)
        'http://localhost:3002',    // Next.js dev (fallback port)
        'http://localhost:8081',    // Expo dev
        'https://almudawwana.ma',   // Production future
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
