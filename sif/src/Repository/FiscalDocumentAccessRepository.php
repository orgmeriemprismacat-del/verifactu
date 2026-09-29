<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;

final class FiscalDocumentAccessRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function append(\PDO $db, array $event): string
    {
        $uuid = $this->uuidGenerator->generate();
        $occurredAt = $event['occurred_at'] ?? (new \DateTimeImmutable(
            'now',
            new \DateTimeZone('Europe/Madrid')
        ))->format('Y-m-d H:i:s.u');

        $db->prepare(
            'INSERT INTO fiscal_document_access (
                UUID_ACCESS, FACTURA_DOCUMENT_ID, UUID_FACTURA, ACTION, RESULT,
                TOKEN_FINGERPRINT, ACTOR_TYPE, ACTOR_ID, ACTOR_ROLE,
                SOURCE_CHANNEL, REQUEST_ID, CORRELATION_ID, REASON_CODE, OCCURRED_AT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
            $event['document_id'] ?? null,
            $event['uuid_factura'],
            strtoupper((string) ($event['action'] ?? 'DOWNLOAD')),
            strtoupper((string) ($event['result'] ?? 'FAILED')),
            $event['token_fingerprint'] ?? null,
            strtoupper((string) ($event['actor_type'] ?? 'INTERNAL_USER')),
            $event['actor_id'] ?? null,
            $event['actor_role'] ?? null,
            strtoupper((string) ($event['source_channel'] ?? 'INTERNAL_API')),
            (string) ($event['request_id'] ?? 'UNTRACKED'),
            (string) ($event['correlation_id'] ?? ($event['request_id'] ?? 'UNTRACKED')),
            $event['reason_code'] ?? null,
            $occurredAt,
        ]);

        return $uuid;
    }
}
