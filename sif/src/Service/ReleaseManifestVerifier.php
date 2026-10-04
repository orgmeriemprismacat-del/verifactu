<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ReleaseManifestVerifier
{
    private const GOVERNED_ROOTS = ['src', 'public', 'config', 'scripts', 'database/migrations'];

    public static function governedRoots(): array
    {
        return self::GOVERNED_ROOTS;
    }

    public function verify(string $baseDir, string $manifestPath): array
    {
        $baseDir = realpath($baseDir) ?: '';
        $manifestPath = trim($manifestPath);
        $manifestReal = $manifestPath === '' ? false : realpath($manifestPath);

        if ($baseDir === '' || $manifestReal === false || !is_file($manifestReal)) {
            throw SifException::unavailable('Release manifest is not available');
        }
        if ($manifestReal === $baseDir || str_starts_with($manifestReal, $baseDir . DIRECTORY_SEPARATOR)) {
            throw SifException::validation('Release manifest must be stored outside the SIF release tree');
        }

        $raw = file_get_contents($manifestReal);
        $manifest = $raw === false ? null : json_decode($raw, true);
        if (!is_array($manifest) || (int) ($manifest['schema'] ?? 0) !== 1) {
            throw SifException::validation('Invalid release manifest');
        }

        $files = $manifest['files'] ?? null;
        if (!is_array($files) || $files === [] || array_is_list($files)) {
            throw SifException::validation('Release manifest must contain a file hash map');
        }

        $declaredArtifactHash = strtolower(trim((string) ($manifest['artifact_hash'] ?? '')));
        if (preg_match('/^[0-9a-f]{64}$/D', $declaredArtifactHash) !== 1) {
            throw SifException::validation('Release manifest artifact hash is missing or invalid');
        }

        $normalizedFiles = [];
        foreach ($files as $relative => $expectedHash) {
            $relative = str_replace('\\', '/', trim((string) $relative));
            $expectedHash = strtolower(trim((string) $expectedHash));

            if ($relative === ''
                || str_starts_with($relative, '/')
                || str_contains('/' . $relative . '/', '/../')
                || !$this->isGovernedRelativePath($relative)
                || preg_match('/^[0-9a-f]{64}$/D', $expectedHash) !== 1
            ) {
                throw SifException::validation('Invalid release manifest entry');
            }
            if (array_key_exists($relative, $normalizedFiles)) {
                throw SifException::validation('Duplicate normalized release manifest path');
            }

            $normalizedFiles[$relative] = $expectedHash;
        }

        ksort($normalizedFiles, SORT_STRING);
        $files = $normalizedFiles;
        $verified = [];
        $mismatches = [];

        foreach ($files as $relative => $expectedHash) {
            $rawPath = $baseDir . '/' . $relative;
            if (is_link($rawPath)) {
                $mismatches[$relative] = 'SYMLINK_NOT_ALLOWED';
                continue;
            }

            $path = realpath($rawPath);
            if ($path === false || !str_starts_with($path, $baseDir . DIRECTORY_SEPARATOR) || !is_file($path)) {
                $mismatches[$relative] = 'MISSING';
                continue;
            }

            $actualHash = hash_file('sha256', $path);
            if (!hash_equals($expectedHash, $actualHash)) {
                $mismatches[$relative] = 'HASH_MISMATCH';
                continue;
            }

            $verified[$relative] = $actualHash;
        }

        foreach ($this->currentGovernedFiles($baseDir) as $relative => $kind) {
            if (!array_key_exists($relative, $files)) {
                $mismatches[$relative] = $kind === 'symlink' ? 'UNEXPECTED_SYMLINK' : 'UNEXPECTED_FILE';
            }
        }

        $artifactJson = json_encode(
            $files,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $artifactHash = hash('sha256', $artifactJson);
        if (!hash_equals($declaredArtifactHash, $artifactHash)) {
            $mismatches['manifest:artifact_hash'] = 'ARTIFACT_HASH_MISMATCH';
        }

        return [
            'ok' => $mismatches === [],
            'artifact_hash' => $artifactHash,
            'file_count' => count($files),
            'verified_count' => count($verified),
            'mismatches' => $mismatches,
        ];
    }

    private function isGovernedRelativePath(string $relative): bool
    {
        foreach (self::GOVERNED_ROOTS as $root) {
            if ($relative === $root || str_starts_with($relative, $root . '/')) {
                return true;
            }
        }

        return false;
    }

    private function currentGovernedFiles(string $baseDir): array
    {
        $inventory = [];

        foreach (self::GOVERNED_ROOTS as $root) {
            $absolute = $baseDir . '/' . $root;
            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                $pathName = $file->getPathname();
                $relative = str_replace('\\', '/', substr($pathName, strlen($baseDir) + 1));

                if ($file->isLink()) {
                    $inventory[$relative] = 'symlink';
                    continue;
                }
                if ($file->isFile()) {
                    $inventory[$relative] = 'file';
                }
            }
        }

        ksort($inventory, SORT_STRING);
        return $inventory;
    }
}
