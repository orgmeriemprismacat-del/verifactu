<?php

namespace Prisma\Sif\Aeat;

use Prisma\Sif\Contract\AeatTransport;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\{FiscalQueueRepository, IncidentRepository};
use Prisma\Sif\Service\FiscalQueueProcessor;

/** All real submissions for this single issuer must use this entry point. */
final class SerialWorker
{
    public function __construct(private \PDO $db, private AeatTransport $transport,
        private int $maxAttempts = 3, private int $baseRetrySeconds = 60, private int $maxRetrySeconds = 3600) {}

    public function runOnce(bool $recoverStale = false): array
    {
        $lock = 'prisma-aeat-' . substr(hash('sha256', (string) $this->db->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([$lock]);
        if ((int) $stmt->fetchColumn() !== 1) {
            return ['ok' => true, 'processed' => false, 'reason' => 'WORKER_BUSY'];
        }
        try {
            $this->db->exec('INSERT IGNORE INTO aeat_worker_state (ID) VALUES (1)');
            $processor = new FiscalQueueProcessor(new TransactionRunner($this->db),
                new FiscalQueueRepository(), new FlowControlledTransport($this->db, $this->transport),
                $this->maxAttempts, $this->baseRetrySeconds, $this->maxRetrySeconds);
            if ($recoverStale) {
                $processor->recoverStaleLocks(900);
            }
            $head = $this->db->query("SELECT *, (NEXT_RETRY_AT IS NULL OR NEXT_RETRY_AT <= NOW()) AS DUE
                FROM fiscal_queue WHERE STATUS <> 'SENT' ORDER BY ID LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            if (!$head) {
                return ['ok' => true, 'processed' => false, 'reason' => 'EMPTY'];
            }
            if (in_array($head['STATUS'], ['PENDING', 'RETRY'], true) && (int) $head['ATTEMPTS'] >= $this->maxAttempts) {
                (new TransactionRunner($this->db))->run(function (\PDO $db) use ($head): void {
                    (new FiscalQueueRepository())->fail($db, $head, 'Attempt budget exhausted after recovery', $this->maxAttempts, null);
                    (new IncidentRepository())->open($db, $head['UUID_FACTURA'], 'AEAT_DEAD_LETTER',
                        'Queue ID ' . $head['ID'] . ': attempt budget exhausted');
                });
                $head['STATUS'] = 'DEAD_LETTER';
            }
            if (!in_array($head['STATUS'], ['PENDING', 'RETRY'], true)) {
                return ['ok' => false, 'processed' => false, 'reason' => 'HEAD_REQUIRES_REVIEW', 'queue_id' => (int) $head['ID']];
            }
            $flowReady = $this->db->query('SELECT NEXT_SEND_AT IS NULL OR NEXT_SEND_AT <= NOW()
                FROM aeat_worker_state WHERE ID = 1')->fetchColumn();
            if (!(bool) $flowReady || !(bool) $head['DUE']) {
                return ['ok' => true, 'processed' => false, 'reason' => 'WAIT'];
            }
            $result = $processor->processNext();
            if (($result['queue_status'] ?? '') === 'DEAD_LETTER'
                || ($result['requires_review'] ?? false) === true
                || in_array($result['aeat_status'] ?? '', ['REJECTED', 'ACCEPTED_WITH_ERRORS'], true)) {
                (new IncidentRepository())->open($this->db, $head['UUID_FACTURA'], 'AEAT_REVIEW',
                    'Queue ID ' . $head['ID'] . ': review protected response/evidence');
            }
            return $result;
        } finally {
            $release = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lock]);
        }
    }
}
