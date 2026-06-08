<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\ManualPackInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualPackInvoicePayloadBuilderTest
{
    public function testBuildsIssueInvoicePaymentPayloadForManualPackTransfer(): void
    {
        $db = TestDatabase::fresh();
        $payload = (new ManualPackInvoicePayloadBuilder())->buildFromSnapshot(
            $this->packSnapshot(['IDPAG' => 920]),
            [
                'amount' => '210',
                'movement_date' => '2026-06-08 10:15:00',
                'reference' => 'TRFPACK920',
                'bank' => 'CAIXA',
                'notes' => 'Pack validat manualment a Passar pagaments',
                'created_by' => 'admin-pack',
            ]
        );

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('TRANSFERENCIA|PACK|IDPAG:920|REF:TRFPACK920', $payload['idempotency_key']);
        Assert::same('PACK', $payload['source_type']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('admin-pack', $payload['created_by']);
        Assert::same(920, $payload['relations'][0]['idpag']);
        Assert::same(920, $payload['relations'][1]['idpag']);
        Assert::same(920, $payload['relations'][2]['idpag']);
        Assert::same('PAYMENT|TRANSFERENCIA|PACK|IDPAG:920|REF:TRFPACK920', $payload['payment']['idempotency_key']);
        Assert::same('TRANSFERENCIA', $payload['payment']['method']);
        Assert::same('INTRANET', $payload['payment']['source_channel']);
        Assert::same('210.00', $payload['payment']['amount']);
        Assert::same('2026-06-08 10:15:00', $payload['payment']['movement_date']);
        Assert::same('TRFPACK920', $payload['payment']['reference']);
        Assert::same('TRFPACK920', $payload['payment']['provider_ref']);
        Assert::same(920, $payload['payment']['idpag']);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $invoice = $db->query('SELECT IDEMPOTENCY_KEY, SOURCE_CHANNEL, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $discountLine = $db->query('SELECT DESC_ORIGEN, DESC_PCT, DESC_IMPORT, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia WHERE ORDRE = 2')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF, IDPAG, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|PACK|IDPAG:920|REF:TRFPACK920', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('210.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('PACK', $discountLine['DESC_ORIGEN']);
        Assert::same('25.00', $discountLine['DESC_PCT']);
        Assert::same('30.00', $discountLine['DESC_IMPORT']);
        Assert::same('90.00', $discountLine['TOTAL']);
        Assert::same('INSCRIPCIO', $discountLine['SOURCE_TYPE']);
        Assert::same(322, (int) $discountLine['SOURCE_ID']);
        Assert::same('PAYMENT|TRANSFERENCIA|PACK|IDPAG:920|REF:TRFPACK920', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('210.00', $payment['IMPORT']);
        Assert::same('TRFPACK920', $payment['PROVIDER_REF']);
        Assert::same(920, (int) $payment['IDPAG']);
        Assert::same('TRFPACK920', $payment['REFERENCIA_BANCARIA']);
    }

    public function testBuildsFallbackIdempotencyWhenManualPackTransferHasNoReference(): void
    {
        $payload = (new ManualPackInvoicePayloadBuilder())->buildFromSnapshot(
            $this->packSnapshot(['IDPAG' => 921]),
            [
                'amount' => '210',
                'movement_date' => '2026-06-09',
                'reference' => '   ',
                'bank' => 'BANC TEST',
            ]
        );

        Assert::same(
            'TRANSFERENCIA|PACK|IDPAG:921|DATA:2026-06-09|IMPORT:210.00|BANC:BANC_TEST',
            $payload['idempotency_key']
        );
        Assert::same(
            'PAYMENT|TRANSFERENCIA|PACK|IDPAG:921|DATA:2026-06-09|IMPORT:210.00|BANC:BANC_TEST',
            $payload['payment']['idempotency_key']
        );
        Assert::same($payload['idempotency_key'], $payload['payment']['provider_ref']);

        (new InvoicePayloadValidator())->validate($payload);
    }

    public function testRejectsMissingManualPackIdpag(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualPackInvoicePayloadBuilder())->buildFromSnapshot(
                $this->packSnapshot(['idpag' => null, 'IDPAG' => null], ['IDPAG' => null]),
                [
                    'amount' => '210',
                    'movement_date' => '2026-06-09',
                ]
            );
        }, 422);
    }

    private function packSnapshot(array $paymentOverrides = [], array $inscriptionOverrides = []): array
    {
        return [
            'pack' => [
                'ID_PACK' => 52,
                'TITOL' => 'Benestar docent',
            ],
            'payment' => array_replace([
                'amount' => '210.00',
                'idpag' => 920,
            ], $paymentOverrides),
            'items' => [
                [
                    'inscription' => array_replace(
                        $this->inscription(321, '06', 'ABC', '120.00', 801),
                        $inscriptionOverrides
                    ),
                    'course' => [
                        'NOM_CURS' => 'Gestio emocional',
                        'DATAI' => '2026-06-10',
                        'DATAF' => '2026-06-20',
                        'HORES' => '12',
                    ],
                ],
                [
                    'inscription' => array_replace(
                        $this->inscription(322, '07', 'DEF', '90.00', 802),
                        $inscriptionOverrides
                    ),
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

    private function inscription(int $id, string $month, string $course, string $amount, int $facturaRelacionada): array
    {
        return [
            'ID' => $id,
            'IDPAG' => 920,
            'ANY' => 2026,
            'MES' => $month,
            'CURS' => $course,
            'TIPUS_INSC' => 'P',
            'NOM' => 'Maria',
            'COGNOMS' => 'Exemple',
            'DNI' => '12345678Z',
            'CORREU' => 'maria@example.test',
            'ADRECA' => 'Carrer Exemple 1',
            'Codi_Postal' => '08001',
            'Poblacio' => 'Barcelona',
            'FACTURA_RELACIONADA' => $facturaRelacionada,
            'A_PAGAR' => $amount,
            'PAGAMENT' => '0.00',
            'FRACCIO' => 0,
        ];
    }
}
