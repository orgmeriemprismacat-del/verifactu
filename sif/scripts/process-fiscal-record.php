<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Repository\FiscalRecordRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\FiscalRecordPayloadBuilder;
use Prisma\Sif\Service\FiscalRecordService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to process fiscal records with SIF_ENV=production.\n");
    exit(1);
}

[$recordType, $selector, $input] = parseFiscalRecordArgs(array_slice($argv, 1));

try {
    $sifDb = ConnectionFactory::make($config);
    $service = new FiscalRecordService(
        new TransactionRunner($sifDb),
        new ManualPaymentInvoiceRepository(),
        new FiscalRecordRepository(new HashCalculator()),
        new FiscalRecordPayloadBuilder()
    );
    $byUuid = $selector['type'] === 'uuid';

    if ($recordType === 'ANULACIO') {
        $result = $byUuid
            ? $service->createCancellationByUuid($selector['value'], $input)
            : $service->createCancellationByNumVisible($selector['value'], $input);
    } else {
        $result = $byUuid
            ? $service->createSubsanationByUuid($selector['value'], $input)
            : $service->createSubsanationByNumVisible($selector['value'], $input);
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

function parseFiscalRecordArgs(array $args): array
{
    $type = strtoupper((string) optionValue($args, ['--type=']));
    $selector = selectorValue($args);
    $input = [];

    foreach ([
        'reason' => ['--reason=', '--motiu='],
        'subsanation_kind' => ['--subsanation-kind=', '--tipus-subsanacio='],
        'detail' => ['--detail=', '--detall='],
        'correction_summary' => ['--correction-summary=', '--resum-correccio='],
        'created_by' => ['--created-by=', '--usuari='],
        'reference' => ['--reference=', '--referencia='],
    ] as $key => $prefixes) {
        $value = optionValue($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    if (!in_array($type, ['ANULACIO', 'SUBSANACIO'], true) || $selector === null || !isset($input['reason'])) {
        usage();
    }
    if ($type === 'SUBSANACIO' && !isset($input['subsanation_kind'])) {
        usage();
    }

    return [$type, $selector, $input];
}

function selectorValue(array $args): ?array
{
    $uuid = optionValue($args, ['--uuid-factura=', '--uuid=']);
    if ($uuid !== null) {
        return ['type' => 'uuid', 'value' => $uuid];
    }

    $number = optionValue($args, ['--num-visible=', '--num-fact=']);

    return $number === null ? null : ['type' => 'num_visible', 'value' => $number];
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

function usage(): void
{
    fwrite(STDERR, "Usage: php sif/scripts/process-fiscal-record.php --type=ANULACIO|SUBSANACIO (--uuid-factura=UUID|--num-visible=NUM) --reason=REASON [--subsanation-kind=SUBSANACION|RECHAZO_PREVIO|SIN_REGISTRO_PREVIO] [--detail=TEXT] [--correction-summary=TEXT] [--created-by=USER] [--reference=REF]\n");
    exit(1);
}
