<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * Private-only secret-store boundary. The wrapping key is a 32-byte hex key,
 * indexed by outbox.WRAP_KEY_VERSION. It must never be committed to Git,
 * exposed to HTTP callers or printed to logs. Implementations must retain
 * older key versions while their outbox rows remain deliverable.
 */
interface NovicePromotionCodeKeyProviderInterface
{
    public function getHexKeyForVersion(string $keyVersion): string;
}
