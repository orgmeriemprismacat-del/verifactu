<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CreditBalanceRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\CreditBalancePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview credit compensation with SIF_ENV=production.\n");
    exit(1);
}

[$uuidCredit, $selector, $input] = parseCreditCompensationArgs(array_slice($argv, 1));
if ($uuidCredit === null || $selector === null) {
    usage('preview');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $credit = loadCredit($sifDb, $uuidCredit);
    $invoice = loadInvoice($sifDb, $selector);
    $payload = (new CreditBalancePayloadBuilder())->forCompensation($credit['UUID_CREDIT'], $invoice['UUID_FACTURA'], $input, $invoice);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'credit' => [
            'uuid_credit' => $credit['UUID_CREDIT'],
            'import_disponible' => $credit['IMPORT_DISPONIBLE'],
            'estat' => $credit['ESTAT'],
        ],
        'invoice' => [
            'uuid_factura' => $invoice['UUID_FACTURA'],
            'num_visible' => $invoice['NUM_VISIBLE'],
            'estat_cobrament' => $invoice['ESTAT_COBRAMENT'] ?? null,
            'total' => $invoice['TOTAL'] ?? null,
        ],
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

function parseCreditCompensationArgs(array $args): array
{
    $uuidCredit = null;
    $selector = null;
    $positionals = [];

    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--uuid-credit=')) {
            $uuidCredit = substr($arg, strlen('--uuid-credit='));
            continue;
        }

        if (str_starts_with($arg, '--uuid-factura=')) {
            $selector = ['type' => 'uuid', 'value' => substr($arg, strlen('--uuid-factura='))];
            continue;
        }

        if (str_starts_with($arg, '--num-visible=')) {
            $selector = ['type' => 'num_visible', 'value' => substr($arg, strlen('--num-visible='))];
            continue;
        }

        if (!str_starts_with($arg, '--')) {
            $positionals[] = $arg;
        }
    }

    $input = [
        'amount' => amount($positionals[0] ?? null),
        'movement_date' => movementDate($positionals[1] ?? null),
    ];

    foreach ([
        'notes' => ['--notes=', '--obs=', '--observations='],
        'allocation_type' => ['--allocation-type='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    return [$uuidCredit, $selector, $input];
}

function loadCredit(\PDO $sifDb, string $uuidCredit): array
{
    $credit = (new CreditBalanceRepository(new UuidGenerator()))->findByUuid($sifDb, $uuidCredit);
    if ($credit === null) {
        throw SifException::validation('Credit balance not found');
    }

    return $credit;
}

function loadInvoice(\PDO $sifDb, array $selector): array
{
    $repository = new ManualPaymentInvoiceRepository();
    if (($selector['type'] ?? '') === 'uuid') {
        $invoice = $repository->findByUuid($sifDb, (string) ($selector['value'] ?? ''));
    } else {
        $invoice = $repository->findByNumVisible($sifDb, (string) ($selector['value'] ?? ''));
    }

    if ($invoice === null) {
        throw SifException::validation('SIF invoice not found for credit compensation');
    }

    return $invoice;
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('preview');
    }

    if (!is_numeric($value)) {
        throw SifException::validation('Invalid credit amount');
    }

    $amount = (float) $value;
    if ($amount <= 0.0) {
        throw SifException::validation('Invalid credit amount');
    }

    return number_format($amount, 2, '.', '');
}

function movementDate(mixed $value): string
{
    if ($value === null || trim((string) $value) === '') {
        usage('preview');
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

function usage(string $script): void
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/{$script}-credit-compensation.php --uuid-credit=UUID (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE [--notes=TEXT]\n"
    );
    exit(1);
}
