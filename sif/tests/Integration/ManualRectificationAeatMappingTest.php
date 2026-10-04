<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Service\AeatRectificationMapper;
use Prisma\Sif\Service\FiscalCorrectionDecisionGuard;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\PayloadIdempotencyValidator;
use Prisma\Sif\Service\RectificationCommandService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRectificationAeatMappingTest
{
    private const ISSUER_NIF = '89890001K';
    private const ISSUER_NAME = 'EMISOR DE PROVES';

    public function testDifferencesBuildsOfficialAeatRectificationFromOriginalSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $original = $this->officialOriginal($db, 'AEAT|UC005|DIFF');
        $commands = $this->commands($db);

        $input = $this->taxedInput('DIFERENCIES', '-50.00', '-10.50', '-60.50');
        $input['type'] = 'R5';
        $input['aeat_fields'] = [
            'SistemaInformatico' => ['Version' => 'ATTACKER'],
        ];
        $preview = $commands->preview(
            $this->actor('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            $original['uuid_factura'],
            $input,
            $this->classification('DIFERENCIES')
        );
        $result = $commands->confirm(
            $this->actor('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            $original['uuid_factura'],
            $input,
            $this->classification('DIFERENCIES'),
            $preview['fingerprint']
        );

        $aeat = $this->storedAeat($db, $result['uuid_factura']);
        Assert::same('R1', $aeat['record']['TipoFactura']);
        Assert::same('I', $aeat['record']['TipoRectificativa']);
        Assert::same(
            $original['num_visible'],
            $aeat['record']['FacturasRectificadas']['IDFacturaRectificada'][0]['NumSerieFactura']
        );
        Assert::same(
            '-50.00',
            $aeat['record']['Desglose']['DetalleDesglose'][0]['BaseImponibleOimporteNoSujeto']
        );
        Assert::same(
            '-10.50',
            $aeat['record']['Desglose']['DetalleDesglose'][0]['CuotaRepercutida']
        );
        Assert::same('0.5-UC005', $aeat['record']['SistemaInformatico']['Version']);
        Assert::same(false, array_key_exists('ImporteRectificacion', $aeat['record']));
    }

    public function testSubstitutionIncludesAmountsFromImmutableOriginalAeatSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $original = $this->officialOriginal($db, 'AEAT|UC005|SUB');
        $commands = $this->commands($db);

        $input = $this->taxedInput('SUBSTITUCIO', '-100.00', '-21.00', '-121.00');
        $input['billing'] = [
            'name' => 'CLIENT RECTIFICAT',
            'nif' => 'B12345678',
        ];

        $preview = $commands->preview(
            $this->actor('cccccccc-cccc-4ccc-8ccc-cccccccccccc'),
            $original['uuid_factura'],
            $input,
            $this->classification('SUBSTITUCIO')
        );
        $result = $commands->confirm(
            $this->actor('dddddddd-dddd-4ddd-8ddd-dddddddddddd'),
            $original['uuid_factura'],
            $input,
            $this->classification('SUBSTITUCIO'),
            $preview['fingerprint']
        );

        $aeat = $this->storedAeat($db, $result['uuid_factura']);
        Assert::same('S', $aeat['record']['TipoRectificativa']);
        Assert::same('100.00', $aeat['record']['ImporteRectificacion']['BaseRectificada']);
        Assert::same('21.00', $aeat['record']['ImporteRectificacion']['CuotaRectificada']);
        Assert::same('CLIENT RECTIFICAT', $aeat['record']['Destinatarios']['IDDestinatario'][0]['NombreRazon']);
        Assert::same('B12345678', $aeat['record']['Destinatarios']['IDDestinatario'][0]['NIF']);
    }

    private function officialOriginal(\PDO $db, string $idempotencyKey): array
    {
        return IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => $idempotencyKey,
            'source_channel' => 'INTRANET',
            'billing' => [
                'name' => 'CLIENT ORIGINAL',
                'nif' => '12345678Z',
            ],
            'totals' => [
                'import_base' => '100.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'GENERAL',
                'iva_pct' => '21.00',
                'iva_import' => '21.00',
                'total' => '121.00',
            ],
            'lines' => [[
                'concept' => 'Servei original',
                'detail' => 'Operacio original subjecta',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'base' => '100.00',
                'import_base' => '100.00',
                'discount_amount' => '0.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'GENERAL',
                'iva_pct' => '21.00',
                'iva_import' => '21.00',
                'total' => '121.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
            ]],
            'aeat_header' => [
                'ObligadoEmision' => [
                    'NombreRazon' => self::ISSUER_NAME,
                    'NIF' => self::ISSUER_NIF,
                ],
            ],
            'aeat_fields' => [
                'DescripcionOperacion' => 'Servei original',
                'Desglose' => [
                    'DetalleDesglose' => [[
                        'Impuesto' => '01',
                        'ClaveRegimen' => '01',
                        'CalificacionOperacion' => 'S1',
                        'TipoImpositivo' => '21.00',
                        'BaseImponibleOimporteNoSujeto' => '100.00',
                        'CuotaRepercutida' => '21.00',
                    ]],
                ],
                'SistemaInformatico' => $this->systemInformation(),
            ],
        ]));
    }

    private function commands(\PDO $db): RectificationCommandService
    {
        $invoices = new ManualPaymentInvoiceRepository();
        $builder = new ManualRectificationPayloadBuilder();

        return new RectificationCommandService(
            $db,
            $invoices,
            $builder,
            new ManualRectificationService(
                $invoices,
                new RectificationRepository(),
                $builder,
                IssueInvoiceTest::serviceFor($db)
            ),
            new FiscalCorrectionDecisionGuard(),
            new PayloadIdempotencyValidator(),
            new SifAuditEventRepository(new UuidGenerator()),
            new OperationalEventRepository(new UuidGenerator()),
            'test',
            new AeatRectificationMapper(
                $invoices,
                self::ISSUER_NIF,
                self::ISSUER_NAME,
                [
                    'system_name' => 'SIF PrisMa TEST',
                    'system_id' => 'PM',
                    'system_version' => '0.5-UC005',
                    'installation_id' => 'LOCAL-TEST',
                ],
                true
            )
        );
    }

    private function taxedInput(
        string $mode,
        string $base,
        string $quota,
        string $total
    ): array {
        return [
            'amount' => $total,
            'reason' => 'AJUST_FISCAL',
            'mode' => $mode,
            'concept' => 'Rectificacio fiscal',
            'detail' => 'Correccio validada per UC-74',
            'fiscal' => [
                'import_base' => $base,
                'taxable_base' => $base,
                'iva_regim' => 'GENERAL',
                'iva_pct' => '21.00',
                'iva_import' => $quota,
                'total' => $total,
            ],
        ];
    }

    private function classification(string $mode): array
    {
        return [
            'decision' => 'RECTIFICATION',
            'source_uc' => 'UC-74',
            'reason_code' => 'AMOUNT_DECREASE',
            'policy_version' => '2026-10',
            'invoice_type' => 'R1',
            'rectification_mode' => $mode,
        ];
    }

    private function actor(string $requestId): array
    {
        return [
            'actor_id' => 'operator-1',
            'roles' => ['FACTURACIO'],
            'request_id' => $requestId,
            'source_channel' => 'INTERNAL_API',
            'rectification_role' => 'FACTURACIO',
            'rectification_scope' => [
                'preview' => true,
                'issue' => true,
            ],
        ];
    }

    private function storedAeat(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT PAYLOAD_JSON FROM factura_registres WHERE UUID_FACTURA = ? ORDER BY FISCAL_ORDER DESC LIMIT 1'
        );
        $stmt->execute([$uuidFactura]);
        $stored = json_decode((string) $stmt->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        $aeat = $stored['aeat'] ?? null;

        if (!is_array($aeat)) {
            Assert::fail('Rectification fiscal record has no official AEAT snapshot');
        }

        return $aeat;
    }

    private function systemInformation(): array
    {
        return [
            'NombreRazon' => self::ISSUER_NAME,
            'NIF' => self::ISSUER_NIF,
            'NombreSistemaInformatico' => 'SIF PrisMa TEST',
            'IdSistemaInformatico' => 'PM',
            'Version' => '0.4-ORIGINAL',
            'NumeroInstalacion' => 'LOCAL-TEST',
            'TipoUsoPosibleSoloVerifactu' => 'S',
            'TipoUsoPosibleMultiOT' => 'N',
            'IndicadorMultiplesOT' => 'N',
        ];
    }
}
