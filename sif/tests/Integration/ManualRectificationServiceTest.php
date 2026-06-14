<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRectificationServiceTest
{
    public function testIssuesRectificationInvoiceAndLinksOriginalInvoice(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $service = $this->service($db);
        $input = [
            'amount' => '-40.00',
            'reason' => 'DEVOLUCIO_PARCIAL',
            'mode' => 'DIFERENCIES',
            'concept' => 'Rectificacio parcial curs',
            'detail' => 'Retorn parcial per baixa',
            'created_by' => 'adam',
        ];

        $first = $service->issueByUuid($db, $original['uuid_factura'], $input);
        $second = $service->issueByUuid($db, $original['uuid_factura'], $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same('R2026/000001', $first['num_visible']);
        Assert::same($original['uuid_factura'], $first['uuid_factura_rectificada']);
        Assert::same($original['num_visible'], $first['num_visible_rectificada']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
        Assert::same('RECTIFIED', (string) $db->query('SELECT ESTAT_FACTURA FROM factura WHERE TIPUS_SERIE = "A"')->fetchColumn());

        $rectification = $db->query(
            'SELECT f.TIPUS_SERIE, f.TIPUS_FACTURA, f.TOTAL, fr.MOTIU, fr.MODE_RECTIFICACIO
             FROM factura_rectificacio fr
             JOIN factura f ON f.UUID_FACTURA = fr.UUID_FACTURA_RECTIFICATIVA'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('R', $rectification['TIPUS_SERIE']);
        Assert::same('R1', $rectification['TIPUS_FACTURA']);
        Assert::same('-40.00', $rectification['TOTAL']);
        Assert::same('DEVOLUCIO_PARCIAL', $rectification['MOTIU']);
        Assert::same('DIFERENCIES', $rectification['MODE_RECTIFICACIO']);

        $relationType = (string) $db->query('SELECT RELATION_TYPE FROM fact_rels WHERE UUID_FACTURA = ' . $db->quote($first['uuid_factura']))->fetchColumn();
        Assert::same('RECTIFIES', $relationType);
    }

    public function testIssuesRectificationByVisibleInvoiceNumber(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|ORIGINAL|NUM',
        ]));

        $result = $this->service($db)->issueByNumVisible($db, $original['num_visible'], [
            'amount' => '-120.00',
            'reason' => 'ANULACIO_TOTAL',
            'mode' => 'SUBSTITUCIO',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same($original['uuid_factura'], $result['uuid_factura_rectificada']);
        Assert::same('R2026/000001', $result['num_visible']);
        Assert::same('SUBSTITUCIO', (string) $db->query('SELECT MODE_RECTIFICACIO FROM factura_rectificacio')->fetchColumn());
    }

    public function testRejectsUnknownOriginalInvoiceBeforeIssuingRectification(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->issueByUuid($db, 'missing-invoice', [
                'amount' => '-40.00',
                'reason' => 'DEVOLUCIO_PARCIAL',
                'mode' => 'DIFERENCIES',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
    }

    private function service(\PDO $db): ManualRectificationService
    {
        return new ManualRectificationService(
            new ManualPaymentInvoiceRepository(),
            new RectificationRepository(),
            new ManualRectificationPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );
    }
}
