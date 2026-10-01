<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\GiftRedemptionNotificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionNotificationServiceTest
{
    public function testEnqueuesFiveStableNotificationsAfterReconciliationAndReplayReusesThem(): void
    {
        $db = TestDatabase::fresh();
        [$giftCode, $uuidEntitlement, $uuidOperation, $invoice, $payment] =
            $this->fixture($db);

        $service = new GiftRedemptionNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        );

        $first = $service->enqueue(
            $db,
            $db,
            501,
            $giftCode,
            $uuidEntitlement,
            $uuidOperation
        );
        $second = $service->enqueue(
            $db,
            $db,
            501,
            $giftCode,
            $uuidEntitlement,
            $uuidOperation
        );

        Assert::same('QUEUED', $first['status']);
        Assert::same(5, $first['count']);
        Assert::same(5, $second['count']);

        foreach ($first['notifications'] as $template => $row) {
            Assert::same(false, $row['idempotency_reused']);
            Assert::same(
                true,
                isset($second['notifications'][$template])
            );
            Assert::same(
                true,
                $second['notifications'][$template]['idempotency_reused']
            );
            Assert::same(
                $row['uuid_notification'],
                $second['notifications'][$template]['uuid_notification']
            );
        }

        Assert::same(
            5,
            (int) $db->query(
                'SELECT COUNT(*) FROM notification_outbox'
            )->fetchColumn()
        );
        Assert::same(
            5,
            (int) $db->query(
                "SELECT COUNT(DISTINCT TEMPLATE_CODE)
                 FROM notification_outbox"
            )->fetchColumn()
        );
        Assert::same(
            5,
            (int) $db->query(
                "SELECT COUNT(*) FROM notification_outbox
                 WHERE STATUS='PENDING'"
            )->fetchColumn()
        );

        $rows = $db->query(
            'SELECT TEMPLATE_CODE, RECIPIENT_TYPE, RECIPIENT_HASH,
                    PAYLOAD_JSON, UUID_FACTURA, UUID_PAYMENT, CORRELATION_ID
             FROM notification_outbox
             ORDER BY TEMPLATE_CODE'
        )->fetchAll(\PDO::FETCH_ASSOC);

        $correlations = [];
        foreach ($rows as $row) {
            $payload = (string) $row['PAYLOAD_JSON'];

            Assert::same(
                false,
                str_contains($payload, 'student@example.test')
            );
            Assert::same(
                false,
                str_contains($payload, $giftCode)
            );
            Assert::same(
                true,
                str_contains($payload, hash('sha256', $giftCode))
            );
            Assert::same(64, strlen((string) $row['RECIPIENT_HASH']));
            Assert::same($invoice, (string) $row['UUID_FACTURA']);
            Assert::same($payment, (string) $row['UUID_PAYMENT']);
            $correlations[(string) $row['CORRELATION_ID']] = true;
        }

        Assert::same(1, count($correlations));
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM notification_outbox
                 WHERE TEMPLATE_CODE='GIFT_REDEMPTION_STUDENT_CONFIRMATION'
                   AND RECIPIENT_TYPE='ALUMNE'"
            )->fetchColumn()
        );
        Assert::same(
            4,
            (int) $db->query(
                "SELECT COUNT(*) FROM notification_outbox
                 WHERE RECIPIENT_TYPE='INTERNAL'"
            )->fetchColumn()
        );
    }

    public function testRefusesToQueueBeforeLegacyGiftIsReconciled(): void
    {
        $db = TestDatabase::fresh();
        [$giftCode, $uuidEntitlement, $uuidOperation] = $this->fixture($db);

        $db->exec('UPDATE regal SET USAT = NULL');

        $service = new GiftRedemptionNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        );

        Assert::throws(
            SifException::class,
            function () use (
                $service,
                $db,
                $giftCode,
                $uuidEntitlement,
                $uuidOperation
            ): void {
                $service->enqueue(
                    $db,
                    $db,
                    501,
                    $giftCode,
                    $uuidEntitlement,
                    $uuidOperation
                );
            },
            409
        );

        Assert::same(
            0,
            (int) $db->query(
                'SELECT COUNT(*) FROM notification_outbox'
            )->fetchColumn()
        );
    }

    private function fixture(\PDO $db): array
    {
        $giftCode = 'GIFT-NOTIFY-SECRET-001';
        $email = 'student@example.test';

        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                CORREU VARCHAR(255) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                pag_observacions VARCHAR(255) NULL
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL,
                USAT INT NULL
            )'
        );

        $db->prepare(
            'INSERT INTO inscripcions
             (ID, CORREU, CURS, ANY, MES, pag_observacions)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            501,
            $email,
            'COURSE-NOTIFY',
            2026,
            '09',
            $giftCode,
        ]);
        $db->prepare(
            'INSERT INTO regal (ID, CODI, USAT)
             VALUES (?, ?, ?)'
        )->execute([177, $giftCode, 501]);

        $invoiceResult = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC018|NOTIFY|INVOICE',
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
            ])
        );

        $paymentResult = RegisterPaymentTest::paymentServiceFor($db)
            ->registerPayment([
                'idempotency_key' => 'UC018|NOTIFY|PAYMENT',
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'WEB',
                'amount' => '120.00',
                'movement_date' => '2026-09-01 10:00:00',
                'reference' => 'UC018-NOTIFY',
                'allocations' => [[
                    'uuid_factura' => $invoiceResult['uuid_factura'],
                    'amount' => '120.00',
                    'allocation_type' => 'INVOICE_PAYMENT',
                ]],
            ]);

        $uuids = new UuidGenerator();
        $uuidOrigin = $uuids->generate();
        $uuidOperation = $uuids->generate();
        $uuidEntitlement = $uuids->generate();

        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
              TAX_SNAPSHOT_JSON, UUID_FACTURA, UUID_PAYMENT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidOrigin,
            'UC018|NOTIFY|ORIGIN',
            'GIFT_PURCHASE',
            'REDSYS',
            'REGAL',
            '177',
            'REGAL',
            'GIFT',
            'BILLABLE',
            'GIFT_PURCHASE',
            'PAID',
            'EUR',
            '120.00',
            '0.00',
            '120.00',
            '{}',
            '{}',
            $invoiceResult['uuid_factura'],
            $paymentResult['uuid_payment'],
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
              PRODUCT_EDITION, CLASSIFICATION, CLASSIFICATION_REASON,
              STATUS, CURRENCY, GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT,
              PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidOperation,
            'UC018|NOTIFY|DEST',
            'ENROLLMENT',
            'WEB',
            'INSCRIPCIO',
            '501',
            'CURS',
            'COURSE-NOTIFY',
            '2026/09',
            'NON_BILLABLE',
            'GIFT_REDEMPTION',
            'COMPLETED',
            'EUR',
            '120.00',
            '120.00',
            '0.00',
            '{}',
            '{}',
        ]);

        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, CONSUMED_UUID_OPERATION, RULE_VERSION,
              RULE_SNAPSHOT_JSON, FACE_VALUE, CURRENCY, STATUS, ISSUED_AT,
              CONSUMED_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidEntitlement,
            'GIFT',
            hash('sha256', $giftCode),
            'person:id:' . hash('sha256', '12345678Z'),
            $uuidOrigin,
            $uuidOperation,
            'GIFT_V1',
            '{}',
            '120.00',
            'EUR',
            'CONSUMED',
            '2026-09-01 10:00:00',
            '2026-10-01 10:00:00',
            'UC018|NOTIFY|ENTITLEMENT',
        ]);

        return [
            $giftCode,
            $uuidEntitlement,
            $uuidOperation,
            (string) $invoiceResult['uuid_factura'],
            (string) $paymentResult['uuid_payment'],
        ];
    }
}
