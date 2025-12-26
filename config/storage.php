<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    |
    | 指定預設的儲存驅動程式。可選值：'supabase' 或 's3'
    | 在遷移期間可透過環境變數動態切換
    |
    */

    'default' => env('STORAGE_DRIVER', 'supabase'),

    /*
    |--------------------------------------------------------------------------
    | Storage Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | 各儲存驅動程式的詳細設定
    |
    */

    'drivers' => [
        's3' => [
            'bucket' => env('AWS_BUCKET'),
            'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            
            // 自訂資料夾路徑
            'folders' => [
                'image' => env('AWS_IMAGE_FOLDER', 'images'),
                'audio' => env('AWS_AUDIO_FOLDER', 'audio'),
                'webp' => env('AWS_WEBP_FOLDER', 'webp'),
            ],
        ],

        'supabase' => [
            'url' => env('SUPABASE_URL'),
            'storage_url' => env('SUPABASE_STORAGE_URL'),
            'key' => env('SUPABASE_SERVICE_ROLE_KEY'),
            'bucket' => env('SUPABASE_BUCKET'),
            
            // 自訂資料夾路徑
            'folders' => [
                'image' => env('SUPABASE_IMAGE_FOLDER', 'images'),
                'audio' => env('SUPABASE_AUDIO_FOLDER', 'audio'),
                'webp' => env('SUPABASE_WEBP_FOLDER', 'webp'),
            ],
        ],
    ],

];
