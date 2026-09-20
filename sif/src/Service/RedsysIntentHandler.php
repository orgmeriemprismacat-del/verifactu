<?php

namespace Prisma\Sif\Service;

interface RedsysIntentHandler
{
    public function sourceType(): string;

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array;
}
