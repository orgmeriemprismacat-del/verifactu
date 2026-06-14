<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview manual rectifications with SIF_ENV=production.\n");
    exit(1);
}

[$selector, $input] = parseManualRectificationArgs(array_slice($argv, 1));
if ($selector === null) {
    usage('preview');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $invoice = loadManualRectificationInvoice($sifDb, $selector);
    $payload = (new ManualRectificationPayloadBuilder())->forOriginalInvoice($invoice, $input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'invoice_selector' => $selector,
        'original_invoice' => [
            'uuid_factura' => $invoice['UUID_FACTURA'],
            'num_visible' => $invoice['NUM_VISIBLE'],
            'estat_factura' => $invoice['ESTAT_FACTURA'] ?? null,
            'total' => $invoice['TOTAL'] ?? null,
        ],
        'payload' => $payload,
        'rectification' => [
            'reason' => $input['reason'],
            'mode' => $input['mode'],
        ],
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

function parseManualRectificationArgs(array $args): array
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
    ];

    foreach ([
        'reason' => ['--reason=', '--motiu='],
        'mode' => ['--mode=', '--mode-rectificacio='],
        'type' => ['--type=', '--tipus-factura='],
        'concept' => ['--concept=', '--concepte='],
        'detail' => ['--detail=', '--details=', '--detall='],
        'created_by' => ['--created-by=', '--user=', '--usuari='],
        'reference' => ['--reference=', '--referencia='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    if (!isset($input['reason'], $input['mode'])) {
        usage('preview');
    }

    return [$selector, $input];
}

function loadManualRectificationInvoice(\PDO $sifDb, array $selector): array
{
    $repository = new ManualPaymentInvoiceRepository();
    if (($selector['type'] ?? '') === 'uuid') {
        $invoice = $repository->findByUuid($sifDb, (string) ($selector['value'] ?? ''));
    } else {
        $invoice = $repository->findByNumVisible($sifDb, (string) ($selector['value'] ?? ''));
    }

    if ($invoice === null) {
        throw SifException::validation('SIF invoice not found for manual rectification');
    }

    return $invoice;
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('preview');
    }

    if (!is_numeric($value)) {
        throw SifException::validation('Invalid rectification amount');
    }

    $amount = (float) $value;
    if (abs($amount) < 0.005) {
        throw SifException::validation('Invalid rectification amount');
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
        "Usage: php sif/scripts/{$script}-manual-rectification.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT --reason=REASON --mode=DIFERENCIES|SUBSTITUCIO [--type=R1] [--concept=TEXT] [--detail=TEXT] [--created-by=USER] [--reference=REF]\n"
    );
    exit(1);
}
