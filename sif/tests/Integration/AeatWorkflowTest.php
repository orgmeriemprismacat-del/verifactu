<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Aeat\{SerialWorker, XmlCodec};
use Prisma\Sif\Contract\AeatTransport;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Repository\{FiscalRecordRepository, ManualPaymentInvoiceRepository};
use Prisma\Sif\Service\{FiscalRecordService, FiscalRecordPayloadBuilder};
use Prisma\Sif\Tests\Support\{Assert, Fixtures, AeatFixtures, TestDatabase};

final class AeatWorkflowTest
{
    private function payload(string $key = 'AEAT-TEST'): array
    {
        $snapshot = AeatFixtures::snapshot();
        $fields = $snapshot['record'];
        $fields['Desglose'] = ['DetalleDesglose' => [[
            'Impuesto' => '01', 'ClaveRegimen' => '01', 'OperacionExenta' => 'E1',
            'BaseImponibleOimporteNoSujeto' => '120.00']]];
        return Fixtures::invoicePayload(['idempotency_key' => $key,
            'aeat_fields' => $fields, 'aeat_header' => $snapshot['header']]);
    }

    private function service(\PDO $db): FiscalRecordService
    {
        return new FiscalRecordService(new TransactionRunner($db), new ManualPaymentInvoiceRepository(),
            new FiscalRecordRepository(new HashCalculator()), new FiscalRecordPayloadBuilder());
    }

