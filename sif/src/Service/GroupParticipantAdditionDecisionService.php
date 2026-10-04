<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class GroupParticipantAdditionDecisionService
{
    public function plan(array $preview, array $decision): array
    {
        $candidate = $preview['candidate'] ?? null;
        if (!is_array($candidate)) {
            throw SifException::validation('Invalid group addition preview');
        }

        $idInsc = (int) ($candidate['id_insc'] ?? 0);
        if ($idInsc <= 0) {
            throw SifException::validation('Invalid candidate enrollment in group addition preview');
        }

        $repricingPolicy = strtoupper(trim((string) ($decision['repricing_policy'] ?? '')));
        if (!in_array($repricingPolicy, [
            'KEEP_EXISTING_MEMBER_PRICES',
            'REPRICE_GROUP_SEPARATELY',
        ], true)) {
            throw SifException::validation('Explicit group repricing policy is required');
        }

        $fiscalAction = strtoupper(trim((string) ($decision['fiscal_action'] ?? '')));
        if (!in_array($fiscalAction, [
            'SUPPLEMENTAL_INVOICE_PARTICIPANT',
            'RECTIFY_GROUP',
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

        $candidateTotalCents = $this->cents($candidate['total'] ?? null);
        if ($candidateTotalCents <= 0) {
            throw SifException::validation('Candidate total must be positive');
        }

        $actions = [[
            'type' => 'ACADEMIC_ADDITION',
            'id_insc' => $idInsc,
            'status' => 'PLANNED',
        ]];

        if ($fiscalAction === 'SUPPLEMENTAL_INVOICE_PARTICIPANT') {
            $actions[] = [
                'type' => 'SUPPLEMENTAL_INVOICE',
                'status' => 'PLANNED',
                'input' => [
                    'amount' => $this->amount($candidateTotalCents),
                    'concept' => (string) ($candidate['concept'] ?? 'Nou participant de grup'),
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => $idInsc,
                    'idpag' => (int) ($preview['idpag'] ?? 0),
                    'reference' => trim((string) ($decision['operation_reference'] ?? '')) ?: null,
                ],
            ];
        } elseif ($fiscalAction === 'RECTIFY_GROUP') {
            $actions[] = [
                'type' => 'GROUP_RECTIFICATION',
                'status' => 'REQUIRES_EXPLICIT_RECTIFICATION_PAYLOAD',
                'reason' => 'GROUP_ADDITION_REQUIRES_CLASSIFIED_RECTIFICATION',
            ];
        }

        if ($repricingPolicy === 'REPRICE_GROUP_SEPARATELY') {
            $actions[] = [
                'type' => 'REPRICE_EXISTING_GROUP',
                'status' => 'REQUIRES_SEPARATE_FISCAL_CLASSIFICATION',
                'participants_after' => (int) ($preview['group']['participants_after'] ?? 0),
            ];
        }

        return [
            'executable' => true,
            'participant_id' => $idInsc,
            'repricing_policy' => $repricingPolicy,
            'fiscal_action' => $fiscalAction,
            'amounts' => [
                'candidate_total' => $this->amount($candidateTotalCents),
            ],
            'actions' => $actions,
            'payment' => [
                'charge_created' => false,
                'reason' => 'A charge may only be registered after a real payment is confirmed.',
            ],
            'execution_note' => 'Plan only: academic addition, fiscal document and later payment remain separately idempotent operations.',
        ];
    }

    private function cents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid amount in group participant addition decision');
        }
        [$euros, $decimal] = array_pad(explode('.', $raw, 2), 2, '');
        return (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
