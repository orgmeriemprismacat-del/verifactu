<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ReadinessExecutionTest
{
    private function execute(string $script, array $overrides = []): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/scripts/' . $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes, null, array_merge(getenv(), $overrides)
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start readiness script.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        Assert::same('', $error);
        return [$exit, json_decode($output, true, 512, JSON_THROW_ON_ERROR)];
    }

    public function testFullSchemaPassesTechnicalPreflight(): void
    {
        TestDatabase::fresh();
        [$exit, $result] = $this->execute('preflight-sif.php');
        Assert::same(0, $exit);
        Assert::same(true, $result['ok']);
        Assert::same(true, $result['checks']['schema_verified']);
        Assert::same(true, $result['checks']['academic_reconciliation_item_table']);
    }

    public function testGoNoGoRejectsMissingExtensionsAndNeverAuthorizesProduction(): void
    {
        $db = TestDatabase::fresh();
        $db->exec('RENAME TABLE academic_reconciliation_item TO test_hidden_academic_item');
        try {
            [$exit, $result] = $this->execute('go-no-go-preproduction.php', [
                'SIF_REDSYS_MERCHANT_KEY' => '', 'SIF_LEGACY_DB_DSN' => '',
            ]);
            Assert::same(1, $exit);
            Assert::same('NO-GO', $result['go_no_go_decision']);
            Assert::same(false, $result['production_authorized']);
            Assert::same('technical_preflight_only', $result['scope']);
            Assert::same(false, $result['checks']['schema_verified']);
            Assert::same(false, $result['checks']['academic_reconciliation_item_table']);
            Assert::same(false, $result['checks']['redsys_merchant_key_configured']);
        } finally {
            $db->exec('RENAME TABLE test_hidden_academic_item TO academic_reconciliation_item');
        }
        [$exit, $result] = $this->execute('go-no-go-preproduction.php', ['SIF_ENV' => 'production']);
        Assert::same(1, $exit);
        Assert::same(false, $result['checks']['environment_not_production']);
    }
}
