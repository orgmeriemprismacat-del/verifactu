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

        return [
            'allowed' => $case === null,
            'reason' => $case === null
                ? 'NO_SIF_USOC_CASE'
                : 'USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION',
            'operation' => $operation,
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'case' => $case,
        ];
    }
}
