<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoiceBeforePaymentAeatInputPolicy;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentAeatSnapshotTest
{
    public function testQualifiedPolicyBuildsXsdValidOfficialSnapshotFromServerInput(): void
    {
        $db = TestDatabase::fresh();
        $input = Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:AEAT-910',
            'source_channel' => 'INTRANET',
            'created_by' => 'gestio-test',
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 910,
                'relation_type' => 'ORIGIN',
            ]],
            'lines' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 910,
            ]],
            'uc004_context' => [
                'legacy_concept1' => 'Curs Didàctica online, realitzat per: Anna Exemple',
                'legacy_concept2' => 'Convocatòria octubre 2026',
            ],
        ]);

        $policy = new InvoiceBeforePaymentAeatInputPolicy(
            'G17881988',
            'Associacio PrisMa',
            true,
            [
                'system_name' => 'SIF PrisMa',
                'system_id' => 'P1',
                'system_version' => '1.0.0',
                'installation_id' => 'PAY-PRE-01',
            ],
            [
                'tax_code' => '01',
                'regime_key' => '01',
                'exemption_reason' => 'E1',
            ]
        );

        $prepared = $policy->prepare($input);

        Assert::same('E1', $prepared['totals']['exemption_reason']);
        Assert::same('E1', $prepared['lines'][0]['exemption_reason']);
        Assert::same(
            'G17881988',
            $prepared['aeat_header']['ObligadoEmision']['NIF']
        );
        Assert::same(
            'E1',
            $prepared['aeat_fields']['Desglose']['DetalleDesglose'][0]['OperacionExenta']
        );
        Assert::same(
            '120.00',
            $prepared['aeat_fields']['Desglose']['DetalleDesglose'][0]['BaseImponibleOimporteNoSujeto']
        );

        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );
        $result = $service->issueBeforePayment($prepared);

        Assert::same(true, $result['ok']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());

        $record = json_decode(
            (string) $db->query('SELECT PAYLOAD_JSON FROM factura_registres LIMIT 1')->fetchColumn(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        Assert::same('RegistroAlta', $record['aeat']['type']);
        Assert::same('F1', $record['aeat']['record']['TipoFactura']);
        Assert::same('E1', $record['aeat']['record']['Desglose']['DetalleDesglose'][0]['OperacionExenta']);
        Assert::same('S', $record['aeat']['record']['SistemaInformatico']['TipoUsoPosibleSoloVerifactu']);
    }

    public function testQualifiedPolicyFailsClosedWithoutValidatedTaxMapping(): void
    {
        $policy = new InvoiceBeforePaymentAeatInputPolicy(
            'G17881988',
            'Associacio PrisMa',
            true,
            [
                'system_name' => 'SIF PrisMa',
                'system_id' => 'P1',
                'system_version' => '1.0.0',
                'installation_id' => 'PAY-PRE-01',
            ],
            []
        );

        Assert::throws(\RuntimeException::class, function () use ($policy): void {
            $policy->prepare(Fixtures::invoicePayload());
        });
    }

    public function testDevelopmentPolicyDoesNotInventAeatFields(): void
    {
        $input = Fixtures::invoicePayload();
        $prepared = (new InvoiceBeforePaymentAeatInputPolicy(
            'G00000000',
            'Associacio PrisMa',
            false
        ))->prepare($input);

        Assert::same(false, array_key_exists('aeat_fields', $prepared));
        Assert::same(false, array_key_exists('aeat_header', $prepared));
    }

    public function testRejectsClientOwnedAeatFieldsInQualifiedPolicy(): void
    {
        $policy = new InvoiceBeforePaymentAeatInputPolicy(
            'G17881988',
            'Associacio PrisMa',
            true,
            [
                'system_name' => 'SIF PrisMa',
                'system_id' => 'P1',
                'system_version' => '1.0.0',
                'installation_id' => 'PAY-PRE-01',
            ],
            [
                'tax_code' => '01',
                'regime_key' => '01',
                'exemption_reason' => 'E1',
            ]
        );

        Assert::throws(SifException::class, function () use ($policy): void {
            $policy->prepare(Fixtures::invoicePayload([
                'aeat_fields' => ['DescripcionOperacion' => 'spoof'],
            ]));
        }, 422);
    }
}
