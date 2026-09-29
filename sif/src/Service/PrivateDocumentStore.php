<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class PrivateDocumentStore
{
    private string $root;

    public function __construct(string $root, private int $maxBytes = 20971520)
    {
        $resolved = realpath(trim($root));
        if ($resolved === false || !is_dir($resolved)) {
            throw new \RuntimeException('Private document root is not configured or does not exist');
        }

        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
        $this->maxBytes = max(1024, $this->maxBytes);
    }

    public function readVerified(string $storedPath, string $expectedHash): string
    {
        $storedPath = trim($storedPath);
        $expectedHash = strtolower(trim($expectedHash));

        if ($storedPath === '' || preg_match('/^[0-9a-f]{64}$/D', $expectedHash) !== 1) {
            throw SifException::unavailable('Document metadata is incomplete');
        }

        $candidate = $this->isAbsolutePath($storedPath)
            ? $storedPath
            : $this->root . DIRECTORY_SEPARATOR . ltrim($storedPath, '/\\');

        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            throw SifException::unavailable('Document bytes are unavailable');
        }

        $prefix = $this->root . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolved, $prefix)) {
            throw SifException::forbidden('Document path escapes private storage');
        }

        $size = filesize($resolved);
        if ($size === false || $size > $this->maxBytes) {
            throw SifException::unavailable('Document size is invalid');
        }

        $bytes = file_get_contents($resolved);
        if ($bytes === false) {
            throw SifException::unavailable('Document bytes are unavailable');
        }

        if (!hash_equals($expectedHash, hash('sha256', $bytes))) {
            throw SifException::conflict('Document integrity check failed');
        }

        return $bytes;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
