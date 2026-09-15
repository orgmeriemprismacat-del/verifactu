<?php

namespace Prisma\Sif\Service;

interface RedsysJobProcessor
{
    public function process(\PDO $sifDb, array $job): array;
}
