<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Aeat\{SchemaManifest, XmlCodec, EvidenceStore, EvidenceVerifier};
use Prisma\Sif\Tests\Support\{Assert, ScriptRunner};

final class AeatIntegrityTest
{
    public function testSchemaMutationAndMissingManifestBlockCodec(): void
    {
        $dir = sys_get_temp_dir() . '/aeat-schema-test-' . bin2hex(random_bytes(12));
        mkdir($dir);
        $source = dirname(__DIR__, 2) . '/resources/aeat';
        $names = array_merge(SchemaManifest::FILES, ['manifest.json']);
        try {
            foreach ($names as $name) {
                copy($source . '/' . $name, $dir . '/' . $name);
            }
            (new SchemaManifest())->verify($dir);
            file_put_contents($dir . '/SuministroLR.xsd', '<tampered/>');
            Assert::throws(\RuntimeException::class, fn () => new XmlCodec($dir));
            copy($source . '/SuministroLR.xsd', $dir . '/SuministroLR.xsd');
            unlink($dir . '/manifest.json');
            Assert::throws(\RuntimeException::class, fn () => new XmlCodec($dir));
        } finally {
            foreach ($names as $name) {
                if (is_file($dir . '/' . $name)) {
                    unlink($dir . '/' . $name);
                }
            }
            rmdir($dir);
        }
    }

    public function testEvidenceCompletenessTamperingAndReadOnlyCli(): void
    {
        $this->withEvidence(function (string $dir, EvidenceStore $store, string $id): void {
            $verifier = new EvidenceVerifier();
            Assert::same('INCOMPLETE', $verifier->verify($dir, $id)['state']);
            $args = ['--directory=' . $dir, '--attempt=' . $id];
            Assert::same(1, ScriptRunner::run('scripts/verify-aeat-evidence.php', [], $args)['exit_code']);
            $store->response($id, '<private-synthetic-response/>', 200);
            $before = hash_file('sha256', $dir . '/' . $id . '/response.xml');
            $result = ScriptRunner::run('scripts/verify-aeat-evidence.php', [], $args);
            Assert::same(0, $result['exit_code']);
            $output = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            Assert::same('RESPONSE_RECORDED', $output['state']);
            Assert::same(false, $output['aeat_acceptance_verified']);
            Assert::same(false, str_contains($result['stdout'], 'private-synthetic-response'));
            Assert::same($before, hash_file('sha256', $dir . '/' . $id . '/response.xml'));
            file_put_contents($dir . '/' . $id . '/response.xml', 'ALTERED');
            Assert::same(['RESPONSE_INTEGRITY_FAILED'], $verifier->verify($dir, $id)['errors']);
            Assert::same(1, ScriptRunner::run('scripts/verify-aeat-evidence.php', [], $args)['exit_code']);
        });
    }

    public function testPartialResponseAndFailureDoNotBecomeCompletedEvidence(): void
    {
        $this->withEvidence(function (string $dir, EvidenceStore $store, string $id): void {
            $store->failure($id, 'SYNTHETIC_TIMEOUT');
            $verifier = new EvidenceVerifier();
            Assert::same('FAILED_ATTEMPT', $verifier->verify($dir, $id)['state']);
            file_put_contents($dir . '/' . $id . '/response.xml', 'PARTIAL');
            Assert::same('INVALID', $verifier->verify($dir, $id)['state']);
            Assert::throws(\InvalidArgumentException::class, fn () => $verifier->verify($dir, '../escape'));
        });
    }

    private function withEvidence(callable $test): void
    {
        $dir = sys_get_temp_dir() . '/aeat-verify-test-' . bin2hex(random_bytes(12));
        mkdir($dir, 0700);
        $id = null;
        try {
            $store = new EvidenceStore($dir);
            $id = $store->begin('<private-synthetic-request/>', ['environment' => 'offline-test']);
            $test($dir, $store, $id);
        } finally {
            if ($id !== null) {
                foreach (['request.xml', 'request.json', 'response.xml', 'response.json', 'failure.json'] as $name) {
                    if (is_file($dir . '/' . $id . '/' . $name)) {
                        unlink($dir . '/' . $id . '/' . $name);
                    }
                }
                rmdir($dir . '/' . $id);
            }
            rmdir($dir);
        }
    }
}
