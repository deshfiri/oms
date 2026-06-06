<?php

return [
    'steadfast' => [
        'api_key' => env('STEADFAST_API_KEY'),
        'secret'  => env('STEADFAST_SECRET'),
    ],
    'pathao' => [
        'base_url'      => env('PATHAO_BASE_URL', 'https://api-hermes.pathao.com'),
        'client_id'     => env('PATHAO_CLIENT_ID'),
        'client_secret' => env('PATHAO_CLIENT_SECRET'),
        'store_id'      => env('PATHAO_STORE_ID'),
    ],
];
