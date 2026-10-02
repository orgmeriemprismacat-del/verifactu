<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\DocumentStorageWriterInterface;
use Prisma\Sif\Exception\SifException;

final class PrivateDocumentWriter implements DocumentStorageWriterInterface
{
    private string $root;

    public function __construct(string $root, private int $maxBytes = 20971520)
    {
        $resolved = realpath(trim($root));
        if ($resolved === false || !is_dir($resolved) || !is_writable($resolved)) {
            throw new \RuntimeException('Private document root is not configured or writable');
        }

        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
        $this->maxBytes = max(1024, $this->maxBytes);
    }

    public function writeVerified(string $storageKey, string $contents): array
    {
        $storageKey = $this->normaliseStorageKey($storageKey);
        $size = strlen($contents);
        if ($size < 1 || $size > $this->maxBytes) {
            throw SifException::validation('Document bytes size is invalid');
        }

        $hash = hash('sha256', $contents);
        $target = $this->targetPath($storageKey);
        $parent = dirname($target);
        $this->ensureDirectory($parent);

        if (is_link($target)) {
            throw SifException::forbidden('Document storage target cannot be a symbolic link');
        }

        if (is_file($target)) {
            return $this->existingResult($storageKey, $target, $hash, $size);
        }

        $tmp = tempnam($parent, '.sif-doc-');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create private document temporary file');
        }

        try {
            $written = file_put_contents($tmp, $contents, LOCK_EX);
            if ($written !== $size) {
                throw new \RuntimeException('Could not persist complete document bytes');
            }

            @chmod($tmp, 0600);
            $readBack = file_get_contents($tmp);
            if ($readBack === false || !hash_equals($hash, hash('sha256', $readBack))) {
                throw SifException::conflict('Document storage verification failed before publication');
            }

            // Atomic immutable publication. link() fails when another worker
            // has already published the same storage key.
            if (!@link($tmp, $target)) {
                if (!is_file($target)) {
                    throw new \RuntimeException('Could not publish private document');
                }

                return $this->existingResult($storageKey, $target, $hash, $size);
            }

            @chmod($target, 0600);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        $verified = file_get_contents($target);
        if ($verified === false || !hash_equals($hash, hash('sha256', $verified))) {
            throw SifException::conflict('Published document integrity verification failed');
        }

        return [
            'storage_key' => $storageKey,
            'hash' => $hash,
            'size' => $size,
            'reused' => false,
        ];
    }

    private function existingResult(
        string $storageKey,
        string $target,
        string $expectedHash,
        int $expectedSize
    ): array {
        if (!is_readable($target)) {
            throw SifException::unavailable('Existing private document is not readable');
        }

        $bytes = file_get_contents($target);
        if ($bytes === false) {
            throw SifException::unavailable('Existing private document is unavailable');
        }

        $actualHash = hash('sha256', $bytes);
        if (!hash_equals($expectedHash, $actualHash) || strlen($bytes) !== $expectedSize) {
            throw SifException::conflict(
                'Private document storage key already exists with different contents'
            );
        }

        return [
            'storage_key' => $storageKey,
            'hash' => $actualHash,
            'size' => strlen($bytes),
            'reused' => true,
        ];
    }

    private function normaliseStorageKey(string $storageKey): string
    {
        $storageKey = trim(str_replace('\\', '/', $storageKey), '/');
        if ($storageKey === '' || strlen($storageKey) > 240) {
            throw SifException::validation('Invalid private document storage key');
        }

        $segments = explode('/', $storageKey);
        foreach ($segments as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
                || preg_match('/^[A-Za-z0-9._-]+$/D', $segment) !== 1
            ) {
                throw SifException::validation('Invalid private document storage key');
            }
        }

        return implode('/', $segments);
    }

    private function targetPath(string $storageKey): string
    {
        return $this->root
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create private document directory');
        }

        $resolved = realpath($directory);
        $prefix = $this->root . DIRECTORY_SEPARATOR;
        if ($resolved === false || ($resolved !== $this->root && !str_starts_with($resolved, $prefix))) {
            throw SifException::forbidden('Document directory escapes private storage');
        }

        if (!is_writable($resolved)) {
            throw SifException::unavailable('Private document directory is not writable');
        }
    }
}
