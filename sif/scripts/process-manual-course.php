<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\ManualCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualCourseInvoiceService;
use Prisma\Sif\Service\PaymentPayloadValidator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process manual course invoices with SIF_ENV=production.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$syncLegacy = in_array('--sync-legacy', $args, true);
$positionals = [];
foreach ($args as $arg) {
    if (!str_starts_with((string) $arg, '--')) {
        $positionals[] = (string) $arg;
    }
}

$idpag = idpag($positionals[0] ?? null);
$input = [
    'amount' => amount($positionals[1] ?? null),
    'movement_date' => movementDate($positionals[2] ?? null),
];

foreach ([
    'reference' => ['--reference=', '--referencia=', '--referencia-bancaria='],
    'bank' => ['--bank=', '--banc='],
    'notes' => ['--notes=', '--obs=', '--observations='],
    'created_by' => ['--created-by=', '--user=', '--usuari='],
] as $key => $prefixes) {
    $value = optionValue($args, $prefixes);
    if ($value !== null) {
        $input[$key] = $value;
    }
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $invoiceService = new InvoiceService(
        new TransactionRunner($sifDb),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $service = new ManualCourseInvoiceService(
        new LegacyCourseSnapshotRepository(),
        new ManualCourseInvoicePayloadBuilder(),
        $invoiceService
    );

    $result = $service->issueFromLegacyCoursePayment($legacyDb, $idpag, $input);
    $legacySync = $result['legacy_sync'] ?? ['relations' => [], 'estat_cobrament' => 'PAID'];

    if ($syncLegacy && ($result['ok'] ?? false) === true) {
        (new LegacySyncService(new LegacySyncRepository()))->syncAfterSifSuccess(
            $legacyDb,
            $legacySync['relations'] ?? [],
            (string) $result['uuid_factura'],
            (string) $result['num_visible'],
            (string) ($legacySync['estat_cobrament'] ?? 'PAID')
        );
        $result['legacy_sync_executed'] = true;
    } else {
        $result['legacy_sync_executed'] = false;
    }

    unset($result['legacy_sync']);
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

function idpag(mixed $value): int
{
    if ($value === null || $value === '') {
        usage();
    }

    if (!is_numeric($value)) {
        throw SifException::validation('Invalid manual course IDPAG');
    }

    $idpag = (int) $value;
    if ($idpag <= 0) {
        throw SifException::validation('Invalid manual course IDPAG');
    }

    return $idpag;
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage();
    }

    if (!is_numeric($value)) {
        throw SifException::validation('Invalid payment amount');
    }

    $amount = (float) $value;
    if ($amount <= 0.0) {
        throw SifException::validation('Invalid payment amount');
    }

    return number_format($amount, 2, '.', '');
}

function movementDate(mixed $value): string
{
    if ($value === null || trim((string) $value) === '') {
        usage();
    }

    return trim((string) $value);
}

function optionValue(array $args, array $prefixes): ?string
{
    foreach ($args as $arg) {
        foreach ($prefixes as $prefix) {
            if (str_starts_with((string) $arg, $prefix)) {
                $value = trim(substr((string) $arg, strlen($prefix)));

                return $value === '' ? null : $value;
            }
        }
    }

    return null;
}

function usage(): void
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/process-manual-course.php IDPAG AMOUNT MOVEMENT_DATE [--reference=REF] [--bank=BANK] [--notes=TEXT] [--created-by=USER] [--sync-legacy]\n"
    );
    exit(1);
}
