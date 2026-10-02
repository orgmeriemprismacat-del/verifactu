<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceBeforePaymentBillingPartyRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentSelectionRepository;
use Prisma\Sif\Service\InvoiceBeforePaymentCommandService;
use Prisma\Sif\Service\InvoiceBeforePaymentLegacyPreparationService;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentServerPayloadAssembler;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Service\PayloadIdempotencyValidator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentCommandServiceTest
{
    public function testPreviewBlocksExistingCoverageButConfirmRetryReusesInvoice(): void
    {
        $db = TestDatabase::fresh();
        $this->seedLegacyTables($db);

        $coverage = new InvoiceBeforePaymentCoverageRepository();
        $preparation = new InvoiceBeforePaymentLegacyPreparationService(
            new InvoiceBeforePaymentSelectionRepository(),
            new InvoiceBeforePaymentBillingPartyRepository(),
            new InvoiceBeforePaymentServerPayloadAssembler(),
            new InvoiceBeforePaymentPayloadBuilder(),
            new PayloadIdempotencyValidator()
        );

        $commands = new InvoiceBeforePaymentCommandService(
            $db,
            $db,
            $preparation,
            new InvoiceBeforePaymentService(
                new InvoiceBeforePaymentPayloadBuilder(),
                IssueInvoiceTest::serviceFor($db)
            ),
            $db,
            $coverage
        );

        $preview = $commands->preview(
            [11],
            7,
            'gestio-test',
            ['fiscal_year' => 2026]
        );

        Assert::same('preview', $preview['action']);
        Assert::same([11], $preview['selection']['ids']);

        $first = $commands->confirm(
            [11],
            7,
            'gestio-test',
            $preview['fingerprint'],
            ['fiscal_year' => 2026]
        );
        $second = $commands->confirm(
            [11],
            7,
            'gestio-test',
            $preview['fingerprint'],
            ['fiscal_year' => 2026]
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn()
        );

        Assert::throws(SifException::class, function () use ($commands): void {
            $commands->preview(
                [11],
                7,
                'gestio-test',
                ['fiscal_year' => 2026]
            );
        }, 409);
    }

    private function seedLegacyTables(\PDO $db): void
    {
        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID BIGINT PRIMARY KEY,
                IDPAG BIGINT NULL,
                `ANY` INT NOT NULL,
                MES VARCHAR(20) NOT NULL,
                CURS VARCHAR(30) NOT NULL,
                NOM VARCHAR(120) NOT NULL,
                COGNOMS VARCHAR(180) NULL,
                DNI VARCHAR(30) NULL,
                CORREU VARCHAR(180) NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                PAGAMENT DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                FACTURA_RELACIONADA BIGINT NULL,
                `INSC CURS` VARCHAR(5) NOT NULL
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE curs (
                `ANY` INT NOT NULL,
                MES VARCHAR(20) NOT NULL,
                CURS VARCHAR(30) NOT NULL,
                NOM_CURS VARCHAR(180) NOT NULL,
                HORES VARCHAR(30) NULL,
                DATAI DATE NULL,
                DATAF DATE NULL,
                PRIMARY KEY (`ANY`, MES, CURS)
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE entitats (
                ID BIGINT PRIMARY KEY,
                CIF VARCHAR(30) NOT NULL,
                RAO VARCHAR(180) NOT NULL,
                ADRECA VARCHAR(180) NULL,
                CP VARCHAR(20) NULL,
                POBLACIO VARCHAR(120) NULL,
                ID_RESP BIGINT NULL
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE entitats_resp (
                ID_RESP BIGINT NOT NULL,
                NOM VARCHAR(120) NULL,
                COGNOMS VARCHAR(180) NULL,
                CORREU VARCHAR(180) NULL,
                DATAI DATETIME NOT NULL,
                DATAF DATETIME NULL
            )'
        );

        $db->exec(
            "INSERT INTO curs (`ANY`, MES, CURS, NOM_CURS, HORES, DATAI, DATAF)
             VALUES (2026, '10', 'DOL', 'Didàctica online', '30', '2026-10-05', '2026-10-27')"
        );
        $db->exec(
            "INSERT INTO inscripcions (
                ID, IDPAG, `ANY`, MES, CURS, NOM, COGNOMS, DNI, CORREU,
                A_PAGAR, PAGAMENT, FACTURA_RELACIONADA, `INSC CURS`
             ) VALUES (
                11, 1001, 2026, '10', 'DOL', 'Anna', 'Exemple', '11111111A',
                'anna@example.test', 80.00, 0.00, NULL, '1'
             )"
        );
        $db->exec(
            "INSERT INTO entitats (ID, CIF, RAO, ADRECA, CP, POBLACIO, ID_RESP)
             VALUES (7, 'B12345678', 'Escola Exemple SL', 'Carrer Un 1', '08001', 'Barcelona', 70)"
        );
        $db->exec(
            "INSERT INTO entitats_resp (ID_RESP, NOM, COGNOMS, CORREU, DATAI, DATAF)
             VALUES (70, 'Responsable', 'Exemple', 'responsable@example.test', '2020-01-01 00:00:00', NULL)"
        );
    }
}
