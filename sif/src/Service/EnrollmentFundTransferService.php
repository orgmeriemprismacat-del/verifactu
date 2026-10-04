<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class EnrollmentFundTransferService
{
    public function __construct(
        private TransactionRunner $transactions,
        private EnrollmentFundMovementRepository $funds,
        private EnrollmentFundTransferPayloadBuilder $builder
    ) {
    }

    public function transfer(array $input): array
    {
        $payload = $this->builder->build($input);

        return $this->transactions->run(
            fn (\PDO $db): array => $this->funds->insertOrReuseInternalTransfer(
                $db,
                $payload
            )
        );
    }

    public function reverseTransfer(array $input): array
    {
        $payload = $this->builder->buildReversal($input);

        return $this->transactions->run(
            fn (\PDO $db): array => $this->funds->insertOrReuseInternalTransferReversal(
                $db,
                $payload
            )
        );
    }
}
