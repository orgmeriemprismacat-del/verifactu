<?php

namespace Prisma\Sif\Aeat;

/** AEAT huella specification 0.1.2. This is not the legacy JSON digest. */
final class RecordHash
{
    public function calculate(string $type, array $record): string
    {
        if (!in_array($type, ['RegistroAlta', 'RegistroAnulacion'], true)) {
            throw new \InvalidArgumentException('Unsupported AEAT record type.');
        }
        $suffix = $type === 'RegistroAnulacion' ? 'Anulada' : '';
        $fields = [];
        foreach (['IDEmisorFactura', 'NumSerieFactura', 'FechaExpedicionFactura'] as $name) {
            $fields[$name . $suffix] = $record['IDFactura'][$name . $suffix] ?? '';
        }
        if ($suffix === '') {
            foreach (['TipoFactura', 'CuotaTotal', 'ImporteTotal'] as $name) {
                $fields[$name] = $record[$name] ?? '';
            }
        }
        $fields['Huella'] = $record['Encadenamiento']['RegistroAnterior']['Huella'] ?? '';
        $fields['FechaHoraHusoGenRegistro'] = $record['FechaHoraHusoGenRegistro'] ?? '';
        $parts = [];
        foreach ($fields as $name => $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('AEAT hash values must be strings, including decimals.');
            }
            $parts[] = $name . '=' . trim($value);
        }
        return strtoupper(hash('sha256', implode('&', $parts)));
    }
}
