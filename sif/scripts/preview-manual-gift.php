<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Service\ManualGiftInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview manual gift invoices with SIF_ENV=production.\n");
    exit(1);
}

[$selector, $input] = parseManualGiftArgs(array_slice($argv, 1));
if ($selector === null) {
    usage('preview');
}

try {
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $snapshot = loadGiftSnapshot($legacyDb, $selector);
    $payload = (new ManualGiftInvoicePayloadBuilder())->buildFromSnapshot($snapshot, $input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'gift_selector' => $selector,
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

function parseManualGiftArgs(array $args): array
{
    $selector = null;
    $positionals = [];

    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--gift-id=')) {
            $selector = ['type' => 'id', 'value' => substr($arg, strlen('--gift-id='))];
            continue;
        }

        if (str_starts_with($arg, '--gift-code=')) {
            $selector = ['type' => 'code', 'value' => substr($arg, strlen('--gift-code='))];
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
        'notes' => ['--notes=', '--obs=', '--observations='],
        'created_by' => ['--created-by=', '--user=', '--usuari='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    return [$selector, $input];
}

function loadGiftSnapshot(\PDO $legacyDb, array $selector): array
{
    $repository = new LegacyGiftSnapshotRepository();
    if (($selector['type'] ?? '') === 'id') {
        return $repository->loadById($legacyDb, (int) ($selector['value'] ?? 0));
    }

    return $repository->loadByCode($legacyDb, (string) ($selector['value'] ?? ''));
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('preview');
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
        "Usage: php sif/scripts/{$script}-manual-gift.php (--gift-id=ID|--gift-code=CODI) AMOUNT MOVEMENT_DATE [--reference=REF] [--bank=BANK] [--notes=TEXT] [--created-by=USER]\n"
    );
    exit(1);
}
