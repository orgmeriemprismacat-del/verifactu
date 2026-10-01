<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Service\RedsysPackEvidenceVerifier;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$allowProduction = filter_var(
    getenv('SIF_UC015_EVIDENCE_ALLOW_PRODUCTION') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
if (($config['env'] ?? 'local') === 'production' && !$allowProduction) {
    fwrite(
        STDERR,
        "Refusing UC-015 evidence verification with SIF_ENV=production unless SIF_UC015_EVIDENCE_ALLOW_PRODUCTION=1.\n"
    );
    exit(1);
}

$dsOrder = trim((string) ($argv[1] ?? ''));
if ($dsOrder === '') {
    fwrite(STDERR, "Usage: php sif/scripts/verify-redsys-pack-evidence.php DS_ORDER\n");
    exit(1);
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $result = (new RedsysPackEvidenceVerifier())->verify($sifDb, $legacyDb, $dsOrder);

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;

    exit(($result['ok'] ?? false) === true ? 0 : 2);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'ds_order' => $dsOrder,
        'error' => $exception->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
