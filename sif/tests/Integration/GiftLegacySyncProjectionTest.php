<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Tests\Support\Assert;

final class GiftLegacySyncProjectionTest
{
    public function testGiftSyncAppendsOperationalMarkerWithoutInventingLegacyInvoice(): void
    {
        $db = new GiftLegacySyncSpyPdo();

        (new LegacySyncService(new LegacySyncRepository()))->syncAfterSifSuccess(
            $db,
            [
                [
                    'source_type' => 'REGAL',
                    'source_id' => 77,
                    'factura_relacionada' => 0,
                ],
            ],
            '11111111-1111-4111-8111-111111111111',
            'A2026/77',
            'PAID'
        );

        Assert::same(1, count($db->preparedSql));
        Assert::stringContainsString('UPDATE regal', $db->preparedSql[0]);
        Assert::stringContainsString('OBSERVACIONS', $db->preparedSql[0]);

        if (str_contains($db->preparedSql[0], 'FACT_REL')) {
            Assert::fail('Gift SIF projection must not invent or overwrite legacy FACT_REL.');
        }

        Assert::same(
            [
                '11111111-1111-4111-8111-111111111111',
                "\nSIF A2026/77 PAID 11111111-1111-4111-8111-111111111111",
                77,
            ],
            $db->executedParams[0]
        );
    }

    public function testCandidatePaymentPageRecognisesSifPaidProjection(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            . '/codi-drive/pay-prisma-cat-canvis-verifactu/PagamentRegalAutomatic.php'
        );
        if ($source === false) {
            Assert::fail('Could not read candidate gift payment page.');
        }

        Assert::stringContainsString('OBSERVACIONS FROM regal WHERE ID=?', $source);
        Assert::stringContainsString('private $sifPaid = false', $source);
        Assert::stringContainsString('SIF\\s+\\S+\\s+PAID', $source);
        Assert::stringContainsString('private function estaPagat()', $source);
        Assert::stringContainsString('if (!$this->estaPagat())', $source);
    }
}

final class GiftLegacySyncSpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new GiftLegacySyncSpyStatement($this);
    }

    public function recordParams(?array $params): void
    {
        $this->executedParams[] = $params ?? [];
    }
}

final class GiftLegacySyncSpyStatement extends \PDOStatement
{
    public function __construct(private GiftLegacySyncSpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->db->recordParams($params);

        return true;
    }
}
