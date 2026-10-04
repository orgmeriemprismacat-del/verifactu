<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * Adds the immutable VERI*FACTU/AEAT registration fields required when UC-017
 * issues a fiscal invoice in preproduction/production.
 *
 * Tax classification is configuration, never inferred from the browser or
 * hardcoded from a generic "EXEMPT" flag.
 */
final class GiftAeatInvoicePayloadEnricher
{
    public function __construct(
        private array $issuer,
        private array $aeat
    ) {
    }

    public function enrich(array $payload): array
    {
        $issuerName = $this->required($this->issuer['name'] ?? null, 'AEAT issuer name');
        $issuerNif = $this->required($this->issuer['nif'] ?? null, 'AEAT issuer NIF');

        $producerName = $this->required(
            $this->aeat['producer_name'] ?? null,
            'AEAT system producer name'
        );
        $producerNif = $this->required(
            $this->aeat['producer_nif'] ?? null,
            'AEAT system producer NIF'
        );
        $systemName = $this->required(
            $this->aeat['system_name'] ?? null,
            'AEAT system name'
        );
        $systemId = $this->required(
            $this->aeat['system_id'] ?? null,
            'AEAT system ID'
        );
        if (preg_match('/^[A-Za-z0-9]{1,2}$/D', $systemId) !== 1) {
            throw SifException::validation('AEAT system ID must contain one or two alphanumeric characters');
        }

        $systemVersion = $this->required(
            $this->aeat['system_version'] ?? null,
            'AEAT system version'
        );
        $installationId = $this->required(
            $this->aeat['installation_id'] ?? null,
            'AEAT installation ID'
        );
        $taxCode = $this->required(
            $this->aeat['gift_tax_code'] ?? null,
            'AEAT gift tax code'
        );
        $regimeKey = $this->required(
            $this->aeat['gift_regime_key'] ?? null,
            'AEAT gift regime key'
        );
        $exemptionCode = strtoupper($this->required(
            $this->aeat['gift_exemption_code'] ?? null,
            'AEAT gift exemption code'
        ));

        if (!in_array($taxCode, ['01', '02', '03', '05'], true)) {
            throw SifException::validation('Invalid AEAT gift tax code');
        }
        if (!in_array(
            $regimeKey,
            ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '14', '15', '17', '18', '19', '20', '21'],
            true
        )) {
            throw SifException::validation('Invalid AEAT gift regime key');
        }
        if (!in_array($exemptionCode, ['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7', 'E8'], true)) {
            throw SifException::validation('Invalid AEAT gift exemption code');
        }

        $base = $this->money(
            $payload['totals']['taxable_base'] ?? $payload['totals']['total'] ?? null,
            'AEAT gift taxable base'
        );
        $concept = trim((string) ($payload['lines'][0]['concept'] ?? 'Val regal formació'));
        if ($concept === '') {
            $concept = 'Val regal formació';
        }

        $payload['aeat_header'] = [
            'ObligadoEmision' => [
                'NombreRazon' => $issuerName,
                'NIF' => $issuerNif,
            ],
        ];
        $payload['aeat_fields'] = [
            'DescripcionOperacion' => mb_substr($concept, 0, 500, 'UTF-8'),
            'Desglose' => [
                'DetalleDesglose' => [[
                    'Impuesto' => $taxCode,
                    'ClaveRegimen' => $regimeKey,
                    'OperacionExenta' => $exemptionCode,
                    'BaseImponibleOimporteNoSujeto' => $base,
                ]],
            ],
            'SistemaInformatico' => [
                'NombreRazon' => $producerName,
                'NIF' => $producerNif,
                'NombreSistemaInformatico' => $systemName,
                'IdSistemaInformatico' => $systemId,
                'Version' => $systemVersion,
                'NumeroInstalacion' => $installationId,
                'TipoUsoPosibleSoloVerifactu' => 'S',
                'TipoUsoPosibleMultiOT' => 'N',
                'IndicadorMultiplesOT' => 'N',
            ],
        ];

        return $payload;
    }

    private function required(mixed $value, string $label): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            throw SifException::validation('Missing ' . $label);
        }

        return $value;
    }

    private function money(mixed $value, string $label): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid ' . $label);
        }

        return number_format((float) $value, 2, '.', '');
    }
}
