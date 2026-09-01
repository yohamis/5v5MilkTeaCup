<?php

return [
    'name' => env('TOURNAMENT_NAME', '王者荣耀 5V5 奶茶杯'),
    'season' => env('TOURNAMENT_SEASON', '2026 夏季赛'),
    'admin_key' => env('TOURNAMENT_ADMIN_KEY'),
    'frontend_origins' => array_filter(explode(',', env('FRONTEND_ORIGINS', 'http://localhost:5173'))),
];
