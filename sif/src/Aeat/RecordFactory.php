<?php

namespace Prisma\Sif\Aeat;

/** Builds a frozen snapshot at record generation, never at transmission time. */
final class RecordFactory
{
    private const ALTA = ['IDVersion', 'IDFactura', 'RefExterna', 'NombreRazonEmisor',
        'Subsanacion', 'RechazoPrevio', 'TipoFactura', 'TipoRectificativa', 'FacturasRectificadas',
        'FacturasSustituidas', 'ImporteRectificacion', 'FechaOperacion', 'DescripcionOperacion',
        'FacturaSimplificadaArt7273', 'FacturaSinIdentifDestinatarioArt61d', 'Macrodato',
        'EmitidaPorTerceroODestinatario', 'Tercero', 'Destinatarios', 'Cupon', 'Desglose',
        'CuotaTotal', 'ImporteTotal', 'Encadenamiento', 'SistemaInformatico',
        'FechaHoraHusoGenRegistro', 'NumRegistroAcuerdoFacturacion', 'IdAcuerdoSistemaInformatico',
        'TipoHuella', 'Huella'];
    private const ANULACION = ['IDVersion', 'IDFactura', 'RefExterna', 'SinRegistroPrevio',
        'RechazoPrevio', 'GeneradoPor', 'Generador', 'Encadenamiento', 'SistemaInformatico',
        'FechaHoraHusoGenRegistro', 'TipoHuella', 'Huella'];

    public function freeze(string $type, array $header, array $fields, ?array $previous,
        \DateTimeImmutable $generatedAt): array
    {
        $order = match ($type) {
            'RegistroAlta' => self::ALTA,
            'RegistroAnulacion' => self::ANULACION,
            default => throw new \InvalidArgumentException('Unsupported AEAT record type.'),
        };
        if (array_diff(array_keys($fields), $order)) {
            throw new \InvalidArgumentException('Unknown AEAT record fields.');
        }
        $fields['IDVersion'] = '1.0';
        $fields['Encadenamiento'] = $previous === null ? ['PrimerRegistro' => 'S']
            : ['RegistroAnterior' => $this->identity($previous) + ['Huella' => $previous['record']['Huella']]];
        $fields['FechaHoraHusoGenRegistro'] = $generatedAt->format('Y-m-d\TH:i:sP');
        $fields['TipoHuella'] = '01';
        $fields['Huella'] = (new RecordHash())->calculate($type, $fields);
        $record = [];
        foreach ($order as $field) {
            if (array_key_exists($field, $fields)) {
                $record[$field] = $fields[$field];
            }
        }
        $snapshot = ['type' => $type, 'header' => $header, 'record' => $record];
        $identity = $this->identity($snapshot);
        if (($header['ObligadoEmision']['NIF'] ?? '') !== $identity['IDEmisorFactura']
            || isset($header['RemisionRequerimiento'])
            || ($record['SistemaInformatico']['TipoUsoPosibleSoloVerifactu'] ?? '') !== 'S'
            || ($record['SistemaInformatico']['TipoUsoPosibleMultiOT'] ?? '') !== 'N'
            || ($record['SistemaInformatico']['IndicadorMultiplesOT'] ?? '') !== 'N') {
            throw new \InvalidArgumentException('Snapshot must identify the single VERI*FACTU issuer.');
        }
        if ($previous !== null && $this->identity($previous)['IDEmisorFactura'] !== $identity['IDEmisorFactura']) {
            throw new \InvalidArgumentException('Cannot chain records from different issuers.');
        }
        if ($type === 'RegistroAlta') {
            $this->checkAlta($record);
        }
        // Reject impossible dates accepted by the XSD's lexical date pattern.
        $date = \DateTimeImmutable::createFromFormat('!d-m-Y', $identity['FechaExpedicionFactura']);
        if (!$date || $date->format('d-m-Y') !== $identity['FechaExpedicionFactura']) {
            throw new \InvalidArgumentException('Invalid invoice issue date.');
        }
        (new XmlCodec())->request($snapshot);
        return $snapshot;
    }

    public function identity(array $snapshot): array
    {
        $suffix = ($snapshot['type'] ?? '') === 'RegistroAnulacion' ? 'Anulada' : '';
        $result = [];
        foreach (['IDEmisorFactura', 'NumSerieFactura', 'FechaExpedicionFactura'] as $field) {
            $value = $snapshot['record']['IDFactura'][$field . $suffix] ?? null;
            if (!is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException('Incomplete AEAT invoice identity.');
            }
            $result[$field] = $value;
        }
        return $result;
    }

    private function checkAlta(array $record): void
    {
        foreach (['CuotaTotal', 'ImporteTotal'] as $field) {
            if (!is_string($record[$field] ?? null)
                || !preg_match('/^-?\d{1,12}\.\d{2}$/D', $record[$field])) {
                throw new \InvalidArgumentException('AEAT totals require decimal strings with two places.');
            }
        }
        if (str_starts_with($record['TipoFactura'] ?? '', 'R')
            && !in_array($record['TipoRectificativa'] ?? '', ['S', 'I'], true)) {
            throw new \InvalidArgumentException('Rectification method is required.');
        }
        if (($record['TipoFactura'] ?? '') === 'F1' && empty($record['Destinatarios'])) {
            throw new \InvalidArgumentException('Complete invoice requires its recipient.');
        }
    }
}
