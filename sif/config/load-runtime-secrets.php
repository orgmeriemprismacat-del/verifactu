<?php

declare(strict_types=1);

/**
 * Load runtime-only SIF values from an INI file outside the repository/webroot.
 *
 * Resolution order:
 * 1. SIF_RUNTIME_SECRETS_FILE, when explicitly configured.
 * 2. ../private/config/runtime-secrets.ini relative to the deployed sif/ tree.
 *
 * Missing files are tolerated so local/test environments do not require
 * production secrets. Preflight/worker code remains fail-closed when a
 * required value is absent.
 */
$explicit = getenv('SIF_RUNTIME_SECRETS_FILE');
$runtimeSecretsFile = is_string($explicit) && trim($explicit) !== ''
    ? trim($explicit)
    : dirname(__DIR__, 2) . '/private/config/runtime-secrets.ini';

if (!is_file($runtimeSecretsFile)) {
    return false;
}

if (!is_readable($runtimeSecretsFile)) {
    throw new RuntimeException('Private runtime secrets file is not readable.');
}

$values = parse_ini_file($runtimeSecretsFile, false, INI_SCANNER_RAW);
if (!is_array($values)) {
    throw new RuntimeException('Private runtime secrets file is invalid.');
}

foreach ($values as $name => $value) {
    if (!is_string($name) || preg_match('/^SIF_[A-Z0-9_]+$/D', $name) !== 1) {
        continue;
    }
    if (!is_string($value)) {
        continue;
    }
    putenv($name . '=' . $value);
}

putenv('SIF_RUNTIME_SECRETS_LOADED=1');

return true;
