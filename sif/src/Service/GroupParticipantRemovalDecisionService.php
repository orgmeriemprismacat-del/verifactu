<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class GroupParticipantRemovalDecisionService
{
    public function plan(array $preview, array $decision): array
    {
        $participant = $preview['participant'] ?? null;
        $context = $preview['decision'] ?? null;
        if (!is_array($participant) || !is_array($context)) {
            throw SifException::validation('Invalid group removal preview');
        }

        $idInsc = (int) ($participant['id_insc'] ?? 0);
        if ($idInsc <= 0) {
            throw SifException::validation('Invalid participant in group removal preview');
        }

        $repricingPolicy = strtoupper(trim((string) ($decision['repricing_policy'] ?? '')));
        if (!in_array($repricingPolicy, [
            'KEEP_EXISTING_MEMBER_PRICES',
            'REPRICE_REMAINING_SEPARATELY',
        ], true)) {
            throw SifException::validation('Explicit group repricing policy is required');
        }

        $fiscalAction = strtoupper(trim((string) ($decision['fiscal_action'] ?? '')));
        if (!in_array($fiscalAction, [
            'RECTIFY_PARTICIPANT_ONLY',
            'NO_RECTIFICATION_APPROVED',
            'REVIEW_REQUIRED',
        ], true)) {
            throw SifException::validation('Explicit group fiscal action is required');
        }
        if ($fiscalAction === 'REVIEW_REQUIRED') {
            return [
                'executable' => false,
                'reason' => 'FISCAL_REVIEW_REQUIRED',
                'participant_id' => $idInsc,
                'repricing_policy' => $repricingPolicy,
                'actions' => [],
            ];
        }

        $billedCents = $this->cents($participant['billed'] ?? null);
        $attributedCents = $this->cents($participant['funds_attributed'] ?? null);
        $rectificationCents = $this->signedCents($decision['rectification_amount'] ?? '0.00');
        $refundCents = $this->positiveOrZeroCents($decision['refund_amount'] ?? '0.00');
        $creditCents = $this->positiveOrZeroCents($decision['credit_amount'] ?? '0.00');
        $nonRefundableCents = $this->positiveOrZeroCents($decision['non_refundable_amount'] ?? '0.00');

        if ($fiscalAction === 'RECTIFY_PARTICIPANT_ONLY') {
            if ($rectificationCents >= 0 || abs($rectificationCents) > $billedCents) {
                throw SifException::validation(
                    'Participant rectification must be negative and cannot exceed billed amount'
                );
            }
        } elseif ($rectificationCents !== 0) {
            throw SifException::validation('Rectification amount requires a rectification fiscal action');
        }

        $economicDispositionCents = $refundCents + $creditCents + $nonRefundableCents;
        if ($economicDispositionCents > $attributedCents) {
            throw SifException::conflict(
                'Refund, credit and non-refundable amounts exceed attributed participant funds'
            );
        }

        if ($refundCents > 0 && trim((string) ($decision['refund_reference'] ?? '')) === '') {
            throw SifException::validation('Refund reference is required for a real refund');
        }

        if ($creditCents > 0) {
            foreach (['credit_holder_type', 'credit_holder_name'] as $field) {
                if (trim((string) ($decision[$field] ?? '')) === '') {
                    throw SifException::validation('Missing credit holder data');
                }
            }
        }

        $actions = [[
            'type' => 'ACADEMIC_REMOVAL',
            'id_insc' => $idInsc,
            'status' => 'PLANNED',
        ]];

        if ($fiscalAction === 'RECTIFY_PARTICIPANT_ONLY') {
            $actions[] = [
                'type' => 'RECTIFICATION',
                'status' => 'PLANNED',
                'input' => [
                    'amount' => $this->amount($rectificationCents),
                    'reason' => trim((string) ($decision['rectification_reason'] ?? 'BAIXA_PARTICIPANT_GRUP')),
                    'mode' => strtoupper(trim((string) ($decision['rectification_mode'] ?? 'DIFERENCIES'))),
                    'concept' => trim((string) ($decision['rectification_concept'] ?? 'Baixa participant de grup')),
                    'reference' => trim((string) ($decision['operation_reference'] ?? '')) ?: null,
                ],
            ];
        }

        if ($refundCents > 0) {
            $actions[] = [
                'type' => 'REFUND',
                'status' => 'REQUIRES_REAL_EXECUTION',
                'input' => [
                    'amount' => $this->amount($refundCents),
                    'reference' => trim((string) $decision['refund_reference']),
                    'movement_date' => trim((string) ($decision['refund_movement_date'] ?? '')),
                    'method' => strtoupper(trim((string) ($decision['refund_method'] ?? 'TRANSFERENCIA'))),
                ],
            ];
            if ($actions[array_key_last($actions)]['input']['movement_date'] === '') {
                throw SifException::validation('Refund movement date is required');
            }
        }

        if ($creditCents > 0) {
            $actions[] = [
                'type' => 'CREDIT',
                'status' => 'PLANNED',
                'input' => [
                    'holder_type' => strtoupper(trim((string) $decision['credit_holder_type'])),
                    'holder_name' => trim((string) $decision['credit_holder_name']),
                    'holder_nif_cif' => trim((string) ($decision['credit_holder_nif_cif'] ?? '')) ?: null,
                    'amount' => $this->amount($creditCents),
                    'source_type' => 'INSCRIPCIO_GRUP',
                    'source_id' => $idInsc,
                    'uuid_factura_origen' => (string) ($preview['uuid_factura'] ?? ''),
                ],
            ];
        }

        if ($repricingPolicy === 'REPRICE_REMAINING_SEPARATELY') {
            $actions[] = [
                'type' => 'REPRICE_REMAINING_GROUP',
                'status' => 'REQUIRES_SEPARATE_FISCAL_CLASSIFICATION',
                'participants_after' => (int) ($preview['group']['participants_after'] ?? 0),
            ];
        }

        $undisposedCents = max(0, $attributedCents - $economicDispositionCents);

        return [
            'executable' => true,
            'participant_id' => $idInsc,
            'repricing_policy' => $repricingPolicy,
            'fiscal_action' => $fiscalAction,
            'amounts' => [
                'billed' => $this->amount($billedCents),
                'funds_attributed' => $this->amount($attributedCents),
                'rectification' => $this->amount($rectificationCents),
                'refund' => $this->amount($refundCents),
                'credit' => $this->amount($creditCents),
                'non_refundable' => $this->amount($nonRefundableCents),
                'undisposed_attributed_funds' => $this->amount($undisposedCents),
            ],
            'actions' => $actions,
            'execution_note' => 'Plan only: downstream operations remain separately idempotent and must be committed by an authorized coordinator.',
        ];
    }

    private function signedCents(mixed $value): int
    {
        return $this->cents($value, true);
    }

    private function positiveOrZeroCents(mixed $value): int
    {
        $cents = $this->cents($value, false);
        if ($cents < 0) {
            throw SifException::validation('Economic disposition amounts cannot be negative');
        }
        return $cents;
    }

    private function cents(mixed $value, bool $allowNegative = false): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        $pattern = $allowNegative
            ? '/^-?\d{1,10}(?:\.\d{1,2})?$/D'
            : '/^\d{1,10}(?:\.\d{1,2})?$/D';
        if (!preg_match($pattern, $raw)) {
            throw SifException::validation('Invalid amount in group participant removal decision');
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '-');
        [$euros, $decimal] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
        return $negative ? -$cents : $cents;
    }

    private function amount(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $value = intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-' . $value : $value;
    }
}
