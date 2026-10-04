<?php

namespace Prisma\Sif\Exception;

/**
 * The request may have reached AEAT, but the local process cannot prove the
 * final remote outcome. This state must be reviewed/reconciled, never retried
 * blindly as a normal transport failure.
 */
final class AeatDeliveryUncertainException extends \RuntimeException
{
    private ?string $evidenceId = null;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if (preg_match('/(?:^|[;\\s])evidence=(\\d{8}T\\d{6}Z-[a-f0-9]{24})(?:$|[;\\s])/D', $message, $match) === 1) {
            $this->evidenceId = $match[1];
        }
    }

    public function evidenceId(): ?string
    {
        return $this->evidenceId;
    }
}
