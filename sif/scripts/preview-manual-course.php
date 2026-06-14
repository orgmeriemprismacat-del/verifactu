<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Service\DiscountSnapshotFileReader;
use Prisma\Sif\Service\ManualCourseInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview manual course invoices with SIF_ENV=production.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$positionals = [];
$discountFile = null;
foreach ($args as $arg) {
    $arg = (string) $arg;
    if (str_starts_with($arg, '--discount-file=')) {
        $discountFile = trim(substr($arg, strlen('--discount-file=')));
        continue;
    }

    if (!str_starts_with($arg, '--')) {
        $positionals[] = $arg;
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
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $discountSnapshot = (new DiscountSnapshotFileReader())->read($discountFile);
    $snapshot = (new LegacyCourseSnapshotRepository())->loadByIdpag($legacyDb, $idpag, $input['amount']);
    if ($discountSnapshot !== null) {
        $snapshot['discount'] = $discountSnapshot;
    }

    $payload = (new ManualCourseInvoicePayloadBuilder())->buildFromSnapshot($snapshot, $input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'idpag' => $idpag,
        'payload' => $payload,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'dry_run' => true,
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
        "Usage: php sif/scripts/preview-manual-course.php IDPAG AMOUNT MOVEMENT_DATE [--reference=REF] [--bank=BANK] [--notes=TEXT] [--created-by=USER] [--discount-file=discount.json]\n"
    );
    exit(1);
}
