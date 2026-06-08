<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\ManualCourseInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualCourseInvoicePayloadBuilderTest
{
    public function testBuildsIssueInvoicePaymentPayloadForManualCourseTransfer(): void
    {
        $db = TestDatabase::fresh();
        $payload = (new ManualCourseInvoicePayloadBuilder())->buildFromSnapshot(
            $this->courseSnapshot(['IDPAG' => 500]),
            [
                'amount' => '95.5',
                'movement_date' => '2026-06-06 12:30:00',
                'reference' => 'TRF500',
                'bank' => 'CAIXA',
                'notes' => 'Transferencia validada a Passar pagaments',
                'created_by' => 'admin-test',
            ]
        );

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('TRANSFERENCIA|CURS|IDPAG:500|REF:TRF500', $payload['idempotency_key']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('admin-test', $payload['created_by']);
        Assert::same(500, $payload['relations'][0]['idpag']);
        Assert::same('PAYMENT|TRANSFERENCIA|CURS|IDPAG:500|REF:TRF500', $payload['payment']['idempotency_key']);
        Assert::same('TRANSFERENCIA', $payload['payment']['method']);
        Assert::same('INTRANET', $payload['payment']['source_channel']);
        Assert::same('95.50', $payload['payment']['amount']);
        Assert::same('2026-06-06 12:30:00', $payload['payment']['movement_date']);
        Assert::same('TRF500', $payload['payment']['reference']);
        Assert::same('TRF500', $payload['payment']['provider_ref']);
        Assert::same(500, $payload['payment']['idpag']);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $invoice = $db->query('SELECT IDEMPOTENCY_KEY, SOURCE_CHANNEL, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF, IDPAG, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|CURS|IDPAG:500|REF:TRF500', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('95.50', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('PAYMENT|TRANSFERENCIA|CURS|IDPAG:500|REF:TRF500', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('95.50', $payment['IMPORT']);
        Assert::same('TRF500', $payment['PROVIDER_REF']);
        Assert::same(500, (int) $payment['IDPAG']);
        Assert::same('TRF500', $payment['REFERENCIA_BANCARIA']);
    }

    public function testBuildsFallbackIdempotencyWhenManualTransferHasNoReference(): void
    {
        $payload = (new ManualCourseInvoicePayloadBuilder())->buildFromSnapshot(
            $this->courseSnapshot(['IDPAG' => 501]),
            [
                'amount' => '50',
                'movement_date' => '2026-06-07',
                'reference' => '   ',
                'bank' => 'BANC TEST',
            ]
        );

        Assert::same(
            'TRANSFERENCIA|CURS|IDPAG:501|DATA:2026-06-07|IMPORT:50.00|BANC:BANC_TEST',
            $payload['idempotency_key']
        );
        Assert::same(
            'PAYMENT|TRANSFERENCIA|CURS|IDPAG:501|DATA:2026-06-07|IMPORT:50.00|BANC:BANC_TEST',
            $payload['payment']['idempotency_key']
        );
        Assert::same($payload['idempotency_key'], $payload['payment']['provider_ref']);

        (new InvoicePayloadValidator())->validate($payload);
    }

    public function testRejectsMissingManualCourseIdpag(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualCourseInvoicePayloadBuilder())->buildFromSnapshot(
                $this->courseSnapshot(),
                [
                    'amount' => '50',
                    'movement_date' => '2026-06-07',
                ]
            );
        }, 422);
    }

    private function courseSnapshot(array $inscriptionOverrides = []): array
    {
        return [
            'inscription' => array_replace([
                'ID' => 510,
                'ANY' => 2026,
                'MES' => '07',
                'CURS' => 'LM',
                'NOM' => 'Joan',
                'COGNOMS' => 'Mostra',
                'DNI' => '87654321Z',
                'CORREU' => 'joan@example.test',
                'ADRECA' => 'Carrer Musica 2',
                'Codi_Postal' => '08002',
                'Poblacio' => 'Barcelona',
                'FACTURA_RELACIONADA' => 810,
                'A_PAGAR' => '95.50',
                'INSC CURS' => '1',
                'PAGAMENT' => '0.00',
                'FRACCIO' => 0,
            ], $inscriptionOverrides),
            'course' => [
                'NOM_CURS' => 'Llenguatge musical',
                'DATAI' => '2026-07-01',
                'DATAF' => '2026-07-31',
                'HORES' => '20',
            ],
        ];
    }
}
