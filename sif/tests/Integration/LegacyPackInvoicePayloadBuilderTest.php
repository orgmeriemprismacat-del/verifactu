<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyPackInvoicePayloadBuilderTest
{
    public function testBuildsBasePayloadFromLegacyPackSnapshot(): void
    {
        $payload = (new LegacyPackInvoicePayloadBuilder())->build($this->packSnapshot());

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('LEGACY|PACK|IDPAG:900', $payload['idempotency_key']);
        Assert::same('A', $payload['series']);
        Assert::same(2026, $payload['year']);
        Assert::same('F1', $payload['type']);
        Assert::same('REDSYS', $payload['source_channel']);
        Assert::same('redsys-pack', $payload['created_by']);

        Assert::same('Maria Exemple', $payload['billing']['name']);
        Assert::same('12345678Z', $payload['billing']['nif']);
        Assert::same('maria@example.test', $payload['billing']['email']);

        Assert::same('240.00', $payload['totals']['import_base']);
        Assert::same('30.00', $payload['totals']['discount']);
        Assert::same('210.00', $payload['totals']['taxable_base']);
        Assert::same('210.00', $payload['totals']['total']);

        Assert::same(2, count($payload['lines']));
        Assert::same('Pack Benestar docent - Gestio emocional', $payload['lines'][0]['concept']);
        Assert::same('Convocatoria 06 2026', $payload['lines'][0]['detail']);
        Assert::same('120.00', $payload['lines'][0]['import_base']);
        Assert::same('0.00', $payload['lines'][0]['discount_amount']);
        Assert::same('120.00', $payload['lines'][0]['total']);
        Assert::same('INSCRIPCIO', $payload['lines'][0]['source_type']);
        Assert::same(301, $payload['lines'][0]['source_id']);

        Assert::same('Pack Benestar docent - Mindfulness a l aula', $payload['lines'][1]['concept']);
        Assert::same('PACK', $payload['lines'][1]['discount_origin']);
        Assert::same('PERCENT', $payload['lines'][1]['discount_mode']);
        Assert::same('25.00', $payload['lines'][1]['discount_pct']);
        Assert::same('30.00', $payload['lines'][1]['discount_amount']);
        Assert::same('Descompte pack 25%', $payload['lines'][1]['discount_text']);
        Assert::same('120.00', $payload['lines'][1]['import_base']);
        Assert::same('90.00', $payload['lines'][1]['taxable_base']);
        Assert::same('90.00', $payload['lines'][1]['total']);
        Assert::same('INSCRIPCIO', $payload['lines'][1]['source_type']);
        Assert::same(302, $payload['lines'][1]['source_id']);

        Assert::same(3, count($payload['relations']));
        Assert::same('PACK', $payload['relations'][0]['source_type']);
        Assert::same(44, $payload['relations'][0]['source_id']);
        Assert::same(900, $payload['relations'][0]['idpag']);
        Assert::same('INSCRIPCIO', $payload['relations'][1]['source_type']);
        Assert::same(301, $payload['relations'][1]['source_id']);
        Assert::same(900, $payload['relations'][1]['idpag']);
        Assert::same('INSCRIPCIO', $payload['relations'][2]['source_type']);
        Assert::same(302, $payload['relations'][2]['source_id']);
        Assert::same(900, $payload['relations'][2]['idpag']);
    }

    public function testComposesWithValidatedRedsysNotificationAndIssueInvoicePayment(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $db,
            'ORDERPACK900',
            900,
            '210.00',
            '0000',
            true,
            ['source' => 'pack-test'],
            'VALIDATED'
        );

        $basePayload = (new LegacyPackInvoicePayloadBuilder())->build($this->packSnapshot());
        $payload = (new RedsysInvoicePayloadBuilder($notifications))
            ->buildFromValidatedNotification($db, 'ORDERPACK900', $basePayload);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same('REDSYS|PACK|IDPAG:900|ORDER:ORDERPACK900', $payload['idempotency_key']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $discountLine = $db->query('SELECT DESC_ORIGEN, DESC_PCT, DESC_IMPORT, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia WHERE ORDRE = 2')
            ->fetch(\PDO::FETCH_ASSOC);
        $packRelation = $db->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER FROM fact_rels WHERE SOURCE_TYPE = \'PACK\'')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('PACK', $discountLine['DESC_ORIGEN']);
        Assert::same('25.00', $discountLine['DESC_PCT']);
        Assert::same('30.00', $discountLine['DESC_IMPORT']);
        Assert::same('90.00', $discountLine['TOTAL']);
        Assert::same('INSCRIPCIO', $discountLine['SOURCE_TYPE']);
        Assert::same(302, (int) $discountLine['SOURCE_ID']);
        Assert::same('PACK', $packRelation['SOURCE_TYPE']);
        Assert::same(44, (int) $packRelation['SOURCE_ID']);
        Assert::same(900, (int) $packRelation['IDPAG']);
        Assert::same('ORDERPACK900', $packRelation['DS_ORDER']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('210.00', $payment['IMPORT']);
        Assert::same('ORDERPACK900', $payment['DS_ORDER']);
        Assert::same(900, (int) $payment['IDPAG']);
    }

    public function testRequiresAtLeastTwoPackLines(): void
    {
        $snapshot = $this->packSnapshot();
        array_pop($snapshot['items']);

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyPackInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    public function testRequiresPackIdentifier(): void
    {
        $snapshot = $this->packSnapshot();
        unset($snapshot['pack']['ID_PACK']);

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyPackInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    private function packSnapshot(): array
    {
        return [
            'pack' => [
                'ID_PACK' => 44,
                'TITOL' => 'Benestar docent',
            ],
            'payment' => [
                'amount' => '210.00',
                'idpag' => 900,
            ],
            'items' => [
                [
                    'inscription' => [
                        'ID' => 301,
                        'IDPAG' => 900,
                        'ANY' => 2026,
                        'MES' => '06',
                        'CURS' => 'ABC',
                        'TIPUS_INSC' => 'P',
                        'NOM' => 'Maria',
                        'COGNOMS' => 'Exemple',
                        'DNI' => '12345678Z',
                        'CORREU' => 'maria@example.test',
                        'ADRECA' => 'Carrer Exemple 1',
                        'Codi_Postal' => '08001',
                        'Poblacio' => 'Barcelona',
                        'FACTURA_RELACIONADA' => 701,
                        'A_PAGAR' => '120.00',
                        'PAGAMENT' => '0.00',
                        'FRACCIO' => 0,
                    ],
                    'course' => [
                        'NOM_CURS' => 'Gestio emocional',
                        'DATAI' => '2026-06-10',
                        'DATAF' => '2026-06-20',
                        'HORES' => '12',
                    ],
                ],
                [
                    'inscription' => [
                        'ID' => 302,
                        'IDPAG' => 900,
                        'ANY' => 2026,
                        'MES' => '07',
                        'CURS' => 'DEF',
                        'TIPUS_INSC' => 'P',
                        'NOM' => 'Maria',
                        'COGNOMS' => 'Exemple',
                        'DNI' => '12345678Z',
                        'CORREU' => 'maria@example.test',
                        'ADRECA' => 'Carrer Exemple 1',
                        'Codi_Postal' => '08001',
                        'Poblacio' => 'Barcelona',
                        'FACTURA_RELACIONADA' => 702,
                        'A_PAGAR' => '90.00',
                        'PAGAMENT' => '0.00',
                        'FRACCIO' => 0,
                    ],
                    'course' => [
                        'NOM_CURS' => 'Mindfulness a l aula',
                        'DATAI' => '2026-07-10',
                        'DATAF' => '2026-07-20',
                        'HORES' => '12',
                    ],
                ],
            ],
        ];
    }
}
