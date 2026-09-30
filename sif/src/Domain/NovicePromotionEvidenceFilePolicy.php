<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure technical policy for UC-111 documentary evidence.
 *
 * The max size and accepted MIME types are injected from trusted server
 * configuration; the class deliberately does not invent a legal retention
 * period or trust a filename extension supplied by the browser.
 */
final class NovicePromotionEvidenceFilePolicy
{
    /** @var array<string, true> */
    private array $allowedMimeTypes;

    /**
     * @param list<string> $allowedMimeTypes
     */
    public function __construct(
        private int $maxSizeBytes,
        array $allowedMimeTypes
    ) {
        if ($maxSizeBytes < 1 || $allowedMimeTypes === []) {
            throw new \InvalidArgumentException(
                'Evidence upload policy needs a positive size limit and MIME allowlist.'
            );
        }

        $this->allowedMimeTypes = [];
        foreach ($allowedMimeTypes as $mimeType) {
            $mimeType = strtolower(trim($mimeType));
            if ($mimeType === '' || !str_contains($mimeType, '/')) {
                throw new \InvalidArgumentException(
                    'Evidence MIME allowlist contains an invalid type.'
                );
            }
            $this->allowedMimeTypes[$mimeType] = true;
        }
    }

    public function assertAcceptable(
        string $mimeType,
        int $sizeBytes,
        string $sha256
    ): void {
        $mimeType = strtolower(trim($mimeType));

        if (!isset($this->allowedMimeTypes[$mimeType])
            || $sizeBytes < 1
            || $sizeBytes > $this->maxSizeBytes
            || preg_match('/^[a-f0-9]{64}$/D', strtolower($sha256)) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Documentary evidence does not satisfy the configured upload policy.'
            );
        }
    }
}
