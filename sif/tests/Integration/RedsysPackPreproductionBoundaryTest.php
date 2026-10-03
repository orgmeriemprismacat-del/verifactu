<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysPackPreproductionBoundaryTest
{
    public function testVerifierFailsClosedOutsideTestOrPreproduction(): void
    {
        $root = dirname(__DIR__, 3);
        $script = $root . '/sif/scripts/verify-redsys-pack-preproduction.php';

        foreach (['production', 'local'] as $environment) {
            [$exitCode, $stdout, $stderr] = $this->run(
                [PHP_BINARY, $script, 'BOUNDARYPACK01'],
                $root,
                array_merge(getenv(), [
                    'SIF_ENV' => $environment,
                    'SIF_DB_DSN' => '',
                    'SIF_LEGACY_DB_DSN' => '',
                ])
            );

            Assert::same(1, $exitCode);
            Assert::same('', trim($stderr));

            $json = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            Assert::same(false, $json['ok']);
            Assert::same($environment, $json['environment']);
            Assert::same(false, $json['checks']['environment_is_test_or_preproduction']);
            Assert::same(
                ['environment_is_test_or_preproduction'],
                $json['failed']
            );
        }
    }

    public function testVerifierRequiresExplicitExecuteBeforePackProcessor(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-pack-preproduction.php');

        Assert::stringContainsString(
            '$execute = in_array(\'--execute\', $args, true);',
            $source
        );
        Assert::stringContainsString('if ($execute) {', $source);

        $executePosition = strpos($source, 'if ($execute) {');
        if ($executePosition === false) {
            Assert::fail('Execute block not found');
        }

        $beforeExecute = substr($source, 0, $executePosition);
        Assert::same(false, str_contains($beforeExecute, '/scripts/process-redsys-pack.php'));

        $executeBlock = substr($source, $executePosition);
        Assert::stringContainsString('/scripts/process-redsys-pack.php', $executeBlock);
        Assert::stringContainsString('--sync-legacy', $executeBlock);
    }

    public function testVerifierCanRequirePersistedEndToEndEvidence(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-pack-preproduction.php');

        Assert::stringContainsString(
            "$verifyEvidence = in_array('--verify-evidence', $args, true);",
            $source
        );
        Assert::stringContainsString('if ($verifyEvidence) {', $source);
        Assert::stringContainsString('/scripts/verify-redsys-pack-evidence.php', $source);
        Assert::stringContainsString("'evidence_exit_zero'", $source);
        Assert::stringContainsString("'evidence_ok'", $source);
        Assert::stringContainsString('[--verify-evidence]', $source);
    }

    public function testVerifierRequiresPackEconomicAndNotificationEvidence(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-pack-preproduction.php');

        foreach ([
            'process_has_invoice_identity',
            'process_has_payment_identity',
            'fund_allocation_present',
            'fund_allocation_has_multiple_components',
            'fund_allocation_amount_positive',
            'fund_allocation_matches_preview_total',
            'fund_allocation_movements_have_identity',
            'notification_outbox_present',
            'notification_outbox_has_identity',
            'legacy_sync_executed',
            'preview_payment_matches_total',
        ] as $check) {
            Assert::stringContainsString("'" . $check . "'", $source);
        }

        Assert::stringContainsString(
            "count(\$fundMovements) === \$fundCount",
            $source
        );
        Assert::stringContainsString(
            "money(\$fundAmount) === money(\$previewTotal)",
            $source
        );
    }

    public function testVerifierSanitizesEvidenceBeforePrintingIt(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-pack-preproduction.php');

        foreach ([
            "'secret'",
            "'password'",
            "'signature'",
            "'merchant_key'",
            "'certificate_password'",
            "'raw_payload'",
            "'raw_payload_json'",
        ] as $forbiddenKey) {
            Assert::stringContainsString($forbiddenKey, $source);
        }

        Assert::stringContainsString(
            "str_contains(\$normalized, 'signature')",
            $source
        );
        Assert::stringContainsString('sanitizeEvidence($json)', $source);
        Assert::stringContainsString("'invoice_total' =>", $source);
        Assert::stringContainsString("'payment_amount' =>", $source);
        Assert::same(
            false,
            str_contains($source, "\$result['preview'] = \$preview['json'];")
        );
    }

    public function testPackPreflightCoversAllRequiredExecutableSurfaces(): void
    {
        $source = $this->read('sif/scripts/preflight-redsys-pack.php');

        foreach ([
            'callback_endpoint_present',
            'intent_create_endpoint_present',
            'worker_script_present',
            'preview_script_present',
            'process_script_present',
            'queue_preflight_script_present',
            'verification_script_present',
            'evidence_script_present',
        ] as $check) {
            Assert::stringContainsString("'" . $check . "'", $source);
        }

        Assert::stringContainsString(
            "/scripts/verify-redsys-pack-preproduction.php",
            $source
        );
        Assert::stringContainsString(
            "/scripts/verify-redsys-pack-evidence.php",
            $source
        );
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        $content = file_get_contents($path);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }

        return $content;
    }

    private function run(array $command, string $cwd, array $env): array
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
            $env,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            Assert::fail('Could not execute PACK preproduction verifier');
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }
}
