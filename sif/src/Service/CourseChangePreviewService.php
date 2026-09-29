<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceReadRepository;

final class CourseChangePreviewService
{
    public function __construct(
        private \PDO $db,
        private InvoiceReadRepository $invoices,
        private CourseChangeImpactClassifier $classifier
    ) {
    }

    public function preview(array $input): array
    {
        $idInsc = $this->positiveInt($input['source_enrollment_id'] ?? null, 'source_enrollment_id');
        $sourceCourse = $this->text($input['source_course'] ?? null, 'source_course', 180);
        $targetCourse = $this->text($input['target_course'] ?? null, 'target_course', 180);

        $invoiceRows = $this->invoices->search($this->db, ['source_ids' => [$idInsc]], 20);
        $invoiceResolution = count($invoiceRows) === 0
            ? 'NONE'
            : (count($invoiceRows) === 1 ? 'ONE' : 'MULTIPLE');

        $originalAmount = trim((string) ($input['original_amount'] ?? ''));
        $paidAmount = trim((string) ($input['paid_amount'] ?? '0.00'));
        $originalAmountSource = 'LEGACY_PREVIEW';
        $paidAmountSource = 'LEGACY_PREVIEW';
        $invoiceContext = null;

        if ($invoiceResolution === 'ONE') {
            $invoice = $invoiceRows[0];
            $uuid = (string) $invoice['UUID_FACTURA'];
            $relations = $this->invoices->findRelations($this->db, $uuid);
            $lines = $this->invoices->findLines($this->db, $uuid);
            $payments = $this->invoices->findPayments($this->db, $uuid);

            $lineAmount = $this->resolveEnrollmentAmount($idInsc, $relations, $lines);
            if ($lineAmount !== null) {
                $originalAmount = $lineAmount;
                $originalAmountSource = 'SIF_INVOICE_LINE';
            } elseif ($this->singleEnrollmentRelation($idInsc, $relations)) {
                $originalAmount = (string) $invoice['TOTAL'];
                $originalAmountSource = 'SIF_INVOICE_TOTAL';
            }

            $paidAmount = $this->netPaid($payments);
            $paidAmountSource = 'SIF_PAYMENT_ALLOCATION';

            $invoiceContext = [
                'uuid_factura' => $uuid,
                'num_visible' => (string) $invoice['NUM_VISIBLE'],
                'estat_factura' => (string) $invoice['ESTAT_FACTURA'],
                'estat_cobrament' => (string) $invoice['ESTAT_COBRAMENT'],
                'estat_aeat' => (string) $invoice['ESTAT_AEAT'],
            ];
        }

        if ($originalAmount === '') {
            throw SifException::validation('Original course amount is required');
        }

        $result = $this->classifier->classify([
            'original_amount' => $originalAmount,
            'standard_target_amount' => $input['standard_target_amount'] ?? null,
            'proposed_target_amount' => $input['proposed_target_amount'] ?? null,
            'management_fee' => $input['management_fee'] ?? '0.00',
            'paid_amount' => $paidAmount,
            'invoice_issued' => $invoiceResolution !== 'NONE',
            'service_changed' => $this->normalized($sourceCourse) !== $this->normalized($targetCourse),
            'manual_price_reason' => $input['manual_price_reason'] ?? null,
        ]);

        if ($invoiceResolution === 'MULTIPLE') {
            $result['fiscal_decision'] = 'REVIEW_REQUIRED';
            $result['fiscal_reason_code'] = 'MULTIPLE_SIF_INVOICES_FOR_ENROLLMENT';
        }

        return [
            'ok' => true,
            'source_enrollment_id' => $idInsc,
            'source_course' => $sourceCourse,
            'target_course' => $targetCourse,
            'invoice_resolution' => $invoiceResolution,
            'invoice' => $invoiceContext,
            'original_amount_source' => $originalAmountSource,
            'paid_amount_source' => $paidAmountSource,
            'impact' => $result,
            'can_confirm_legacy_change' => $invoiceResolution !== 'MULTIPLE',
        ];
    }

    private function resolveEnrollmentAmount(int $idInsc, array $relations, array $lines): ?string
    {
        $lineIds = [];
        foreach ($relations as $relation) {
            if (
                strtoupper((string) ($relation['SOURCE_TYPE'] ?? '')) === 'INSCRIPCIO'
                && (int) ($relation['SOURCE_ID'] ?? 0) === $idInsc
                && (int) ($relation['ID_FACTURA_LINIA'] ?? 0) > 0
            ) {
                $lineIds[(int) $relation['ID_FACTURA_LINIA']] = true;
            }
        }

        $cents = 0;
        $matches = 0;
        foreach ($lines as $line) {
            $lineMatches = isset($lineIds[(int) ($line['ID'] ?? 0)])
                || (
                    strtoupper((string) ($line['SOURCE_TYPE'] ?? '')) === 'INSCRIPCIO'
                    && (int) ($line['SOURCE_ID'] ?? 0) === $idInsc
                );

            if ($lineMatches) {
                $cents += $this->toCents((string) $line['TOTAL']);
                $matches++;
            }
        }

        return $matches > 0 ? $this->fromCents($cents) : null;
    }

    private function singleEnrollmentRelation(int $idInsc, array $relations): bool
    {
        $ids = [];
        foreach ($relations as $relation) {
            if (strtoupper((string) ($relation['SOURCE_TYPE'] ?? '')) === 'INSCRIPCIO') {
                $sourceId = (int) ($relation['SOURCE_ID'] ?? 0);
                if ($sourceId > 0) {
                    $ids[$sourceId] = true;
                }
            }
        }

        return count($ids) === 1 && isset($ids[$idInsc]);
    }

    private function netPaid(array $payments): string
    {
        $cents = 0;
        foreach ($payments as $payment) {
            if (strtoupper((string) ($payment['ESTAT'] ?? '')) !== 'CONFIRMED') {
                continue;
            }

            $amount = $this->toCents((string) ($payment['IMPORT_ASSIGNAT'] ?? '0.00'));
            $type = strtoupper((string) ($payment['TIPUS_MOVIMENT'] ?? ''));

            if (in_array($type, ['CHARGE', 'COMPENSATION'], true)) {
                $cents += $amount;
            } elseif ($type === 'REFUND') {
                $cents -= $amount;
            }
        }

        return $this->fromCents(max(0, $cents));
    }

    private function positiveInt(mixed $value, string $field): int
    {
        $text = trim((string) $value);
        if (!ctype_digit($text) || (int) $text <= 0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return (int) $text;
    }

    private function text(mixed $value, string $field, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text, 'UTF-8') > $maxLength) {
            throw SifException::validation('Invalid ' . $field);
        }

        return $text;
    }

    private function normalized(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }

    private function toCents(string $value): int
    {
        $value = str_replace(',', '.', trim($value));
        if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $value) !== 1) {
            throw SifException::validation('Invalid stored monetary value');
        }
        [$whole, $decimals] = array_pad(explode('.', $value, 2), 2, '');
        $decimals = str_pad($decimals, 2, '0');

        return ((int) $whole * 100) + (int) substr($decimals, 0, 2);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
