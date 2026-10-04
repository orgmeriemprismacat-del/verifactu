<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\MigrationRunner;
use Prisma\Sif\Service\RuntimeVersionInspector;
use Prisma\Sif\Service\SifVersionEvidenceVerifier;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$allowProduction = filter_var(
    getenv('SIF_UC010_EVIDENCE_ALLOW_PRODUCTION') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);
if (($config['env'] ?? 'local') === 'production' && !$allowProduction) {
    fwrite(
        STDERR,
        "Refusing UC-010 evidence verification with SIF_ENV=production unless SIF_UC010_EVIDENCE_ALLOW_PRODUCTION=1.\n"
    );
    exit(1);
}

$uuidVersion = strtolower(trim((string) ($argv[1] ?? '')));
if ($uuidVersion === '') {
    fwrite(STDERR, "Usage: php sif/scripts/verify-version-governance-evidence.php UUID_VERSION\n");
    exit(1);
}

try {
    $baseDir = dirname(__DIR__);
    $db = ConnectionFactory::make($config);
    $result = (new SifVersionEvidenceVerifier(
        new RuntimeVersionInspector(
            $baseDir,
            new MigrationRunner($baseDir . '/database')
        ),
        $config
    ))->verify($db, $uuidVersion);

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;

    exit(($result['ok'] ?? false) === true ? 0 : 2);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'uuid_version' => $uuidVersion,
        'error' => $exception->getCode() >= 400 && $exception->getCode() < 500
            ? $exception->getMessage()
            : 'UC-010 evidence verification failed',
        'production_authorized' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
