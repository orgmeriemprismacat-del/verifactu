<?php

namespace Prisma\Sif\Database;

final class TransactionRunner
{
    public function __construct(private \PDO $db)
    {
    }

    public function run(callable $callback): mixed
    {
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback($this->db);

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
