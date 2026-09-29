<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\{Assert, TestDatabase, Fixtures, AeatFixtures, ScriptRunner};

final class FiscalRecordCliTest
{
    private function issue(\PDO $db): array
    {
        $snapshot = AeatFixtures::snapshot();
        $fields = $snapshot['record'];
        $fields['Desglose'] = ['DetalleDesglose' => [[
            'Impuesto' => '01', 'ClaveRegimen' => '01', 'OperacionExenta' => 'E1',
            'BaseImponibleOimporteNoSujeto' => '120.00']]];
        return IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'aeat_fields' => $fields, 'aeat_header' => $snapshot['header']]));
    }

    private function run(string $script, array $args): array
    {
        $result = ScriptRunner::run('scripts/' . $script . '-fiscal-record.php', [], $args);
        Assert::same('', $result['stderr']);
        return [$result['exit_code'], json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR)];
    }

    public function testCliSubsanationPreviewIsReadOnlyAndConfirmationIsIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->issue($db);
        $fields = json_decode($db->query('SELECT PAYLOAD_JSON FROM factura_registres')->fetchColumn(), true)['aeat']['record'];
        $fields['DescripcionOperacion'] = 'Correccio XML des del CLI';
        $file = tempnam(sys_get_temp_dir(), 'sif-cli-');
        try {
            file_put_contents($file, json_encode($fields, JSON_THROW_ON_ERROR));
            $args = ['--type=SUBSANACIO', '--uuid=' . $invoice['uuid_factura'], '--reason=ERROR_REGISTRE',
                '--reference=CLI-CORRECTION', '--subsanation-kind=SUBSANACION', '--corrected-fields=' . $file];
            $chain = $db->query('SELECT * FROM fiscal_chain_state')->fetchAll(\PDO::FETCH_ASSOC);
            [$exit, $preview] = $this->run('preview', $args);
            Assert::same(0, $exit);
            Assert::same(true, $preview['advisory_only']);
            Assert::stringContainsString('RegistroAlta', $preview['xml_preview']);
            Assert::same($chain, $db->query('SELECT * FROM fiscal_chain_state')->fetchAll(\PDO::FETCH_ASSOC));
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
            [$exit, $created] = $this->run('process', $args);
            Assert::same(0, $exit);
            Assert::same(false, $created['idempotency_reused']);
            Assert::same(true, $this->run('process', $args)[1]['idempotency_reused']);
            Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
            $fields['ImporteTotal'] = 'INVALID';
            file_put_contents($file, json_encode($fields));
            $args[3] = '--reference=CLI-INVALID';
            Assert::same(1, $this->run('preview', $args)[0]);
            Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        } finally {
            unlink($file);
        }
    }

    public function testCancellationPreviewAndConfirmUseTheSameRejectionRules(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->issue($db);
        $args = ['--type=ANULACIO', '--num-visible=' . $invoice['num_visible'], '--reason=ERROR', '--reference=CLI-CANCEL'];
        Assert::same(0, $this->run('process', $args)[0]);
        Assert::same(true, $this->run('preview', $args)[1]['existing_result']['idempotency_reused']);
        $args[3] = '--reference=CLI-CANCEL-SECOND';
        foreach (['preview', 'process'] as $script) {
            Assert::same(409, $this->run($script, $args)[1]['code']);
        }
        $db->exec("UPDATE factura_registres SET ESTAT_AEAT='REJECTED' WHERE TIPUS_REGISTRE='ANULACIO'");
        $args[] = '--cancellation-mode=RECHAZO_PREVIO';
        Assert::stringContainsString('<sf:RechazoPrevio>S</sf:RechazoPrevio>', $this->run('preview', $args)[1]['xml_preview']);
        Assert::same(0, $this->run('process', $args)[0]);
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
    }

    public function testAbsentRegistrationAliasCannotBypassPreviousRejectionCheck(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->issue($db);
        $fields = json_decode($db->query('SELECT PAYLOAD_JSON FROM factura_registres')->fetchColumn(), true)['aeat']['record'];
        $file = tempnam(sys_get_temp_dir(), 'sif-cli-');
        try {
            file_put_contents($file, json_encode($fields));
            $args = ['--type=SUBSANACIO', '--uuid=' . $invoice['uuid_factura'], '--reason=ERROR',
                '--subsanation-kind=SIN_REGISTRO_PREVIO', '--corrected-fields=' . $file];
            foreach (['preview', 'process'] as $script) {
                Assert::same(409, $this->run($script, $args)[1]['code']);
            }
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        } finally {
            unlink($file);
        }
    }

    public function testProductionGuardRejectsBeforeReadingInputOrConnecting(): void
    {
        foreach (['preview', 'process'] as $script) {
            $result = ScriptRunner::run('scripts/' . $script . '-fiscal-record.php',
                ['SIF_ENV' => 'production', 'SIF_DB_DSN' => 'invalid']);
            Assert::same(1, $result['exit_code']);
            Assert::stringContainsString('SIF_ENV=production', $result['stderr']);
        }
    }
}
