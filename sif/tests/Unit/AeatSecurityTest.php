<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Aeat\{ClientCertificate, EvidenceStore, SoapTransport};
use Prisma\Sif\Tests\Support\Assert;

final class AeatSecurityTest
{
    public function testCertificatePasswordValidityAndNoSecretMetadata(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'aeat-cert-test-');
        $password = bin2hex(random_bytes(16));
        try {
            $options = ['private_key_bits' => 2048, 'digest_alg' => 'sha256'];
            $config = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
            if (is_file($config)) {
                $options['config'] = $config;
            }
            $key = openssl_pkey_new($options);
            $csr = openssl_csr_new(['commonName' => 'LOCAL TEST NOT AN AEAT CERTIFICATE'], $key, $options);
            $cert = openssl_csr_sign($csr, null, $key, 2, $options);
            if (!$cert || !openssl_pkcs12_export_to_file($cert, $path, $key, $password)) {
                throw new \RuntimeException('Could not generate synthetic test certificate.');
            }
            $reader = new ClientCertificate($path, $password);
            $metadata = $reader->inspect();
            Assert::matchesRegularExpression('/^[a-f0-9]{64}$/', $metadata['fingerprint_sha256']);
            Assert::same(false, str_contains(json_encode($metadata), $password));
            Assert::same(false, str_contains(json_encode($metadata), 'PRIVATE KEY'));
            Assert::throws(\RuntimeException::class, fn () => (new ClientCertificate($path, 'WRONG'))->inspect());
            Assert::throws(\RuntimeException::class, fn () => $reader->inspect(time() + 3 * 86400));
            Assert::throws(\RuntimeException::class, fn () => $reader->inspect(0));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testEvidenceIsHashedAndCannotBeOverwritten(): void
    {
        $dir = sys_get_temp_dir() . '/aeat-evidence-test-' . bin2hex(random_bytes(12));
        mkdir($dir, 0700);
        $id = null;
        try {
            $store = new EvidenceStore($dir);
            $id = $store->begin('<synthetic-request/>', ['environment' => 'offline-test']);
            $store->response($id, '<synthetic-response/>', 200);
            $request = json_decode(file_get_contents($dir . '/' . $id . '/request.json'), true);
            $response = json_decode(file_get_contents($dir . '/' . $id . '/response.json'), true);
            Assert::same(hash('sha256', '<synthetic-request/>'), $request['request_sha256']);
            Assert::same(hash('sha256', '<synthetic-response/>'), $response['response_sha256']);
            Assert::throws(\RuntimeException::class, fn () => $store->response($id, 'OVERWRITE', 500));
            Assert::same('<synthetic-response/>', file_get_contents($dir . '/' . $id . '/response.xml'));
            Assert::throws(\InvalidArgumentException::class, fn () => $store->failure('../escape', 'ERROR'));
        } finally {
            if ($id !== null) {
                foreach (['request.xml', 'request.json', 'response.xml', 'response.json'] as $name) {
                    if (is_file($dir . '/' . $id . '/' . $name)) {
                        unlink($dir . '/' . $id . '/' . $name);
                    }
                }
                rmdir($dir . '/' . $id);
            }
            rmdir($dir);
        }
    }

    public function testRejectsRepositoryStorageAndProductionEndpoint(): void
    {
        $repo = dirname(__DIR__, 3);
        Assert::throws(\RuntimeException::class, fn () => new EvidenceStore($repo));
        Assert::throws(\RuntimeException::class,
            fn () => (new ClientCertificate($repo . '/config/sif.php', ''))->inspect());
        $store = new EvidenceStore(sys_get_temp_dir());
        Assert::throws(\InvalidArgumentException::class, fn () => new SoapTransport(
            new ClientCertificate('/nonexistent', ''), $store,
            'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP'));
    }
}
