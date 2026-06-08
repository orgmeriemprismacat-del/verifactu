<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyCourseInvoicePayloadBuilderTest
{
    public function testBuildsBasePayloadFromLegacyCourseSnapshot(): void
    {
        $payload = (new LegacyCourseInvoicePayloadBuilder())->build($this->courseSnapshot());

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('LEGACY|CURS|INSCRIPCIO:300', $payload['idempotency_key']);
        Assert::same('A', $payload['series']);
        Assert::same(2026, $payload['year']);
        Assert::same('F1', $payload['type']);
        Assert::same('REDSYS', $payload['source_channel']);
        Assert::same('redsys-curs-normal', $payload['created_by']);

        Assert::same('Maria Exemple', $payload['billing']['name']);
        Assert::same('12345678Z', $payload['billing']['nif']);
        Assert::same('Carrer Exemple 1', $payload['billing']['address']);
        Assert::same('08001', $payload['billing']['cp']);
        Assert::same('Barcelona', $payload['billing']['city']);
        Assert::same('maria@example.test', $payload['billing']['email']);
        Assert::same('ES', $payload['billing']['country']);

        Assert::same('120.00', $payload['totals']['import_base']);
        Assert::same('120.00', $payload['totals']['taxable_base']);
        Assert::same('120.00', $payload['totals']['total']);
        Assert::same('EXEMPT', $payload['totals']['iva_regim']);
        Assert::same('0.00', $payload['totals']['iva_import']);

        Assert::same('Curs Gestio emocional', $payload['lines'][0]['concept']);
        Assert::same('Convocatoria 06 2026. Pagament fraccionat', $payload['lines'][0]['detail']);
        Assert::same('1.00', $payload['lines'][0]['quantity']);
        Assert::same('120.00', $payload['lines'][0]['unit_price']);
        Assert::same('INSCRIPCIO', $payload['lines'][0]['source_type']);
        Assert::same(300, $payload['lines'][0]['source_id']);

        Assert::same('INSCRIPCIO', $payload['relations'][0]['source_type']);
        Assert::same(300, $payload['relations'][0]['source_id']);
        Assert::same(700, $payload['relations'][0]['factura_relacionada']);
        Assert::same(1, $payload['relations'][0]['visible_alumne']);
    }

    public function testComposesWithValidatedRedsysNotificationAndIssueInvoicePayment(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $db,
            'ORDER300',
            300,
            '120.00',
            '0000',
            true,
            ['source' => 'test'],
            'VALIDATED'
        );

        $basePayload = (new LegacyCourseInvoicePayloadBuilder())->build($this->courseSnapshot());
        $payload = (new RedsysInvoicePayloadBuilder($notifications))
            ->buildFromValidatedNotification($db, 'ORDER300', $basePayload);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same('REDSYS|CURS|IDPAG:300|ORDER:ORDER300', $payload['idempotency_key']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $line = $db->query('SELECT CONCEPTE, DETALL, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia')
            ->fetch(\PDO::FETCH_ASSOC);
        $relation = $db->query('SELECT FACTURA_RELACIONADA, IDPAG, DS_ORDER FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('Curs Gestio emocional', $line['CONCEPTE']);
        Assert::same('Convocatoria 06 2026. Pagament fraccionat', $line['DETALL']);
        Assert::same('120.00', $line['TOTAL']);
        Assert::same('INSCRIPCIO', $line['SOURCE_TYPE']);
        Assert::same(300, (int) $line['SOURCE_ID']);
        Assert::same(700, (int) $relation['FACTURA_RELACIONADA']);
        Assert::same(300, (int) $relation['IDPAG']);
        Assert::same('ORDER300', $relation['DS_ORDER']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('120.00', $payment['IMPORT']);
        Assert::same('ORDER300', $payment['DS_ORDER']);
        Assert::same(300, (int) $payment['IDPAG']);
    }

    public function testFreezesPromotionalCodeDiscountSnapshotOnCourseLine(): void
    {
        $snapshot = $this->courseSnapshot();
        $snapshot['payment']['amount'] = '90.00';
        $snapshot['inscription']['A_PAGAR'] = '90.00';
        $snapshot['discount'] = [
            'origin' => 'CODI_PROMO',
            'mode' => 'PERCENT',
            'code' => 'MACABODETITULAR#300',
            'pct' => '25.00',
            'amount' => '30.00',
            'base' => '120.00',
            'text' => 'Descompte promocional aplicat',
            'internal_reason' => 'promocions.CODI_DESCOMPTE validat abans de Redsys',
        ];

        $payload = (new LegacyCourseInvoicePayloadBuilder())->build($snapshot);

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('120.00', $payload['totals']['import_base']);
        Assert::same('30.00', $payload['totals']['discount']);
        Assert::same('90.00', $payload['totals']['taxable_base']);
        Assert::same('90.00', $payload['totals']['total']);
        Assert::same('120.00', $payload['lines'][0]['import_base']);
        Assert::same('CODI_PROMO', $payload['lines'][0]['discount_origin']);
        Assert::same('PERCENT', $payload['lines'][0]['discount_mode']);
        Assert::same('MACABODETITULAR#300', $payload['lines'][0]['discount_code']);
        Assert::same('25.00', $payload['lines'][0]['discount_pct']);
        Assert::same('30.00', $payload['lines'][0]['discount_amount']);
        Assert::same('Descompte promocional aplicat', $payload['lines'][0]['discount_text']);
        Assert::same(
            'promocions.CODI_DESCOMPTE validat abans de Redsys',
            $payload['lines'][0]['discount_internal_reason']
        );
        Assert::same('90.00', $payload['lines'][0]['total']);
    }

    public function testPersistsPromotionalCodeDiscountFieldsWhenIssuedWithRedsys(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $snapshot = $this->courseSnapshot();
        $snapshot['payment']['amount'] = '90.00';
        $snapshot['inscription']['A_PAGAR'] = '90.00';
        $snapshot['discount'] = [
            'origin' => 'CODI_PROMO',
            'mode' => 'PERCENT',
            'code' => 'MACABODETITULAR#300',
            'pct' => '25.00',
            'amount' => '30.00',
            'base' => '120.00',
            'text' => 'Descompte promocional aplicat',
        ];

        $notifications->recordReceived(
            $db,
            'ORDERPROMO300',
            300,
            '90.00',
            '0000',
            true,
            ['source' => 'promo-test'],
            'VALIDATED'
        );

        $basePayload = (new LegacyCourseInvoicePayloadBuilder())->build($snapshot);
        $payload = (new RedsysInvoicePayloadBuilder($notifications))
            ->buildFromValidatedNotification($db, 'ORDERPROMO300', $basePayload);

        IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        $line = $db->query(
            'SELECT IMPORT_BASE, DESC_ORIGEN, DESC_MODE, DESC_CODI_PROMO,
                    DESC_PCT, DESC_IMPORT, DESC_TEXT_VISIBLE, TOTAL
             FROM factura_linia'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('120.00', $line['IMPORT_BASE']);
        Assert::same('CODI_PROMO', $line['DESC_ORIGEN']);
        Assert::same('PERCENT', $line['DESC_MODE']);
        Assert::same('MACABODETITULAR#300', $line['DESC_CODI_PROMO']);
        Assert::same('25.00', $line['DESC_PCT']);
        Assert::same('30.00', $line['DESC_IMPORT']);
        Assert::same('Descompte promocional aplicat', $line['DESC_TEXT_VISIBLE']);
        Assert::same('90.00', $line['TOTAL']);
    }

    public function testFreezesTemporaryPromotionDiscountIdFromSnapshot(): void
    {
        $snapshot = $this->courseSnapshot();
        $snapshot['payment']['amount'] = '96.00';
        $snapshot['inscription']['A_PAGAR'] = '96.00';
        $snapshot['discount'] = [
            'origin' => 'PROMOCIO_TEMPORAL',
            'mode' => 'PERCENT',
            'id' => 44,
            'pct' => '20.00',
            'amount' => '24.00',
            'base' => '120.00',
            'text' => 'Descompte promocional aplicat',
        ];

        $payload = (new LegacyCourseInvoicePayloadBuilder())->build($snapshot);

        Assert::same('PROMOCIO_TEMPORAL', $payload['lines'][0]['discount_origin']);
        Assert::same(44, $payload['lines'][0]['discount_id']);
        Assert::same('20.00', $payload['lines'][0]['discount_pct']);
        Assert::same('24.00', $payload['lines'][0]['discount_amount']);
        Assert::same('96.00', $payload['lines'][0]['total']);
    }

    public function testRequiresInscriptionIdentifier(): void
    {
        $snapshot = $this->courseSnapshot();
        unset($snapshot['inscription']['ID']);

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyCourseInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    public function testRequiresCurrentPaymentAmount(): void
    {
        $snapshot = $this->courseSnapshot();
        unset($snapshot['payment']);

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyCourseInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    private function courseSnapshot(): array
    {
        return [
            'inscription' => [
                'ID' => 300,
                'ANY' => 2026,
                'MES' => '06',
                'CURS' => 'ABC',
                'NOM' => 'Maria',
                'COGNOMS' => 'Exemple',
                'DNI' => '12345678Z',
                'CORREU' => 'maria@example.test',
                'ADRECA' => 'Carrer Exemple 1',
                'Codi_Postal' => '08001',
                'Poblacio' => 'Barcelona',
                'FACTURA_RELACIONADA' => 700,
                'A_PAGAR' => '240.00',
                'PAGAMENT' => '120.00',
                'FRACCIO' => 1,
            ],
            'course' => [
                'NOM_CURS' => 'Gestio emocional',
                'DATAI' => '2026-06-10',
                'DATAF' => '2026-06-20',
                'HORES' => '12',
            ],
            'payment' => [
                'amount' => '120.00',
            ],
        ];
    }
}
