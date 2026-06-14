<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocEntityPreflightScriptTest
{
    public function testPreflightChecksUsocEntityRequirementsWithoutIssuingInvoice(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preflight-usoc-entity.php');

        if ($source === false) {
            Assert::fail('Could not read USOC entity preflight script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('environment_not_production', $source);
        Assert::stringContainsString('legacy_db_configured', $source);
        Assert::stringContainsString('sif_database_connectivity', $source);
        Assert::stringContainsString('legacy_database_connectivity', $source);
        Assert::stringContainsString('factura_table', $source);
        Assert::stringContainsString('factura_linia_table', $source);
        Assert::stringContainsString('fact_rels_table', $source);
        Assert::stringContainsString('fiscal_chain_state_seeded', $source);
        Assert::stringContainsString('legacy_inscripcions_table', $source);
        Assert::stringContainsString('legacy_curs_table', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new UsocEntityInvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('USOC entity preflight must stay read-only.');
        }
    }
}
