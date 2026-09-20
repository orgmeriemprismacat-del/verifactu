<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysPaymentIntentTest
{
    public function testCreatesAndReloadsIntentByDsOrder(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );

        $created = $service->create($db, [
            'ds_order' => 'ORDERINTENT1',
            'idpag' => 700,
            'source_type' => 'CURS',
            'source_id' => '700',
            'expected_amount' => '120.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
            'created_by' => 'test',
        ]);

        $loaded = (new RedsysPaymentIntentRepository())->findByDsOrder($db, 'ORDERINTENT1');

        Assert::same($created['uuid_intent'], $loaded['UUID_INTENT']);
        Assert::same('120.00', number_format((float) $loaded['EXPECTED_AMOUNT'], 2, '.', ''));
    }

    public function testEquivalentIntentReusesExistingDsOrder(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT2');

        $first = $service->create($db, $input);
        $second = $service->create($db, $input);

        Assert::same($first['uuid_intent'], $second['uuid_intent']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testDifferentIntentForSameDsOrderConflicts(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT3');
        $service->create($db, $input);

        $changed = $input;
        $changed['expected_amount'] = '121.00';

        Assert::throws(SifException::class, static function () use ($db, $service, $changed): void {
            $service->create($db, $changed);
        }, 409);
    }

    public function testRejectsUnsupportedSourceType(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT4');
        $input['source_type'] = 'ALTRE';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsEmptySnapshot(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT5');
        $input['snapshot'] = [];

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsScalarSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT16');
        $input['snapshot'] = 'text';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsNonSerializableSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $snapshot = [];
        $snapshot['self'] = &$snapshot;
        $input = $this->intentInput('ORDERINTENT17');
        $input['snapshot'] = $snapshot;

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsInvalidAmount(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT6');
        $input['expected_amount'] = '0.00';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsPartiallyNumericAmount(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT14');
        $input['expected_amount'] = '12oops';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsMissingDsOrder(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('   ');

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsDsOrderLongerThanColumn(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput(str_repeat('A', 41));

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsInvalidIdpag(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT7');
        $input['idpag'] = 0;

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsPartiallyNumericIdpag(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT15');
        $input['idpag'] = '7oops';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsInvalidCurrency(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT8');
        $input['currency'] = 'EURO';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsMissingSourceId(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT9');
        $input['source_id'] = '   ';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsSourceIdLongerThanColumn(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT10');
        $input['source_id'] = str_repeat('1', 65);

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsNonNumericGiftSourceId(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT11');
        $input['idpag'] = null;
        $input['source_type'] = 'REGAL';
        $input['source_id'] = 'CODI-X';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsMissingTerminal(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT12');
        $input['terminal'] = '   ';

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    public function testRejectsTerminalLongerThanColumn(): void
    {
        $db = TestDatabase::fresh();
        $service = new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
        $input = $this->intentInput('ORDERINTENT13');
        $input['terminal'] = str_repeat('1', 21);

        Assert::throws(SifException::class, static function () use ($db, $service, $input): void {
            $service->create($db, $input);
        }, 422);
    }

    private function intentInput(string $dsOrder): array
    {
        return [
            'ds_order' => $dsOrder,
            'idpag' => 700,
            'source_type' => 'CURS',
            'source_id' => '700',
            'expected_amount' => '120.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
            'created_by' => 'test',
        ];
    }
}
