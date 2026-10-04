<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3) . '/codi-drive/intranet-actual/SifInternalApiClient.php';
require_once dirname(__DIR__, 3) . '/codi-drive/intranet-actual/SifLegacyInvoiceMutationGuard.php';

final class HistoricalInvoiceLegacyCutoverGuardTest
{
    public function testBlocksLegacyInvoiceMutationWhenUc011InvoiceExists(): void
    {
        $this->withFlags(true, true, function (): void {
            $client = new HistoricalInvoiceCutoverFakeClient([
                [
                    '_http_status' => 200,
                    'results' => [['uuid_factura' => 'historical-uuid']],
                ],
            ]);
            $guard = new \SifLegacyInvoiceMutationGuard($client);

            $error = Assert::throws(
                \RuntimeException::class,
                fn () => $guard->assertLegacyMutationAllowed($this->user(), 9123),
                409
            );

            Assert::stringContainsString('governada pel SIF', $error->getMessage());
            Assert::same('HISTORIC_WEB_FACTURES', $client->criteria[0]['source_type']);
            Assert::same([9123], $client->criteria[0]['source_ids']);
        });
    }

    public function testFailsClosedWhenUc007ProtectionIsDisabled(): void
    {
        $this->withFlags(true, false, function (): void {
            $client = new HistoricalInvoiceCutoverFakeClient([]);
            $guard = new \SifLegacyInvoiceMutationGuard($client);

            Assert::throws(
                \RuntimeException::class,
                fn () => $guard->assertLegacyMutationAllowed($this->user(), 9123),
                503
            );
            Assert::same([], $client->criteria);
        });
    }

    public function testFailsClosedWhenSifQueryFails(): void
    {
        $this->withFlags(true, true, function (): void {
            $client = new HistoricalInvoiceCutoverFakeClient([
                [
                    '_http_status' => 503,
                    'results' => [],
                ],
            ]);
            $guard = new \SifLegacyInvoiceMutationGuard($client);

            Assert::throws(
                \RuntimeException::class,
                fn () => $guard->assertLegacyMutationAllowed($this->user(), 9123),
                503
            );
        });
    }

    public function testFeatureFlagOffLeavesLegacyFlowUntouched(): void
    {
        $this->withFlags(false, true, function (): void {
            $client = new HistoricalInvoiceCutoverFakeClient([
                [
                    '_http_status' => 200,
                    'results' => [['uuid_factura' => 'historical-uuid']],
                ],
            ]);
            $guard = new \SifLegacyInvoiceMutationGuard($client);

            $guard->assertLegacyMutationAllowed($this->user(), 9123);
            Assert::same([], $client->criteria);
        });
    }

    public function testLegacyEndpointsInvokeGuardBeforeMutationOrRegeneration(): void
    {
        $root = dirname(__DIR__, 3) . '/codi-drive/intranet-actual/ajax/alumnes';

        $cases = [
            'guardarDadesFactura_Factures.php' => [
                'guard' => 'assertLegacyMutationAllowed',
                'action' => 'guardarDadesFactura_Factures(',
            ],
            'anularFactura_Factures.php' => [
                'guard' => 'assertLegacyMutationAllowed',
                'action' => 'anularFactura(',
            ],
            'descarregaFactura.php' => [
                'guard' => 'assertLegacyRelationAllowed',
                'action' => 'generaFactura(',
            ],
        ];

        foreach ($cases as $file => $needles) {
            $source = file_get_contents($root . '/' . $file);
            if (!is_string($source)) {
                Assert::fail('Could not read legacy invoice endpoint ' . $file);
            }

            $guardPosition = strpos($source, $needles['guard']);
            $actionPosition = strpos($source, $needles['action']);
            if ($guardPosition === false || $actionPosition === false) {
                Assert::fail('Missing cut-over guard or mutation action in ' . $file);
            }
            if ($guardPosition >= $actionPosition) {
                Assert::fail('Cut-over guard must execute before legacy action in ' . $file);
            }
        }
    }

    private function user(): object
    {
        return new class {
            public function getUsuari(): string
            {
                return 'uc011-test-operator';
            }

            public function getRols(): array
            {
                return ['ADMIN'];
            }
        };
    }

    private function withFlags(bool $guardEnabled, bool $queryEnabled, callable $callback): void
    {
        $oldGuard = getenv('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS');
        $oldQuery = getenv('SIF_UC007_QUERY_ENABLED');

        putenv('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=' . ($guardEnabled ? '1' : '0'));
        putenv('SIF_UC007_QUERY_ENABLED=' . ($queryEnabled ? '1' : '0'));

        try {
            $callback();
        } finally {
            $oldGuard === false
                ? putenv('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS')
                : putenv('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=' . $oldGuard);
            $oldQuery === false
                ? putenv('SIF_UC007_QUERY_ENABLED')
                : putenv('SIF_UC007_QUERY_ENABLED=' . $oldQuery);
        }
    }
}

final class HistoricalInvoiceCutoverFakeClient extends \SifInternalApiClient
{
    public array $criteria = [];

    public function __construct(private array $responses)
    {
    }

    public function searchInvoices(
        string $actorId,
        array $roles,
        array $criteria,
        int $limit = 50
    ): array {
        $this->criteria[] = $criteria;

        if ($this->responses === []) {
            return [
                '_http_status' => 200,
                'results' => [],
            ];
        }

        return array_shift($this->responses);
    }
}
