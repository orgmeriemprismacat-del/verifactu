<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Service\PackEnrollmentFundAllocationService;
use Prisma\Sif\Service\PackPaymentNotificationService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysPackEvidenceVerifier;
use Prisma\Sif\Service\RedsysPackInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysPackEvidenceVerifierTest
{
    public function testVerifiesCompletePackEvidenceWithoutExposingPersonalData(): void
    {
        [$db, $legacy, $result] = $this->completeEvidence();

        $evidence = (new RedsysPackEvidenceVerifier())->verify(
            $db,
            $legacy,
            'ORDERPACKEVIDENCE'
        );

        Assert::same(true, $evidence['ok']);
        Assert::same([], $evidence['failed']);
        Assert::same('210.00', $evidence['amount']);
        Assert::same('PROCESSED', $evidence['queue_status']);
        Assert::same('PENDING', $evidence['outbox_status']);
        Assert::same([501, 502], $evidence['inscription_ids']);
        Assert::same($result['uuid_factura'], $evidence['uuid_factura']);
        Assert::same($result['uuid_payment'], $evidence['uuid_payment']);

        $encoded = json_encode($evidence);
        Assert::same(false, str_contains((string) $encoded, 'maria@example.test'));
        Assert::same(false, str_contains((string) $encoded, '12345678Z'));
    }

    public function testFailsClosedWhenPackOutboxEvidenceIsMissing(): void
    {
        [$db, $legacy] = $this->completeEvidence();

        $db->exec('DELETE FROM notification_outbox');

        $evidence = (new RedsysPackEvidenceVerifier())->verify(
            $db,
            $legacy,
            'ORDERPACKEVIDENCE'
        );

        Assert::same(false, $evidence['ok']);
        Assert::same(true, in_array('single_notification_outbox', $evidence['failed'], true));
    }

    private function completeEvidence(): array
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();

        $snapshot = [
            'pack' => [
                'ID_PACK' => 77,
                'TITOL' => 'Benestar docent',
                'CODI' => 'BDOC',
            ],
            'payment' => [
                'idpag' => 910,
                'amount' => '210.00',
            ],
            'items' => [
                [
                    'ordinal' => 1,
                    'inscription' => $this->inscription(501, '06', 'ABC', '120.00', '0.00', '0.00'),
                    'course' => ['NOM_CURS' => 'Gestio emocional'],
                ],
                [
                    'ordinal' => 2,
                    'inscription' => $this->inscription(502, '07', 'DEF', '120.00', '30.00', '25.00'),
                    'course' => ['NOM_CURS' => 'Mindfulness a l aula'],
                ],
            ],
        ];

        $snapshotJson = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($snapshotJson === false) {
            Assert::fail('Could not encode PACK snapshot');
        }

        $db->prepare(
            "INSERT INTO redsys_payment_intent (
                UUID_INTENT, DS_ORDER, IDPAG, SOURCE_TYPE, SOURCE_ID,
                EXPECTED_AMOUNT, CURRENCY, TERMINAL, SNAPSHOT_JSON,
                STATUS, CREATED_BY
             ) VALUES (?, ?, ?, 'PACK', ?, ?, 'EUR', '1', ?, 'CONFIRMED', 'test')"
        )->execute([
            '11111111-1111-4111-8111-111111111111',
            'ORDERPACKEVIDENCE',
            910,
            '77',
            '210.00',
            $snapshotJson,
        ]);

        $notification = $notifications->recordReceived(
            $db,
            'ORDERPACKEVIDENCE',
            910,
            '210.00',
            '0000',
            true,
            [
                'source' => 'uc015-evidence-test',
                'currency_code' => '978',
                'terminal' => '1',
                'signature_version' => 'HMAC_SHA256_V1',
                'payload_hash' => str_repeat('a', 64),
            ],
            'VALIDATED'
        );

        $service = new RedsysPackInvoiceService(
            $notifications,
            new LegacyPackSnapshotRepository(),
            new LegacyPackInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db),
            new PackPaymentNotificationService(
                new NotificationOutboxRepository(new UuidGenerator())
            ),
            new PackEnrollmentFundAllocationService(
                new EnrollmentFundMovementRepository(new UuidGenerator())
            )
        );

        $result = $service->issueFromIntentSnapshot(
            $db,
            'ORDERPACKEVIDENCE',
            $snapshot
        );

        $queueResult = $result;
        $queueResult['legacy_sync_executed'] = true;
        $queueJson = json_encode(
            $queueResult,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($queueJson === false) {
            Assert::fail('Could not encode callback result');
        }

        $db->prepare(
            "INSERT INTO redsys_callback_queue (
                UUID_JOB, NOTIFICATION_ID, UUID_INTENT, STATUS, ATTEMPTS,
                RESULT_JSON, UUID_FACTURA, UUID_PAYMENT, PROCESSED_AT
             ) VALUES (?, ?, ?, 'PROCESSED', 1, ?, ?, ?, NOW())"
        )->execute([
            '22222222-2222-4222-8222-222222222222',
            (int) $notification['notification_id'],
            '11111111-1111-4111-8111-111111111111',
            $queueJson,
            $result['uuid_factura'],
            $result['uuid_payment'],
        ]);

        $legacy = new RedsysPackEvidenceLegacyPdo([
            [
                'ID' => 501,
                'A_PAGAR' => '120.00',
                'PAGAMENT' => '120.00',
                'DATA_PAG' => '2026-10-01 10:00:00',
                'OBSERVACIONS' => 'SIF ' . $result['uuid_factura'],
            ],
            [
                'ID' => 502,
                'A_PAGAR' => '90.00',
                'PAGAMENT' => '90.00',
                'DATA_PAG' => '2026-10-01 10:00:00',
                'OBSERVACIONS' => 'SIF ' . $result['uuid_factura'],
            ],
        ]);

        return [$db, $legacy, $result];
    }

    private function inscription(
        int $id,
        string $month,
        string $course,
        string $base,
        string $discount,
        string $pct
    ): array {
        $total = number_format((float) $base - (float) $discount, 2, '.', '');

        return [
            'ID' => $id,
            'IDPAG' => 910,
            'ANY' => 2026,
            'MES' => $month,
            'CURS' => $course,
            'TIPUS_INSC' => 'P',
            'NOM' => 'Maria',
            'COGNOMS' => 'Exemple',
            'DNI' => '12345678Z',
            'CORREU' => 'maria@example.test',
            'ADRECA' => 'Carrer Exemple 1',
            'Codi_Postal' => '08001',
            'Poblacio' => 'Barcelona',
            'A_PAGAR' => $total,
            'IMPORT_BASE' => $base,
            'DESC_IMPORT' => $discount,
            'DESC_PCT' => $pct,
            'TOTAL' => $total,
        ];
    }
}

final class RedsysPackEvidenceLegacyPdo extends \PDO
{
    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new RedsysPackEvidenceLegacyStatement($this->rows);
    }
}

final class RedsysPackEvidenceLegacyStatement extends \PDOStatement
{
    public function __construct(private array $rows)
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }
}
