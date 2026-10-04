<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class GroupParticipantRemovalPreviewService
{
    public function __construct(private EnrollmentFundMovementRepository $funds)
    {
    }

    public function preview(\PDO $db, string $uuidFactura, int $idInsc): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '' || $idInsc <= 0) {
            throw SifException::validation('Group participant removal preview requires invoice and enrollment');
        }

        $stmt = $db->prepare(
            "SELECT
                f.UUID_FACTURA,
                f.NUM_VISIBLE,
                f.ESTAT_COBRAMENT,
                f.TOTAL AS FACTURA_TOTAL,
                f.BILLING_NOM_RAO,
                f.BILLING_NIF_CIF,
                fl.ID AS ID_FACTURA_LINIA,
                fl.ORDRE,
                fl.CONCEPTE,
                fl.IMPORT_BASE,
                fl.DESC_IMPORT,
                fl.DESC_PCT,
                fl.TOTAL AS LINIA_TOTAL,
                fr.IDPAG
             FROM factura f
             INNER JOIN factura_linia fl
               ON fl.UUID_FACTURA = f.UUID_FACTURA
              AND fl.SOURCE_TYPE = 'INSCRIPCIO'
              AND fl.SOURCE_ID = ?
             INNER JOIN fact_rels fr
               ON fr.UUID_FACTURA = f.UUID_FACTURA
              AND fr.SOURCE_TYPE = 'INSCRIPCIO'
              AND fr.SOURCE_ID = ?
             WHERE f.UUID_FACTURA = ?"
        );
        $stmt->execute([$idInsc, $idInsc, $uuidFactura]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            throw SifException::conflict(
                'Expected exactly one group invoice line and relation for enrollment'
            );
        }
        $row = $rows[0];

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

        $lineTotalCents = $this->cents($row['LINIA_TOTAL']);
        $attributedCents = $this->cents(
            $this->funds->attributedBalanceForEnrollment($db, $idInsc, $uuidFactura)
        );

        if ($attributedCents < 0) {
            throw SifException::conflict('Enrollment has a negative attributed fund balance');
        }

        $unpaidCents = max(0, $lineTotalCents - $attributedCents);
        $overAttributedCents = max(0, $attributedCents - $lineTotalCents);

        return [
            'uuid_factura' => (string) $row['UUID_FACTURA'],
            'num_visible' => (string) $row['NUM_VISIBLE'],
            'invoice_payment_status' => (string) $row['ESTAT_COBRAMENT'],
            'invoice_total' => $this->money($row['FACTURA_TOTAL']),
            'idpag' => $row['IDPAG'] === null ? null : (int) $row['IDPAG'],
            'receiver' => [
                'name' => (string) $row['BILLING_NOM_RAO'],
                'nif' => (string) $row['BILLING_NIF_CIF'],
            ],
            'participant' => [
                'id_insc' => $idInsc,
                'invoice_line_id' => (int) $row['ID_FACTURA_LINIA'],
                'line_order' => (int) $row['ORDRE'],
                'concept' => (string) $row['CONCEPTE'],
                'base' => $this->money($row['IMPORT_BASE']),
                'discount' => $this->money($row['DESC_IMPORT']),
                'discount_pct' => $row['DESC_PCT'] === null
                    ? null
                    : $this->money($row['DESC_PCT']),
                'billed' => $this->amount($lineTotalCents),
                'funds_attributed' => $this->amount($attributedCents),
                'unpaid' => $this->amount($unpaidCents),
                'over_attributed' => $this->amount($overAttributedCents),
                'max_refundable_before_policy' => $this->amount(min($lineTotalCents, $attributedCents)),
            ],
            'group' => [
                'participants_before' => $participantCount,
                'participants_after' => max(0, $participantCount - 1),
                'invoiced_total_before' => $this->money($group[1] ?? '0.00'),
            ],
            'decision' => [
                'academic_removal_can_be_prepared' => true,
                'refund_requires_real_execution' => $attributedCents > 0,
                'refund_default_amount' => null,
                'credit_default_amount' => null,
                'fiscal_correction_required_to_classify' => true,
                'repricing_policy_required' => true,
                'automatic_commit_allowed' => false,
                'reason' => 'GROUP_POST_ISSUE_REPRICING_AND_FISCAL_POLICY_REQUIRED',
            ],
            'fund_movements' => $this->funds->movementsForEnrollment(
                $db,
                $idInsc,
                $uuidFactura
            ),
        ];
    }

    private function cents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid monetary value in group removal preview');
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
