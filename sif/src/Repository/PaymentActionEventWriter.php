<?php

namespace Prisma\Sif\Repository;

interface PaymentActionEventWriter
{
    public function append(\PDO $db, array $event): string;
}
