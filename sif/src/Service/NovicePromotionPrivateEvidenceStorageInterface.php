<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * Private object-storage boundary for UC-111 documentary evidence.
 *
 * Implementations MUST keep the object non-public and make putPrivate()
 * idempotent for the same storage reference + expected content. The SIF
 * persists only an opaque private reference, hash and metadata; this contract
 * does not return a public URL.
 */
interface NovicePromotionPrivateEvidenceStorageInterface
{
    public function putPrivate(
        string $sourceLocalPath,
        string $storageRef,
        string $mimeType,
        string $expectedSha256,
        int $expectedSizeBytes
    ): void;

    /**
     * Best-effort cleanup for a failed/abandoned write. Implementations must
     * never delete an object belonging to another storageRef.
     */
    public function deletePrivate(string $storageRef): void;
}
