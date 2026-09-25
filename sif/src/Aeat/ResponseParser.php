<?php

namespace Prisma\Sif\Aeat;

final class ResponseParser
{
    /** One immutable record per request; batch/global success never implies line success. */
    public function parse(string $xml, array $snapshot): array
    {
        $codec = new XmlCodec();
        $doc = $codec->parse($xml);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('soap', XmlCodec::SOAP);
        $xp->registerNamespace('r', XmlCodec::BASE . 'RespuestaSuministro.xsd');
        $xp->registerNamespace('sf', XmlCodec::BASE . 'SuministroInformacion.xsd');
        if ($xp->query('/soap:Envelope/soap:Body/soap:Fault')->length !== 0) {
            throw new \RuntimeException('AEAT SOAP fault; inspect protected response evidence.');
        }
        $roots = $xp->query('/soap:Envelope/soap:Body/r:RespuestaRegFactuSistemaFacturacion');
        if ($roots->length !== 1 || $xp->query('/soap:Envelope/soap:Body/*')->length !== 1) {
            throw new \RuntimeException('Unexpected AEAT SOAP response.');
        }
        $root = $roots->item(0);
        $bodyDoc = new \DOMDocument('1.0', 'UTF-8');
        $bodyDoc->appendChild($bodyDoc->importNode($root, true));
        $codec->validate($bodyDoc, 'RespuestaSuministro.xsd');
        if ($xp->evaluate('string(r:Cabecera/sf:ObligadoEmision/sf:NIF)', $root)
            !== ($snapshot['header']['ObligadoEmision']['NIF'] ?? null)) {
            throw new \RuntimeException('AEAT response issuer mismatch.');
        }
        $lines = $xp->query('r:RespuestaLinea', $root);
        if ($lines->length !== 1) {
            throw new \RuntimeException('Missing or ambiguous AEAT line response.');
        }
        $line = $lines->item(0);
        foreach ((new RecordFactory())->identity($snapshot) as $field => $value) {
            if ($xp->evaluate('string(r:IDFactura/sf:' . $field . ')', $line) !== $value) {
                throw new \RuntimeException('AEAT response belongs to another invoice.');
            }
        }
        $operation = $xp->evaluate('string(r:Operacion/sf:TipoOperacion)', $line);
        if ($operation !== ($snapshot['type'] === 'RegistroAnulacion' ? 'Anulacion' : 'Alta')) {
            throw new \RuntimeException('AEAT response operation mismatch.');
        }
        foreach (['Subsanacion', 'RechazoPrevio', 'SinRegistroPrevio'] as $flag) {
            $returned = $xp->evaluate('string(r:Operacion/sf:' . $flag . ')', $line);
            $expected = $snapshot['record'][$flag] ?? 'N';
            if (($returned === '' ? 'N' : $returned) !== $expected) {
                throw new \RuntimeException('AEAT response operation flags mismatch.');
            }
        }
        $state = $xp->evaluate('string(r:EstadoRegistro)', $line);
        $status = match ($state) {
            'Correcto' => 'ACCEPTED',
            'AceptadoConErrores' => 'ACCEPTED_WITH_ERRORS',
            'Incorrecto' => 'REJECTED',
            default => throw new \RuntimeException('Unknown AEAT line state.'),
        };
        $duplicate = $xp->query('r:RegistroDuplicado', $line)->length !== 0;
        return [
            'status' => $status,
            'response' => [
                'estado_envio' => $xp->evaluate('string(r:EstadoEnvio)', $root),
                'estado_registro' => $state,
                'csv' => $xp->evaluate('string(r:CSV)', $root),
                'flow_wait_seconds' => (int) $xp->evaluate('string(r:TiempoEsperaEnvio)', $root),
                'error_code' => $xp->evaluate('string(r:CodigoErrorRegistro)', $line),
                'error_message' => $xp->evaluate('string(r:DescripcionErrorRegistro)', $line),
                'duplicate' => $duplicate,
                'requires_review' => $duplicate || $status !== 'ACCEPTED',
                'response_sha256' => hash('sha256', $xml),
            ],
        ];
    }
}
