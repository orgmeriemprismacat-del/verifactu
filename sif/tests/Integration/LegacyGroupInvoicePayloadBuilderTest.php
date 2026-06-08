<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\LegacyGroupInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyGroupInvoicePayloadBuilderTest
{
    public function testBuildsBasePayloadFromLegacyGroupSnapshot(): void
    {
        $payload = (new LegacyGroupInvoicePayloadBuilder())->build($this->groupSnapshot());

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('LEGACY|GRUP|IDPAG:940', $payload['idempotency_key']);
        Assert::same('GRUP', $payload['source_type']);
        Assert::same('REDSYS', $payload['source_channel']);
        Assert::same('legacy-group', $payload['created_by']);
        Assert::same('Responsable Grup', $payload['billing']['name']);
        Assert::same('44444444G', $payload['billing']['nif']);
        Assert::same('resp@example.test', $payload['billing']['email']);
        Assert::same('220.00', $payload['totals']['import_base']);
        Assert::same('20.00', $payload['totals']['discount']);
        Assert::same('200.00', $payload['totals']['total']);

        Assert::same(2, count($payload['lines']));
        Assert::same('Grup Comunicacio assertiva - Anna Participant', $payload['lines'][0]['concept']);
        Assert::same('Convocatoria 06 2026', $payload['lines'][0]['detail']);
        Assert::same('120.00', $payload['lines'][0]['import_base']);
        Assert::same('0.00', $payload['lines'][0]['discount_amount']);
        Assert::same('120.00', $payload['lines'][0]['total']);
        Assert::same('INSCRIPCIO', $payload['lines'][0]['source_type']);
        Assert::same(701, $payload['lines'][0]['source_id']);

        Assert::same('Grup Comunicacio assertiva - Biel Participant', $payload['lines'][1]['concept']);
        Assert::same('GRUP', $payload['lines'][1]['discount_origin']);
        Assert::same('20.00', $payload['lines'][1]['discount_amount']);
        Assert::same('80.00', $payload['lines'][1]['total']);
        Assert::same('INSCRIPCIO', $payload['lines'][1]['source_type']);
        Assert::same(702, $payload['lines'][1]['source_id']);
        Assert::same(false, str_contains($payload['lines'][1]['concept'], '22222222B'));
        Assert::same(false, str_contains($payload['lines'][1]['detail'], '22222222B'));

        Assert::same(3, count($payload['relations']));
        Assert::same('GRUP', $payload['relations'][0]['source_type']);
        Assert::same(940, $payload['relations'][0]['source_id']);
        Assert::same(940, $payload['relations'][0]['idpag']);
        Assert::same(0, $payload['relations'][0]['visible_alumne']);
        Assert::same('INSCRIPCIO', $payload['relations'][1]['source_type']);
        Assert::same(701, $payload['relations'][1]['source_id']);
        Assert::same(9301, $payload['relations'][1]['factura_relacionada']);
        Assert::same(0, $payload['relations'][1]['visible_alumne']);
        Assert::same('INSCRIPCIO', $payload['relations'][2]['source_type']);
        Assert::same(702, $payload['relations'][2]['source_id']);
        Assert::same(9302, $payload['relations'][2]['factura_relacionada']);
        Assert::same(0, $payload['relations'][2]['visible_alumne']);
    }

    public function testComposesWithValidatedRedsysNotificationAndIssueInvoicePayment(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $db,
            'ORDERGROUP940',
            940,
            '200.00',
            '0000',
            true,
            ['source' => 'group-test'],
            'VALIDATED'
        );

        $basePayload = (new LegacyGroupInvoicePayloadBuilder())->build($this->groupSnapshot());
        $payload = (new RedsysInvoicePayloadBuilder($notifications))
            ->buildFromValidatedNotification($db, 'ORDERGROUP940', $basePayload);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same('REDSYS|GRUP|IDPAG:940|ORDER:ORDERGROUP940', $payload['idempotency_key']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM fact_rels WHERE VISIBLE_ALUMNE = 0')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $groupRelation = $db->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER, VISIBLE_ALUMNE FROM fact_rels WHERE SOURCE_TYPE = \'GRUP\'')
            ->fetch(\PDO::FETCH_ASSOC);
        $participantRelation = $db->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER, VISIBLE_ALUMNE FROM fact_rels WHERE SOURCE_TYPE = \'INSCRIPCIO\' ORDER BY SOURCE_ID LIMIT 1')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('GRUP', $groupRelation['SOURCE_TYPE']);
        Assert::same(940, (int) $groupRelation['SOURCE_ID']);
        Assert::same(940, (int) $groupRelation['IDPAG']);
        Assert::same('ORDERGROUP940', $groupRelation['DS_ORDER']);
        Assert::same(0, (int) $groupRelation['VISIBLE_ALUMNE']);
        Assert::same('INSCRIPCIO', $participantRelation['SOURCE_TYPE']);
        Assert::same(701, (int) $participantRelation['SOURCE_ID']);
        Assert::same(940, (int) $participantRelation['IDPAG']);
        Assert::same('ORDERGROUP940', $participantRelation['DS_ORDER']);
        Assert::same(0, (int) $participantRelation['VISIBLE_ALUMNE']);
    }

    public function testRequiresAtLeastOneParticipant(): void
    {
        $snapshot = $this->groupSnapshot();
        $snapshot['items'] = [];

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyGroupInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    public function testRequiresResponsibleBilling(): void
    {
        $snapshot = $this->groupSnapshot();
        unset($snapshot['responsible']['DNI']);

        Assert::throws(SifException::class, function () use ($snapshot): void {
            (new LegacyGroupInvoicePayloadBuilder())->build($snapshot);
        }, 422);
    }

    private function groupSnapshot(): array
    {
        return [
            'responsible' => [
                'NOM' => 'Responsable',
                'COGNOMS' => 'Grup',
                'DNI' => '44444444G',
                'CORREU' => 'resp@example.test',
                'ADRECA' => 'Carrer Grup 4',
                'Codi_Postal' => '08004',
                'Poblacio' => 'Barcelona',
            ],
            'payment' => [
                'idpag' => 940,
                'amount' => '200.00',
            ],
            'items' => [
                [
                    'inscription' => $this->inscription(701, 'Anna', 'Participant', '11111111A', '120.00', '0.00', 9301),
                    'course' => $this->course(),
                ],
                [
                    'inscription' => array_replace(
                        $this->inscription(702, 'Biel', 'Participant', '22222222B', '80.00', '20.00', 9302),
                        [
                            'IMPORT_BASE' => '100.00',
                            'DESC_IMPORT' => '20.00',
                            'DESC_ORIGEN' => 'GRUP',
                            'DESC_TEXT' => 'Descompte grup',
                        ]
                    ),
                    'course' => $this->course(),
                ],
            ],
        ];
    }

    private function inscription(
        int $id,
        string $name,
        string $surname,
        string $dni,
        string $amount,
        string $paid,
        int $facturaRelacionada
    ): array {
        return [
            'ID' => $id,
            'IDPAG' => 940,
            'ANY' => 2026,
            'MES' => '06',
            'CURS' => 'COM',
            'Grup' => 'A',
            'TIPUS_INSC' => 'G',
            'NOM' => $name,
            'COGNOMS' => $surname,
            'DNI' => $dni,
            'CORREU' => strtolower($name) . '@example.test',
            'FACTURA_RELACIONADA' => $facturaRelacionada,
            'A_PAGAR' => $amount,
            'PAGAMENT' => $paid,
            'INSC CURS' => '1',
            'FRACCIO' => 0,
            'FRACCIONAT' => 0,
        ];
    }

    private function course(): array
    {
        return [
            'NOM_CURS' => 'Comunicacio assertiva',
            'DATAI' => '2026-06-10',
            'DATAF' => '2026-06-20',
            'HORES' => '12',
        ];
    }
}
