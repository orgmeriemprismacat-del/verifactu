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

        $resolved = $repository->resolveUniqueOriginForInscription($db, 10);
        $byUuid = $repository->assertUuidMatches($db, $invoice['uuid_factura'], 10);
        $byNumber = $repository->assertNumVisibleMatches($db, $invoice['num_visible'], 10);

        Assert::same($invoice['uuid_factura'], $resolved['UUID_FACTURA']);
        Assert::same(123, (int) $resolved['IDPAG']);
        Assert::same($invoice['uuid_factura'], $byUuid['UUID_FACTURA']);
        Assert::same($invoice['uuid_factura'], $byNumber['UUID_FACTURA']);
    }

    public function testRejectsInvoiceWithMultipleInscriptionOrigins(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LINK|MULTI_ORIGIN',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $db->prepare(
            "INSERT INTO fact_rels (
                UUID_FACTURA,
                SOURCE_TYPE,
                SOURCE_ID,
                RELATION_TYPE,
                IDPAG,
                VISIBLE_ALUMNE
            ) VALUES (?, 'INSCRIPCIO', ?, 'ORIGIN', ?, 1)"
        )->execute([$invoice['uuid_factura'], 11, 123]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new ClaimPaymentInvoiceLinkRepository())
                ->resolveUniqueOriginForInscription($db, 10);
        }, 409);
    }

    public function testRejectsOriginRelationWithoutValidIdpag(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LINK|NO_IDPAG',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $db->prepare(
            'UPDATE fact_rels SET IDPAG = NULL
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = \'INSCRIPCIO\'
               AND SOURCE_ID = ?'
        )->execute([$invoice['uuid_factura'], 10]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new ClaimPaymentInvoiceLinkRepository())
                ->resolveUniqueOriginForInscription($db, 10);
        }, 409);
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
