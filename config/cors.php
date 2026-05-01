<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Đây là cấu hình CORS cho Laravel. Đặc biệt cần thiết cho route
    | /broadcasting/auth khi frontend và backend chạy trên domain khác nhau
    | (ví dụ: fluid.imkhoa.io.vn <-> note-app-1-t2o5.onrender.com).
    |
    */

    'paths' => [
        'broadcasting/auth',
        'api/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
