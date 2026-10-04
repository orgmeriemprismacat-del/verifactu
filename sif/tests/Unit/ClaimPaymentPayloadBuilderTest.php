<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ClaimPaymentPayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentPayloadBuilderTest
{
    public function testBuildsClaimPaymentPayloadWithClaimReference(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '80',
                'movement_date' => '2026-06-13 12:00:00',
                'claim_reference' => 'REC-2026-001',
                'bank' => 'CAIXA',
                'created_by' => 'admin-cobraments',
                'notes' => 'Pagament rebut despres de reclamacio',
            ]
        );

        Assert::same('CLAIM|REF:REC-2026-001', $payload['idempotency_key']);
        Assert::same('CHARGE', $payload['movement_type']);
        Assert::same('TRANSFERENCIA', $payload['method']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('80.00', $payload['amount']);
        Assert::same('2026-06-13 12:00:00', $payload['movement_date']);
        Assert::same('REC-2026-001', $payload['reference']);
        Assert::same('CAIXA', $payload['bank']);
        Assert::same('admin-cobraments', $payload['created_by']);
        Assert::same('11111111-1111-4111-8111-111111111111', $payload['allocations'][0]['uuid_factura']);
        Assert::same('80.00', $payload['allocations'][0]['amount']);
        Assert::same('CLAIM_PAYMENT', $payload['allocations'][0]['allocation_type']);

        (new PaymentPayloadValidator())->validate($payload);
    }

    public function testTypedBankReceiptUsesTypeInIdempotencyAndBankReference(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '40.00',
                'movement_date' => '2026-10-04 02:20:00',
                'external_receipt_type' => 'BANK_REFERENCE',
                'external_receipt_id' => 'BAN-101',
                'created_by' => 'gestio-test',
            ]
        );

        Assert::same(
            'CLAIM|RECEIPT:BANK_REFERENCE:BAN-101',
            $payload['idempotency_key']
        );
        Assert::same('BAN-101', $payload['reference']);
    }

    public function testTypedRedsysReceiptMapsToDsOrderInsteadOfBankReference(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '40.00',
                'movement_date' => '2026-10-04 02:20:00',
                'external_receipt_type' => 'DS_ORDER',
                'external_receipt_id' => '123456789012',
                'created_by' => 'gestio-test',
            ]
        );

        Assert::same(
            'CLAIM|RECEIPT:DS_ORDER:123456789012',
            $payload['idempotency_key']
        );
        Assert::same('123456789012', $payload['ds_order']);
        Assert::same(false, array_key_exists('reference', $payload));
    }

    public function testCarriesAuthoritativeIdpagIntoPaymentPayload(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '20.00',
                'movement_date' => '2026-10-04 02:55:00',
                'external_receipt_type' => 'BANK_REFERENCE',
                'external_receipt_id' => 'BANK-IDPAG-123',
                'idpag' => 123,
                'created_by' => 'gestio-test',
            ]
        );

        Assert::same(123, $payload['idpag']);
    }

    public function testExternalReceiptIdUsesDedicatedIdempotencyFamily(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '40.00',
                'movement_date' => '2026-10-04 02:20:00',
                'external_receipt_id' => 'BAN-101',
                'claim_reference' => 'CLAIM-7',
                'created_by' => 'gestio-test',
            ]
        );

        Assert::same('CLAIM|RECEIPT:BAN-101', $payload['idempotency_key']);
        Assert::same('BAN-101', $payload['reference']);
        Assert::same('40.00', $payload['amount']);
        Assert::same('CLAIM_PAYMENT', $payload['allocations'][0]['allocation_type']);
    }

    public function testBuildsFallbackIdempotencyWithRequiredUserWhenReferenceIsMissing(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'num_visible' => 'A2026/000321',
                'amount' => '40',
                'movement_date' => '2026-06-13',
                'created_by' => 'admin-cobraments',
            ]
        );

        Assert::same(
            'CLAIM|FACT:A2026/000321|DATA:2026-06-13|IMPORT:40.00|USUARI:admin-cobraments',
            $payload['idempotency_key']
        );
        Assert::same('CLAIM_PAYMENT', $payload['allocations'][0]['allocation_type']);

        (new PaymentPayloadValidator())->validate($payload);
    }

    public function testRejectsFallbackIdempotencyWithoutUser(): void
    {
        Assert::throws(
            SifException::class,
            static fn (): array => (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
                '11111111-1111-4111-8111-111111111111',
                [
                    'num_visible' => 'A2026/000321',
                    'amount' => '40',
                    'movement_date' => '2026-06-13',
                ]
            ),
            422
        );
    }
}
