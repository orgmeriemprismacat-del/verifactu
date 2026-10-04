<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingTermsRepository;

final class UsocFinancingTermsService
{
    public function __construct(private UsocFinancingTermsRepository $terms)
    {
    }

    public function prepare(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $requestId,
        int $idInsc,
        int $idpag,
        mixed $studentAmount,
        mixed $entityAmount,
        string $actorId,
        array $roles
    ): array {
        $requestId = trim($requestId);
        $actorId = trim($actorId);
        if (
            $requestId === ''
            || strlen($requestId) > 120
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
            || $idInsc <= 0
            || $idpag <= 0
            || $actorId === ''
        ) {
            throw SifException::validation('Invalid USOC financing terms identity');
        }

        $student = $this->positiveMoney($studentAmount, 'Invalid USOC student amount');
        $entity = $this->positiveMoney($entityAmount, 'Invalid USOC entity amount');
        $legacy = $this->legacyState($legacyDb, $idInsc, $idpag);

        if ((int) $legacy['TIPUS_DESC'] !== 4 || (int) $legacy['VALID_DESC'] !== 1) {
            throw SifException::conflict(
                'USOC financing terms require a validated USOC inscription'
            );
        }

        $legacyStudent = $this->positiveMoney(
            $legacy['A_PAGAR'] ?? null,
            'Invalid legacy USOC A_PAGAR amount'
        );
        if ($student !== $legacyStudent) {
            throw SifException::conflict(
                'USOC financing student amount does not match legacy A_PAGAR'
            );
        }

        $sifDb->beginTransaction();
        try {
            $result = $this->terms->prepare(
                $sifDb,
                $requestId,
                $idInsc,
                $idpag,
                $student,
                $entity,
                $actorId,
                $roles,
                $this->legacyHash($legacy)
            );
            $sifDb->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    public function view(\PDO $sifDb, int $idInsc, int $idpag): array
    {
        if ($idInsc <= 0 || $idpag <= 0) {
            throw SifException::validation('Invalid USOC financing terms identity');
        }

        $terms = $this->terms->findByInscriptionAndIdpag($sifDb, $idInsc, $idpag);
        if ($terms === null) {
            throw SifException::conflict('USOC financing terms not found');
        }

        return $terms;
    }

    private function legacyState(\PDO $legacyDb, int $idInsc, int $idpag): array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, IDPAG, TIPUS_DESC, VALID_DESC, A_PAGAR
             FROM inscripcions
             WHERE ID = ? AND IDPAG = ?'
        );
        $stmt->execute([$idInsc, $idpag]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            throw SifException::conflict(
                'Legacy USOC inscription not found or ambiguous for financing terms'
            );
        }

        return $rows[0];
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation($message);
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
        if ($cents <= 0) {
            throw SifException::validation($message);
        }

        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function legacyHash(array $legacy): string
    {
        $canonical = json_encode([
            'ID' => (int) $legacy['ID'],
            'IDPAG' => (int) $legacy['IDPAG'],
            'TIPUS_DESC' => (int) $legacy['TIPUS_DESC'],
            'VALID_DESC' => (int) $legacy['VALID_DESC'],
            'A_PAGAR' => number_format((float) $legacy['A_PAGAR'], 2, '.', ''),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical);
    }
}
