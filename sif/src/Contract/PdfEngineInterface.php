<?php

namespace Prisma\Sif\Contract;

interface PdfEngineInterface
{
    public function renderHtml(string $html): string;
}
