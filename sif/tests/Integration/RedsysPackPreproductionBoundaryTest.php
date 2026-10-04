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

    public function testVerifierStopsBeforeMutationWhenPreflightOrPreviewFails(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-pack-preproduction.php');

        Assert::stringContainsString('$preExecutionFailures = array_keys(array_filter(', $source);
        Assert::stringContainsString("'pre_execution_ready'", $source);
        Assert::stringContainsString(
            'if (($execute || $diagnosticProcess) && $preExecutionFailures !== [])',
            $source
        );

        $guard = strpos(
            $source,
            'if (($execute || $diagnosticProcess) && $preExecutionFailures !== [])'
        );
        $execute = strpos($source, 'if ($execute) {');
        Assert::same(true, $guard !== false);
        Assert::same(true, $execute !== false);
        Assert::same(true, $guard < $execute);
    }

    public function testVerifierExecutesTargetedProductionWorkerBeforeEvidenceVerification(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-pack-preproduction.php');

        Assert::stringContainsString(
            '$execute = in_array(\'--execute\', $args, true);',
            $source
        );
        Assert::stringContainsString('if ($execute) {', $source);
        Assert::stringContainsString('/scripts/process-redsys-callback-queue.php', $source);
        Assert::stringContainsString("'--limit=1'", $source);
        Assert::stringContainsString("'--ds-order=' . $dsOrder", $source);
        Assert::stringContainsString('/scripts/verify-redsys-pack-evidence.php', $source);
        Assert::stringContainsString("'worker_targeted'", $source);
        Assert::stringContainsString("'worker_claimed_one'", $source);
        Assert::stringContainsString("'worker_processed_one'", $source);
        Assert::stringContainsString("'evidence_ok'", $source);

        $executePosition = strpos($source, 'if ($execute) {');
        $diagnosticPosition = strpos($source, 'if ($diagnosticProcess) {');
        Assert::same(true, $executePosition !== false);
        Assert::same(true, $diagnosticPosition !== false);
        Assert::same(true, $executePosition < $diagnosticPosition);

        $executeBlock = substr($source, $executePosition, $diagnosticPosition - $executePosition);
        Assert::same(false, str_contains($executeBlock, '/scripts/process-redsys-pack.php'));
    }

    public function testTargetedWorkerCliRejectsEmptyDsOrderFilter(): void
    {
        $source = $this->read('sif/scripts/process-redsys-callback-queue.php');

        Assert::stringContainsString('$dsOrderFilterRequested = false;', $source);
        Assert::stringContainsString('$dsOrderFilterRequested = true;', $source);
        Assert::stringContainsString(
            "($dsOrderFilterRequested && $dsOrder === '')",
            $source
        );
    }

    public function testVerifierRequiresPackEconomicAndNotificationEvidence(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-pack-preproduction.php');

        foreach ([
            'worker_exit_zero',
            'worker_ok',
            'worker_targeted',
            'worker_claimed_one',
            'worker_processed_one',
            'evidence_exit_zero',
            'evidence_ok',
            'preview_payment_matches_total',
        ] as $check) {
            Assert::stringContainsString("'" . $check . "'", $source);
        }

        Assert::stringContainsString(
            "'evidence_' . \$name",
            $source
        );
        Assert::stringContainsString(
            "verify-redsys-pack-evidence.php",
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
