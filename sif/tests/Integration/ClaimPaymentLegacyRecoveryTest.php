<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\ClaimPaymentExternalReceiptRepository;
use Prisma\Sif\Service\ClaimPaymentLegacySyncService;
use Prisma\Sif\Service\ClaimPaymentReceiptResolver;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ClaimPaymentLegacyRecoveryTest
{
    public function testRetryReusesPersistedSifReceiptAndCompletesLegacyProjection(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|RECOVERY|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $created = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'CLAIM|RECEIPT:BANK_REFERENCE:RECOVERY-40',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '40.00',
            'movement_date' => '2026-10-04 04:00:00',
            'reference' => 'RECOVERY-40',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '40.00',
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);

        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn()
        );

        // Simula que el commit SIF ja es va fer però el legacy encara conserva
        // el baseline anterior perquè la projecció va fallar.
        $legacy = new ClaimPaymentLegacySyncSpyPdo('120.00', '0.00');

        $reused = (new ClaimPaymentReceiptResolver(
            new ClaimPaymentExternalReceiptRepository()
        ))->resolveExisting(
            $db,
            'BANK_REFERENCE',
            'RECOVERY-40',
            $invoice['uuid_factura'],
            '40.00',
            123
        );

        Assert::same(true, $reused['idempotency_reused']);
        Assert::same(true, $reused['reconciled_existing']);
        Assert::same($created['uuid_payment'], $reused['uuid_payment']);

        $projection = (new ClaimPaymentLegacySyncService())->syncAfterSifSuccess(
            $db,
            $legacy,
            10,
            123,
            $invoice['uuid_factura'],
            $invoice['num_visible'],
            '40.00'
        );

        Assert::same('40.00', $projection['projected_payment']);
        Assert::same('PARTIALLY_PAID', $projection['status']);
        Assert::same('40.00', $legacy->updatedPayment);

        // El retry només reutilitza el fet econòmic ja persistent.
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn()
        );
    }
}
