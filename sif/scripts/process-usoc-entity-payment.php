<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;
use Prisma\Sif\Service\UsocCaseReconciler;
use Prisma\Sif\Service\UsocEntityPaymentService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process USOC entity payments with SIF_ENV=production.\n");
    exit(1);
}

[$uuidFactura, $input] = parseArgs(array_slice($argv, 1));

try {
    $db = ConnectionFactory::make($config);
    $paymentService = new PaymentService(
        new TransactionRunner($db),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $cases = new UsocFinancingCaseRepository(new UuidGenerator());
    $service = new UsocEntityPaymentService(
        $cases,
        new ManualPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualPaymentPayloadBuilder(),
            $paymentService
        ),
        new UsocCaseReconciler($cases)
    );

    echo json_encode(
        $service->registerByEntityInvoiceUuid($db, $uuidFactura, $input),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
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
    $uuidFactura = optionValue($args, ['--uuid-factura=', '--uuid=']);
    $amount = optionValue($args, ['--amount=', '--import=']);
    $movementDate = optionValue($args, ['--movement-date=', '--data-pag=']);

    if ($uuidFactura === null || $amount === null || $movementDate === null) {
        usage();
    }
    if (!is_numeric($amount) || (float) $amount <= 0.0) {
        throw SifException::validation('Invalid USOC entity payment amount');
    }

    $input = [
        'amount' => number_format((float) $amount, 2, '.', ''),
        'movement_date' => $movementDate,
        'method' => optionValue($args, ['--method=', '--metode=']) ?? 'TRANSFERENCIA',
    ];
    foreach ([
        'reference' => ['--reference=', '--referencia='],
        'bank' => ['--bank=', '--banc='],
        'notes' => ['--notes=', '--obs='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    return [$uuidFactura, $input];
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

function usage(): never
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/process-usoc-entity-payment.php --uuid-factura=UUID --amount=AMOUNT --movement-date='YYYY-MM-DD HH:MM:SS' [--reference=REF] [--bank=BANK] [--method=TRANSFERENCIA|MANUAL] [--notes=TEXT]\n"
    );
    exit(1);
}
