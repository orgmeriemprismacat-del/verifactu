<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRectificationFiscalTest
{
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
