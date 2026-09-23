<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * Trusted private mail adapter. Do not log code or recipient, place the code
 * in a URL query string, or return it to a web controller. Returning true
 * means the provider ACCEPTED the message, not that it was received/read.
 *
 * $messageId is stable for the entitlement: if the provider supports a
 * deduplication key, use it. SMTP itself is not exactly-once.
 */
interface NovicePromotionMailTransportInterface
{
    public function sendPromotionCode(string $verifiedEmail, string $code, string $messageId): bool;
}
