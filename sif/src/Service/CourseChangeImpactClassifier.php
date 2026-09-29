<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class CourseChangeImpactClassifier
{
    public function classify(array $input): array
    {
        $original = $this->money($input, 'original_amount');
        $standardTarget = $this->money($input, 'standard_target_amount');
        $proposedTarget = array_key_exists('proposed_target_amount', $input)
            && $input['proposed_target_amount'] !== null
            && trim((string) $input['proposed_target_amount']) !== ''
            ? $this->money($input, 'proposed_target_amount')
            : $standardTarget;
        $managementFee = $this->money($input, 'management_fee', '0.00');
        $paid = $this->money($input, 'paid_amount', '0.00');

        $invoiceIssued = (bool) ($input['invoice_issued'] ?? false);
        $serviceChanged = (bool) ($input['service_changed'] ?? false);
        $manualReason = trim((string) ($input['manual_price_reason'] ?? ''));

        $pricingMode = $proposedTarget === $standardTarget ? 'STANDARD' : 'MANUAL';
        if ($pricingMode === 'MANUAL' && $manualReason === '') {
            throw SifException::validation('Manual course price requires a reason');
        }
        if (mb_strlen($manualReason, 'UTF-8') > 500) {
            throw SifException::validation('Manual course price reason is too long');
        }

        $targetTotal = $proposedTarget + $managementFee;
        $courseDelta = $proposedTarget - $original;
        $totalDelta = $targetTotal - $original;

        $priceRelation = $courseDelta === 0
            ? 'SAME'
            : ($courseDelta > 0 ? 'HIGHER' : 'LOWER');

        $fiscalDecision = 'NONE';
        $fiscalReason = 'NO_ISSUED_INVOICE';
        if ($invoiceIssued) {
            if ($serviceChanged) {
                $fiscalDecision = 'RECTIFY_AND_REISSUE';
                $fiscalReason = 'SERVICE_OR_CONCEPT_CHANGED';
            } elseif ($totalDelta !== 0) {
                $fiscalDecision = 'RECTIFY_DIFFERENCE';
                $fiscalReason = 'AMOUNT_CHANGED';
            } else {
                $fiscalReason = 'SAME_SERVICE_AND_AMOUNT';
            }
        }

        $economicDecision = 'NONE';
        $amountDue = 0;
        $excessAmount = 0;

        if ($paid < $targetTotal) {
            $economicDecision = 'AMOUNT_DUE';
            $amountDue = $targetTotal - $paid;
        } elseif ($paid > $targetTotal) {
            $economicDecision = 'EXCESS_TO_RESOLVE';
            $excessAmount = $paid - $targetTotal;
        }

        return [
            'pricing_mode' => $pricingMode,
            'manual_price_reason' => $pricingMode === 'MANUAL' ? $manualReason : null,
            'price_relation' => $priceRelation,
            'original_amount' => $this->format($original),
            'standard_target_amount' => $this->format($standardTarget),
            'effective_target_amount' => $this->format($proposedTarget),
            'management_fee' => $this->format($managementFee),
            'target_total' => $this->format($targetTotal),
            'course_delta' => $this->formatSigned($courseDelta),
            'total_delta' => $this->formatSigned($totalDelta),
            'paid_amount' => $this->format($paid),
            'economic_decision' => $economicDecision,
            'amount_due' => $this->format($amountDue),
            'excess_amount' => $this->format($excessAmount),
            'fiscal_decision' => $fiscalDecision,
            'fiscal_reason_code' => $fiscalReason,
            'invoice_issued' => $invoiceIssued,
            'service_changed' => $serviceChanged,
        ];
    }

    private function money(array $input, string $field, string $default = null): int
    {
        $value = $input[$field] ?? $default;
        if ($value === null) {
            throw SifException::validation('Missing monetary field: ' . $field);
        }

        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw SifException::validation('Invalid monetary field: ' . $field);
        }

        [$whole, $decimals] = array_pad(explode('.', $text, 2), 2, '');
        $decimals = str_pad($decimals, 2, '0');

        return ((int) $whole * 100) + (int) substr($decimals, 0, 2);
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function formatSigned(int $cents): string
    {
        $prefix = $cents > 0 ? '+' : '';
        return $prefix . number_format($cents / 100, 2, '.', '');
    }
}
