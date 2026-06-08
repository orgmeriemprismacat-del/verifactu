<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Service\ManualCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualCourseInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualCourseInvoiceServiceTest
{
    public function testIssuesInvoiceAndPaymentFromManualCourseTransfer(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new ManualCourseLegacySpyPdo([
            $this->legacyInscription(),
            $this->legacyCourse(),
            $this->legacyInscription(),
            $this->legacyCourse(),
        ]);
        $service = $this->service($sifDb);
        $input = [
            'amount' => '88.40',
            'movement_date' => '2026-06-08 09:15:00',
            'reference' => 'TRF600',
            'bank' => 'CAIXA',
            'notes' => 'Validat manualment a Passar pagaments',
        ];

        $first = $service->issueFromLegacyCoursePayment($legacyDb, 600, $input);
        $second = $service->issueFromLegacyCoursePayment($legacyDb, 600, $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][0]['source_type']);
        Assert::same(610, $first['legacy_sync']['relations'][0]['source_id']);
        Assert::same(910, $first['legacy_sync']['relations'][0]['factura_relacionada']);
        Assert::same(600, $first['legacy_sync']['relations'][0]['idpag']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(4, count($legacyDb->preparedSql));

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, SOURCE_CHANNEL, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF, IDPAG, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|CURS|IDPAG:600|REF:TRF600', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('88.40', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('PAYMENT|TRANSFERENCIA|CURS|IDPAG:600|REF:TRF600', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('88.40', $payment['IMPORT']);
        Assert::same('TRF600', $payment['PROVIDER_REF']);
        Assert::same(600, (int) $payment['IDPAG']);
        Assert::same('TRF600', $payment['REFERENCIA_BANCARIA']);
    }

    public function testRejectsInvalidIdpagBeforeLoadingLegacySnapshot(): void
    {
        $legacyDb = new ManualCourseLegacySpyPdo([]);

        Assert::throws(SifException::class, function () use ($legacyDb): void {
            $this->service(TestDatabase::fresh())->issueFromLegacyCoursePayment(
                $legacyDb,
                0,
                [
                    'amount' => '88.40',
                    'movement_date' => '2026-06-08',
                ]
            );
        }, 422);

        Assert::same([], $legacyDb->preparedSql);
    }

    private function service(\PDO $sifDb): ManualCourseInvoiceService
    {
        return new ManualCourseInvoiceService(
            new LegacyCourseSnapshotRepository(),
            new ManualCourseInvoicePayloadBuilder(),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function legacyInscription(): array
    {
        return [
            'ID' => 610,
            'ANY' => 2026,
            'MES' => '09',
            'CURS' => 'CO',
            'NOM' => 'Anna',
            'COGNOMS' => 'Manual',
            'DNI' => '11111111A',
            'CORREU' => 'anna@example.test',
            'ADRECA' => 'Carrer Manual 3',
            'Codi_Postal' => '08003',
            'Poblacio' => 'Barcelona',
            'FACTURA_RELACIONADA' => 910,
            'A_PAGAR' => '88.40',
            'INSC CURS' => '1',
            'PAGAMENT' => '0.00',
            'FRACCIO' => 0,
        ];
    }

    private function legacyCourse(): array
    {
        return [
            'NOM_CURS' => 'Comunicacio assertiva',
            'DATAI' => '2026-09-01',
            'DATAF' => '2026-09-30',
            'HORES' => '16',
        ];
    }
}

final class ManualCourseLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new ManualCourseLegacySpyStatement($this);
    }

    public function nextRow(): mixed
    {
        if ($this->rows === []) {
            return false;
        }

        return array_shift($this->rows);
    }

    public function recordParams(?array $params): void
    {
        $this->executedParams[] = $params ?? [];
    }
}

final class ManualCourseLegacySpyStatement extends \PDOStatement
{
    public function __construct(private ManualCourseLegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->db->recordParams($params);

        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->db->nextRow();
    }
}
