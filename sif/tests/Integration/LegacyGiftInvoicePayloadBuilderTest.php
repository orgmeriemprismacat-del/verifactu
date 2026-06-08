<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\LegacyGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyGiftInvoicePayloadBuilderTest
{
    public function testBuildsBasePayloadFromLegacyGiftSnapshot(): void
    {
        $payload = (new LegacyGiftInvoicePayloadBuilder())->build($this->giftSnapshot());

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('LEGACY|REGAL|ID:77', $payload['idempotency_key']);
        Assert::same('REGAL', $payload['source_type']);
        Assert::same('REDSYS', $payload['source_channel']);
        Assert::same('legacy-gift', $payload['created_by']);
        Assert::same('Compradora Regal', $payload['billing']['name']);
        Assert::same('55555555R', $payload['billing']['nif']);
        Assert::same('compradora@example.test', $payload['billing']['email']);
        Assert::same('120.00', $payload['totals']['import_base']);
        Assert::same('120.00', $payload['totals']['total']);

        Assert::same(1, count($payload['lines']));
        Assert::same('Curs regal Comunicacio assertiva', $payload['lines'][0]['concept']);
        Assert::same('Codi regal REGAL-77', $payload['lines'][0]['detail']);
        Assert::same('120.00', $payload['lines'][0]['import_base']);
        Assert::same('120.00', $payload['lines'][0]['total']);
        Assert::same('REGAL', $payload['lines'][0]['source_type']);
        Assert::same(77, $payload['lines'][0]['source_id']);

        Assert::same(1, count($payload['relations']));
        Assert::same('REGAL', $payload['relations'][0]['source_type']);
        Assert::same(77, $payload['relations'][0]['source_id']);
        Assert::same(0, $payload['relations'][0]['factura_relacionada']);
        Assert::same(0, $payload['relations'][0]['visible_alumne']);

        Assert::same('REGAL-77', $payload['gift']['code']);
        Assert::same('Compradora Regal', $payload['gift']['buyer_name']);
        Assert::same('Destinatari Regal', $payload['gift']['recipient_name']);
    }

    public function testComposesWithValidatedRedsysNotificationAndIssueInvoicePayment(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $db,
            'ORDERGIFT77',
            null,
            '120.00',
            '0000',
            true,
            ['source' => 'gift-test'],
            'VALIDATED'
        );

        $basePayload = (new LegacyGiftInvoicePayloadBuilder())->build($this->giftSnapshot());
        $payload = (new RedsysInvoicePayloadBuilder($notifications))
            ->buildFromValidatedNotification($db, 'ORDERGIFT77', $basePayload);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same('REDSYS|REGAL|IDPAG:NULL|ORDER:ORDERGIFT77', $payload['idempotency_key']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $line = $db->query('SELECT CONCEPTE, DETALL, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia')
            ->fetch(\PDO::FETCH_ASSOC);
        $relation = $db->query('SELECT SOURCE_TYPE, SOURCE_ID, DS_ORDER, IDPAG FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('Curs regal Comunicacio assertiva', $line['CONCEPTE']);
        Assert::same('Codi regal REGAL-77', $line['DETALL']);
        Assert::same('120.00', $line['TOTAL']);
        Assert::same('REGAL', $line['SOURCE_TYPE']);
        Assert::same(77, (int) $line['SOURCE_ID']);
        Assert::same('REGAL', $relation['SOURCE_TYPE']);
        Assert::same(77, (int) $relation['SOURCE_ID']);
        Assert::same('ORDERGIFT77', $relation['DS_ORDER']);
        Assert::same(null, $relation['IDPAG']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('120.00', $payment['IMPORT']);
        Assert::same('ORDERGIFT77', $payment['DS_ORDER']);
        Assert::same(null, $payment['IDPAG']);
    }

    public function testRequiresGiftIdentifier(): void
    {
        $snapshot = $this->giftSnapshot();
        unset($snapshot['gift']['ID']);

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyGiftInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    public function testRequiresBuyerNif(): void
    {
        $snapshot = $this->giftSnapshot();
        unset($snapshot['gift']['NIFC']);

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyGiftInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    private function giftSnapshot(): array
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
                'OBSERVACIONS' => 'Dedicatoria comercial',
            ],
        ];
    }
}
