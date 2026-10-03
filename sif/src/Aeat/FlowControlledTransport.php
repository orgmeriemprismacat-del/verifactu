<?php

namespace Prisma\Sif\Aeat;

use Prisma\Sif\Contract\AeatTransport;

/** Used under SerialWorker's DB advisory lock, including throughout network I/O. */
final class FlowControlledTransport implements AeatTransport
{
    public function __construct(private \PDO $db, private AeatTransport $transport) {}

    public function send(array $fiscalPayload): array
    {
        // Persist a conservative wait before network I/O. If this write fails,
        // no remote delivery has started and the caller may treat it as retryable.
        $this->delay(60);

        try {
            $result = $this->transport->send($fiscalPayload);
        } catch (\Throwable $error) {
            // A failed/uncertain delivery still needs a full wait after it finishes.
            try {
                $this->delay(60);
            } catch (\Throwable) {
                // Preserve the original transport outcome. The caller decides
                // RETRY vs REVIEW according to the transport exception type.
            }
            throw $error;
        }

        // Once the wrapped transport has returned, a remote result is known.
        // Operational flow-control anomalies must never turn that result into
        // a retry of the same fiscal record.
        $response = $result['response'] ?? null;
        $wait = is_array($response) ? ($response['flow_wait_seconds'] ?? 60) : 60;
        if (!is_int($wait) || $wait < 0 || $wait > 999999) {
            $wait = 60;
            if (is_array($response)) {
                $result['response']['requires_review'] = true;
                $result['response']['flow_control_warning'] = 'INVALID_FLOW_WAIT';
            }
        }

        try {
            $this->delay(max(60, $wait));
        } catch (\Throwable) {
            // The pre-send 60-second guard is already persisted. Preserve the
            // remote result and flag the operational anomaly for review rather
            // than throwing and causing a blind resend.
            if (is_array($result['response'] ?? null)) {
                $result['response']['requires_review'] = true;
                $result['response']['flow_control_warning'] = 'FLOW_WAIT_PERSIST_FAILED';
            }
        }

        return $result;
    }

    private function delay(int $seconds): void
    {
        $stmt = $this->db->prepare(
            'UPDATE aeat_worker_state SET NEXT_SEND_AT = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE ID = 1'
        );
        $stmt->execute([$seconds]);
        if ($stmt->rowCount() > 1) {
            throw new \RuntimeException('Invalid AEAT worker state update.');
        }
    }
}
