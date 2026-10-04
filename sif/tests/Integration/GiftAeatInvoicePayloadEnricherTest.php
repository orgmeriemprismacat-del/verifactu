<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\GiftAeatInvoicePayloadEnricher;
use Prisma\Sif\Service\LegacyGiftInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftAeatInvoicePayloadEnricherTest
{
    public function testPreproductionGiftBuildsOfficialAeatSnapshotAndPersistsIt(): void
    {
        $previousEnv = getenv('SIF_ENV');
        putenv('SIF_ENV=PREPRODUCTION');

        try {
            $builder = new LegacyGiftInvoicePayloadBuilder(
                new GiftAeatInvoicePayloadEnricher(
                    [
                        'name' => 'EMISOR DE PRUEBAS',
                        'nif' => '89890001K',
                    ],
                    [
                        'producer_name' => 'PRODUCTOR DE PRUEBAS',
                        'producer_nif' => '89890001K',
                        'system_name' => 'SIF PrisMa TEST',
                        'system_id' => 'PM',
                        'system_version' => '0.3-TEST',
                        'installation_id' => 'LOCAL-TEST',
                        'gift_tax_code' => '01',
                        'gift_regime_key' => '01',
                        'gift_exemption_code' => 'E1',
                    ]
                )
            );

            $payload = $builder->build($this->snapshot());

            Assert::same('89890001K', $payload['aeat_header']['ObligadoEmision']['NIF']);
            Assert::same(
                'Curs regal Comunicacio assertiva',
                $payload['aeat_fields']['DescripcionOperacion']
            );
            Assert::same(
                'E1',
                $payload['aeat_fields']['Desglose']['DetalleDesglose'][0]['OperacionExenta']
            );
            Assert::same(
                '120.00',
                $payload['aeat_fields']['Desglose']['DetalleDesglose'][0]['BaseImponibleOimporteNoSujeto']
            );
            Assert::same(
                'PM',
                $payload['aeat_fields']['SistemaInformatico']['IdSistemaInformatico']
            );

            $db = TestDatabase::fresh();
            $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);
            Assert::same(true, $result['ok']);

            $stored = json_decode(
                (string) $db->query('SELECT PAYLOAD_JSON FROM factura_registres LIMIT 1')
                    ->fetchColumn(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            Assert::same('RegistroAlta', $stored['aeat']['type']);
            Assert::same(
                '89890001K',
                $stored['aeat']['record']['IDFactura']['IDEmisorFactura']
            );
            Assert::same(
                'E1',
                $stored['aeat']['record']['Desglose']['DetalleDesglose'][0]['OperacionExenta']
            );
            Assert::same(
                'S',
                $stored['aeat']['record']['SistemaInformatico']['TipoUsoPosibleSoloVerifactu']
            );
        } finally {
            if ($previousEnv === false) {
                putenv('SIF_ENV');
            } else {
                putenv('SIF_ENV=' . $previousEnv);
            }
        }
    }

    public function testPreproductionGiftFailsClosedWithoutConfiguredAeatContext(): void
    {
        $previousEnv = getenv('SIF_ENV');
        putenv('SIF_ENV=PREPRODUCTION');

        try {
            Assert::throws(
                \RuntimeException::class,
                fn (): array => (new LegacyGiftInvoicePayloadBuilder())->build($this->snapshot())
            );

            Assert::throws(
                SifException::class,
                fn (): array => (new LegacyGiftInvoicePayloadBuilder(
                    new GiftAeatInvoicePayloadEnricher([], [])
                ))->build($this->snapshot()),
                422
            );
        } finally {
            if ($previousEnv === false) {
                putenv('SIF_ENV');
            } else {
                putenv('SIF_ENV=' . $previousEnv);
            }
        }
    }

    private function snapshot(): array
    {
        return [
            'gift' => [
                'ID' => 77,
                'NOM_CURS' => 'Comunicacio assertiva',
                'CCURS' => 'COM',
                'NOMC' => 'Compradora Regal',
                'NIFC' => '55555555R',
                'MAILC' => 'compradora@example.test',
                'ADRECAC' => 'Carrer Regal 5',
                'POBLEC' => 'Barcelona',
                'CPC' => '08005',
                'CODI' => 'REGAL-77',
                'IMPORT' => '120.00',
                'FACT_REL' => 0,
                'ORIGEN' => 'Compradora Regal',
                'DESTI' => 'Destinatari Regal',
                'OBSERVACIONS' => 'Dedicatoria',
            ],
        ];
    }
}
