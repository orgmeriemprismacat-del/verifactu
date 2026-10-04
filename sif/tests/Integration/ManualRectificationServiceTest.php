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

    public function testPersistsCatalanAliasesInRectificationLink(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|ORIGINAL|ALIASES',
        ]));

        $result = $this->service($db)->issueByUuid($db, $original['uuid_factura'], [
            'amount' => '-25.00',
            'motiu' => 'AJUST_IMPORT',
            'mode_rectificacio' => 'DIFERENCIES',
            'detall' => 'Correccio amb aliases catalans',
        ]);

        Assert::same(true, $result['ok']);

        $rectification = $db->query(
            'SELECT MOTIU, MODE_RECTIFICACIO, DETAILS FROM factura_rectificacio'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('AJUST_IMPORT', $rectification['MOTIU']);
        Assert::same('DIFERENCIES', $rectification['MODE_RECTIFICACIO']);
        Assert::same('Correccio amb aliases catalans', $rectification['DETAILS']);
    }

    public function testSubstitutionPersistsCorrectedBillingWithoutMutatingOriginal(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|ORIGINAL|BILLING',
        ]));

        $result = $this->service($db)->issueByUuid($db, $original['uuid_factura'], [
            'amount' => '-120.00',
            'reason' => 'CANVI_RECEPTOR',
            'mode' => 'SUBSTITUCIO',
            'billing' => [
                'name' => 'Empresa Correcta SL',
                'nif' => 'B12345678',
                'address' => 'Carrer Fiscal 2',
                'cp' => '17001',
                'city' => 'Girona',
                'province' => 'Girona',
                'country' => 'ES',
                'email' => 'fiscal@example.test',
            ],
        ]);

        $rectified = $db->query(
            'SELECT BILLING_NOM_RAO, BILLING_NIF_CIF, BILLING_ADRECA, BILLING_CP, BILLING_POBLACIO
             FROM factura WHERE UUID_FACTURA = ' . $db->quote($result['uuid_factura'])
        )->fetch(\PDO::FETCH_ASSOC);
        $source = $db->query(
            'SELECT BILLING_NOM_RAO, BILLING_NIF_CIF
             FROM factura WHERE UUID_FACTURA = ' . $db->quote($original['uuid_factura'])
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('Empresa Correcta SL', $rectified['BILLING_NOM_RAO']);
        Assert::same('B12345678', $rectified['BILLING_NIF_CIF']);
        Assert::same('Carrer Fiscal 2', $rectified['BILLING_ADRECA']);
        Assert::same('17001', $rectified['BILLING_CP']);
        Assert::same('Girona', $rectified['BILLING_POBLACIO']);
        Assert::same('Client Exemple', $source['BILLING_NOM_RAO']);
        Assert::same('12345678Z', $source['BILLING_NIF_CIF']);
    }

    public function testExplicitTaxedFiscalBlockIsPersistedEndToEnd(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|ORIGINAL|TAXED',
            'totals' => [
                'import_base' => '100.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'GENERAL',
                'iva_pct' => '21.00',
                'iva_import' => '21.00',
                'total' => '121.00',
            ],
            'lines' => [[
                'concept' => 'Servei subjecte',
                'detail' => 'Servei de prova amb IVA',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'base' => '100.00',
                'import_base' => '100.00',
                'discount_amount' => '0.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'GENERAL',
                'iva_pct' => '21.00',
                'iva_import' => '21.00',
                'total' => '121.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
            ]],
        ]));

        $result = $this->service($db)->issueByUuid($db, $original['uuid_factura'], [
            'amount' => '-60.50',
            'reason' => 'AJUST_IMPORT',
            'mode' => 'DIFERENCIES',
            'fiscal' => [
                'import_base' => '-50.00',
                'taxable_base' => '-50.00',
                'iva_regim' => 'GENERAL',
                'iva_pct' => '21.00',
                'iva_import' => '-10.50',
                'total' => '-60.50',
            ],
        ]);

        $stored = $db->query(
            'SELECT IMPORT_BASE, BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT, TOTAL
             FROM factura WHERE UUID_FACTURA = ' . $db->quote($result['uuid_factura'])
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('-50.00', $stored['IMPORT_BASE']);
        Assert::same('-50.00', $stored['BASE_IMPOSABLE']);
        Assert::same('GENERAL', $stored['IVA_REGIM']);
        Assert::same('21.00', $stored['IVA_PCT']);
        Assert::same('-10.50', $stored['IVA_IMPORT']);
        Assert::same('-60.50', $stored['TOTAL']);
    }

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
        Assert::same(1, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());
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
