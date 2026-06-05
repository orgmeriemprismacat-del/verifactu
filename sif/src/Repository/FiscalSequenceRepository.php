<?php

namespace Prisma\Sif\Repository;

final class FiscalSequenceRepository
{
    public function next(\PDO $db, string $series, int $year): int
    {
        $db->prepare(
            'INSERT INTO fiscal_sequence (TIPUS_SERIE, ANY_FACT, LAST_NUM)
             VALUES (?, ?, 0)
             ON DUPLICATE KEY UPDATE LAST_NUM = LAST_NUM'
        )->execute([$series, $year]);

        $stmt = $db->prepare(
            'SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = ? AND ANY_FACT = ? FOR UPDATE'
        );
        $stmt->execute([$series, $year]);
        $last = $stmt->fetchColumn();

        if ($last === false) {
            throw new \RuntimeException('Could not lock fiscal sequence.');
        }

        $next = (int) $last + 1;
        $db->prepare('UPDATE fiscal_sequence SET LAST_NUM = ? WHERE TIPUS_SERIE = ? AND ANY_FACT = ?')
            ->execute([$next, $series, $year]);

        return $next;
    }
}
