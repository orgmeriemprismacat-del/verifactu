<?php

namespace Prisma\Sif\Tests\Support;

use Prisma\Sif\Aeat\RecordFactory;

final class AeatFixtures
{
    public static function snapshot(): array
    {
        return (new RecordFactory())->freeze('RegistroAlta',
            ['ObligadoEmision' => ['NombreRazon' => 'EMISOR DE PRUEBAS', 'NIF' => '89890001K']],
            [
                'IDFactura' => ['IDEmisorFactura' => '89890001K', 'NumSerieFactura' => '12345678/G33',
                    'FechaExpedicionFactura' => '01-01-2024'],
                'NombreRazonEmisor' => 'EMISOR DE PRUEBAS', 'TipoFactura' => 'F1',
                'DescripcionOperacion' => 'Formació & proves <XML>',
                'Destinatarios' => ['IDDestinatario' => [
                    ['NombreRazon' => 'CLIENT DE PROVES', 'NIF' => '12345678Z']]],
                'Desglose' => ['DetalleDesglose' => [
                    ['Impuesto' => '01', 'ClaveRegimen' => '01', 'CalificacionOperacion' => 'S1',
                        'TipoImpositivo' => '21.00', 'BaseImponibleOimporteNoSujeto' => '100.00',
                        'CuotaRepercutida' => '21.00']]],
                'CuotaTotal' => '21.00', 'ImporteTotal' => '121.00',
                'SistemaInformatico' => ['NombreRazon' => 'PRODUCTOR DE PRUEBAS', 'NIF' => '89890001K',
                    'NombreSistemaInformatico' => 'SIF PrisMa TEST', 'IdSistemaInformatico' => 'PM',
                    'Version' => '0.3-BORRADOR', 'NumeroInstalacion' => 'LOCAL-TEST',
                    'TipoUsoPosibleSoloVerifactu' => 'S', 'TipoUsoPosibleMultiOT' => 'N',
                    'IndicadorMultiplesOT' => 'N'],
            ], null, new \DateTimeImmutable('2024-01-01T19:20:30+01:00'));
    }

    public static function response(array $snapshot, string $state = 'Correcto'): string
    {
        $id = (new RecordFactory())->identity($snapshot);
        $identity = '';
        foreach ($id as $field => $value) {
            $identity .= '<sf:' . $field . '>' . htmlspecialchars($value, ENT_XML1, 'UTF-8')
                . '</sf:' . $field . '>';
        }
        $base = \Prisma\Sif\Aeat\XmlCodec::BASE;
        $operation = $snapshot['type'] === 'RegistroAlta' ? 'Alta' : 'Anulacion';
        $flags = '';
        foreach (['Subsanacion', 'RechazoPrevio', 'SinRegistroPrevio'] as $flag) {
            if (isset($snapshot['record'][$flag])) {
                $flags .= '<sf:' . $flag . '>' . htmlspecialchars($snapshot['record'][$flag], ENT_XML1, 'UTF-8')
                    . '</sf:' . $flag . '>';
            }
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><r:RespuestaRegFactuSistemaFacturacion xmlns:r="' . $base . 'RespuestaSuministro.xsd"'
            . ' xmlns:sf="' . $base . 'SuministroInformacion.xsd">'
            . '<r:CSV>TEST-CSV-NOT-AEAT-EVIDENCE</r:CSV>'
            . '<r:Cabecera><sf:ObligadoEmision><sf:NombreRazon>EMISOR DE PRUEBAS</sf:NombreRazon>'
            . '<sf:NIF>89890001K</sf:NIF></sf:ObligadoEmision></r:Cabecera>'
            . '<r:TiempoEsperaEnvio>60</r:TiempoEsperaEnvio><r:EstadoEnvio>'
            . ($state === 'Incorrecto' ? 'Incorrecto' : 'Correcto') . '</r:EstadoEnvio>'
            . '<r:RespuestaLinea><r:IDFactura>' . $identity . '</r:IDFactura>'
            . '<r:Operacion><sf:TipoOperacion>' . $operation . '</sf:TipoOperacion>' . $flags . '</r:Operacion>'
            . '<r:EstadoRegistro>' . $state . '</r:EstadoRegistro></r:RespuestaLinea>'
            . '</r:RespuestaRegFactuSistemaFacturacion></soap:Body></soap:Envelope>';
    }
}
