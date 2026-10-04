<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\EnrollmentFundTransferPayloadBuilder;
use Prisma\Sif\Service\EnrollmentFundTransferService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process enrollment fund transfers with SIF_ENV=production.\n");
    exit(1);
}

$input = parseTransferArgs(array_slice($argv, 1));

try {
    $db = ConnectionFactory::make($config);
    $service = new EnrollmentFundTransferService(
        new TransactionRunner($db),
        new EnrollmentFundMovementRepository(new UuidGenerator()),
        new EnrollmentFundTransferPayloadBuilder()
    );
    $result = $service->transfer($input);

    echo json_encode(
        $result,
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

function parseTransferArgs(array $args): array
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
        'idempotency_key' => ['--idempotency-key='],
        'source_enrollment_id' => ['--source-enrollment-id=', '--id-insc-origin=', '--id-insc-origen='],
        'target_enrollment_id' => ['--target-enrollment-id=', '--id-insc-destination=', '--id-insc-dest=', '--id-insc-desti='],
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

    if (!isset(
        $input['idempotency_key'],
        $input['source_enrollment_id'],
        $input['target_enrollment_id']
    )) {
        usage('process');
    }

    return $input;
}

function amount(mixed $value): string
{
    if ($value === null || $value === '') {
        usage('process');
    }

    $text = trim(str_replace(',', '.', (string) $value));
    if (!is_numeric($text) || (float) $text <= 0.0) {
        throw SifException::validation('Invalid enrollment fund transfer amount');
    }

    return number_format((float) $text, 2, '.', '');
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
        "Usage: php sif/scripts/{$script}-enrollment-fund-transfer.php AMOUNT --source-enrollment-id=ID --target-enrollment-id=ID --idempotency-key=KEY [--correlation-id=ID] [--uuid-operation=UUID] [--notes=TEXT] [--order=N]\n"
    );
    exit(1);
}
