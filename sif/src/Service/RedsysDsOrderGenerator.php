<?php

namespace Prisma\Sif\Service;

final class RedsysDsOrderGenerator
{
    public function generate(): string
    {
        return (string) random_int(100000000000, 999999999999);
    }
}
