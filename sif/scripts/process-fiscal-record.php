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

try {
    [$recordType, $selector, $input] = (new \Prisma\Sif\Cli\FiscalRecordArguments())->parse(array_slice($argv, 1));
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
