<?php

namespace Prisma\Sif\Contract;

interface FiscalDocumentRendererInterface
{
    /**
     * @return array{contents:string, extension:string}
     */
    public function render(\PDO $db, array $job): array;
}
