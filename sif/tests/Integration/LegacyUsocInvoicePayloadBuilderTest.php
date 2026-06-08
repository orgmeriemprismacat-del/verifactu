<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyUsocInvoicePayloadBuilderTest
{
    public function testBuildsStudentPayloadFromValidatedUsocSnapshot(): void
    {
        $payload = (new LegacyUsocInvoicePayloadBuilder())->buildStudentPayload($this->usocSnapshot());

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('LEGACY|USOC_ALUMNE|IDPAG:980', $payload['idempotency_key']);
        Assert::same('USOC_ALUMNE', $payload['source_type']);
        Assert::same('REDSYS', $payload['source_channel']);
        Assert::same('redsys-usoc-student', $payload['created_by']);
        Assert::same('Alumna USOC', $payload['billing']['name']);
        Assert::same('12345678Z', $payload['billing']['nif']);
        Assert::same('alumna@example.test', $payload['billing']['email']);
        Assert::same('100.00', $payload['totals']['import_base']);
        Assert::same('25.00', $payload['totals']['discount']);
        Assert::same('75.00', $payload['totals']['total']);

        Assert::same(1, count($payload['lines']));
        Assert::same('Comunicacio assertiva', $payload['lines'][0]['concept']);
        Assert::same('Convocatoria 2026/06 - COM', $payload['lines'][0]['detail']);
        Assert::same('100.00', $payload['lines'][0]['import_base']);
        Assert::same('25.00', $payload['lines'][0]['discount_amount']);
        Assert::same('USOC', $payload['lines'][0]['discount_origin']);
        Assert::same('Descompte USOC', $payload['lines'][0]['discount_text']);
        Assert::same('TIPUS_DESC=4;VALID_DESC=1', $payload['lines'][0]['discount_internal_reason']);
        Assert::same('75.00', $payload['lines'][0]['total']);
        Assert::same('INSCRIPCIO', $payload['lines'][0]['source_type']);
        Assert::same(880, $payload['lines'][0]['source_id']);

        Assert::same(1, count($payload['relations']));
        Assert::same('INSCRIPCIO', $payload['relations'][0]['source_type']);
        Assert::same(880, $payload['relations'][0]['source_id']);
        Assert::same(980, $payload['relations'][0]['idpag']);
        Assert::same(1880, $payload['relations'][0]['factura_relacionada']);
        Assert::same(1, $payload['relations'][0]['visible_alumne']);

        Assert::same(4, $payload['usoc']['tipus_desc']);
        Assert::same(1, $payload['usoc']['valid_desc']);
        Assert::same('75.00', $payload['usoc']['student_amount']);
    }

    public function testComposesStudentPayloadWithValidatedRedsysNotificationAndPayment(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $db,
            'ORDERUSOC980',
            980,
            '75.00',
            '0000',
            true,
            ['source' => 'usoc-student-test'],
            'VALIDATED'
        );

        $basePayload = (new LegacyUsocInvoicePayloadBuilder())->buildStudentPayload($this->usocSnapshot());
        $payload = (new RedsysInvoicePayloadBuilder($notifications))
            ->buildFromValidatedNotification($db, 'ORDERUSOC980', $basePayload);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same('REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:ORDERUSOC980', $payload['idempotency_key']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $line = $db->query('SELECT CONCEPTE, DESC_ORIGEN, DESC_IMPORT, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia')
            ->fetch(\PDO::FETCH_ASSOC);
        $relation = $db->query('SELECT SOURCE_TYPE, SOURCE_ID, DS_ORDER, IDPAG, VISIBLE_ALUMNE FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $db->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('Comunicacio assertiva', $line['CONCEPTE']);
        Assert::same('USOC', $line['DESC_ORIGEN']);
        Assert::same('25.00', $line['DESC_IMPORT']);
        Assert::same('75.00', $line['TOTAL']);
        Assert::same('INSCRIPCIO', $line['SOURCE_TYPE']);
        Assert::same(880, (int) $line['SOURCE_ID']);
        Assert::same('INSCRIPCIO', $relation['SOURCE_TYPE']);
        Assert::same(880, (int) $relation['SOURCE_ID']);
        Assert::same('ORDERUSOC980', $relation['DS_ORDER']);
        Assert::same(980, (int) $relation['IDPAG']);
        Assert::same(1, (int) $relation['VISIBLE_ALUMNE']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('75.00', $payment['IMPORT']);
        Assert::same('ORDERUSOC980', $payment['DS_ORDER']);
        Assert::same(980, (int) $payment['IDPAG']);
    }

    public function testBuildsEntityPayloadWithExplicitUsocBillingData(): void
    {
        $db = TestDatabase::fresh();
        $payload = (new LegacyUsocInvoicePayloadBuilder())->buildEntityPayload($this->usocSnapshot(), [
            'billing' => [
                'name' => 'USOC',
                'nif' => 'G00000000',
                'address' => 'Carrer Entitat 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'country' => 'ES',
                'email' => 'facturacio@usoc.example.test',
            ],
            'amount' => '25.00',
            'student_invoice_uuid' => '11111111-2222-3333-4444-555555555555',
            'created_by' => 'manual-usoc',
        ]);

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same(
            'INTRANET|USOC_ENTITAT|ID_INSC:880|FACT_ALUMNE:11111111-2222-3333-4444-555555555555',
            $payload['idempotency_key']
        );
        Assert::same('USOC_ENTITAT', $payload['source_type']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('manual-usoc', $payload['created_by']);
        Assert::same('USOC', $payload['billing']['name']);
        Assert::same('G00000000', $payload['billing']['nif']);
        Assert::same('25.00', $payload['totals']['total']);
        Assert::same('Diferencia USOC - Comunicacio assertiva', $payload['lines'][0]['concept']);
        Assert::same('25.00', $payload['lines'][0]['total']);
        Assert::same('USOC_ENTITY', $payload['relations'][0]['relation_type']);
        Assert::same(0, $payload['relations'][0]['visible_alumne']);
        Assert::same('11111111-2222-3333-4444-555555555555', $payload['usoc']['student_invoice_uuid']);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $relation = $db->query('SELECT SOURCE_TYPE, SOURCE_ID, RELATION_TYPE, VISIBLE_ALUMNE FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('INSCRIPCIO', $relation['SOURCE_TYPE']);
        Assert::same(880, (int) $relation['SOURCE_ID']);
        Assert::same('USOC_ENTITY', $relation['RELATION_TYPE']);
        Assert::same(0, (int) $relation['VISIBLE_ALUMNE']);
    }

    public function testRejectsSnapshotsThatAreNotValidatedUsocDiscounts(): void
    {
        $notUsoc = $this->usocSnapshot();
        $notUsoc['inscription']['TIPUS_DESC'] = 3;

        Assert::throws(SifException::class, function () use ($notUsoc): void {
            (new LegacyUsocInvoicePayloadBuilder())->buildStudentPayload($notUsoc);
        }, 409);

        $notValidated = $this->usocSnapshot();
        $notValidated['inscription']['VALID_DESC'] = 0;

        Assert::throws(SifException::class, function () use ($notValidated): void {
            (new LegacyUsocInvoicePayloadBuilder())->buildStudentPayload($notValidated);
        }, 409);
    }

    public function testEntityPayloadRequiresExplicitBillingAmountAndStudentInvoice(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new LegacyUsocInvoicePayloadBuilder())->buildEntityPayload($this->usocSnapshot(), [
                'billing' => ['name' => 'USOC', 'nif' => 'G00000000'],
                'amount' => '25.00',
            ]);
        }, 422);

        Assert::throws(SifException::class, function (): void {
            (new LegacyUsocInvoicePayloadBuilder())->buildEntityPayload($this->usocSnapshot(), [
                'billing' => ['name' => 'USOC', 'nif' => 'G00000000'],
                'student_invoice_uuid' => '11111111-2222-3333-4444-555555555555',
            ]);
        }, 422);
    }

    private function usocSnapshot(): array
    {
        return [
            'inscription' => [
                'ID' => 880,
                'ANY' => 2026,
                'MES' => '06',
                'CURS' => 'COM',
                'NOM' => 'Alumna',
                'COGNOMS' => 'USOC',
                'DNI' => '12345678Z',
                'CORREU' => 'alumna@example.test',
                'ADRECA' => 'Carrer Alumna 10',
                'Codi_Postal' => '08002',
                'Poblacio' => 'Barcelona',
                'FACTURA_RELACIONADA' => 1880,
                'A_PAGAR' => '75.00',
                'IMPORT_BASE' => '100.00',
                'DESC_IMPORT' => '25.00',
                'PAGAMENT' => 1,
                'IDPAG' => 980,
                'TIPUS_DESC' => 4,
                'VALID_DESC' => 1,
                'FRACCIO' => 0,
                'FRACCIONAT' => 0,
            ],
            'course' => [
                'NOM_CURS' => 'Comunicacio assertiva',
                'DATAI' => '2026-06-10',
                'DATAF' => '2026-06-20',
                'HORES' => 12,
            ],
            'usoc' => [
                'student_amount' => '75.00',
                'entity_amount' => '25.00',
                'tipus_desc' => 4,
                'valid_desc' => 1,
            ],
            'payment' => [
                'amount' => '75.00',
                'idpag' => 980,
            ],
        ];
    }
}
