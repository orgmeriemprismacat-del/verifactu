<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\UsocEntityInvoiceService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process USOC entity invoices with SIF_ENV=production.\n");
    exit(1);
}

$payloadFile = payloadFile(array_slice($argv, 1), 'process');

try {
    $input = readPayloadFile($payloadFile);
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $invoiceService = new InvoiceService(
        new TransactionRunner($sifDb),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator())
    );
    $service = new UsocEntityInvoiceService(
        new LegacyUsocSnapshotRepository(),
        new LegacyUsocInvoicePayloadBuilder(),
        $invoiceService
    );

    $result = $service->issueEntityFromExplicitInput($legacyDb, $input);
    $result['payment_registered'] = false;

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
        throw SifException::validation('Could not read USOC entity payload file');
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid USOC entity payload JSON');
    }

    return $payload;
}

function usage(string $script): never
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/{$script}-usoc-entity.php --payload-file=payload.json\n"
    );
    exit(1);
}
