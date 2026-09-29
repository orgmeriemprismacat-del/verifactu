<?php

namespace Prisma\Sif\Contract;

interface InvoiceVisibilityPolicyInterface
{
    public function canView(array $actor, array $invoice, array $relations): bool;

    public function project(array $actor, array $view): array;
}
