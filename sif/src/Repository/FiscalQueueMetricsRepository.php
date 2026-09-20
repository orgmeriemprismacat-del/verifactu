<?php

namespace Prisma\Sif\Repository;

final class FiscalQueueMetricsRepository
{
    public function snapshot(\PDO $db, int $staleLockSeconds = 900): array
    {
        if ($staleLockSeconds < 60) {
            throw new \InvalidArgumentException('Stale lock threshold must be at least 60 seconds.');
        }

        $counts = [
            'PENDING' => 0,
            'PROCESSING' => 0,
            'RETRY' => 0,
            'SENT' => 0,
            'DEAD_LETTER' => 0,
        ];
        $rows = $db->query('SELECT STATUS, COUNT(*) AS TOTAL FROM fiscal_queue GROUP BY STATUS')
            ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $counts[(string) $row['STATUS']] = (int) $row['TOTAL'];
        }

        $due = (int) $db->query(
            "SELECT COUNT(*) FROM fiscal_queue
             WHERE STATUS IN ('PENDING', 'RETRY')
               AND (NEXT_RETRY_AT IS NULL OR NEXT_RETRY_AT <= NOW())"
        )->fetchColumn();
        $stale = $db->prepare(
            "SELECT COUNT(*) FROM fiscal_queue
             WHERE STATUS = 'PROCESSING'
               AND LOCKED_AT < ?"
        );
        $stale->execute([
            (new \DateTimeImmutable('now'))
                ->modify('-' . $staleLockSeconds . ' seconds')
                ->format('Y-m-d H:i:s'),
        ]);

        return [
            'counts' => $counts,
            'due' => $due,
            'stale_locks' => (int) $stale->fetchColumn(),
            'oldest_actionable_at' => $this->oldestActionableAt($db),
        ];
    }

    private function oldestActionableAt(\PDO $db): ?string
    {
        $value = $db->query(
            "SELECT MIN(COALESCE(NEXT_RETRY_AT, CREATED_AT))
             FROM fiscal_queue
             WHERE STATUS IN ('PENDING', 'RETRY')"
        )->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }
}
