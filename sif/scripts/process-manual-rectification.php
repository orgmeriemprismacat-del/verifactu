<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process manual rectifications with SIF_ENV=production.\n");
    exit(1);
}

[$selector, $input] = parseManualRectificationArgs(array_slice($argv, 1));
if ($selector === null) {
    usage('process');
}

try {
    $sifDb = ConnectionFactory::make($config);
    $invoiceService = new InvoiceService(
        new TransactionRunner($sifDb),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator())
    );
    $service = new ManualRectificationService(
        new ManualPaymentInvoiceRepository(),
        new RectificationRepository(),
        new ManualRectificationPayloadBuilder(),
        $invoiceService
    );

    if (($selector['type'] ?? '') === 'uuid') {
        $result = $service->issueByUuid($sifDb, (string) ($selector['value'] ?? ''), $input);
    } else {
        $result = $service->issueByNumVisible($sifDb, (string) ($selector['value'] ?? ''), $input);
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
        usage('process');
    }

    return [$selector, $input];
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('process');
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
