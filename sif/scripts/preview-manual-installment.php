<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ManualInstallmentPaymentPayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview manual installments with SIF_ENV=production.\n");
    exit(1);
}

[$selector, $input] = parseManualInstallmentArgs(array_slice($argv, 1));
if ($selector === null) {
    usage('preview');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $invoice = loadManualInstallmentInvoice($sifDb, $selector);
    $payload = (new ManualInstallmentPaymentPayloadBuilder())->forExistingInvoice($invoice['UUID_FACTURA'], $input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'invoice_selector' => $selector,
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

function parseManualInstallmentArgs(array $args): array
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
        'id_insc' => ['--id-insc=', '--id-inscripcio=', '--inscription-id='],
        'user' => ['--user=', '--usuari=', '--created-by='],
        'reference' => ['--reference=', '--referencia=', '--referencia-bancaria='],
        'bank' => ['--bank=', '--banc='],
        'notes' => ['--notes=', '--obs=', '--observations='],
        'allocation_type' => ['--allocation-type='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    return [$selector, $input];
}

function loadManualInstallmentInvoice(\PDO $sifDb, array $selector): array
{
    $repository = new ManualPaymentInvoiceRepository();
    if (($selector['type'] ?? '') === 'uuid') {
        $invoice = $repository->findByUuid($sifDb, (string) ($selector['value'] ?? ''));
    } else {
        $invoice = $repository->findByNumVisible($sifDb, (string) ($selector['value'] ?? ''));
    }

    if ($invoice === null) {
        throw SifException::validation('SIF invoice not found for manual installment');
    }

    return $invoice;
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('preview');
    }

    if (!is_numeric($value)) {
        throw SifException::validation('Invalid installment amount');
    }

    $amount = (float) $value;
    if ($amount <= 0.0) {
        throw SifException::validation('Invalid installment amount');
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
        "Usage: php sif/scripts/{$script}-manual-installment.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE --id-insc=ID --user=USER [--reference=REF] [--bank=BANK] [--notes=TEXT]\n"
    );
    exit(1);
}
