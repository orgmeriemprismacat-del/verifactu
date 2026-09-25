<?php

namespace Prisma\Sif\Aeat;

use Prisma\Sif\Contract\AeatTransport;

/** Used under SerialWorker's DB advisory lock, including throughout network I/O. */
final class FlowControlledTransport implements AeatTransport
{
    public function __construct(private \PDO $db, private AeatTransport $transport) {}

    public function send(array $fiscalPayload): array
    {
        // Persist a conservative wait even when the process dies during delivery.
        $this->delay(60);
        try {
            $result = $this->transport->send($fiscalPayload);
            $wait = $result['response']['flow_wait_seconds'] ?? 60;
            if (!is_int($wait) || $wait < 0 || $wait > 999999) {
                throw new \RuntimeException('Invalid AEAT flow wait.');
            }
        } catch (\Throwable $error) {
            // A failed/uncertain delivery still needs a full wait after it finishes.
            $this->delay(60);
            throw $error;
        }
        $this->delay(max(60, $wait));
        return $result;
    }

    private function delay(int $seconds): void
    {
        $stmt = $this->db->prepare(
            'UPDATE aeat_worker_state SET NEXT_SEND_AT = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE ID = 1');
        $stmt->execute([$seconds]);
    }
}
