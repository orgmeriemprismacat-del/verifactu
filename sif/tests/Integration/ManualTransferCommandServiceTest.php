<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\ManualTransferCommandService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualTransferCommandServiceTest
{
    public function testAuthorizedActorRegistersTransferWithImmutableBankEventId(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $result = $this->service($db)->register($db, $this->actor(), [
            'uuid_factura' => $invoice['uuid_factura'],
            'amount' => '120.00',
            'movement_date' => '2026-10-03 10:00:00',
            'external_bank_event_id' => 'BANK-EVENT-20261003-0001',
            'reference' => 'MATRICULA',
            'bank' => 'BANC TEST',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same('CREATED', $result['status']);
        Assert::same('11111111-1111-4111-8111-111111111111', $result['request_id']);

        $payment = $db->query(
            'SELECT IDEMPOTENCY_KEY, PROVIDER_REF, REFERENCIA_BANCARIA FROM payment_transaction'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|BANK_EVENT_SHA256:' . hash('sha256', 'BANK-EVENT-20261003-0001'), $payment['IDEMPOTENCY_KEY']);
        Assert::same('BANK-EVENT-20261003-0001', $payment['PROVIDER_REF']);
        Assert::same('MATRICULA', $payment['REFERENCIA_BANCARIA']);
    }

    public function testRejectsActorWithoutManualTransferRole(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->register($db, [
                'actor_id' => 'operator@example.test',
                'roles' => ['READ_ONLY'],
                'request_id' => '11111111-1111-4111-8111-111111111111',
            ], [
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'amount' => '10.00',
                'movement_date' => '2026-10-03',
                'external_bank_event_id' => 'BANK-EVENT-DENIED',
            ]);
        }, 403);
    }

    public function testRejectsBankEventIdLongerThanProviderRefColumn(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->register($db, $this->actor(), [
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'amount' => '10.00',
                'movement_date' => '2026-10-03',
                'external_bank_event_id' => str_repeat('X', 81),
                'bank' => 'BBVA',
            ]);
        }, 422);
    }

    public function testRejectsTpvBankFromManualTransferFlow(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->register($db, $this->actor(), [
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'amount' => '10.00',
                'movement_date' => '2026-10-03',
                'external_bank_event_id' => 'TPV-MUST-NOT-ENTER-UC022',
                'bank' => 'tpv',
            ]);
        }, 422);
    }

    public function testRequiresImmutableBankEventId(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->register($db, $this->actor(), [
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'amount' => '10.00',
                'movement_date' => '2026-10-03',
            ]);
        }, 422);
    }

    public function testRejectsSameBankEventAgainstDifferentInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = IssueInvoiceTest::serviceFor($db);

        $firstInvoice = $invoiceService->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC022|CMD|A',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $secondInvoice = $invoiceService->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC022|CMD|B',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $service = $this->service($db);
        $payload = [
            'amount' => '60.00',
            'movement_date' => '2026-10-03 10:30:00',
            'external_bank_event_id' => 'BANK-EVENT-SAME',
            'reference' => 'MATRICULA',
            'bank' => 'BANC TEST',
        ];

        $service->register($db, $this->actor(), array_merge($payload, [
            'uuid_factura' => $firstInvoice['uuid_factura'],
        ]));

        Assert::throws(SifException::class, function () use ($service, $db, $secondInvoice, $payload): void {
            $service->register($db, $this->actor(), array_merge($payload, [
                'uuid_factura' => $secondInvoice['uuid_factura'],
            ]));
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
    }

    private function service(\PDO $db): ManualTransferCommandService
    {
        return new ManualTransferCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            ['PAYMENT_WRITE']
        );
    }

    private function actor(): array
    {
        return [
            'actor_id' => 'operator@example.test',
            'roles' => ['PAYMENT_WRITE'],
            'request_id' => '11111111-1111-4111-8111-111111111111',
        ];
    }
}
