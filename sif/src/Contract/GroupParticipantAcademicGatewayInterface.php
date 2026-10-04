<?php

namespace Prisma\Sif\Contract;

interface GroupParticipantAcademicGatewayInterface
{
    public function addParticipant(
        \PDO $legacyDb,
        int $idInsc,
        array $context
    ): array;

    public function removeParticipant(
        \PDO $legacyDb,
        int $idInsc,
        array $context
    ): array;
}
