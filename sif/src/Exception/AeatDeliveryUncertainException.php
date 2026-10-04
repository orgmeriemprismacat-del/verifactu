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
    private ?string $responseSha256 = null;
    private ?int $httpStatus = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $evidenceId = null,
        ?string $responseSha256 = null,
        ?int $httpStatus = null
    ) {
        parent::__construct($message, $code, $previous);

        $candidateEvidence = $evidenceId;
        if ($candidateEvidence === null
            && preg_match(
                '/(?:^|[;\\s])evidence=(\\d{8}T\\d{6}Z-[a-f0-9]{24})(?:$|[;\\s])/D',
                $message,
                $match
            ) === 1
        ) {
            $candidateEvidence = $match[1];
        }
        if (is_string($candidateEvidence)
            && preg_match('/^\\d{8}T\\d{6}Z-[a-f0-9]{24}$/D', $candidateEvidence) === 1
        ) {
            $this->evidenceId = $candidateEvidence;
        }

        if (is_string($responseSha256)
            && preg_match('/^[a-f0-9]{64}$/D', $responseSha256) === 1
        ) {
            $this->responseSha256 = $responseSha256;
        }
        if ($httpStatus !== null && $httpStatus >= 0 && $httpStatus <= 599) {
            $this->httpStatus = $httpStatus;
        }
    }

    public function evidenceId(): ?string
    {
        return $this->evidenceId;
    }

    public function responseSha256(): ?string
    {
        return $this->responseSha256;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
