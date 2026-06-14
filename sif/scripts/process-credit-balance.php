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
    fwrite(STDERR, "Refusing to process credit balances with SIF_ENV=production.\n");
    exit(1);
}

$input = parseCreditBalanceArgs(array_slice($argv, 1));

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
    $result = $service->createCredit($input);

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

function parseCreditBalanceArgs(array $args): array
{
    $positionals = [];
    foreach ($args as $arg) {
        if (!str_starts_with((string) $arg, '--')) {
            $positionals[] = (string) $arg;
        }
    }

    $input = [
        'amount' => amount(optionValue($args, ['--amount=', '--import=']) ?? ($positionals[0] ?? null)),
    ];

    foreach ([
        'holder_type' => ['--holder-type=', '--tipus-titular='],
        'holder_id' => ['--holder-id=', '--id-titular='],
        'holder_nif_cif' => ['--holder-nif-cif=', '--nif-cif=', '--nif='],
        'holder_name' => ['--holder-name=', '--holder-nom=', '--nom-titular='],
        'source_type' => ['--source-type=', '--origen='],
        'source_id' => ['--source-id=', '--id-origen='],
        'uuid_factura_origen' => ['--uuid-factura-origen=', '--invoice-origin-uuid='],
        'uuid_factura_rectificativa' => ['--uuid-factura-rectificativa=', '--rectification-invoice-uuid='],
        'review_after' => ['--review-after=', '--revisar-despres='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    if (!isset($input['holder_type'], $input['holder_name'], $input['source_type'])) {
        usage('process');
    }

    return $input;
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
        "Usage: php sif/scripts/{$script}-credit-balance.php AMOUNT --holder-type=STUDENT|ENTITY --holder-name=NAME --source-type=BAIXA|CANVI_CURS|RECTIFICATIVA [--holder-id=ID] [--holder-nif-cif=NIF] [--source-id=ID] [--uuid-factura-origen=UUID] [--uuid-factura-rectificativa=UUID] [--review-after=YYYY-MM-DD]\n"
    );
    exit(1);
}
