<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'chess_com' => [
        'base_url' => env('CHESS_COM_API_BASE_URL', 'https://api.chess.com/pub'),
        'timeout' => (int) env('CHESS_COM_API_TIMEOUT', 15),
        'connect_timeout' => (int) env('CHESS_COM_API_CONNECT_TIMEOUT', 5),
        'retry_times' => (int) env('CHESS_COM_API_RETRY_TIMES', 2),
        'retry_sleep_milliseconds' => (int) env('CHESS_COM_API_RETRY_SLEEP_MS', 500),
        'request_delay_microseconds' => (int) env('CHESS_COM_API_REQUEST_DELAY_US', 200_000),
    ],

    'stockfish' => [
        'path' => env('STOCKFISH_PATH', 'C:\\Program Files\\stockfish\\stockfish-windows-x86-64-avx2.exe'),
        'depth' => (int) env('STOCKFISH_DEPTH', 18),
        'movetime_ms' => (int) env('STOCKFISH_MOVETIME_MS', 500),
        'timeout_seconds' => (int) env('STOCKFISH_TIMEOUT_SECONDS', 30),
    ],

];
