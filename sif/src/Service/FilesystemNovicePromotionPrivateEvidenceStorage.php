<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * Local private-filesystem implementation for UC-111 evidence.
 *
 * The configured root must live outside the public web tree. The service
 * accepts only opaque UC-111 storage references and writes atomically with
 * restrictive permissions.
 */
final class FilesystemNovicePromotionPrivateEvidenceStorage implements NovicePromotionPrivateEvidenceStorageInterface
{
    private string $root;

    public function __construct(string $root)
    {
        $resolved = realpath(trim($root));
        if ($resolved === false || !is_dir($resolved) || !is_writable($resolved)) {
            throw new \RuntimeException('Novice evidence private storage root is not available.');
        }

        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function putPrivate(
        string $sourceLocalPath,
        string $storageRef,
        string $mimeType,
        string $expectedSha256,
        int $expectedSizeBytes
    ): void {
        $source = realpath($sourceLocalPath);
        if ($source === false || !is_file($source) || !is_readable($source)) {
            throw SifException::unavailable('Evidence source bytes are unavailable.');
        }

        $this->assertStorageRef($storageRef);
        $expectedSha256 = strtolower(trim($expectedSha256));
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedSha256) !== 1 || $expectedSizeBytes < 1) {
            throw SifException::validation('Evidence integrity metadata is invalid.');
        }

        $size = filesize($source);
        $hash = hash_file('sha256', $source);
        if ($size === false || $hash === false
            || (int) $size !== $expectedSizeBytes
            || !hash_equals($expectedSha256, strtolower($hash))
        ) {
            throw SifException::conflict('Evidence source does not match the expected immutable snapshot.');
        }

        $destination = $this->pathFor($storageRef);
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw SifException::unavailable('Could not create private evidence directory.');
        }

        if (is_file($destination)) {
            $existingSize = filesize($destination);
            $existingHash = hash_file('sha256', $destination);
            if ($existingSize === $expectedSizeBytes
                && is_string($existingHash)
                && hash_equals($expectedSha256, strtolower($existingHash))
            ) {
                return;
            }

            throw SifException::conflict('Private evidence reference already contains different bytes.');
        }

        $temporary = $destination . '.tmp-' . bin2hex(random_bytes(8));
        try {
            if (!copy($source, $temporary)) {
                throw SifException::unavailable('Could not write private evidence bytes.');
            }
            @chmod($temporary, 0600);

            $writtenSize = filesize($temporary);
            $writtenHash = hash_file('sha256', $temporary);
            if ($writtenSize === false || $writtenHash === false
                || (int) $writtenSize !== $expectedSizeBytes
                || !hash_equals($expectedSha256, strtolower($writtenHash))
            ) {
                throw SifException::conflict('Private evidence integrity verification failed.');
            }

            if (!rename($temporary, $destination)) {
                throw SifException::unavailable('Could not commit private evidence object.');
            }
            @chmod($destination, 0600);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function deletePrivate(string $storageRef): void
    {
        $this->assertStorageRef($storageRef);
        $path = $this->pathFor($storageRef);
        if (is_file($path) && !unlink($path)) {
            throw SifException::unavailable('Could not delete private evidence object.');
        }
    }

    private function assertStorageRef(string $storageRef): void
    {
        if (preg_match(
            '/^novice-evidence\/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.bin$/D',
            strtolower(trim($storageRef))
        ) !== 1) {
            throw SifException::validation('Invalid private evidence storage reference.');
        }
    }

    private function pathFor(string $storageRef): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, strtolower(trim($storageRef)));
    }
}
