<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\RedsysCallbackService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCallbackTest
{
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
        $service = new RedsysCallbackService(new RedsysNotificationRepository());

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

    public function testAuthorizedCallbackOnlyValidatesNotification(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysCallbackService(new RedsysNotificationRepository());

        $result = $service->receiveCallback($db, [
            'ds_order' => 'ORDER125',
            'idpag' => 125,
            'amount' => '60.00',
            'response_code' => '0000',
        ], true);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['duplicate']);
        Assert::same('ORDER125', $result['ds_order']);
        Assert::same('VALIDATED', $result['status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
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
        $service = new RedsysCallbackService(new RedsysNotificationRepository());

        $result = $service->receiveCallback($db, [
            'ds_order' => 'ORDER126',
            'idpag' => 126,
            'amount' => '60.00',
            'response_code' => '0101',
        ], true);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['duplicate']);
        Assert::same('ORDER126', $result['ds_order']);
        Assert::same('ERROR', $result['status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $stmt = $db->prepare('SELECT STATUS, RESPONSE_CODE, SIGNATURE_VALID FROM redsys_notifications WHERE DS_ORDER = ?');
        $stmt->execute(['ORDER126']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        Assert::same('ERROR', $row['STATUS']);
        Assert::same('0101', $row['RESPONSE_CODE']);
        Assert::same(1, (int) $row['SIGNATURE_VALID']);
    }
}
