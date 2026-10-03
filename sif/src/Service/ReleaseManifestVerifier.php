<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ReleaseManifestVerifier
{
    public function verify(string $baseDir, string $manifestPath): array
    {
        $baseDir = realpath($baseDir) ?: '';
        $manifestPath = trim($manifestPath);

        if ($baseDir === '' || $manifestPath === '' || !is_file($manifestPath)) {
            throw SifException::unavailable('Release manifest is not available');
        }

        $raw = file_get_contents($manifestPath);
        $manifest = $raw === false ? null : json_decode($raw, true);
        if (!is_array($manifest) || (int) ($manifest['schema'] ?? 0) !== 1) {
            throw SifException::validation('Invalid release manifest');
        }

        $files = $manifest['files'] ?? null;
        if (!is_array($files) || $files === [] || array_is_list($files)) {
            throw SifException::validation('Release manifest must contain a file hash map');
        }

        ksort($files, SORT_STRING);
        $verified = [];
        $mismatches = [];

        foreach ($files as $relative => $expectedHash) {
            $relative = str_replace('\\', '/', trim((string) $relative));
            $expectedHash = strtolower(trim((string) $expectedHash));

            if ($relative === ''
                || str_starts_with($relative, '/')
                || str_contains('/' . $relative . '/', '/../')
                || preg_match('/^[0-9a-f]{64}$/D', $expectedHash) !== 1
            ) {
                throw SifException::validation('Invalid release manifest entry');
            }

            $path = realpath($baseDir . '/' . $relative);
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

        $artifactJson = json_encode(
            $files,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return [
            'ok' => $mismatches === [],
            'artifact_hash' => hash('sha256', $artifactJson),
            'file_count' => count($files),
            'verified_count' => count($verified),
            'mismatches' => $mismatches,
        ];
    }
}
