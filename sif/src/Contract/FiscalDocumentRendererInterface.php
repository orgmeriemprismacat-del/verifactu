<?php

namespace Prisma\Sif\Contract;

interface FiscalDocumentRendererInterface
{
    /**
     * The snapshot has already passed fiscal hash verification and is the only
     * authoritative invoice input exposed to the renderer.
     *
     * @return array{contents:string, extension:string}
     */
    public function render(array $snapshot, array $job): array;
}
