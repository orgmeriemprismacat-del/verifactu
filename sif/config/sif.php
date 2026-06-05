<?php

return [
    'env' => getenv('SIF_ENV') ?: 'local',
    'db' => [
        'dsn' => getenv('SIF_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=sif_test;charset=utf8mb4',
        'user' => getenv('SIF_DB_USER') ?: 'sif_test',
        'password' => getenv('SIF_DB_PASSWORD') ?: '',
    ],
    'issuer' => [
        'nif' => getenv('SIF_ISSUER_NIF') ?: 'G00000000',
        'name' => getenv('SIF_ISSUER_NAME') ?: 'Associacio PrisMa',
    ],
    'series' => [
        'invoice' => getenv('SIF_SERIES_INVOICE') ?: 'A',
        'rectification' => getenv('SIF_SERIES_RECTIFICATION') ?: 'R',
    ],
    'redsys' => [
        'merchant_key' => getenv('SIF_REDSYS_MERCHANT_KEY') ?: '',
    ],
];
