<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Service\EnrollmentFundTransferPayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview enrollment fund transfer reversals with SIF_ENV=production.\n");
    exit(1);
}

$input = parseReversalArgs(array_slice($argv, 1));

try {
    $payload = (new EnrollmentFundTransferPayloadBuilder())->buildReversal($input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
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

function parseReversalArgs(array $args): array
{
    $positionals = [];
    foreach ($args as $arg) {
        if (!str_starts_with((string) $arg, '--')) {
            $positionals[] = (string) $arg;
        }
    }

    $movementUuid = optionValue(
        $args,
        ['--movement-uuid=', '--transfer-uuid=', '--reverses-uuid-movement=']
    ) ?? ($positionals[0] ?? null);

    if ($movementUuid === null || trim((string) $movementUuid) === '') {
        usage('preview');
    }

    $input = ['movement_uuid' => $movementUuid];

    foreach ([
        'correlation_id' => ['--correlation-id='],
        'uuid_operation' => ['--uuid-operation='],
        'notes' => ['--notes=', '--obs=', '--observations='],
        'order' => ['--order='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    return $input;
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
        "Usage: php sif/scripts/{$script}-enrollment-fund-transfer-reversal.php MOVEMENT_UUID [--correlation-id=ID] [--uuid-operation=UUID] [--notes=TEXT] [--order=N]\n"
    );
    exit(1);
}
