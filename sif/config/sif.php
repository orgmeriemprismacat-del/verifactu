<?php

return [
    'env' => getenv('SIF_ENV') ?: 'local',
    'db' => [
        'dsn' => getenv('SIF_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=sif_test;charset=utf8mb4',
        'user' => getenv('SIF_DB_USER') ?: 'sif_test',
        'password' => getenv('SIF_DB_PASSWORD') ?: '',
    ],
    'legacy_db' => [
        'dsn' => getenv('SIF_LEGACY_DB_DSN') ?: '',
        'user' => getenv('SIF_LEGACY_DB_USER') ?: '',
        'password' => getenv('SIF_LEGACY_DB_PASSWORD') ?: '',
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
    'aeat' => [
        'wsdl' => getenv('SIF_AEAT_WSDL') ?: '',
        'endpoint' => getenv('SIF_AEAT_ENDPOINT') ?: '',
        'xsd_path' => getenv('SIF_AEAT_XSD_PATH') ?: '',
        'certificate_path' => getenv('SIF_AEAT_CERT_PATH') ?: '',
        'certificate_password' => getenv('SIF_AEAT_CERT_PASSWORD') ?: '',
        'issuer_nif' => getenv('SIF_ISSUER_NIF') ?: '',
        'system_id' => getenv('SIF_AEAT_SYSTEM_ID') ?: '',
        'system_version' => getenv('SIF_AEAT_SYSTEM_VERSION') ?: '',
        'installation_id' => getenv('SIF_AEAT_INSTALLATION_ID') ?: '',
        'max_attempts' => (int) (getenv('SIF_AEAT_MAX_ATTEMPTS') ?: 3),
        'base_retry_seconds' => (int) (getenv('SIF_AEAT_BASE_RETRY_SECONDS') ?: 60),
        'max_retry_seconds' => (int) (getenv('SIF_AEAT_MAX_RETRY_SECONDS') ?: 3600),
    ],
];
