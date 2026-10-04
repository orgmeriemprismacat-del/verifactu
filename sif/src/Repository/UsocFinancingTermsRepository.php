<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class UsocFinancingTermsRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function findByRequestId(\PDO $db, string $requestId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM usoc_financing_terms WHERE REQUEST_ID = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByInscriptionAndIdpag(
        \PDO $db,
        int $idInsc,
        int $idpag,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM usoc_financing_terms WHERE ID_INSC = ? AND IDPAG = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$idInsc, $idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function prepare(
        \PDO $db,
        string $requestId,
        int $idInsc,
        int $idpag,
        string $studentAmount,
        string $entityAmount,
        string $actorId,
        array $roles,
        string $legacyHash
    ): array {
        $existingRequest = $this->findByRequestId($db, $requestId, true);
        if ($existingRequest !== null) {
            $this->assertSame(
                $existingRequest,
                $idInsc,
                $idpag,
                $studentAmount,
                $entityAmount
            );

            return $this->result($existingRequest, true);
        }

        $existingTerms = $this->findByInscriptionAndIdpag($db, $idInsc, $idpag, true);
        if ($existingTerms !== null) {
            $this->assertSame(
                $existingTerms,
                $idInsc,
                $idpag,
                $studentAmount,
                $entityAmount
            );

            return $this->result($existingTerms, true);
        }

        $uuid = $this->uuidGenerator->generate();
        try {
            $db->prepare(
                'INSERT INTO usoc_financing_terms (
                    UUID_TERMS, REQUEST_ID, ID_INSC, IDPAG,
                    STUDENT_AMOUNT, ENTITY_AMOUNT, ACTOR_ID, ACTOR_ROLES,
                    LEGACY_STATE_HASH
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $uuid,
                $requestId,
                $idInsc,
                $idpag,
                $studentAmount,
                $entityAmount,
                $actorId,
                $this->roles($roles),
                $legacyHash,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $raced = $this->findByInscriptionAndIdpag($db, $idInsc, $idpag, true)
                ?? $this->findByRequestId($db, $requestId, true);
            if ($raced === null) {
                throw $exception;
            }

            $this->assertSame($raced, $idInsc, $idpag, $studentAmount, $entityAmount);
            return $this->result($raced, true);
        }

        $created = $this->findByRequestId($db, $requestId, true);
        if ($created === null) {
            throw new \RuntimeException('USOC financing terms could not be reloaded after insert');
        }

        return $this->result($created, false);
    }

    private function assertSame(
        array $existing,
        int $idInsc,
        int $idpag,
        string $studentAmount,
        string $entityAmount
    ): void {
        if ((int) $existing['ID_INSC'] !== $idInsc || (int) $existing['IDPAG'] !== $idpag) {
            throw SifException::conflict(
                'USOC financing terms request is already bound to another inscription'
            );
        }

        if (
            number_format((float) $existing['STUDENT_AMOUNT'], 2, '.', '') !== $studentAmount
            || number_format((float) $existing['ENTITY_AMOUNT'], 2, '.', '') !== $entityAmount
        ) {
            throw SifException::conflict(
                'USOC financing terms already exist with different amounts'
            );
        }
    }

    private function result(array $row, bool $reused): array
    {
        $row['idempotency_reused'] = $reused;
        return $row;
    }

    private function roles(array $roles): ?string
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => strtoupper(trim((string) $role)),
            $roles
        ))));
        sort($normalized, SORT_STRING);

        if ($normalized === []) {
            return null;
        }

        return substr(implode(',', $normalized), 0, 255);
    }
}
