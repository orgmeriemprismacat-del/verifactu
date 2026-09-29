<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * Trusted INTERNAL mail boundary. Implementations must not log the secret or
 * expose it to the browser; they must deliver it to the email argument only.
 * Returning true means accepted by the provider, NOT received by the user.
 * No SMTP/production adapter is included in this branch.
 */
interface NoviceEmailChallengeTransportInterface
{
    public function sendChallenge(
        string $email,
        string $uuidChallenge,
        string $oneTimeSecret,
        \DateTimeImmutable $expiresAt
    ): bool;
}
