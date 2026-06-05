<?php

namespace Prisma\Sif\Exception;

final class SifException extends \RuntimeException
{
    public static function validation(string $message): self
    {
        return new self($message, 422);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}
