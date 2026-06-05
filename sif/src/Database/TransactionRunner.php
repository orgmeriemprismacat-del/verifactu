<?php

namespace Prisma\Sif\Database;

final class TransactionRunner
{
    public function __construct(private \PDO $db)
    {
    }

    public function run(callable $callback): mixed
    {
        $this->db->beginTransaction();

        try {
            $result = $callback($this->db);
            $this->db->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
