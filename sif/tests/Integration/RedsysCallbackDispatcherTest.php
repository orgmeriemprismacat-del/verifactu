<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Service\RedsysCallbackDispatcher;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\RedsysGiftInvoiceService;
use Prisma\Sif\Service\RedsysGroupInvoiceService;
use Prisma\Sif\Service\RedsysIntentHandler;
use Prisma\Sif\Service\RedsysPackInvoiceService;
use Prisma\Sif\Service\RedsysUsocInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RecordingRedsysIntentHandler implements RedsysIntentHandler
{
    public function __construct(private string $type, private \ArrayObject $calls)
    {
    }

    public function sourceType(): string
    {
        return $this->type;
    }

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array
    {
        $this->calls->append($this->type);

        return ['ok' => true, 'uuid_factura' => $dsOrder, 'uuid_payment' => $dsOrder];
    }
}

final class RedsysCallbackDispatcherTest
{
    public function testFiveInvoiceServicesImplementSnapshotHandlerContract(): void
    {
        foreach ([
            RedsysCourseInvoiceService::class,
            RedsysPackInvoiceService::class,
            RedsysGroupInvoiceService::class,
            RedsysGiftInvoiceService::class,
            RedsysUsocInvoiceService::class,
        ] as $serviceClass) {
            Assert::same(true, is_subclass_of($serviceClass, RedsysIntentHandler::class));
        }
    }

    public function testRoutesEverySupportedSourceType(): void
    {
        $db = TestDatabase::fresh();
        $calls = new \ArrayObject();
        $types = ['CURS', 'PACK', 'GRUP', 'REGAL', 'USOC_ALUMNE'];
        $handlers = array_map(
            fn (string $type): RedsysIntentHandler => new RecordingRedsysIntentHandler($type, $calls),
            $types
        );
        $dispatcher = new RedsysCallbackDispatcher($handlers);

        foreach ($types as $type) {
            $dispatcher->process($db, [
                'DS_ORDER' => 'ORDER-' . $type,
                'SOURCE_TYPE' => $type,
                'SOURCE_ID' => $type === 'REGAL' ? '77' : '700',
                'SNAPSHOT_JSON' => json_encode(['source_type' => $type]),
            ]);
        }

        Assert::same($types, $calls->getArrayCopy());
    }
}
