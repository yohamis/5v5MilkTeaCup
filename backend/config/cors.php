<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(explode(',', env('FRONTEND_ORIGINS', 'http://localhost:5173'))),
    'allowed_origins_patterns' => ['#^https?://(localhost|127\\.0\\.0\\.1)(:\\d+)?$#'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
