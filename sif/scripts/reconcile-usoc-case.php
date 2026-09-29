<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Service\UsocCaseReconciler;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to reconcile USOC financing cases with SIF_ENV=production.\n");
    exit(1);
}

[$inscriptionId, $idpag] = parseArgs(array_slice($argv, 1));

try {
    $db = ConnectionFactory::make($config);
    $reconciler = new UsocCaseReconciler(
        new UsocFinancingCaseRepository(new UuidGenerator())
    );
    $case = $reconciler->reconcile($db, $inscriptionId, $idpag);

    echo json_encode([
        'ok' => true,
        'id_insc' => $inscriptionId,
        'idpag' => $idpag,
        'case' => $case,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function parseArgs(array $args): array
{
    $inscriptionId = null;
    $idpag = null;

    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--id-insc=')) {
            $value = substr($arg, strlen('--id-insc='));
            if (!is_numeric($value) || (int) $value <= 0) {
                throw SifException::validation('Invalid USOC inscription ID');
            }
            $inscriptionId = (int) $value;
            continue;
        }

        if (str_starts_with($arg, '--idpag=')) {
            $value = substr($arg, strlen('--idpag='));
            if (!is_numeric($value) || (int) $value <= 0) {
                throw SifException::validation('Invalid USOC IDPAG');
            }
            $idpag = (int) $value;
        }
    }

    if ($inscriptionId === null || $idpag === null) {
        fwrite(STDERR, "Usage: php sif/scripts/reconcile-usoc-case.php --id-insc=ID_INSC --idpag=IDPAG\n");
        exit(1);
    }

    return [$inscriptionId, $idpag];
}
