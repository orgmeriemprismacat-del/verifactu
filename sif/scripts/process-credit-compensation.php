<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CreditBalanceRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\CreditBalancePayloadBuilder;
use Prisma\Sif\Service\CreditBalanceService;
use Prisma\Sif\Service\PaymentPayloadValidator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process credit compensation with SIF_ENV=production.\n");
    exit(1);
}

[$uuidCredit, $selector, $input] = parseCreditCompensationArgs(array_slice($argv, 1));
if ($uuidCredit === null || $selector === null) {
    usage('process');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $service = new CreditBalanceService(
        new TransactionRunner($sifDb),
        new CreditBalanceRepository(new UuidGenerator()),
        new ManualPaymentInvoiceRepository(),
        new CreditBalancePayloadBuilder(),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );

    if (($selector['type'] ?? '') === 'uuid') {
        $result = $service->applyCreditByUuid($uuidCredit, (string) ($selector['value'] ?? ''), $input);
    } else {
        $result = $service->applyCreditByNumVisible($uuidCredit, (string) ($selector['value'] ?? ''), $input);
    }

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

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('process');
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
        usage('process');
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
