<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;
use Prisma\Sif\Service\HistoricalInvoiceMigrationService;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process historical invoice migrations with SIF_ENV=production.\n");
    exit(1);
}

$payloadFile = payloadFile(array_slice($argv, 1), 'process');

try {
    $payload = readPayloadFile($payloadFile);
    $sifDb = ConnectionFactory::make($config);
    $service = new HistoricalInvoiceMigrationService(
        new TransactionRunner($sifDb),
        new HistoricalInvoicePayloadBuilder(),
        new HistoricalInvoiceMigrationRepository(new UuidGenerator())
    );

    $result = $service->importHistoricalInvoice($payload);
    $result['verifactu_record_created'] = false;
    $result['aeat_queue_created'] = false;

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function payloadFile(array $args, string $script): string
{
    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--payload-file=')) {
            $path = trim(substr($arg, strlen('--payload-file=')));

            return $path === '' ? usage($script) : $path;
        }

        if (!str_starts_with($arg, '--')) {
            return $arg;
        }
    }

    usage($script);
}

function readPayloadFile(string $payloadFile): array
{
    $json = file_get_contents($payloadFile);
    if ($json === false) {
        throw SifException::validation('Could not read historical invoice migration payload file');
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid historical invoice migration payload JSON');
    }

    return $payload;
}

function usage(string $script): never
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/{$script}-historical-invoice-migration.php --payload-file=payload.json\n"
    );
    exit(1);
}
