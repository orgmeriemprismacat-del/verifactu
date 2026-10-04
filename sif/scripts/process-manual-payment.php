<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\JointInvoiceEnrollmentFundAllocationService;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process manual payments with SIF_ENV=production.\n");
    exit(1);
}

[$selector, $input] = parseManualPaymentArgs(array_slice($argv, 1));
if ($selector === null) {
    usage('process');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $paymentService = new PaymentService(
        new TransactionRunner($sifDb),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $service = new ManualPaymentService(
        new ManualPaymentInvoiceRepository(),
        new ManualPaymentPayloadBuilder(),
        $paymentService,
        new JointInvoiceEnrollmentFundAllocationService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        )
    );

    if (($selector['type'] ?? '') === 'uuid') {
        $result = $service->registerByUuid(
            $sifDb,
            (string) ($selector['value'] ?? ''),
            $input
        );
    } else {
        $result = $service->registerByNumVisible(
            $sifDb,
            (string) ($selector['value'] ?? ''),
            $input
        );
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

function parseManualPaymentArgs(array $args): array
{
    $selector = null;
    $positionals = [];

    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--uuid-factura=')) {
            $selector = ['type' => 'uuid', 'value' => substr($arg, strlen('--uuid-factura='))];
            continue;
        }

        if (str_starts_with($arg, '--uuid=')) {
            $selector = ['type' => 'uuid', 'value' => substr($arg, strlen('--uuid='))];
            continue;
        }

        if (str_starts_with($arg, '--num-visible=')) {
            $selector = ['type' => 'num_visible', 'value' => substr($arg, strlen('--num-visible='))];
            continue;
        }

        if (str_starts_with($arg, '--num-fact=')) {
            $selector = ['type' => 'num_visible', 'value' => substr($arg, strlen('--num-fact='))];
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
        'reference' => ['--reference=', '--referencia=', '--referencia-bancaria='],
        'bank' => ['--bank=', '--banc='],
        'method' => ['--method=', '--metode='],
        'notes' => ['--notes=', '--obs=', '--observations='],
        'allocation_type' => ['--allocation-type='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    $participantAllocations = optionValue($args, ['--participant-allocations=']);
    if ($participantAllocations !== null) {
        $input['participant_allocations'] = participantAllocations($participantAllocations);
    }

    return [$selector, $input];
}

function participantAllocations(string $value): array
{
    $result = [];

    foreach (explode(',', $value) as $item) {
        $item = trim($item);
        if ($item === '' || !str_contains($item, ':')) {
            throw SifException::validation(
                'Invalid participant allocation; expected ID_INSC:AMOUNT'
            );
        }

        [$idRaw, $amountRaw] = array_map('trim', explode(':', $item, 2));
        if (!ctype_digit($idRaw) || (int) $idRaw <= 0 || !is_numeric($amountRaw)) {
            throw SifException::validation('Invalid participant allocation');
        }

        $amount = number_format((float) $amountRaw, 2, '.', '');
        if ((float) $amount <= 0.0 || array_key_exists((int) $idRaw, $result)) {
            throw SifException::validation('Invalid or duplicate participant allocation');
        }

        $result[(int) $idRaw] = $amount;
    }

    if ($result === []) {
        throw SifException::validation('At least one participant allocation is required');
    }

    return $result;
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('process');
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
        "Usage: php sif/scripts/{$script}-manual-payment.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE [--reference=REF] [--bank=BANK] [--method=TRANSFERENCIA|MANUAL] [--notes=TEXT] [--participant-allocations=ID_INSC:AMOUNT,ID_INSC:AMOUNT]\n"
    );
    exit(1);
}
