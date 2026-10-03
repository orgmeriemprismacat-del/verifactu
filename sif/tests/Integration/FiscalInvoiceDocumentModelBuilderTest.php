<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\AeatInvoiceQrUrlBuilder;
use Prisma\Sif\Service\FiscalInvoiceDocumentModelBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class FiscalInvoiceDocumentModelBuilderTest
{
    public function testBuildsDocumentOnlyFromPersistedSifInvoiceSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|DOCUMENT-MODEL|1',
                'billing' => [
                    'name' => 'Empresa Document SL',
                    'nif' => 'B12345678',
                    'address' => 'Carrer Document 1',
                    'cp' => '08001',
                    'city' => 'Barcelona',
                    'country' => 'ES',
                ],
            ])
        );

        $builder = new FiscalInvoiceDocumentModelBuilder(
            new InvoiceReadRepository(),
            new AeatInvoiceQrUrlBuilder(
                'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR',
                '0.5.0'
            ),
            'G00000000',
            'Associacio PrisMa'
        );

        $model = $builder->build($db, $issued['uuid_factura']);

        Assert::same('SIF_PERSISTED_INVOICE', $model['snapshot_source']);
        Assert::same($issued['uuid_factura'], $model['uuid_factura']);
        Assert::same($issued['num_visible'], $model['num_visible']);
        Assert::same(true, count($model['lines']) > 0);
        Assert::same('Empresa Document SL', $model['billing']['name']);
        Assert::same('B12345678', $model['billing']['nif']);
        Assert::same('120.00', $model['totals']['total']);
        Assert::same('G00000000', $model['issuer']['nif']);
        Assert::same('M', $model['qr']['error_correction']);
        Assert::same('0.5.0', $model['qr']['spec_version']);
        Assert::stringContainsString(
            'nif=G00000000',
            $model['qr']['url']
        );
        Assert::stringContainsString(
            'numserie=' . rawurlencode($issued['num_visible']),
            $model['qr']['url']
        );
        Assert::stringContainsString(
            'importe=120',
            $model['qr']['url']
        );
    }
}
