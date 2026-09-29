<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Aeat\{RecordHash, RecordFactory, XmlCodec, ResponseParser};
use Prisma\Sif\Tests\Support\{Assert, AeatFixtures};

final class AeatProtocolTest
{
    public function testOfficialFirstRecordHashVector(): void
    {
        $record = ['IDFactura' => ['IDEmisorFactura' => '89890001K',
            'NumSerieFactura' => '12345678/G33', 'FechaExpedicionFactura' => '01-01-2024'],
            'TipoFactura' => 'F1', 'CuotaTotal' => '12.35', 'ImporteTotal' => '123.45',
            'FechaHoraHusoGenRegistro' => '2024-01-01T19:20:30+01:00'];
        Assert::same('3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60',
            (new RecordHash())->calculate('RegistroAlta', $record));
    }

    public function testAltaXsdEscapingAndStableRetry(): void
    {
        $snapshot = AeatFixtures::snapshot();
        $codec = new XmlCodec();
        $xml = $codec->request($snapshot);
        Assert::stringContainsString('Formació &amp; proves &lt;XML&gt;', $xml);
        Assert::same($xml, $codec->request($snapshot));
        Assert::same(false, str_contains($xml, 'Signature'));
    }

    public function testCancellationChainsToGlobalPreviousRecord(): void
    {
        $prior = AeatFixtures::snapshot();
        $factory = new RecordFactory();
        $record = ['IDFactura' => ['IDEmisorFacturaAnulada' => '89890001K',
            'NumSerieFacturaAnulada' => '12345678/G33', 'FechaExpedicionFacturaAnulada' => '01-01-2024'],
            'SistemaInformatico' => $prior['record']['SistemaInformatico']];
        $cancel = $factory->freeze('RegistroAnulacion', $prior['header'], $record, $prior,
            new \DateTimeImmutable('2024-01-01T19:20:35+01:00'));
        Assert::same($prior['record']['Huella'], $cancel['record']['Encadenamiento']['RegistroAnterior']['Huella']);
        Assert::stringContainsString('RegistroAnulacion', (new XmlCodec())->request($cancel));
    }

    public function testRejectsTamperedHashMissingFieldAndInvalidDate(): void
    {
        $snapshot = AeatFixtures::snapshot();
        $snapshot['record']['ImporteTotal'] = '122.00';
        Assert::throws(\RuntimeException::class, fn () => (new XmlCodec())->request($snapshot));
        $snapshot = AeatFixtures::snapshot();
        unset($snapshot['record']['Desglose']);
        Assert::throws(\RuntimeException::class, fn () => (new XmlCodec())->request($snapshot));
        $snapshot = AeatFixtures::snapshot();
        $snapshot['record']['IDFactura']['FechaExpedicionFactura'] = '31-02-2024';
        Assert::throws(\InvalidArgumentException::class, fn () => (new RecordFactory())->freeze(
            $snapshot['type'], $snapshot['header'], $snapshot['record'], null, new \DateTimeImmutable()));
    }

    public function testParsesAllLineStates(): void
    {
        $snapshot = AeatFixtures::snapshot();
        foreach (['Correcto' => 'ACCEPTED', 'AceptadoConErrores' => 'ACCEPTED_WITH_ERRORS',
            'Incorrecto' => 'REJECTED'] as $state => $expected) {
            $result = (new ResponseParser())->parse(AeatFixtures::response($snapshot, $state), $snapshot);
            Assert::same($expected, $result['status']);
            Assert::same(60, $result['response']['flow_wait_seconds']);
        }
    }

    public function testRejectsWrongInvoiceFaultAndEntities(): void
    {
        $snapshot = AeatFixtures::snapshot();
        $response = AeatFixtures::response($snapshot);
        Assert::throws(\RuntimeException::class, fn () => (new ResponseParser())->parse(
            str_replace('12345678/G33', 'OTHER', $response), $snapshot));
        Assert::throws(\RuntimeException::class, fn () => (new ResponseParser())->parse(
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            . '<soap:Fault/></soap:Body></soap:Envelope>', $snapshot));
        Assert::throws(\RuntimeException::class, fn () => (new XmlCodec())->parse(
            '<!DOCTYPE x [<!ENTITY secret SYSTEM "file:///private">]><x>&secret;</x>'));
    }

    public function testDoesNotApplyOriginalAltaResponseToSubsanation(): void
    {
        $original = AeatFixtures::snapshot();
        $corrected = (new RecordFactory())->correct($original, $original['record'], $original,
            'RECHAZO_PREVIO', new \DateTimeImmutable('2024-01-01T19:21:30+01:00'));
        Assert::throws(\RuntimeException::class, fn () => (new ResponseParser())->parse(
            AeatFixtures::response($original), $corrected));
        Assert::same('ACCEPTED', (new ResponseParser())->parse(
            AeatFixtures::response($corrected), $corrected)['status']);
    }

    public function testPersistedJsonKeyOrderDoesNotChangeRequestBytes(): void
    {
        $snapshot = AeatFixtures::snapshot();
        $reorder = function (array $value) use (&$reorder): array {
            foreach ($value as &$child) {
                if (is_array($child)) {
                    $child = $reorder($child);
                }
            }
            unset($child);
            if (!array_is_list($value)) {
                ksort($value);
            }
            return $value;
        };
        Assert::same((new XmlCodec())->request($snapshot), (new XmlCodec())->request($reorder($snapshot)));
    }
}
