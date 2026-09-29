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
    'invoice_query' => [
        'full_read_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('SIF_INVOICE_FULL_READ_ROLES') ?: '')
        ))),
        'minimal_read_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('SIF_INVOICE_MINIMAL_READ_ROLES') ?: '')
        ))),
        'max_results' => (int) (getenv('SIF_INVOICE_QUERY_MAX_RESULTS') ?: 50),
    ],
    'documents' => [
        'root' => getenv('SIF_DOCUMENT_ROOT') ?: '',
        'max_bytes' => (int) (getenv('SIF_DOCUMENT_MAX_BYTES') ?: 20971520),
    ],
    'internal_api' => [
        'key_id' => getenv('SIF_INTERNAL_API_KEY_ID') ?: '',
        'secret' => getenv('SIF_INTERNAL_API_SECRET') ?: '',
        'max_clock_skew_seconds' => (int) (getenv('SIF_INTERNAL_API_MAX_SKEW') ?: 300),
        'signed_path' => getenv('SIF_INTERNAL_API_SIGNED_PATH') ?: '/api/factures/query.php',
        'document_signed_path' => getenv('SIF_INTERNAL_DOCUMENT_SIGNED_PATH') ?: '/api/documents/download.php',
        'aeat_operations_signed_path' => getenv('SIF_INTERNAL_AEAT_OPERATIONS_SIGNED_PATH') ?: '/api/aeat/operations.php',
        'incident_signed_path' => getenv('SIF_INTERNAL_INCIDENT_SIGNED_PATH') ?: '/api/incidents/manage.php',
    ],
    'incidents' => [
        'read_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('SIF_INCIDENT_READ_ROLES') ?: '')
        ))),
        'manage_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('SIF_INCIDENT_MANAGE_ROLES') ?: '')
        ))),
        'max_results' => (int) (getenv('SIF_INCIDENT_QUERY_MAX_RESULTS') ?: 50),
    ],
    'redsys' => [
        'merchant_key' => getenv('SIF_REDSYS_MERCHANT_KEY') ?: '',
    ],
    'novice_promotion' => [
        // 32-byte AES wrapping key encoded as 64 hex chars. Keep it only in
        // the runtime secret store/environment, never in Git.
        'wrapping_key_hex' => getenv('SIF_NOVICE_PROMO_WRAP_KEY_HEX') ?: '',
        'key_version' => getenv('SIF_NOVICE_PROMO_KEY_VERSION') ?: 'v1',
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
        'read_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('SIF_AEAT_READ_ROLES') ?: '')
        ))),
        'reconcile_roles' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('SIF_AEAT_RECONCILE_ROLES') ?: '')
        ))),
    ],
];
