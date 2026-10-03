<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\AeatInvoiceQrUrlBuilder;
use Prisma\Sif\Tests\Support\Assert;

final class AeatInvoiceQrUrlBuilderTest
{
    public function testBuildsOfficialVerifactuQrParametersWithRfc3986Encoding(): void
    {
        $builder = new AeatInvoiceQrUrlBuilder(
            'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
            '0.5.0'
        );

        $result = $builder->build(
            '89890001K',
            '12345678&G33',
            '2024-01-01',
            '241.40'
        );

        Assert::same(
            'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR'
                . '?nif=89890001K'
                . '&numserie=12345678%26G33'
                . '&fecha=01-01-2024'
                . '&importe=241.4',
            $result['url']
        );
        Assert::same('M', $result['error_correction']);
        Assert::same(30, $result['size_mm_min']);
        Assert::same(40, $result['size_mm_max']);
        Assert::same('QR tributario:', $result['label']);
        Assert::same('VERI*FACTU', $result['verifactu_legend']);
        Assert::same('0.5.0', $result['spec_version']);
    }

    public function testRejectsNonAsciiOrOversizedInvoiceNumber(): void
    {
        $builder = new AeatInvoiceQrUrlBuilder(
            'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR'
        );

        Assert::throws(SifException::class, function () use ($builder): void {
            $builder->build(
                '89890001K',
                'Factura número à',
                '2026-10-02',
                '120.00'
            );
        }, 422);

        Assert::throws(SifException::class, function () use ($builder): void {
            $builder->build(
                '89890001K',
                str_repeat('A', 61),
                '2026-10-02',
                '120.00'
            );
        }, 422);
    }

    public function testRejectsUnsafeBaseUrl(): void
    {
        Assert::throws(\RuntimeException::class, static function (): void {
            new AeatInvoiceQrUrlBuilder('http://example.test/ValidarQR');
        });
    }
}
