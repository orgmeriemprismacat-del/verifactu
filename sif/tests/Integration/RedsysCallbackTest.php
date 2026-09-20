<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysCallbackService;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCallbackTest
{
    public function testAuthorizedCallbackCreatesOneJobWithoutIssuingInvoice(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDERQUEUE1', 'CURS', '80.00');
        $service = $this->callbackService();

        $result = $service->receiveCallback($db, [
            'ds_order' => 'ORDERQUEUE1',
            'amount' => '80.00',
            'response_code' => '0000',
            'currency' => 'EUR',
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => str_repeat('a', 64),
        ], true);

        Assert::same('QUEUED', $result['queue_status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
    }

    public function testDuplicateAuthorizedCallbackKeepsOneJob(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDERQUEUE3', 'CURS', '80.00');
        $service = $this->callbackService();
        $payload = [
            'ds_order' => 'ORDERQUEUE3',
            'amount' => '80.00',
            'response_code' => '0000',
            'currency' => 'EUR',
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => str_repeat('c', 64),
        ];

        $first = $service->receiveCallback($db, $payload, true);
        $second = $service->receiveCallback($db, $payload, true);

        Assert::same($first['uuid_job'], $second['uuid_job']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
    }

    public function testContradictoryDuplicateCallbackConflictsAndKeepsOneJob(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDERQUEUECONFLICT', 'CURS', '80.00');
        $service = $this->callbackService();
        $payload = [
            'ds_order' => 'ORDERQUEUECONFLICT',
            'amount' => '80.00',
            'response_code' => '0000',
            'currency' => 'EUR',
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => str_repeat('1', 64),
        ];
        $service->receiveCallback($db, $payload, true);

        $contradictory = $payload;
        $contradictory['response_code'] = '0101';
        $contradictory['payload_hash'] = str_repeat('2', 64);

        Assert::throws(SifException::class, static function () use ($db, $service, $contradictory): void {
            $service->receiveCallback($db, $contradictory, true);
        }, 409);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }

    public function testMismatchedAmountRollsBackNotificationAndJob(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDERQUEUE4', 'CURS', '80.00');
        $service = $this->callbackService();
        $payload = [
            'ds_order' => 'ORDERQUEUE4',
            'amount' => '81.00',
            'response_code' => '0000',
            'currency' => 'EUR',
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => str_repeat('e', 64),
        ];

        Assert::throws(SifException::class, static function () use ($db, $service, $payload): void {
            $service->receiveCallback($db, $payload, true);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
    }

    public function testCallbackIgnoresExternalIdpagAndUsesIntent(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDERQUEUE5', 'CURS', '80.00');
        $service = $this->callbackService();

        $service->receiveCallback($db, [
            'ds_order' => 'ORDERQUEUE5',
            'idpag' => 999,
            'amount' => '80.00',
            'response_code' => '0000',
            'currency' => 'EUR',
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => str_repeat('f', 64),
        ], true);

        $idpag = $db->query(
            "SELECT IDPAG FROM redsys_notifications WHERE DS_ORDER = 'ORDERQUEUE5'"
        )->fetchColumn();
        Assert::same(700, (int) $idpag);
    }

    public function testCallbackAcceptsCurrentSignatureValidatorPayloadShape(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDERQUEUE6', 'CURS', '80.00');
        $service = $this->callbackService();

        $result = $service->receiveCallback($db, [
            'ds_order' => 'ORDERQUEUE6',
            'amount' => '80.00',
            'response_code' => '0000',
            'redsys' => [
                'decoded' => [
                    'Ds_Currency' => '978',
                    'Ds_Terminal' => '1',
                ],
            ],
        ], true);

        Assert::same('QUEUED', $result['queue_status']);
    }

    public function testDuplicateDsOrderDoesNotCreateSecondNotification(): void
    {
        $db = TestDatabase::fresh();
        $repo = new RedsysNotificationRepository();

        $first = $repo->recordReceived($db, 'ORDER123', 123, '120.00', '0000', true, ['source' => 'test']);
        $second = $repo->recordReceived($db, 'ORDER123', 123, '120.00', '0000', true, ['source' => 'test']);

        Assert::same(false, $first['duplicate']);
        Assert::same(true, $second['duplicate']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
    }

    public function testUnsignedCallbackIsRejectedBeforeRecording(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->callbackService();

        Assert::throws(SifException::class, function () use ($db, $service): void {
            $service->receiveCallback($db, [
                'ds_order' => 'ORDER124',
                'idpag' => 124,
                'amount' => '80.00',
                'response_code' => '0000',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
    }

    public function testAuthorizedCallbackValidatesNotificationAndQueuesFiscalWork(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDER125', 'CURS', '60.00');
        $service = $this->callbackService();

        $result = $service->receiveCallback($db, [
            'ds_order' => 'ORDER125',
            'amount' => '60.00',
            'response_code' => '0000',
            'currency' => 'EUR',
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => str_repeat('d', 64),
        ], true);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['duplicate']);
        Assert::same('ORDER125', $result['ds_order']);
        Assert::same('VALIDATED', $result['status']);
        Assert::same('QUEUED', $result['queue_status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $stmt = $db->prepare('SELECT STATUS, SIGNATURE_VALID FROM redsys_notifications WHERE DS_ORDER = ?');
        $stmt->execute(['ORDER125']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        Assert::same('VALIDATED', $row['STATUS']);
        Assert::same(1, (int) $row['SIGNATURE_VALID']);
    }

    public function testDeniedCallbackRecordsErrorWithoutFiscalOrPaymentEffects(): void
    {
        $db = TestDatabase::fresh();
        $this->createIntent($db, 'ORDER126', 'CURS', '60.00');
        $service = $this->callbackService();

        $result = $service->receiveCallback($db, [
            'ds_order' => 'ORDER126',
            'amount' => '60.00',
            'response_code' => '0101',
            'currency' => 'EUR',
            'currency_code' => '978',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => str_repeat('b', 64),
        ], true);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['duplicate']);
        Assert::same('ORDER126', $result['ds_order']);
        Assert::same('ERROR', $result['status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $stmt = $db->prepare('SELECT STATUS, RESPONSE_CODE, SIGNATURE_VALID FROM redsys_notifications WHERE DS_ORDER = ?');
        $stmt->execute(['ORDER126']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        Assert::same('ERROR', $row['STATUS']);
        Assert::same('0101', $row['RESPONSE_CODE']);
        Assert::same(1, (int) $row['SIGNATURE_VALID']);
    }

    private function createIntent(\PDO $db, string $dsOrder, string $sourceType, string $amount): array
    {
        return (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => 700,
            'source_type' => $sourceType,
            'source_id' => '700',
            'expected_amount' => $amount,
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
        ]);
    }

    private function callbackService(): RedsysCallbackService
    {
        return new RedsysCallbackService(
            new RedsysPaymentIntentRepository(),
            new RedsysNotificationRepository(),
            new RedsysCallbackQueueRepository(new UuidGenerator()),
            new IncidentRepository()
        );
    }
}