    public function testIssueSubsanateCancelWithPersistedXmlAndGlobalChain(): void
    {
        $db = TestDatabase::fresh();
        $first = IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload('AEAT-SECOND'));
        $rows = $db->query('SELECT PAYLOAD_JSON FROM factura_registres ORDER BY FISCAL_ORDER')->fetchAll(\PDO::FETCH_COLUMN);
        $original = json_decode($rows[0], true)['aeat'];
        $last = json_decode($rows[1], true)['aeat'];
        $fields = $original['record'];
        $fields['DescripcionOperacion'] = 'Correccio de descripcio del registre';
        $input = ['reason' => 'REGISTRE_INCORRECTE', 'reference' => 'CORRECT-1',
            'subsanation_kind' => 'SUBSANACION', 'corrected_fields' => $fields];
        $service = $this->service($db);
        $correction = $service->createSubsanationByUuid($first['uuid_factura'], $input);
        Assert::same(true, $service->createSubsanationByUuid($first['uuid_factura'], $input)['idempotency_reused']);
        $stored = json_decode($db->query('SELECT PAYLOAD_JSON FROM factura_registres ORDER BY FISCAL_ORDER DESC LIMIT 1')->fetchColumn(), true);
        Assert::same($last['record']['Huella'], $stored['aeat']['record']['Encadenamiento']['RegistroAnterior']['Huella']);
        Assert::same('S', $stored['aeat']['record']['Subsanacion']);
        Assert::stringContainsString('RegistroAlta', (new XmlCodec())->request($stored['aeat']));
        $service->createCancellationByUuid($first['uuid_factura'], ['reason' => 'ERROR', 'reference' => 'CANCEL-1']);
        $stored = json_decode($db->query('SELECT PAYLOAD_JSON FROM factura_registres ORDER BY FISCAL_ORDER DESC LIMIT 1')->fetchColumn(), true);
        Assert::stringContainsString('RegistroAnulacion', (new XmlCodec())->request($stored['aeat']));
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
    }

    public function testCannotMixPrototypeChainOrReuseReferenceForAnotherInvoice(): void
    {
        $db = TestDatabase::fresh();
        $first = IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
        Assert::throws(\RuntimeException::class,
            fn () => IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload()));
        $second = IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload('AEAT-OTHER'));
        $input = ['reference' => 'SAME-REFERENCE', 'reason' => 'ERROR'];
        $this->service($db)->createCancellationByUuid($first['uuid_factura'], $input);
        Assert::throws(\Prisma\Sif\Exception\SifException::class,
            fn () => $this->service($db)->createCancellationByUuid($second['uuid_factura'], $input), 409);
    }

    public function testWorkerPersistsResponseAndHonoursGlobalWait(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload('AEAT-SECOND'));
        $transport = new class implements AeatTransport {
            public int $calls = 0;
            public function send(array $payload): array {
                $this->calls++;
                return ['status' => 'ACCEPTED', 'response' => ['flow_wait_seconds' => 120],
                    'request_xml' => (new XmlCodec())->request($payload['aeat'])];
            }
        };
        $worker = new SerialWorker($db, $transport);
        Assert::same('ACCEPTED', $worker->runOnce()['aeat_status']);
        Assert::same('WAIT', $worker->runOnce()['reason']);
        Assert::same(1, $transport->calls);
        Assert::stringContainsString('RegistroAlta', $db->query('SELECT XML_PAYLOAD FROM factura_registres ORDER BY FISCAL_ORDER LIMIT 1')->fetchColumn());
        Assert::same(0, (int) $db->query("SELECT COUNT(*) FROM errors_verifactu WHERE TIPUS_INCIDENCIA = 'AEAT_REVIEW'")->fetchColumn());
    }

    public function testAcceptedResponseWithReviewFlagCreatesIncidentWithoutResending(): void
    {
        foreach (['requires_review', 'duplicate'] as $flag) {
            $db = TestDatabase::fresh();
            IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
            $transport = new class($flag) implements AeatTransport {
                public int $calls = 0;
                public function __construct(private string $flag) {}
                public function send(array $payload): array {
                    $this->calls++;
                    return ['status' => 'ACCEPTED', 'response' => [$this->flag => true],
                        'request_xml' => (new XmlCodec())->request($payload['aeat'])];
                }
            };
            $worker = new SerialWorker($db, $transport);
            $result = $worker->runOnce();
            Assert::same('ACCEPTED', $result['aeat_status']);
            Assert::same(true, $result['requires_review']);
            Assert::same('SENT', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
            Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM errors_verifactu WHERE TIPUS_INCIDENCIA = 'AEAT_REVIEW'")->fetchColumn());
            Assert::same('EMPTY', $worker->runOnce()['reason']);
            Assert::same(1, $transport->calls);
            Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM errors_verifactu WHERE TIPUS_INCIDENCIA = 'AEAT_REVIEW'")->fetchColumn());
        }
    }

    public function testRetriesSameSnapshotThenDeadLettersAndBlocksFollowingRecords(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload('AEAT-SECOND'));
        $transport = new class implements AeatTransport {
            public array $requests = [];
            public function send(array $payload): array {
                $this->requests[] = (new XmlCodec())->request($payload['aeat']);
                throw new \RuntimeException('Synthetic timeout');
            }
        };
        $worker = new SerialWorker($db, $transport);
        for ($i = 0; $i < 3; $i++) {
            $db->exec('UPDATE fiscal_queue SET NEXT_RETRY_AT = NULL');
            $db->exec('UPDATE aeat_worker_state SET NEXT_SEND_AT = NULL');
            $result = $worker->runOnce();
        }
        Assert::same('DEAD_LETTER', $result['queue_status']);
        Assert::same(1, count(array_unique($transport->requests)));
        Assert::same('HEAD_REQUIRES_REVIEW', $worker->runOnce()['reason']);
        Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM errors_verifactu WHERE TIPUS_INCIDENCIA = 'AEAT_REVIEW'")->fetchColumn());
    }

    public function testFailedDeliveryRestartsGlobalWaitAndSurvivesWorkerRestart(): void
    {
        foreach (['timeout', 'invalid_wait'] as $failure) {
            $db = TestDatabase::fresh();
            IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
            $transport = new class($db, $failure) implements AeatTransport {
                public int $calls = 0;
                public function __construct(private \PDO $db, private string $failure) {}
                public function send(array $payload): array {
                    $this->calls++;
                    // Simulate the initial deadline expiring during network I/O, without sleeping.
                    $this->db->exec('UPDATE aeat_worker_state SET NEXT_SEND_AT = DATE_SUB(NOW(), INTERVAL 1 SECOND)');
                    if ($this->failure === 'timeout') {
                        throw new \RuntimeException('Synthetic timeout');
                    }
                    return ['status' => 'ACCEPTED', 'response' => ['flow_wait_seconds' => -1]];
                }
            };
            $result = (new SerialWorker($db, $transport))->runOnce();
            Assert::same('RETRY', $result['queue_status']);
            Assert::same($failure === 'timeout' ? 'Synthetic timeout' : 'Invalid AEAT flow wait.', $result['error']);
            Assert::same(1, (int) $db->query('SELECT NEXT_SEND_AT >= DATE_ADD(NOW(), INTERVAL 55 SECOND) FROM aeat_worker_state WHERE ID = 1')->fetchColumn());
            // Make the per-record retry due: the persisted global wait must still stop it.
            $db->exec('UPDATE fiscal_queue SET NEXT_RETRY_AT = NULL');
            Assert::same('WAIT', (new SerialWorker($db, $transport))->runOnce()['reason']);
            Assert::same(1, $transport->calls);
            Assert::same(1, (int) $db->query('SELECT ATTEMPTS FROM fiscal_queue')->fetchColumn());
        }
    }

    public function testResponseProjectionKeepsFullUnicodeEvidenceInRecord(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
        $transport = new class implements AeatTransport {
            public function send(array $payload): array {
                return ['status' => 'ACCEPTED_WITH_ERRORS', 'response' => [
                    'csv' => 'SYNTHETIC-CSV', 'error_code' => 'TEST',
                    'error_message' => str_repeat('ó', 600), 'flow_wait_seconds' => 90],
                    'request_xml' => (new XmlCodec())->request($payload['aeat'])];
            }
        };
        Assert::same('ACCEPTED_WITH_ERRORS', (new SerialWorker($db, $transport))->runOnce()['aeat_status']);
        $queue = $db->query('SELECT * FROM fiscal_queue')->fetch(\PDO::FETCH_ASSOC);
        Assert::same('SYNTHETIC-CSV', $queue['AEAT_CSV']);
        Assert::same('TEST', $queue['AEAT_ERROR_CODE']);
        Assert::same(str_repeat('ó', 500), $queue['AEAT_ERROR_MESSAGE']);
        Assert::same(90, (int) $queue['FLOW_WAIT_SECONDS']);
        $response = json_decode($db->query('SELECT AEAT_RESPONSE_JSON FROM factura_registres')->fetchColumn(), true);
        Assert::same(str_repeat('ó', 600), $response['error_message']);
    }

    public function testCompetingWorkerCannotClaimOrRecoverWhileLockIsHeld(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
        $db->exec("UPDATE fiscal_queue SET STATUS = 'PROCESSING', ATTEMPTS = 1,
            LOCKED_AT = DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $owner = TestDatabase::connect();
        $lock = 'prisma-aeat-' . substr(hash('sha256', (string) $db->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
        $acquire = $owner->prepare('SELECT GET_LOCK(?, 0)');
        $acquire->execute([$lock]);
        Assert::same(1, (int) $acquire->fetchColumn());
        $transport = new class implements AeatTransport {
            public int $calls = 0;
            public function send(array $payload): array {
                $this->calls++;
                return ['status' => 'ACCEPTED', 'response' => []];
            }
        };
        try {
            Assert::same('WORKER_BUSY', (new SerialWorker($db, $transport))->runOnce(true)['reason']);
            Assert::same('PROCESSING', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
            Assert::same(1, (int) $db->query('SELECT ATTEMPTS FROM fiscal_queue')->fetchColumn());
            Assert::same(0, $transport->calls);
        } finally {
            $release = $owner->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lock]);
        }
        Assert::same('ACCEPTED', (new SerialWorker($db, $transport))->runOnce(true)['aeat_status']);
        Assert::same(1, $transport->calls);
        Assert::same(2, (int) $db->query('SELECT ATTEMPTS FROM fiscal_queue')->fetchColumn());
    }

    public function testRecoveredExhaustedAttemptBlocksFollowingRecordWithoutDelivery(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload());
        IssueInvoiceTest::serviceFor($db)->issueInvoice($this->payload('AEAT-FOLLOWING'));
        $db->exec("UPDATE fiscal_queue SET STATUS = 'PROCESSING', ATTEMPTS = 3,
            LOCKED_AT = DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY ID LIMIT 1");
        $transport = new class implements AeatTransport {
            public int $calls = 0;
            public function send(array $payload): array {
                $this->calls++;
                throw new \RuntimeException('Unexpected delivery');
            }
        };
        $worker = new SerialWorker($db, $transport);
        Assert::same('HEAD_REQUIRES_REVIEW', $worker->runOnce()['reason']);
        Assert::same('PROCESSING', $db->query('SELECT STATUS FROM fiscal_queue ORDER BY ID LIMIT 1')->fetchColumn());
        Assert::same('HEAD_REQUIRES_REVIEW', $worker->runOnce(true)['reason']);
        $rows = $db->query('SELECT STATUS, ATTEMPTS, LOCKED_AT FROM fiscal_queue ORDER BY ID')->fetchAll(\PDO::FETCH_ASSOC);
        Assert::same('DEAD_LETTER', $rows[0]['STATUS']);
        Assert::same(3, (int) $rows[0]['ATTEMPTS']);
        Assert::same(null, $rows[0]['LOCKED_AT']);
        Assert::same('PENDING', $rows[1]['STATUS']);
        Assert::same(0, (int) $rows[1]['ATTEMPTS']);
        Assert::same('HEAD_REQUIRES_REVIEW', $worker->runOnce(true)['reason']);
        Assert::same(0, $transport->calls);
        Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM errors_verifactu WHERE TIPUS_INCIDENCIA = 'AEAT_DEAD_LETTER'")->fetchColumn());
        Assert::same('ERROR', $db->query('SELECT ESTAT_AEAT FROM factura_registres ORDER BY FISCAL_ORDER LIMIT 1')->fetchColumn());
    }
}
