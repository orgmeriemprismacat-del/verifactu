<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class InternalApiRequestRepository
{
    public function claim(
        \PDO $db,
        string $requestId,
        string $keyId,
        string $actorId,
        array $roles,
        string $method,
        string $path,
        string $bodyHash,
        \DateTimeImmutable $requestedAt
    ): void {
        try {
            $db->prepare(
                'INSERT INTO internal_api_request (
                    REQUEST_ID, KEY_ID, ACTOR_ID, ACTOR_ROLES, HTTP_METHOD,
                    REQUEST_PATH, BODY_HASH, REQUESTED_AT
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $requestId,
                $keyId,
                $actorId,
                implode(',', $roles),
                $method,
                $path,
                $bodyHash,
                $requestedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw SifException::conflict('Internal API request replay detected');
            }

            throw $exception;
        }
    }
}
