<?php

namespace Prisma\Sif\Contract;

interface DocumentAuthorizationPolicyInterface
{
    public function canDownload(array $actor, array $invoice, array $relations, array $document): bool;
}
