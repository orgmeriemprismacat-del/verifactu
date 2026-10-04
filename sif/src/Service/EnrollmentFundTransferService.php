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
        return $this->transactions->run(
            fn (\PDO $db): array => $this->transferInTransaction($db, $input)
        );
    }

    public function transferInTransaction(\PDO $db, array $input): array
    {
        if (!$db->inTransaction()) {
            throw new \RuntimeException(
                'transferInTransaction requires an active transaction'
            );
        }

        $payload = $this->builder->build($input);

        return $this->funds->insertOrReuseInternalTransfer($db, $payload);
    }

    public function reverseTransfer(array $input): array
    {
        return $this->transactions->run(
            fn (\PDO $db): array => $this->reverseTransferInTransaction($db, $input)
        );
    }

    public function reverseTransferInTransaction(\PDO $db, array $input): array
    {
        if (!$db->inTransaction()) {
            throw new \RuntimeException(
                'reverseTransferInTransaction requires an active transaction'
            );
        }

        $payload = $this->builder->buildReversal($input);

        return $this->funds->insertOrReuseInternalTransferReversal($db, $payload);
    }
}
