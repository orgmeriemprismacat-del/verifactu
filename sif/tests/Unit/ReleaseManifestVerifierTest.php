<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ReleaseManifestVerifier;
use Prisma\Sif\Service\RuntimeConfigFingerprint;
use Prisma\Sif\Tests\Support\Assert;

final class ReleaseManifestVerifierTest
{
    public function testManifestVerifiesBytesAndProducesStableArtifactHash(): void
    {
        $dir = $this->tempDir();
        $evidenceDir = $this->tempDir();
        try {
            mkdir($dir . '/src', 0700, true);
            file_put_contents($dir . '/src/a.php', '<?php echo 1;');
            file_put_contents($dir . '/src/b.php', '<?php echo 2;');

            $files = [
                'src/b.php' => hash_file('sha256', $dir . '/src/b.php'),
                'src/a.php' => hash_file('sha256', $dir . '/src/a.php'),
            ];
            ksort($files, SORT_STRING);
            $manifest = $evidenceDir . '/manifest.json';
            file_put_contents($manifest, json_encode(['schema' => 1, 'files' => $files], JSON_THROW_ON_ERROR));

            $result = (new ReleaseManifestVerifier())->verify($dir, $manifest);
            Assert::same(true, $result['ok']);
            Assert::same(2, $result['verified_count']);
            Assert::same(
                hash('sha256', json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                $result['artifact_hash']
            );

            file_put_contents($dir . '/src/a.php', '<?php echo 99;');
            $changed = (new ReleaseManifestVerifier())->verify($dir, $manifest);
            Assert::same(false, $changed['ok']);
            Assert::same('HASH_MISMATCH', $changed['mismatches']['src/a.php']);
        } finally {
            $this->removeTree($dir);
            $this->removeTree($evidenceDir);
        }
    }

    public function testManifestRejectsLocationInsideReleaseTree(): void
    {
        $dir = $this->tempDir();
        try {
            mkdir($dir . '/src', 0700, true);
            file_put_contents($dir . '/src/a.php', '<?php echo 1;');
            $manifest = $dir . '/manifest.json';
            file_put_contents($manifest, json_encode([
                'schema' => 1,
                'files' => ['src/a.php' => hash_file('sha256', $dir . '/src/a.php')],
            ], JSON_THROW_ON_ERROR));

            Assert::throws(SifException::class, fn () => (new ReleaseManifestVerifier())->verify($dir, $manifest), 422);
        } finally {
            $this->removeTree($dir);
        }
    }

    public function testManifestRejectsUnexpectedFileAddedAfterBuild(): void
    {
        $dir = $this->tempDir();
        $evidenceDir = $this->tempDir();
        try {
            mkdir($dir . '/src', 0700, true);
            file_put_contents($dir . '/src/a.php', '<?php echo 1;');

            $files = ['src/a.php' => hash_file('sha256', $dir . '/src/a.php')];
            $manifest = $evidenceDir . '/manifest.json';
            file_put_contents($manifest, json_encode(['schema' => 1, 'files' => $files], JSON_THROW_ON_ERROR));

            $initial = (new ReleaseManifestVerifier())->verify($dir, $manifest);
            Assert::same(true, $initial['ok']);

            file_put_contents($dir . '/src/extra.php', '<?php echo "unexpected";');

            $changed = (new ReleaseManifestVerifier())->verify($dir, $manifest);
            Assert::same(false, $changed['ok']);
            Assert::same('UNEXPECTED_FILE', $changed['mismatches']['src/extra.php'] ?? null);
        } finally {
            $this->removeTree($dir);
            $this->removeTree($evidenceDir);
        }
    }

    public function testManifestRejectsTraversal(): void
    {
        $dir = $this->tempDir();
        $evidenceDir = $this->tempDir();
        try {
            $manifest = $evidenceDir . '/manifest.json';
            file_put_contents($manifest, json_encode([
                'schema' => 1,
                'files' => ['../secret' => str_repeat('a', 64)],
            ], JSON_THROW_ON_ERROR));

            Assert::throws(SifException::class, fn () => (new ReleaseManifestVerifier())->verify($dir, $manifest), 422);
        } finally {
            $this->removeTree($dir);
            $this->removeTree($evidenceDir);
        }
    }

    public function testConfigFingerprintIsCanonicalAndChangesWithRuntimeConfig(): void
    {
        $fingerprint = new RuntimeConfigFingerprint();
        $a = ['z' => ['secret' => 'one', 'x' => 2], 'a' => 1];
        $b = ['a' => 1, 'z' => ['x' => 2, 'secret' => 'one']];
        $c = ['a' => 1, 'z' => ['x' => 2, 'secret' => 'two']];

        Assert::same($fingerprint->hash($a), $fingerprint->hash($b));
        Assert::notSame($fingerprint->hash($a), $fingerprint->hash($c));
        Assert::same(64, strlen($fingerprint->hash($a)));

        $gateOff = [
            'env' => 'preproduction',
            'version_governance' => [
                'activation_enabled' => false,
                'runtime_git_revision' => str_repeat('a', 40),
                'release_manifest_path' => '/private/a.json',
                'require_backup_evidence' => true,
            ],
        ];
        $gateOn = $gateOff;
        $gateOn['version_governance']['activation_enabled'] = true;
        $gateOn['version_governance']['runtime_git_revision'] = str_repeat('b', 40);
        $gateOn['version_governance']['release_manifest_path'] = '/private/b.json';

        Assert::same($fingerprint->hash($gateOff), $fingerprint->hash($gateOn));
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/sif-uc010-' . bin2hex(random_bytes(8));
        mkdir($dir, 0700, true);
        return $dir;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
