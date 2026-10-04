<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ClaimPaymentInvoiceLinkRepository;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ClaimPaymentInvoiceLinkRepositoryTest
{
    public function testMatchesInvoiceUuidAndVisibleNumberToOriginInscription(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LINK|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $repository = new ClaimPaymentInvoiceLinkRepository();

        $byUuid = $repository->assertUuidMatches($db, $invoice['uuid_factura'], 10);
        $byNumber = $repository->assertNumVisibleMatches($db, $invoice['num_visible'], 10);

        Assert::same($invoice['uuid_factura'], $byUuid['UUID_FACTURA']);
        Assert::same($invoice['uuid_factura'], $byNumber['UUID_FACTURA']);
    }

    public function testRejectsInvoiceThatBelongsToAnotherInscription(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LINK|WRONG_INSCRIPTION',
                'emesa_abans_cobrament' => 1,
            ])
        );

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            (new ClaimPaymentInvoiceLinkRepository())->assertUuidMatches(
                $db,
                $invoice['uuid_factura'],
                999
            );
        }, 409);
    }
}
