<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\ManualGroupInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualGroupInvoicePayloadBuilderTest
{
    public function testBuildsIssueInvoicePaymentPayloadForManualGroupTransfer(): void
    {
        $db = TestDatabase::fresh();
        $payload = (new ManualGroupInvoicePayloadBuilder())->buildFromSnapshot(
            $this->groupSnapshot(['idpag' => 960]),
            [
                'amount' => '200',
                'movement_date' => '2026-06-11 09:15:00',
                'reference' => 'TRFGRUP960',
                'bank' => 'CAIXA',
                'notes' => 'Grup validat manualment a Passar pagaments',
                'created_by' => 'admin-grup',
            ]
        );

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('TRANSFERENCIA|GRUP|IDPAG:960|REF:TRFGRUP960', $payload['idempotency_key']);
        Assert::same('GRUP', $payload['source_type']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('admin-grup', $payload['created_by']);
        Assert::same(960, $payload['relations'][0]['idpag']);
        Assert::same(960, $payload['relations'][1]['idpag']);
        Assert::same(960, $payload['relations'][2]['idpag']);
        Assert::same('PAYMENT|TRANSFERENCIA|GRUP|IDPAG:960|REF:TRFGRUP960', $payload['payment']['idempotency_key']);
        Assert::same('TRANSFERENCIA', $payload['payment']['method']);
        Assert::same('INTRANET', $payload['payment']['source_channel']);
        Assert::same('200.00', $payload['payment']['amount']);
        Assert::same('2026-06-11 09:15:00', $payload['payment']['movement_date']);
        Assert::same('TRFGRUP960', $payload['payment']['reference']);
        Assert::same('TRFGRUP960', $payload['payment']['provider_ref']);
        Assert::same(960, $payload['payment']['idpag']);

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
        $groupRelation = $db->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, VISIBLE_ALUMNE FROM fact_rels WHERE SOURCE_TYPE = \'GRUP\'')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF, IDPAG, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|GRUP|IDPAG:960|REF:TRFGRUP960', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('200.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('GRUP', $groupRelation['SOURCE_TYPE']);
        Assert::same(960, (int) $groupRelation['SOURCE_ID']);
        Assert::same(960, (int) $groupRelation['IDPAG']);
        Assert::same(0, (int) $groupRelation['VISIBLE_ALUMNE']);
        Assert::same('PAYMENT|TRANSFERENCIA|GRUP|IDPAG:960|REF:TRFGRUP960', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('200.00', $payment['IMPORT']);
        Assert::same('TRFGRUP960', $payment['PROVIDER_REF']);
        Assert::same(960, (int) $payment['IDPAG']);
        Assert::same('TRFGRUP960', $payment['REFERENCIA_BANCARIA']);
    }

    public function testBuildsFallbackIdempotencyWhenManualGroupTransferHasNoReference(): void
    {
        $payload = (new ManualGroupInvoicePayloadBuilder())->buildFromSnapshot(
            $this->groupSnapshot(['idpag' => 961]),
            [
                'amount' => '200',
                'movement_date' => '2026-06-12',
                'reference' => '   ',
                'bank' => 'BANC TEST',
            ]
        );

        Assert::same(
            'TRANSFERENCIA|GRUP|IDPAG:961|DATA:2026-06-12|IMPORT:200.00|BANC:BANC_TEST',
            $payload['idempotency_key']
        );
        Assert::same(
            'PAYMENT|TRANSFERENCIA|GRUP|IDPAG:961|DATA:2026-06-12|IMPORT:200.00|BANC:BANC_TEST',
            $payload['payment']['idempotency_key']
        );
        Assert::same($payload['idempotency_key'], $payload['payment']['provider_ref']);

        (new InvoicePayloadValidator())->validate($payload);
    }

    public function testRejectsAmountDifferentFromGroupInvoiceTotal(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualGroupInvoicePayloadBuilder())->buildFromSnapshot(
                $this->groupSnapshot(['idpag' => 962]),
                [
                    'amount' => '199.99',
                    'movement_date' => '2026-06-12',
                ]
            );
        }, 422);
    }

    private function groupSnapshot(array $paymentOverrides = []): array
    {
        return [
            'responsible' => [
                'NOM' => 'Responsable',
                'COGNOMS' => 'Grup',
                'DNI' => '44444444G',
                'CORREU' => 'resp@example.test',
                'ADRECA' => 'Carrer Grup 4',
                'Codi_Postal' => '08004',
                'Poblacio' => 'Barcelona',
            ],
            'payment' => array_replace([
                'idpag' => 960,
                'amount' => '200.00',
            ], $paymentOverrides),
            'items' => [
                [
                    'inscription' => $this->inscription(761, 'Anna', 'Participant', '120.00'),
                    'course' => $this->course(),
                ],
                [
                    'inscription' => array_replace(
                        $this->inscription(762, 'Biel', 'Participant', '80.00'),
                        [
                            'IMPORT_BASE' => '100.00',
                            'DESC_IMPORT' => '20.00',
                            'DESC_ORIGEN' => 'GRUP',
                            'DESC_TEXT' => 'Descompte grup',
                        ]
                    ),
                    'course' => $this->course(),
                ],
            ],
        ];
    }

    private function inscription(int $id, string $name, string $surname, string $amount): array
    {
        return [
            'ID' => $id,
            'IDPAG' => 960,
            'ANY' => 2026,
            'MES' => '06',
            'CURS' => 'COM',
            'Grup' => 'A',
            'TIPUS_INSC' => 'G',
            'NOM' => $name,
            'COGNOMS' => $surname,
            'DNI' => $id . 'G',
            'CORREU' => strtolower($name) . '@example.test',
            'FACTURA_RELACIONADA' => $id + 9000,
            'A_PAGAR' => $amount,
            'PAGAMENT' => '0.00',
            'INSC CURS' => '1',
            'FRACCIO' => 0,
            'FRACCIONAT' => 0,
        ];
    }

    private function course(): array
    {
        return [
            'NOM_CURS' => 'Comunicacio assertiva',
            'DATAI' => '2026-06-10',
            'DATAF' => '2026-06-20',
            'HORES' => '12',
        ];
    }
}
