<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;

final class UsocLifecycleGuardService
{
    public function __construct(private UsocFinancingCaseRepository $cases)
    {
    }

    public function check(
        \PDO $db,
        int $idInsc,
        int $idpag,
        string $operation
    ): array {
        if ($idInsc <= 0 || $idpag <= 0) {
            throw SifException::validation('Invalid USOC lifecycle identity');
        }

        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['course_change', 'cancellation'], true)) {
            throw SifException::validation('Invalid USOC lifecycle operation');
        }

        $case = $this->cases->findByInscriptionAndIdpag($db, $idInsc, $idpag);
        $orphanFiscalEvidence = $case === null
            ? $this->findUsocFiscalEvidence($db, $idInsc, $idpag)
            : null;

        $allowed = $case === null && $orphanFiscalEvidence === null;
        $reason = 'NO_SIF_USOC_CASE';
        if ($case !== null) {
            $reason = 'USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION';
        } elseif ($orphanFiscalEvidence !== null) {
            $reason = 'USOC_FISCAL_EVIDENCE_WITHOUT_CASE_REQUIRES_REVIEW';
        }

        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'operation' => $operation,
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'case' => $case,
            'orphan_fiscal_evidence' => $orphanFiscalEvidence,
        ];
    }

    private function findUsocFiscalEvidence(\PDO $db, int $idInsc, int $idpag): ?array
    {
        $stmt = $db->prepare(
            "SELECT f.UUID_FACTURA, f.IDEMPOTENCY_KEY, f.SOURCE_CHANNEL,
                    r.RELATION_TYPE, r.VISIBLE_ALUMNE
             FROM factura AS f
             INNER JOIN fact_rels AS r ON r.UUID_FACTURA = f.UUID_FACTURA
             WHERE r.SOURCE_TYPE = 'INSCRIPCIO'
               AND r.SOURCE_ID = ?
               AND r.IDPAG = ?
               AND (
                    f.IDEMPOTENCY_KEY LIKE 'REDSYS|USOC_ALUMNE|IDPAG:%'
                    OR f.IDEMPOTENCY_KEY LIKE 'INTRANET|USOC_ENTITAT|ID_INSC:%'
                    OR r.RELATION_TYPE = 'USOC_ENTITY'
               )
             ORDER BY f.CREATED_AT ASC
             LIMIT 1"
        );
        $stmt->execute([$idInsc, $idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
