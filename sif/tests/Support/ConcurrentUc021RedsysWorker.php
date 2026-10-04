<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentPaymentFlowLockRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Service\RedsysCoursePaymentIntentService;
use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Integration\IssueInvoiceTest;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

[$script, $mode, $barrier, $worker] = array_pad($argv, 4, null);

if (!in_array($mode, ['intent', 'invoice'], true)
    || !is_string($barrier) || $barrier === ''
    || !is_string($worker) || $worker === ''
) {
    fwrite(STDERR, "Invalid UC-021 concurrency worker arguments\n");
    exit(2);
}

try {
    touch($barrier . '/ready-' . $worker);
    $deadline = microtime(true) + 10;
    while (!is_file($barrier . '/go')) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('UC-021 worker barrier timeout');
        }
        usleep(10000);
    }

    $sifDb = TestDatabase::connect();

    if ($mode === 'intent') {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        $legacyDb = ConnectionFactory::makeLegacy($config);

        $service = new RedsysCoursePaymentIntentService(
            new LegacyCourseSnapshotRepository(),
            new RedsysPaymentIntentService(
                new RedsysPaymentIntentRepository(),
                new UuidGenerator()
            ),
            new RedsysDsOrderGenerator(),
            null,
            null,
            new InvoiceBeforePaymentCoverageRepository(),
            new EnrollmentPaymentFlowLockRepository()
        );

        $result = $service->create($sifDb, $legacyDb, [
            'idpag' => 700,
            'requested_amount' => '100.00',
            'terminal' => '1',
            'ds_order' => '700000000021',
            'created_by' => 'uc021-concurrency-intent',
        ]);

        echo json_encode([
            'ok' => true,
            'mode' => $mode,
            'uuid_intent' => $result['uuid_intent'] ?? null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;
        exit(0);
    }

    $service = new InvoiceBeforePaymentService(
        new InvoiceBeforePaymentPayloadBuilder(),
        IssueInvoiceTest::serviceFor($sifDb)
    );

    $result = $service->issueBeforePayment(Fixtures::invoicePayload([
        'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|UC021:CONCURRENT:710',
        'source_channel' => 'INTRANET',
        'created_by' => 'uc021-concurrency-invoice',
        'relations' => [[
            'source_type' => 'INSCRIPCIO',
            'source_id' => 710,
            'relation_type' => 'ORIGIN',
            'visible_alumne' => 0,
        ]],
        'lines' => [[
            'concept' => 'Curs concurrent UC-021',
            'detail' => 'Participant 710',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'base' => '100.00',
            'import_base' => '100.00',
            'discount_amount' => '0.00',
            'taxable_base' => '100.00',
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => '100.00',
            'source_type' => 'INSCRIPCIO',
            'source_id' => 710,
        ]],
        'totals' => [
            'import_base' => '100.00',
            'taxable_base' => '100.00',
            'total' => '100.00',
        ],
    ]));

    echo json_encode([
        'ok' => true,
        'mode' => $mode,
        'uuid_factura' => $result['uuid_factura'] ?? null,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'mode' => $mode,
        'code' => $exception->getCode(),
        'error' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
}
