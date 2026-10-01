<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceBeforePaymentBillingPartyRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentSelectionRepository;
use Prisma\Sif\Service\InvoiceBeforePaymentLegacyPreparationService;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentServerPayloadAssembler;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\PayloadIdempotencyValidator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentLegacyPreparationServiceTest
{
    public function testBuildsAuthoritativePreviewFromLegacyIdsAndEntityId(): void
    {
        $db = TestDatabase::fresh();
        $this->seedLegacyTables($db);

        $service = $this->service();
        $first = $service->prepare(
            $db,
            $db,
            [12, 11],
            7,
            [
                'created_by' => 'gestio-test',
                'fiscal_year' => 2026,
                'observations' => 'Factura abans de realitzar pagament',
            ]
        );
        $second = $service->prepare(
            $db,
            $db,
            [11, 12],
            7,
            [
                'created_by' => 'gestio-test',
                'fiscal_year' => 2026,
                'observations' => 'Factura abans de realitzar pagament',
            ]
        );

        (new InvoicePayloadValidator())->validate($first['payload']);

        Assert::same([11, 12], $first['selection']['ids']);
        Assert::same(2, $first['selection']['count']);
        Assert::same(7, $first['billing']['entity_id']);
        Assert::same('Escola Exemple SL', $first['billing']['name']);
        Assert::same('B12345678', $first['billing']['nif']);
        Assert::same('200.00', $first['payload']['totals']['total']);
        Assert::same('INTRANET', $first['payload']['source_channel']);
        Assert::same('gestio-test', $first['payload']['created_by']);
        Assert::same(1, $first['payload']['emesa_abans_cobrament']);
        Assert::same(11, $first['payload']['relations'][0]['source_id']);
        Assert::same(12, $first['payload']['relations'][1]['source_id']);
        Assert::same('ORIGIN', $first['payload']['relations'][0]['relation_type']);
        Assert::same('INSCRIPCIO', $first['payload']['lines'][0]['source_type']);
        Assert::same('80.00', $first['payload']['lines'][0]['total']);
        Assert::same('120.00', $first['payload']['lines'][1]['total']);
        Assert::same($first['fingerprint'], $second['fingerprint']);
        Assert::matchesRegularExpression('/^[a-f0-9]{64}$/', $first['fingerprint']);
        Assert::matchesRegularExpression(
            '/^INTRANET\|FACTURA_ABANS_COBRAR\|SEL:[a-f0-9]{40}$/',
            $first['payload']['idempotency_key']
        );
    }

    public function testFingerprintChangesWhenAuthoritativeAmountChanges(): void
    {
        $db = TestDatabase::fresh();
        $this->seedLegacyTables($db);
        $service = $this->service();

        $before = $service->prepare(
            $db,
            $db,
            [11, 12],
            7,
            ['created_by' => 'gestio-test', 'fiscal_year' => 2026]
        );

        $db->exec('UPDATE inscripcions SET A_PAGAR = 125.00 WHERE ID = 12');

        $after = $service->prepare(
            $db,
            $db,
            [11, 12],
            7,
            ['created_by' => 'gestio-test', 'fiscal_year' => 2026]
        );

        Assert::notSame($before['fingerprint'], $after['fingerprint']);
        Assert::same('205.00', $after['payload']['totals']['total']);
        Assert::same(
            $before['payload']['idempotency_key'],
            $after['payload']['idempotency_key']
        );
    }

    public function testRejectsMixedCourseOrEditionSelection(): void
    {
        $db = TestDatabase::fresh();
        $this->seedLegacyTables($db);
        $db->exec(
            "INSERT INTO curs (`ANY`, MES, CURS, NOM_CURS, HORES, DATAI, DATAF)
             VALUES (2026, '11', 'ALT', 'Curs alternatiu', '10', '2026-11-01', '2026-11-30')"
        );
        $db->exec(
            "INSERT INTO inscripcions (
                ID, IDPAG, `ANY`, MES, CURS, NOM, COGNOMS, DNI, CORREU,
                A_PAGAR, PAGAMENT, FACTURA_RELACIONADA, `INSC CURS`
             ) VALUES (
                13, 1003, 2026, '11', 'ALT', 'Tercera', 'Persona', '33333333C',
                'tercera@example.test', 50.00, 0.00, NULL, '1'
             )"
        );

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->prepare(
                $db,
                $db,
                [11, 13],
                7,
                ['created_by' => 'gestio-test', 'fiscal_year' => 2026]
            );
        }, 422);
    }

    public function testRejectsAlreadyPaidOrAlreadyInvoicedInscription(): void
    {
        $db = TestDatabase::fresh();
        $this->seedLegacyTables($db);

        $db->exec('UPDATE inscripcions SET PAGAMENT = 10.00 WHERE ID = 11');

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->prepare(
                $db,
                $db,
                [11],
                7,
                ['created_by' => 'gestio-test', 'fiscal_year' => 2026]
            );
        }, 409);

        $db->exec('UPDATE inscripcions SET PAGAMENT = 0.00, FACTURA_RELACIONADA = 55 WHERE ID = 11');

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->prepare(
                $db,
                $db,
                [11],
                7,
                ['created_by' => 'gestio-test', 'fiscal_year' => 2026]
            );
        }, 409);
    }

    public function testEntityIsResolvedByInternalIdNotVisibleName(): void
    {
        $db = TestDatabase::fresh();
        $this->seedLegacyTables($db);
        $db->exec(
            "INSERT INTO entitats (ID, CIF, RAO, ADRECA, CP, POBLACIO, ID_RESP)
             VALUES (8, 'B99999999', 'Escola Exemple SL', 'Carrer Dos 2', '08002', 'Barcelona', NULL)"
        );

        $prepared = $this->service()->prepare(
            $db,
            $db,
            [11],
            8,
            ['created_by' => 'gestio-test', 'fiscal_year' => 2026]
        );

        Assert::same(8, $prepared['billing']['entity_id']);
        Assert::same('B99999999', $prepared['billing']['nif']);
        Assert::same('B99999999', $prepared['payload']['billing']['nif']);
    }

    private function service(): InvoiceBeforePaymentLegacyPreparationService
    {
        return new InvoiceBeforePaymentLegacyPreparationService(
            new InvoiceBeforePaymentSelectionRepository(),
            new InvoiceBeforePaymentBillingPartyRepository(),
            new InvoiceBeforePaymentServerPayloadAssembler(),
            new InvoiceBeforePaymentPayloadBuilder(),
            new PayloadIdempotencyValidator()
        );
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
             ) VALUES
             (11, 1001, 2026, '10', 'DOL', 'Anna', 'Exemple', '11111111A',
              'anna@example.test', 80.00, 0.00, NULL, '1'),
             (12, 1002, 2026, '10', 'DOL', 'Berta', 'Mostra', '22222222B',
              'berta@example.test', 120.00, 0.00, NULL, '1')"
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
