<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class GroupParticipantAdditionPreviewService
{
    public function preview(\PDO $db, string $uuidFactura, array $candidate): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Group participant addition preview requires invoice');
        }

        foreach (['id_insc', 'idpag', 'concept', 'base', 'discount', 'total'] as $field) {
            if (!array_key_exists($field, $candidate)) {
                throw SifException::validation('Missing group participant candidate field ' . $field);
            }
        }

        $idInsc = $this->positiveInt($candidate['id_insc'], 'Invalid candidate enrollment ID');
        $idpag = $this->positiveInt($candidate['idpag'], 'Invalid candidate group IDPAG');
        $concept = trim((string) $candidate['concept']);
        if ($concept === '') {
            throw SifException::validation('Candidate concept is required');
        }

        $baseCents = $this->cents($candidate['base']);
        $discountCents = $this->cents($candidate['discount']);
        $totalCents = $this->cents($candidate['total']);
        if ($baseCents <= 0 || $discountCents < 0 || $totalCents <= 0
            || $baseCents - $discountCents !== $totalCents
        ) {
            throw SifException::validation('Candidate group line amounts are inconsistent');
        }

        $invoiceStmt = $db->prepare(
            "SELECT f.UUID_FACTURA, f.NUM_VISIBLE, f.ESTAT_COBRAMENT, f.TOTAL,
                    f.BILLING_NOM_RAO, f.BILLING_NIF_CIF, fr.IDPAG
             FROM factura f
             INNER JOIN fact_rels fr
               ON fr.UUID_FACTURA = f.UUID_FACTURA
              AND fr.SOURCE_TYPE = 'GRUP'
             WHERE f.UUID_FACTURA = ?"
        );
        $invoiceStmt->execute([$uuidFactura]);
        $invoiceRows = $invoiceStmt->fetchAll(\PDO::FETCH_ASSOC);
        if (count($invoiceRows) !== 1) {
            throw SifException::conflict('Expected exactly one GRUP relation for invoice');
        }
        $invoice = $invoiceRows[0];

        $invoiceIdpag = (int) ($invoice['IDPAG'] ?? 0);
        if ($invoiceIdpag <= 0 || $invoiceIdpag !== $idpag) {
            throw SifException::conflict('Candidate enrollment does not belong to invoice group');
        }

        $duplicateStmt = $db->prepare(
            "SELECT COUNT(*)
             FROM fact_rels
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = 'INSCRIPCIO'
               AND SOURCE_ID = ?"
        );
        $duplicateStmt->execute([$uuidFactura, $idInsc]);
        if ((int) $duplicateStmt->fetchColumn() !== 0) {
            throw SifException::conflict('Candidate enrollment is already included in group invoice');
        }

        $groupStmt = $db->prepare(
            "SELECT COUNT(*), COALESCE(SUM(TOTAL), 0)
             FROM factura_linia
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = 'INSCRIPCIO'"
        );
        $groupStmt->execute([$uuidFactura]);
        $group = $groupStmt->fetch(\PDO::FETCH_NUM);
        $participantCount = (int) ($group[0] ?? 0);
        if ($participantCount < 1) {
            throw SifException::conflict('Group invoice has no participant lines');
        }

        $currentLinesTotalCents = $this->cents($group[1] ?? '0.00');
        $projectedNominalCents = $currentLinesTotalCents + $totalCents;

        return [
            'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            'num_visible' => (string) $invoice['NUM_VISIBLE'],
            'invoice_payment_status' => (string) $invoice['ESTAT_COBRAMENT'],
            'invoice_total_original' => $this->money($invoice['TOTAL']),
            'idpag' => $invoiceIdpag,
            'receiver' => [
                'name' => (string) $invoice['BILLING_NOM_RAO'],
                'nif' => (string) $invoice['BILLING_NIF_CIF'],
            ],
            'candidate' => [
                'id_insc' => $idInsc,
                'concept' => $concept,
                'base' => $this->amount($baseCents),
                'discount' => $this->amount($discountCents),
                'total' => $this->amount($totalCents),
            ],
            'group' => [
                'participants_before' => $participantCount,
                'participants_after' => $participantCount + 1,
                'invoiced_lines_total_before' => $this->amount($currentLinesTotalCents),
                'candidate_nominal_total' => $this->amount($totalCents),
                'projected_nominal_total_before_repricing_policy' => $this->amount($projectedNominalCents),
            ],
            'decision' => [
                'duplicate' => false,
                'candidate_can_be_prepared' => true,
                'original_invoice_must_remain_immutable' => true,
                'fiscal_document_required_to_classify' => true,
                'repricing_policy_required' => true,
                'new_charge_required_now' => false,
                'automatic_commit_allowed' => false,
                'reason' => 'GROUP_POST_ISSUE_ADDITION_REPRICING_AND_FISCAL_POLICY_REQUIRED',
            ],
        ];
    }

    private function positiveInt(mixed $value, string $message): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
            throw SifException::validation($message);
        }
        return (int) $raw;
    }

    private function cents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid monetary value in group addition preview');
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '-');
        [$euros, $decimal] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimal, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function money(mixed $value): string
    {
        return $this->amount($this->cents($value));
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
