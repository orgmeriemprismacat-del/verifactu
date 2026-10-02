<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCoursePreproductionBoundaryTest
{
    public function testVerifierFailsClosedOutsideTestOrPreproduction(): void
    {
        $root = dirname(__DIR__, 3);
        $script = $root . '/sif/scripts/verify-redsys-course-preproduction.php';

        foreach (['production', 'local'] as $environment) {
            [$exitCode, $stdout, $stderr] = $this->run(
                [PHP_BINARY, $script, 'BOUNDARY00001'],
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

    public function testVerifierRequiresExplicitExecuteBeforeProcessScript(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-course-preproduction.php');

        Assert::stringContainsString(
            '$execute = in_array(\'--execute\', $args, true);',
            $source
        );
        Assert::stringContainsString('if ($execute) {', $source);

        $executeBlock = strstr($source, 'if ($execute) {');
        if ($executeBlock === false) {
            Assert::fail('Execute block not found');
        }

        Assert::stringContainsString(
            "/scripts/process-redsys-course.php",
            $executeBlock
        );

        $beforeExecute = substr($source, 0, strpos($source, 'if ($execute) {'));
        if (str_contains($beforeExecute, '/scripts/process-redsys-course.php')) {
            Assert::fail('Mutation script must not be referenced before explicit --execute block');
        }
    }

    public function testSyncLegacyRequiresEconomicProjectionEvidence(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-course-preproduction.php');
        $process = $this->read('sif/scripts/process-redsys-course.php');

        Assert::stringContainsString(
            "legacy_payment_sync_present",
            $source
        );
        Assert::stringContainsString(
            "legacy_payment_sync_status_valid",
            $source
        );
        Assert::stringContainsString(
            "notification_outbox_present",
            $source
        );
        Assert::stringContainsString(
            "notification_outbox_has_identity",
            $source
        );
        Assert::stringContainsString(
            "['PARTIALLY_PAID', 'PAID']",
            $source
        );
        Assert::stringContainsString('fund_allocation_present', $source);
        Assert::stringContainsString('fund_allocation_count_one', $source);
        Assert::stringContainsString('fund_allocation_amount_positive', $source);
        Assert::stringContainsString('fund_allocation_has_identity', $source);

        Assert::stringContainsString(
            'CourseLegacyPaymentSyncService',
            $process
        );
        Assert::stringContainsString(
            "\$result['legacy_payment_sync'] = (new CourseLegacyPaymentSyncService())->sync(",
            $process
        );
    }

    public function testEvidenceSanitizerRemovesSecretsSignaturesAndRawPayloads(): void
    {
        $source = $this->read('sif/scripts/verify-redsys-course-preproduction.php');

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
            'str_contains($normalized, \'signature\')',
            $source
        );
    }

    public function testCoursePreflightCoversCutoverDependencies(): void
    {
        $source = $this->read('sif/scripts/preflight-redsys-course.php');

        foreach ([
            'environment_is_test_or_preproduction',
            'bridge_redsys_merchant_code_configured',
            'bridge_redsys_merchant_key_configured',
            'bridge_redsys_terminal_configured',
            'bridge_and_sif_redsys_keys_match',
            'internal_api_base_url_https_configured',
            'internal_api_key_id_configured',
            'internal_api_secret_configured',
            'course_intent_signed_path_matches_bridge',
            'course_status_signed_path_matches_bridge',
            'redsys_callback_url_https_configured',
            'redsys_gateway_url_https_configured',
            'cutover_configuration_consistent',
            'legacy_drain_confirmed_if_cutover',
            'payment_allocation_table',
            'enrollment_fund_movement_table',
            'notification_outbox_table',
            'redsys_payment_intent_table',
            'redsys_callback_queue_table',
            'callback_endpoint_present',
            'course_intent_endpoint_present',
            'worker_script_present',
            'verification_script_present',
        ] as $check) {
            Assert::stringContainsString("'" . $check . "'", $source);
        }
    }

    public function testCourseIntentSignedPathIsExplicitlyConfigured(): void
    {
        $config = $this->read('sif/config/sif.php');
        Assert::stringContainsString(
            "'redsys_course_intent_signed_path' => getenv('SIF_INTERNAL_REDSYS_COURSE_INTENT_SIGNED_PATH') ?: '/api/redsys/course-intent.php'",
            $config
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
            Assert::fail('Could not execute preproduction verifier');
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
