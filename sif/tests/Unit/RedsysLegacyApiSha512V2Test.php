<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3) . '/codi-drive/pay-prisma-cat-canvis-verifactu/inc/apiRedsys.php';

final class RedsysLegacyApiSha512V2Test
{
    public function testOfficialRedsysSha512V2VectorMatches(): void
    {
        $api = new \RedsysAPI();
        $api->setParameter('DS_MERCHANT_AMOUNT', '999');
        $api->setParameter('DS_MERCHANT_ORDER', '1234567890');
        $api->setParameter('DS_MERCHANT_MERCHANTCODE', '999008881');
        $api->setParameter('DS_MERCHANT_CURRENCY', '978');
        $api->setParameter('DS_MERCHANT_TRANSACTIONTYPE', '0');
        $api->setParameter('DS_MERCHANT_TERMINAL', '1');
        $api->setParameter('DS_MERCHANT_MERCHANTURL', 'http://www.prueba.com/urlNotificacion.php');
        $api->setParameter('DS_MERCHANT_URLOK', 'http://www.prueba.com/urlOK.php');
        $api->setParameter('DS_MERCHANT_URLKO', 'http://www.prueba.com/urlKO.php');

        Assert::same(
            'eyJEU19NRVJDSEFOVF9BTU9VTlQiOiI5OTkiLCJEU19NRVJDSEFOVF9PUkRFUiI6IjEyMzQ1Njc4OTAiLCJEU19NRVJDSEFOVF9NRVJDSEFOVENPREUiOiI5OTkwMDg4ODEiLCJEU19NRVJDSEFOVF9DVVJSRU5DWSI6Ijk3OCIsIkRTX01FUkNIQU5UX1RSQU5TQUNUSU9OVFlQRSI6IjAiLCJEU19NRVJDSEFOVF9URVJNSU5BTCI6IjEiLCJEU19NRVJDSEFOVF9NRVJDSEFOVFVSTCI6Imh0dHA6XC9cL3d3dy5wcnVlYmEuY29tXC91cmxOb3RpZmljYWNpb24ucGhwIiwiRFNfTUVSQ0hBTlRfVVJMT0siOiJodHRwOlwvXC93d3cucHJ1ZWJhLmNvbVwvdXJsT0sucGhwIiwiRFNfTUVSQ0hBTlRfVVJMS08iOiJodHRwOlwvXC93d3cucHJ1ZWJhLmNvbVwvdXJsS08ucGhwIn0',
            $api->createMerchantParametersV2()
        );
        Assert::same(
            'Vjo02eSWq249IeZZp3R-ArFnGLhKY0OuzDDlx1BuVtZDC2yhczA7_11uZhsYzLZBCMFAz8u8uzGDX3AErHKmmw',
            $api->createMerchantSignatureV2('sq7HjrUOBfKmC576ILgskD5srU870gJ7')
        );
    }

    public function testLegacyHelperNoLongerDependsOnRemovedMcryptExtension(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/codi-drive/pay-prisma-cat-canvis-verifactu/inc/apiRedsys.php'
        );
        if ($source === false) {
            Assert::fail('Could not read legacy Redsys helper.');
        }

        if (str_contains($source, 'mcrypt_encrypt(')) {
            Assert::fail('UC-014 must not depend on removed mcrypt functions under PHP 8.');
        }
        Assert::stringContainsString("'aes-128-cbc'", $source);
        Assert::stringContainsString("hash_hmac('sha512'", $source);
    }
}
