<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Drivers Configuration
    |--------------------------------------------------------------------------
    |
    | AWS S3 儲存驅動設定
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

            'folders' => [
                'image' => env('AWS_IMAGE_FOLDER', 'images'),
                'audio' => env('AWS_AUDIO_FOLDER', 'audio'),
                'webp' => env('AWS_WEBP_FOLDER', 'webp'),
                // 「大家怎麼說」的回答錄音：正式資料夾，以及還沒送出的暫存資料夾（由 S3 生命週期規則自動過期）
                'response_audio' => env('AWS_RESPONSE_AUDIO_FOLDER', 'response-audio'),
                'response_audio_pending' => env('AWS_RESPONSE_AUDIO_PENDING_FOLDER', 'audio-pending'),
            ],
        ],
    ],

];
