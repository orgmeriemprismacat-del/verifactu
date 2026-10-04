<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\RuntimeConfigFingerprint;
use Prisma\Sif\Tests\Support\Assert;

final class RuntimeConfigFingerprintTest
{
    public function testSecretRotationDoesNotChangePublicFingerprint(): void
    {
        $fingerprint = new RuntimeConfigFingerprint();
        $base = $this->config();

        $rotated = $base;
        $rotated['db']['password'] = 'another-password';
        $rotated['internal_api']['secret'] = 'another-internal-secret';
        $rotated['redsys']['merchant_key'] = 'another-merchant-key';
        $rotated['aeat']['certificate_password'] = 'another-certificate-password';

        Assert::same($fingerprint->hash($base), $fingerprint->hash($rotated));
    }

    public function testSecretPresenceChangeDoesChangeFingerprint(): void
    {
        $fingerprint = new RuntimeConfigFingerprint();
        $base = $this->config();

        $missing = $base;
        $missing['internal_api']['secret'] = '';

        Assert::notSame($fingerprint->hash($base), $fingerprint->hash($missing));
    }

    public function testFunctionalConfigurationChangeDoesChangeFingerprint(): void
    {
        $fingerprint = new RuntimeConfigFingerprint();
        $base = $this->config();

        $changed = $base;
        $changed['series']['invoice'] = 'B';

        Assert::notSame($fingerprint->hash($base), $fingerprint->hash($changed));
    }

    public function testEvidenceLocationAndActivationGateDoNotChangeFingerprint(): void
    {
        $fingerprint = new RuntimeConfigFingerprint();
        $base = $this->config();

        $changed = $base;
        $changed['version_governance']['runtime_git_revision'] = str_repeat('b', 40);
        $changed['version_governance']['release_manifest_path'] = '/another/private/manifest.json';
        $changed['version_governance']['activation_enabled'] = false;

        Assert::same($fingerprint->hash($base), $fingerprint->hash($changed));
    }

    private function config(): array
    {
        return [
            'env' => 'test',
            'db' => [
                'dsn' => 'mysql:host=127.0.0.1;dbname=sif_test;charset=utf8mb4',
                'user' => 'sif_test',
                'password' => 'db-password',
            ],
            'series' => ['invoice' => 'A', 'rectification' => 'R'],
            'internal_api' => [
                'key_id' => 'internal-key-id',
                'secret' => 'internal-secret',
            ],
            'redsys' => [
                'merchant_code' => '123456789',
                'merchant_key' => 'merchant-secret',
            ],
            'aeat' => [
                'endpoint' => 'https://example.test/aeat',
                'certificate_path' => '/private/cert.p12',
                'certificate_password' => 'certificate-secret',
            ],
            'version_governance' => [
                'read_roles' => ['SIF_AUDITOR'],
                'manage_roles' => ['SIF_ADMIN'],
                'runtime_git_revision' => str_repeat('a', 40),
                'release_manifest_path' => '/private/release-manifest.json',
                'activation_enabled' => true,
                'require_backup_evidence' => true,
            ],
        ];
    }
}
