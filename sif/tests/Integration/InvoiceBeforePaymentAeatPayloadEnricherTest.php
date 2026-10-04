<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Aeat\XmlCodec;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoiceBeforePaymentAeatPayloadEnricher;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentServerPayloadAssembler;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentAeatPayloadEnricherTest
{
    public function testPreproductionUc021BuildsAndPersistsXsdValidOfficialAeatSnapshot(): void
    {
        $db = TestDatabase::fresh();

        $assembler = new InvoiceBeforePaymentServerPayloadAssembler(
            new InvoiceBeforePaymentAeatPayloadEnricher(
                'preproduction',
                [
                    'name' => 'Associacio PrisMa',
                    'nif' => 'G17881988',
                ],
                [
                    'issuer_nif' => 'G17881988',
                    'system_name' => 'SIF PrisMa',
                    'system_id' => 'PM',
                    'system_version' => '0.3-BORRADOR',
                    'installation_id' => 'UC021-TEST',
                ]
            )
        );

        $input = $assembler->buildInput(
            [
                [
                    'ID' => 901,
                    'IDPAG' => 1901,
                    'ANY' => 2026,
                    'MES' => '10',
                    'CURS' => 'UC21',
                    'NOM_CURS' => 'Curs de prova UC-021',
                    'HORES' => '30',
                    'NOM' => 'Anna',
                    'COGNOMS' => 'Exemple',
                    'A_PAGAR' => '80.00',
                    'PAGAMENT' => '0.00',
                    'FACTURA_RELACIONADA' => null,
                    'INSC_CURS' => '1',
                ],
                [
                    'ID' => 902,
                    'IDPAG' => 1902,
                    'ANY' => 2026,
                    'MES' => '10',
                    'CURS' => 'UC21',
                    'NOM_CURS' => 'Curs de prova UC-021',
                    'HORES' => '30',
                    'NOM' => 'Berta',
                    'COGNOMS' => 'Mostra',
                    'A_PAGAR' => '120.00',
                    'PAGAMENT' => '0.00',
                    'FACTURA_RELACIONADA' => null,
                    'INSC_CURS' => '1',
                ],
            ],
            [
                'entity_id' => 77,
                'name' => 'Escola Exemple SL',
                'nif' => 'B12345678',
                'address' => 'Carrer Exemple 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'province' => 'Barcelona',
                'country' => 'ES',
                'email' => 'responsable@example.test',
            ],
            [
                'created_by' => 'uc021-aeat-test',
                'fiscal_year' => 2026,
            ]
        );

        Assert::same('E1', $input['totals']['exemption_reason']);
        Assert::same('E1', $input['lines'][0]['exemption_reason']);
        Assert::same('E1', $input['lines'][1]['exemption_reason']);
        Assert::same('G17881988', $input['aeat_header']['ObligadoEmision']['NIF']);
        Assert::same(
            'E1',
            $input['aeat_fields']['Desglose']['DetalleDesglose'][0]['OperacionExenta']
        );
        Assert::same(
            '200.00',
            $input['aeat_fields']['Desglose']['DetalleDesglose'][0]['BaseImponibleOimporteNoSujeto']
        );
        Assert::same(
            'SIF PrisMa',
            $input['aeat_fields']['SistemaInformatico']['NombreSistemaInformatico']
        );

        $payload = (new InvoiceBeforePaymentPayloadBuilder())->build($input);
        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        $previousEnv = getenv('SIF_ENV');
        putenv('SIF_ENV=preproduction');
        try {
            $issued = $service->issueBeforePayment($payload);
        } finally {
            if ($previousEnv === false) {
                putenv('SIF_ENV');
            } else {
                putenv('SIF_ENV=' . $previousEnv);
            }
        }

        Assert::same(true, $issued['ok']);

        $json = (string) $db->query(
            "SELECT PAYLOAD_JSON
             FROM factura_registres
             WHERE UUID_FACTURA = " . $db->quote((string) $issued['uuid_factura'])
        )->fetchColumn();
        $record = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        Assert::same(true, isset($record['aeat']) && is_array($record['aeat']));
        Assert::same('RegistroAlta', $record['aeat']['type']);
        Assert::same('G17881988', $record['aeat']['record']['IDFactura']['IDEmisorFactura']);
        Assert::same('E1', $record['aeat']['record']['Desglose']['DetalleDesglose'][0]['OperacionExenta']);

        $xml = (new XmlCodec())->request($record['aeat']);
        Assert::stringContainsString('RegistroAlta', $xml);
        Assert::stringContainsString('OperacionExenta', $xml);
        Assert::stringContainsString('G17881988', $xml);
    }

    public function testDevelopmentDoesNotRequireOrAddOfficialAeatConfiguration(): void
    {
        $payload = (new InvoiceBeforePaymentAeatPayloadEnricher(
            'development',
            [],
            []
        ))->enrich([
            'lines' => [],
        ]);

        Assert::same(false, array_key_exists('aeat_fields', $payload));
        Assert::same(false, array_key_exists('aeat_header', $payload));
    }

    public function testPreproductionFailsClosedWhenIssuerConfigurationDisagrees(): void
    {
        $enricher = new InvoiceBeforePaymentAeatPayloadEnricher(
            'preproduction',
            [
                'name' => 'Associacio PrisMa',
                'nif' => 'G17881988',
            ],
            [
                'issuer_nif' => 'G00000000',
                'system_name' => 'SIF PrisMa',
                'system_id' => 'PM',
                'system_version' => '0.3-BORRADOR',
                'installation_id' => 'UC021-TEST',
            ]
        );

        Assert::throws(SifException::class, function () use ($enricher): void {
            $enricher->enrich([
                'lines' => [[
                    'iva_regim' => 'EXEMPT',
                    'exemption_reason' => 'E1',
                    'taxable_base' => '100.00',
                    'concept' => 'Curs',
                ]],
            ]);
        }, 409);
    }
}
