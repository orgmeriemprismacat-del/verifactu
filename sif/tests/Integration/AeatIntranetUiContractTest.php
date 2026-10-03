<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class AeatIntranetUiContractTest
{
    public function testPanelUsesAuthenticatedServerSideBoundaryAndCsrfForReconciliation(): void
    {
        $root = dirname(__DIR__, 3);
        $page = $this->read($root . '/codi-drive/intranet-actual/sif-registres-aeat.php');
        $js = $this->read($root . '/codi-drive/intranet-actual/js/sif-registres-aeat.js');
        $bridge = $this->read($root . '/codi-drive/intranet-actual/ajax/sif/sifAeat.php');
        $client = $this->read($root . '/codi-drive/intranet-actual/SifInternalAeatClient.php');
        $api = $this->read($root . '/sif/public/api/aeat/operations.php');

        Assert::stringContainsString('sif_aeat_csrf', $page);
        Assert::stringContainsString('random_bytes(32)', $page);
        Assert::stringContainsString('sif-registres-aeat.js', $page);
        Assert::stringContainsString('Control de remissió AEAT', $page);

        Assert::stringContainsString("method: 'POST'", $js);
        foreach (["action: 'summary'", "action: 'list'", "action: 'detail'", "action: 'preflight'", "action: 'reconcile'"] as $action) {
            Assert::stringContainsString($action, $js);
        }
        Assert::stringContainsString('csrf_token: csrf', $js);
        Assert::stringContainsString('Conciliar sense reenviar', $js);
        Assert::stringContainsString("get('queue_id')", $js);
        Assert::stringContainsString('loadDetail(Number(deepQueueId))', $js);

        Assert::stringContainsString("REQUEST_METHOD", $bridge);
        Assert::stringContainsString("!== 'POST'", $bridge);
        Assert::stringContainsString('comprovarSessio.php', $bridge);
        Assert::stringContainsString('hash_equals', $bridge);
        Assert::stringContainsString('SifInternalAeatClient', $bridge);
        Assert::stringContainsString("if ($action === 'reconcile')", $bridge);
        Assert::stringContainsString('Cache-Control: private, no-store', $bridge);

        Assert::stringContainsString("hash_hmac('sha256'", $client);
        Assert::stringContainsString('X-SIF-Signature', $client);
        Assert::stringContainsString('X-SIF-Request-Id', $client);
        Assert::stringContainsString('SIF AEAT internal API requires HTTPS', $client);
        Assert::stringContainsString('CURLOPT_FOLLOWLOCATION => false', $client);

        Assert::stringContainsString('InternalApiAuthenticator', $api);
        Assert::stringContainsString("['aeat']['read_roles']", $api);
        Assert::stringContainsString("['aeat']['reconcile_roles']", $api);
        Assert::stringContainsString('AeatReviewReconciliationService', $api);
        Assert::stringContainsString('AeatPreflight', $api);

        if (str_contains($js, 'X-SIF-Signature') || str_contains($js, 'SIF_INTERNAL_API_SECRET')) {
            Assert::fail('AEAT browser code must never contain HMAC signing material.');
        }
    }

    public function testPanelKeepsTransportAndRemoteFiscalStatusesSeparateAndExposesNoProtectedPayload(): void
    {
        $root = dirname(__DIR__, 3);
        $js = $this->read($root . '/codi-drive/intranet-actual/js/sif-registres-aeat.js');
        $repository = $this->read($root . '/sif/src/Repository/AeatOperationsReadRepository.php');

        foreach (['SENT', 'ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED', 'REVIEW', 'DEAD_LETTER'] as $status) {
            Assert::stringContainsString($status, $js);
        }

        Assert::stringContainsString('ESTAT_AEAT', $js);
        Assert::stringContainsString('FISCAL_ORDER', $js);
        Assert::stringContainsString('UUID_ATTEMPT', $js);

        foreach (['XML_PAYLOAD', 'PAYLOAD_JSON'] as $protected) {
            if (str_contains($repository, "SELECT {$protected}") || str_contains($js, $protected)) {
                Assert::fail('AEAT operational panel must not expose protected fiscal payload: ' . $protected);
            }
        }
    }

    public function testReconciliationUiNeverOffersASecondSoapSubmission(): void
    {
        $root = dirname(__DIR__, 3);
        $js = $this->read($root . '/codi-drive/intranet-actual/js/sif-registres-aeat.js');
        $api = $this->read($root . '/sif/public/api/aeat/operations.php');
        $service = $this->read($root . '/sif/src/Service/AeatReviewReconciliationService.php');

        Assert::stringContainsString('reconciled_without_resend', $service);
        Assert::stringContainsString('REQUEST_HASH', $service);
        Assert::stringContainsString('Only the latest AEAT submission attempt', $service);
        Assert::stringContainsString('AeatReviewReconciliationService', $api);

        foreach (['SoapTransport', 'run-aeat-worker.php', '--send-test'] as $forbidden) {
            if (str_contains($js, $forbidden) || str_contains($api, $forbidden) || str_contains($service, $forbidden)) {
                Assert::fail('REVIEW reconciliation must not expose or invoke a second AEAT transport: ' . $forbidden);
            }
        }
    }

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read UC-009 contract source: ' . $path);
        }
        return $source;
    }
}
