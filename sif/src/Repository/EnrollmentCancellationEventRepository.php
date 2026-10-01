<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class EnrollmentCancellationEventRepository
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function append(\PDO $db, array $event): array
    {
        $operationalUuid = trim((string) ($event['uuid_operational_event'] ?? ''));
        $enrollmentId = (int) ($event['enrollment_id'] ?? 0);

        if ($operationalUuid === '' || $enrollmentId <= 0) {
            throw SifException::validation('Invalid enrollment cancellation identity');
        }

        $existing = $this->findByOperationalEvent($db, $operationalUuid);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $db->prepare(
            'INSERT INTO enrollment_cancellation_event (
                UUID_CANCELLATION, UUID_OPERATIONAL_EVENT, ENROLLMENT_ID,
                CANCELLATION_REASON, EFFECTIVE_AT, ECONOMIC_DECISION,
                RETURN_AMOUNT, CREDIT_AMOUNT, NON_RETURN_REASON,
                FISCAL_DECISION, UUID_RECTIFYING_INVOICE,
                UUID_REFUND_PAYMENT, UUID_CREDIT
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $this->uuids->generate(),
            $operationalUuid,
            $enrollmentId,
            strtoupper(trim((string) ($event['cancellation_reason'] ?? 'USOC_CANCELLATION'))),
            trim((string) ($event['effective_at'] ?? '')),
            strtoupper(trim((string) ($event['economic_decision'] ?? 'NONE'))),
            $this->money($event['return_amount'] ?? '0.00'),
            $this->money($event['credit_amount'] ?? '0.00'),
            $this->nullable($event['non_return_reason'] ?? null),
            strtoupper(trim((string) ($event['fiscal_decision'] ?? 'NONE'))),
            $this->nullable($event['uuid_rectifying_invoice'] ?? null),
            $this->nullable($event['uuid_refund_payment'] ?? null),
            $this->nullable($event['uuid_credit'] ?? null),
        ]);

        return $this->findByOperationalEvent($db, $operationalUuid)
            ?? throw new \RuntimeException('Enrollment cancellation event could not be reloaded');
    }

    public function findByOperationalEvent(\PDO $db, string $uuid): ?array
    {
        $stmt = $db->prepare(
            'SELECT * FROM enrollment_cancellation_event WHERE UUID_OPERATIONAL_EVENT = ?'
        );
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw SifException::validation('Invalid cancellation monetary value');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
