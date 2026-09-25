<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\AeatPreflight;
use Prisma\Sif\Tests\Support\Assert;

final class AeatPreflightTest
{
    public function testRejectsMissingOrInsecureConfiguration(): void
    {
        $result = (new AeatPreflight())->check([
            'wsdl' => 'http://example.test/service.wsdl',
            'endpoint' => '',
        ]);

        Assert::same(false, $result['ready']);
        Assert::same(false, $result['checks']['wsdl_https']);
        Assert::same(false, $result['checks']['endpoint_https']);
        Assert::same(false, $result['checks']['certificate_readable']);
        Assert::same(false, $result['checks']['issuer_nif_present']);
    }

    public function testChecksFilesWithoutExposingCertificatePassword(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'sif-aeat-');
        if ($temporary === false) {
            Assert::fail('Could not create temporary preflight file');
        }

        try {
            $result = (new AeatPreflight())->check([
                'wsdl' => 'https://prewww2.aeat.es/service.wsdl',
                'endpoint' => 'https://prewww1.aeat.es/service',
                'xsd_path' => $temporary,
                'certificate_path' => $temporary,
                'certificate_password' => 'not-returned-secret',
                'issuer_nif' => 'G00000000',
                'system_id' => '01',
                'system_version' => '1.0.0',
                'installation_id' => 'TEST-01',
            ]);

            Assert::same(true, $result['checks']['xsd_readable']);
            Assert::same(true, $result['checks']['certificate_readable']);
            Assert::same(true, $result['checks']['certificate_password_present']);
            Assert::same(false, $result['checks']['certificate_usable']);
            Assert::same(false, $result['checks']['test_endpoint_allowed']);
            Assert::same(false, $result['ready']);
            if (str_contains(json_encode($result), 'not-returned-secret')) {
                Assert::fail('AEAT preflight must not expose certificate passwords');
            }
        } finally {
            unlink($temporary);
        }
    }
}
