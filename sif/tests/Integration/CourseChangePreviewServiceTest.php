<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\CourseChangeImpactClassifier;
use Prisma\Sif\Service\CourseChangePreviewService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class CourseChangePreviewServiceTest
{
    public function testUsesSifInvoiceLineAndRealAllocationInsteadOfBrowserAmounts(): void
    {
        $db = TestDatabase::fresh();
        $this->insertInvoice($db, 42, '100.00', '100.00', 1);

        $result = $this->service($db)->preview([
            'source_enrollment_id' => 42,
            'source_course' => 'Curs A',
            'target_course' => 'Curs A',
            'original_amount' => '999.00',
            'standard_target_amount' => '130.00',
            'proposed_target_amount' => '130.00',
            'management_fee' => '0.00',
            'paid_amount' => '0.00',
        ]);

        Assert::same('ONE', $result['invoice_resolution']);
        Assert::same('SIF_INVOICE_LINE', $result['original_amount_source']);
        Assert::same('SIF_PAYMENT_ALLOCATION', $result['paid_amount_source']);
        Assert::same('100.00', $result['impact']['original_amount']);
        Assert::same('100.00', $result['impact']['paid_amount']);
        Assert::same('RECTIFY_DIFFERENCE', $result['impact']['fiscal_decision']);
        Assert::same('30.00', $result['impact']['amount_due']);
    }

    public function testDifferentCourseSameAmountProposesReplacement(): void
    {
        $db = TestDatabase::fresh();
        $this->insertInvoice($db, 42, '100.00', '100.00', 1);

        $result = $this->service($db)->preview([
            'source_enrollment_id' => 42,
            'source_course' => 'Curs A',
            'target_course' => 'Curs B',
            'original_amount' => '100.00',
            'standard_target_amount' => '100.00',
            'proposed_target_amount' => '100.00',
            'management_fee' => '0.00',
            'paid_amount' => '100.00',
        ]);

        Assert::same('SAME', $result['impact']['price_relation']);
        Assert::same('RECTIFY_AND_REISSUE', $result['impact']['fiscal_decision']);
        Assert::same('NONE', $result['impact']['economic_decision']);
    }

    public function testLowerManualPriceProducesExcessButNotAutomaticRefund(): void
    {
        $db = TestDatabase::fresh();
        $this->insertInvoice($db, 42, '100.00', '100.00', 1);

        $result = $this->service($db)->preview([
            'source_enrollment_id' => 42,
            'source_course' => 'Curs A',
            'target_course' => 'Curs A',
            'original_amount' => '100.00',
            'standard_target_amount' => '90.00',
            'proposed_target_amount' => '80.00',
            'manual_price_reason' => 'Excepció comercial aprovada',
            'management_fee' => '0.00',
            'paid_amount' => '0.00',
        ]);

        Assert::same('MANUAL', $result['impact']['pricing_mode']);
        Assert::same('LOWER', $result['impact']['price_relation']);
        Assert::same('EXCESS_TO_RESOLVE', $result['impact']['economic_decision']);
        Assert::same('20.00', $result['impact']['excess_amount']);
    }

    public function testMultipleSifInvoicesRequireReviewInsteadOfAutomaticFiscalDecision(): void
    {
        $db = TestDatabase::fresh();
        $this->insertInvoice($db, 42, '100.00', '100.00', 1);
        $this->insertInvoice($db, 42, '100.00', '0.00', 2);

        $result = $this->service($db)->preview([
            'source_enrollment_id' => 42,
            'source_course' => 'Curs A',
            'target_course' => 'Curs B',
            'original_amount' => '100.00',
            'standard_target_amount' => '100.00',
            'proposed_target_amount' => '100.00',
            'management_fee' => '0.00',
            'paid_amount' => '100.00',
        ]);

        Assert::same('MULTIPLE', $result['invoice_resolution']);
        Assert::same('REVIEW_REQUIRED', $result['impact']['fiscal_decision']);
        Assert::same(false, $result['can_confirm_legacy_change']);
    }

    private function service(\PDO $db): CourseChangePreviewService
    {
        return new CourseChangePreviewService(
            $db,
            new InvoiceReadRepository(),
            new CourseChangeImpactClassifier()
        );
    }

    private function insertInvoice(
        \PDO $db,
        int $idInsc,
        string $amount,
        string $paid,
        int $sequence
    ): void {
        $uuid = sprintf('00000000-0000-4000-8000-%012d', $sequence);
        $paymentUuid = sprintf('10000000-0000-4000-8000-%012d', $sequence);
        $numVisible = 'A2026/' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

        $stmt = $db->prepare(
            'INSERT INTO factura (
                UUID_FACTURA, IDEMPOTENCY_KEY, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
                TIPUS_FACTURA, DATA_EMISSIO, ESTAT_COBRAMENT, ESTAT_FACTURA, ESTAT_AEAT,
                BILLING_NOM_RAO, BILLING_NIF_CIF, IMPORT_BASE, BASE_IMPOSABLE, TOTAL,
                SOURCE_CHANNEL, CREATED_BY
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $uuid,
            'TEST-UC071-' . $sequence,
            'A',
            2026,
            $sequence,
            $numVisible,
            'F1',
            '2026-09-29 10:00:00',
            $paid === $amount ? 'PAID' : 'PENDING',
            'ISSUED',
            'PENDING',
            'Client Test',
            '12345678Z',
            $amount,
            $amount,
            $amount,
            'TEST',
            'test',
        ]);

        $line = $db->prepare(
            'INSERT INTO factura_linia (
                UUID_FACTURA, ORDRE, CONCEPTE, QUANTITAT, PREU_UNITARI, IMPORT_BASE,
                BASE_IMPOSABLE, TOTAL, SOURCE_TYPE, SOURCE_ID
             ) VALUES (?, 1, ?, 1.00, ?, ?, ?, ?, ?, ?)'
        );
        $line->execute([
            $uuid,
            'Curs A',
            $amount,
            $amount,
            $amount,
            $amount,
            'INSCRIPCIO',
            $idInsc,
        ]);
        $lineId = (int) $db->lastInsertId();

        $rel = $db->prepare(
            'INSERT INTO fact_rels (
                UUID_FACTURA, SOURCE_TYPE, SOURCE_ID, RELATION_TYPE, ID_FACTURA_LINIA
             ) VALUES (?, ?, ?, ?, ?)'
        );
        $rel->execute([$uuid, 'INSCRIPCIO', $idInsc, 'ORIGIN', $lineId]);

        if ((float) $paid > 0) {
            $payment = $db->prepare(
                'INSERT INTO payment_transaction (
                    UUID_PAYMENT, IDEMPOTENCY_KEY, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL,
                    IMPORT, DATA_MOVIMENT, PAYLOAD_HASH, ESTAT
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $payment->execute([
                $paymentUuid,
                'TEST-PAY-UC071-' . $sequence,
                'CHARGE',
                'TRANSFERENCIA',
                'TEST',
                $paid,
                '2026-09-29 10:01:00',
                str_repeat((string) $sequence, 64),
                'CONFIRMED',
            ]);

            $allocation = $db->prepare(
                'INSERT INTO payment_allocation (
                    UUID_PAYMENT, UUID_FACTURA, IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO
                 ) VALUES (?, ?, ?, ?)'
            );
            $allocation->execute([$paymentUuid, $uuid, $paid, 'INVOICE_PAYMENT']);
        }
    }
}
