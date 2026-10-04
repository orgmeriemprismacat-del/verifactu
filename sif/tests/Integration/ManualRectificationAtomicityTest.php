<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRectificationAtomicityTest
{
    public function testLinkFailureRollsBackEntireRectificationGraph(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|ORIGINAL|ROLLBACK',
        ]));

        Assert::throws(\PDOException::class, function () use ($db, $original): void {
            $this->service($db)->issueByUuid($db, $original['uuid_factura'], [
                'amount' => '-10.00',
                'reason' => str_repeat('X', 81),
                'mode' => 'DIFERENCIES',
                'reference' => 'ROLLBACK-LINK-FAILURE',
            ]);
        });

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura WHERE TIPUS_SERIE = "R"')->fetchColumn());
        Assert::same('ISSUED', (string) $db->query(
            'SELECT ESTAT_FACTURA FROM factura WHERE UUID_FACTURA = ' . $db->quote($original['uuid_factura'])
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            'SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1'
        )->fetchColumn());
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
