<?php

namespace Prisma\Sif\Aeat;

/** Call only inside the transaction holding fiscal_chain_state row 1. */
final class RegistrationSnapshot
{
    public function previous(\PDO $db, array $chain, bool $requested): ?array
    {
        if ((int) $chain['LAST_FISCAL_ORDER'] === 0) {
            return null;
        }
        $stmt = $db->prepare('SELECT PAYLOAD_JSON FROM factura_registres WHERE FISCAL_ORDER = ?');
        $stmt->execute([$chain['LAST_FISCAL_ORDER']]);
        $payload = json_decode((string) $stmt->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        $previous = $payload['aeat'] ?? null;
        if ($requested !== is_array($previous)) {
            throw new \RuntimeException('Cannot mix internal prototype and official AEAT chains. Use an empty test database.');
        }
        return $previous;
    }

    public function invoice(\PDO $db, array $chain, array $payload, string $number,
        \DateTimeImmutable $issuedAt): ?array
    {
        $requested = isset($payload['aeat_fields']);
        $previous = $this->previous($db, $chain, $requested);
        if (!$requested) {
            return null;
        }
        $fields = $payload['aeat_fields'];
        $header = $payload['aeat_header'] ?? [];
        $fields['IDFactura'] = ['IDEmisorFactura' => $header['ObligadoEmision']['NIF'] ?? '',
            'NumSerieFactura' => $number, 'FechaExpedicionFactura' => $issuedAt->format('d-m-Y')];
        $fields['NombreRazonEmisor'] = $header['ObligadoEmision']['NombreRazon'] ?? '';
        $fields['TipoFactura'] = $payload['type'];
        $fields['CuotaTotal'] = $payload['totals']['iva_import'] ?? '0.00';
        $fields['ImporteTotal'] = $payload['totals']['total'];
        $fields['Destinatarios'] = ['IDDestinatario' => [[
            'NombreRazon' => $payload['billing']['name'], 'NIF' => $payload['billing']['nif']]]];
        unset($fields['Subsanacion'], $fields['RechazoPrevio']);
        return (new RecordFactory())->freeze('RegistroAlta', $header, $fields, $previous, $issuedAt);
    }
}
