<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Repository\UsocFinancingTermsRepository;
use Prisma\Sif\Service\RedsysCoursePaymentIntentService;
use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Service\UsocFinancingTermsStateHasher;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCoursePaymentIntentServiceTest
{
    public function testCreatesIntentFromAuthoritativePendingBalance(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = $this->legacyDb(false);
        $service = $this->service();

        $result = $service->create($sifDb, $legacyDb, [
            'idpag' => 700,
            'requested_amount' => '100.00',
            'terminal' => '1',
            'ds_order' => '700000000001',
        ]);

        Assert::same('700000000001', $result['ds_order']);
        Assert::same('100.00', $result['amount']);
        Assert::same('100.00', $result['pending_before']);
        Assert::same(710, $result['source_id']);

        $row = $sifDb->query("SELECT EXPECTED_AMOUNT, SOURCE_TYPE, SOURCE_ID, SNAPSHOT_JSON FROM redsys_payment_intent WHERE DS_ORDER='700000000001'")
            ->fetch(\PDO::FETCH_ASSOC);
        Assert::same('100.00', number_format((float) $row['EXPECTED_AMOUNT'], 2, '.', ''));
        Assert::same('CURS', $row['SOURCE_TYPE']);
        Assert::same('710', (string) $row['SOURCE_ID']);

        $snapshot = json_decode((string) $row['SNAPSHOT_JSON'], true);
        Assert::same('100.00', $snapshot['payment']['amount']);
        Assert::same('20.00', $snapshot['payment']['paid_before']);
        Assert::same('120.00', $snapshot['payment']['contract_total']);
    }

    public function testRejectsPartialAmountWhenEnrollmentIsNotFractional(): void
    {
        $sifDb = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create($sifDb, $this->legacyDb(false), [
                'idpag' => 700,
                'requested_amount' => '50.00',
                'terminal' => '1',
                'ds_order' => '700000000002',
            ]);
        }, 409);

        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testAllowsPartialAmountWhenEnrollmentIsFractional(): void
    {
        $sifDb = TestDatabase::fresh();

        $result = $this->service()->create($sifDb, $this->legacyDb(true), [
            'idpag' => 700,
            'requested_amount' => '50.00',
            'terminal' => '1',
            'ds_order' => '700000000003',
        ]);

        Assert::same('50.00', $result['amount']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testUsesExactCentsForDecimalPendingBalance(): void
    {
        $sifDb = TestDatabase::fresh();

        $result = $this->service()->create($sifDb, $this->legacyDb(true, '0.30', '0.10'), [
            'idpag' => 700,
            'requested_amount' => '0.20',
            'terminal' => '1',
            'ds_order' => '700000000005',
        ]);

        Assert::same('0.20', $result['amount']);
        Assert::same('0.20', $result['pending_before']);

        Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create($sifDb, $this->legacyDb(true, '0.30', '0.10'), [
                'idpag' => 700,
                'requested_amount' => '0.21',
                'terminal' => '1',
                'ds_order' => '700000000006',
            ]);
        }, 422);
    }

    public function testValidatedUsocCannotBeRoutedAsGenericCourseIntent(): void
    {
        $sifDb = TestDatabase::fresh();

        $exception = Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create($sifDb, $this->legacyDb(false, '120.00', '20.00', 4, 1), [
                'idpag' => 700,
                'requested_amount' => '100.00',
                'terminal' => '1',
                'ds_order' => '700000000007',
            ]);
        }, 409);

        Assert::same(
            'USOC course payment requires prepared financing terms.',
            $exception->getMessage()
        );
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testValidatedUsocWithPreparedTermsCreatesDedicatedUsocStudentIntent(): void
    {
        $sifDb = TestDatabase::fresh();
        $terms = new UsocFinancingTermsRepository(new UuidGenerator());

        $sifDb->beginTransaction();
        $prepared = $terms->prepare(
            $sifDb,
            'req-usoc-checkout-terms',
            710,
            700,
            '75.00',
            '25.00',
            'secretaria-test',
            ['ADMIN'],
            (new UsocFinancingTermsStateHasher())->hash([
                'ID' => 710,
                'IDPAG' => 700,
                'ANY' => 2026,
                'MES' => '10',
                'CURS' => 'ABC',
                'TIPUS_DESC' => 4,
                'VALID_DESC' => 1,
                'A_PAGAR' => '75.00',
            ])
        );
        $sifDb->commit();

        $result = $this->service($terms)->create(
            $sifDb,
            $this->legacyDb(false, '75.00', '0.00', 4, 1),
            [
                'idpag' => 700,
                'requested_amount' => '75.00',
                'terminal' => '1',
                'ds_order' => '700000000009',
            ]
        );

        Assert::same('USOC_ALUMNE', $result['source_type']);
        Assert::same('75.00', $result['amount']);
        Assert::same('25.00', $result['entity_amount']);

        $intent = $sifDb->query(
            "SELECT SOURCE_TYPE, SOURCE_ID, EXPECTED_AMOUNT, SNAPSHOT_JSON
             FROM redsys_payment_intent
             WHERE DS_ORDER = '700000000009'"
        )->fetch(\PDO::FETCH_ASSOC);
        $snapshot = json_decode((string) $intent['SNAPSHOT_JSON'], true);

        Assert::same('USOC_ALUMNE', $intent['SOURCE_TYPE']);
        Assert::same('710', (string) $intent['SOURCE_ID']);
        Assert::same('75.00', number_format((float) $intent['EXPECTED_AMOUNT'], 2, '.', ''));
        Assert::same('75.00', (string) $snapshot['usoc']['student_amount']);
        Assert::same('25.00', (string) $snapshot['usoc']['entity_amount']);
        Assert::same((string) $prepared['UUID_TERMS'], (string) $snapshot['usoc']['terms_uuid']);
        Assert::same(4, (int) $snapshot['inscription']['TIPUS_DESC']);
        Assert::same(1, (int) $snapshot['inscription']['VALID_DESC']);
    }

    public function testRejectsPreparedUsocTermsAfterCourseIdentityChanges(): void
    {
        $sifDb = TestDatabase::fresh();
        $terms = new UsocFinancingTermsRepository(new UuidGenerator());

        $sifDb->beginTransaction();
        $terms->prepare(
            $sifDb,
            'req-usoc-stale-terms',
            710,
            700,
            '75.00',
            '25.00',
            'secretaria-test',
            ['ADMIN'],
            (new UsocFinancingTermsStateHasher())->hash([
                'ID' => 710,
                'IDPAG' => 700,
                'ANY' => 2026,
                'MES' => '09',
                'CURS' => 'OLD',
                'TIPUS_DESC' => 4,
                'VALID_DESC' => 1,
                'A_PAGAR' => '75.00',
            ])
        );
        $sifDb->commit();

        $exception = Assert::throws(SifException::class, function () use ($sifDb, $terms): void {
            $this->service($terms)->create(
                $sifDb,
                $this->legacyDb(false, '75.00', '0.00', 4, 1),
                [
                    'idpag' => 700,
                    'requested_amount' => '75.00',
                    'terminal' => '1',
                    'ds_order' => '700000000010',
                ]
            );
        }, 409);

        Assert::same(
            'Prepared USOC financing terms no longer match the current course state.',
            $exception->getMessage()
        );
        Assert::same(
            0,
            (int) $sifDb->query(
                "SELECT COUNT(*) FROM redsys_payment_intent WHERE DS_ORDER='700000000010'"
            )->fetchColumn()
        );
    }

    public function testPendingUsocCannotStartPaymentBeforeValidation(): void
    {
        $sifDb = TestDatabase::fresh();

        $exception = Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create($sifDb, $this->legacyDb(false, '120.00', '20.00', 4, 0), [
                'idpag' => 700,
                'requested_amount' => '100.00',
                'terminal' => '1',
                'ds_order' => '700000000008',
            ]);
        }, 409);

        Assert::same('USOC discount is not in a payable state.', $exception->getMessage());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testRejectsMissingOrInvalidTerminal(): void
    {
        $sifDb = TestDatabase::fresh();

        foreach ([null, '', '1234', 'A'] as $terminal) {
            Assert::throws(SifException::class, function () use ($sifDb, $terminal): void {
                $input = [
                    'idpag' => 700,
                    'requested_amount' => '100.00',
                    'ds_order' => '700000000099',
                ];
                if ($terminal !== null) {
                    $input['terminal'] = $terminal;
                }
                $this->service()->create($sifDb, $this->legacyDb(false), $input);
            }, 422);
        }
    }

    public function testRejectsCourseOrderThatDoesNotMatchRedsysContract(): void
    {
        $sifDb = TestDatabase::fresh();

        foreach (['ABC000000001', '7000000000001', '12-INVALID'] as $invalidOrder) {
            Assert::throws(SifException::class, function () use ($sifDb, $invalidOrder): void {
                $this->service()->create($sifDb, $this->legacyDb(false), [
                    'idpag' => 700,
                    'requested_amount' => '100.00',
                    'terminal' => '1',
                    'ds_order' => $invalidOrder,
                ]);
            }, 422);
        }
    }

    public function testRejectsAmountAboveAuthoritativePendingBalance(): void
    {
        $sifDb = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create($sifDb, $this->legacyDb(true), [
                'idpag' => 700,
                'requested_amount' => '100.01',
                'terminal' => '1',
                'ds_order' => '700000000004',
            ]);
        }, 422);
    }

    private function service(
        ?UsocFinancingTermsRepository $terms = null
    ): RedsysCoursePaymentIntentService {
        return new RedsysCoursePaymentIntentService(
            new LegacyCourseSnapshotRepository(),
            new RedsysPaymentIntentService(
                new RedsysPaymentIntentRepository(),
                new UuidGenerator()
            ),
            new RedsysDsOrderGenerator(),
            null,
            null,
            $terms
        );
    }

    private function legacyDb(
        bool $fractional,
        string $contractTotal = '120.00',
        string $alreadyPaid = '20.00',
        int $tipusDesc = 0,
        int $validDesc = 0
    ): RedsysCourseIntentLegacySpyPdo {
        return new RedsysCourseIntentLegacySpyPdo([
            [
                'ID' => 710,
                'ANY' => 2026,
                'MES' => '10',
                'CURS' => 'ABC',
                'NOM' => 'Persona',
                'COGNOMS' => 'Prova',
                'DNI' => '00000000T',
                'CORREU' => 'prova@example.invalid',
                'ADRECA' => 'Carrer prova',
                'Codi_Postal' => '17000',
                'Poblacio' => 'Girona',
                'FACTURA_RELACIONADA' => null,
                'A_PAGAR' => $contractTotal,
                'INSC CURS' => '0',
                'PAGAMENT' => $alreadyPaid,
                'FRACCIONAT' => $fractional ? 1 : 0,
                'FRACCIO' => '',
                'TIPUS_DESC' => $tipusDesc,
                'VALID_DESC' => $validDesc,
            ],
            [
                'NOM_CURS' => 'Curs de prova',
                'DATAI' => '2026-10-01',
                'DATAF' => '2026-10-31',
                'HORES' => 30,
            ],
        ]);
    }
}

final class RedsysCourseIntentLegacySpyPdo extends \PDO
{
    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new RedsysCourseIntentLegacySpyStatement($this);
    }

    public function nextRow(): mixed
    {
        return array_shift($this->rows) ?: false;
    }
}

final class RedsysCourseIntentLegacySpyStatement extends \PDOStatement
{
    public function __construct(private RedsysCourseIntentLegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->db->nextRow();
    }
}
