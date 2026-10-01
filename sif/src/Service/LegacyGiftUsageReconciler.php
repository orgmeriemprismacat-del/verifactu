<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * Final UC-018 legacy reconciliation.
 *
 * This runs only after the SIF redemption has committed. A failure here is
 * retriable: the SIF operation/entitlement/fund movement are idempotent and
 * the next request can safely attempt the same compare-and-set again.
 */
final class LegacyGiftUsageReconciler
{
    public function reconcile(
        \PDO $legacyDb,
        string $giftCode,
        int $enrollmentId
    ): array {
        $giftCode = trim($giftCode);
        if ($giftCode === '' || strlen($giftCode) > 200 || $enrollmentId < 1) {
            throw SifException::validation('Invalid legacy gift reconciliation input');
        }

        if ($legacyDb->inTransaction()) {
            throw new \LogicException(
                'Legacy gift reconciliation must start its own transaction'
            );
        }

        $legacyDb->beginTransaction();
        try {
            $statement = $legacyDb->prepare(
                'SELECT ID, USAT FROM regal WHERE CODI = ? FOR UPDATE'
            );
            $statement->execute([$giftCode]);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) !== 1) {
                throw SifException::conflict(
                    'Expected exactly one legacy gift during reconciliation'
                );
            }

            $giftId = (int) $rows[0]['ID'];
            $usedBy = $rows[0]['USAT'] ?? null;

            if ($usedBy !== null && $usedBy !== '' && (int) $usedBy !== 0) {
                if ((int) $usedBy !== $enrollmentId) {
                    throw SifException::conflict(
                        'Legacy gift is linked to another enrollment'
                    );
                }

                $legacyDb->commit();

                return [
                    'gift_id' => $giftId,
                    'enrollment_id' => $enrollmentId,
                    'status' => 'RECONCILED',
                    'idempotency_reused' => true,
                ];
            }

            $update = $legacyDb->prepare(
                'UPDATE regal
                 SET USAT = ?
                 WHERE CODI = ?
                   AND (USAT IS NULL OR USAT = 0)'
            );
            $update->execute([$enrollmentId, $giftCode]);

            if ($update->rowCount() !== 1) {
                $verify = $legacyDb->prepare(
                    'SELECT USAT FROM regal WHERE CODI = ? FOR UPDATE'
                );
                $verify->execute([$giftCode]);
                $current = $verify->fetchColumn();

                if ($current === false || (int) $current !== $enrollmentId) {
                    throw SifException::conflict(
                        'Legacy gift usage changed concurrently'
                    );
                }
            }

            $legacyDb->commit();

            return [
                'gift_id' => $giftId,
                'enrollment_id' => $enrollmentId,
                'status' => 'RECONCILED',
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($legacyDb->inTransaction()) {
                $legacyDb->rollBack();
            }
            throw $exception;
        }
    }
}
