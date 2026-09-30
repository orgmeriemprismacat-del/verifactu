<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocValidationDecisionRepository;

final class UsocValidationDecisionService
{
    public function __construct(private UsocValidationDecisionRepository $decisions)
    {
    }

    public function begin(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $requestId,
        int $idInsc,
        int $desiredValidDesc,
        string $actorId,
        array $roles
    ): array {
        $this->assertIdentity($requestId, $idInsc, $desiredValidDesc, $actorId);
        $legacy = $this->legacyState($legacyDb, $idInsc);

        if ((int) $legacy['TIPUS_DESC'] !== 4) {
            return [
                'tracked' => false,
                'reason' => 'NOT_USOC',
                'should_apply_legacy' => true,
            ];
        }

        $current = (int) $legacy['VALID_DESC'];
        if (!in_array($current, [0, 1, 2], true)) {
            throw SifException::conflict('Unexpected legacy USOC validation state');
        }

        $sifDb->beginTransaction();
        try {
            $decision = $this->decisions->begin(
                $sifDb,
                $requestId,
                'USOC|VALIDATION|ID_INSC:' . $idInsc,
                $idInsc,
                $desiredValidDesc,
                $actorId,
                $roles,
                $current
            );

            $state = (string) $decision['STATE'];
            if ($state === 'COMMITTED' && $current !== $desiredValidDesc) {
                throw SifException::conflict(
                    'Committed USOC validation decision no longer matches legacy state'
                );
            }

            if ($state === 'REQUESTED') {
                if ($current === $desiredValidDesc) {
                    $decision = $this->decisions->markCommitted(
                        $sifDb,
                        $requestId,
                        $current,
                        $this->legacyHash($legacy)
                    );
                } elseif ($current !== 0) {
                    $decision = $this->decisions->markReviewRequired(
                        $sifDb,
                        $requestId,
                        $current,
                        $this->legacyHash($legacy),
                        'LEGACY_DECISION_CONFLICT'
                    );
                }
            }

            $sifDb->commit();

            return $this->result($decision, (int) $legacy['VALID_DESC']);
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    public function complete(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $requestId,
        string $actorId
    ): array {
        $requestId = trim($requestId);
        $actorId = trim($actorId);
        if ($requestId === '' || $actorId === '') {
            throw SifException::validation('USOC validation request and actor are required');
        }

        $sifDb->beginTransaction();
        try {
            $decision = $this->decisions->findByRequestId($sifDb, $requestId, true);
            if ($decision === null) {
                throw SifException::conflict('USOC validation decision not found');
            }
            if ((string) $decision['ACTOR_ID'] !== $actorId) {
                throw SifException::forbidden('USOC validation decision belongs to another actor');
            }

            $legacy = $this->legacyState($legacyDb, (int) $decision['ID_INSC']);
            if ((int) $legacy['TIPUS_DESC'] !== 4) {
                $decision = $this->decisions->markReviewRequired(
                    $sifDb,
                    $requestId,
                    (int) $legacy['VALID_DESC'],
                    $this->legacyHash($legacy),
                    'LEGACY_NO_LONGER_USOC'
                );
                $sifDb->commit();
                return $this->result($decision, (int) $legacy['VALID_DESC']);
            }

            $current = (int) $legacy['VALID_DESC'];
            $desired = (int) $decision['DESIRED_VALID_DESC'];

            if ((string) $decision['STATE'] === 'COMMITTED' && $current !== $desired) {
                throw SifException::conflict(
                    'Committed USOC validation decision no longer matches legacy state'
                );
            }

            if ($current === $desired) {
                $decision = $this->decisions->markCommitted(
                    $sifDb,
                    $requestId,
                    $current,
                    $this->legacyHash($legacy)
                );
            } elseif ($current !== 0) {
                $decision = $this->decisions->markReviewRequired(
                    $sifDb,
                    $requestId,
                    $current,
                    $this->legacyHash($legacy),
                    'LEGACY_DECISION_CONFLICT'
                );
            }

            $sifDb->commit();
            return $this->result($decision, $current);
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    private function result(array $decision, int $legacyValidDesc): array
    {
        $state = (string) $decision['STATE'];

        return [
            'tracked' => true,
            'uuid_decision' => (string) $decision['UUID_DECISION'],
            'request_id' => (string) $decision['REQUEST_ID'],
            'id_insc' => (int) $decision['ID_INSC'],
            'desired_valid_desc' => (int) $decision['DESIRED_VALID_DESC'],
            'legacy_valid_desc' => $legacyValidDesc,
            'state' => $state,
            'should_apply_legacy' => $state === 'REQUESTED' && $legacyValidDesc === 0,
            'review_reason' => $decision['REVIEW_REASON'] ?? null,
        ];
    }

    private function legacyState(\PDO $legacyDb, int $idInsc): array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, TIPUS_DESC, VALID_DESC
             FROM inscripcions
             WHERE ID = ?'
        );
        $stmt->execute([$idInsc]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            throw SifException::conflict('Legacy USOC inscription not found or ambiguous');
        }

        return $rows[0];
    }

    private function legacyHash(array $legacy): string
    {
        $canonical = json_encode([
            'ID' => (int) $legacy['ID'],
            'TIPUS_DESC' => (int) $legacy['TIPUS_DESC'],
            'VALID_DESC' => (int) $legacy['VALID_DESC'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical);
    }

    private function assertIdentity(
        string $requestId,
        int $idInsc,
        int $desiredValidDesc,
        string $actorId
    ): void {
        $requestId = trim($requestId);
        $actorId = trim($actorId);

        if ($requestId === '' || strlen($requestId) > 120 || $idInsc <= 0 || $actorId === '') {
            throw SifException::validation('Invalid USOC validation decision identity');
        }
        if (!in_array($desiredValidDesc, [1, 2], true)) {
            throw SifException::validation('Invalid desired USOC validation decision');
        }
    }
}
