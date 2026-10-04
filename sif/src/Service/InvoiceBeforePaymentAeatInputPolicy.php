<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentAeatInputPolicy
{
    public function __construct(
        private string $issuerNif,
        private string $issuerName,
        private bool $requireOfficialSnapshot,
        private array $systemInformation = [],
        private array $taxInformation = []
    ) {
        $this->issuerNif = strtoupper(trim($this->issuerNif));
        $this->issuerName = trim($this->issuerName);
    }

    public function prepare(array $input): array
    {
        if (!$this->requireOfficialSnapshot) {
            return $input;
        }

        if (array_key_exists('aeat_fields', $input) || array_key_exists('aeat_header', $input)) {
            throw SifException::validation('Invoice-before-payment AEAT fields are server-owned');
        }

        if ($this->issuerNif === '' || $this->issuerName === '' || $this->issuerNif === 'G00000000') {
            throw new \RuntimeException(
                'Configured non-placeholder SIF issuer is required for UC-004 official AEAT payloads'
            );
        }

        if (($input['type'] ?? null) !== 'F1') {
            throw SifException::validation('UC-004 official AEAT policy currently supports F1 invoices only');
        }

        $taxCode = $this->requiredTaxValue('tax_code');
        $regimeKey = $this->requiredTaxValue('regime_key');
        $exemptionReason = $this->requiredTaxValue('exemption_reason');

        if (!in_array($taxCode, ['01', '02', '03', '05'], true)) {
            throw new \RuntimeException('Invalid UC-004 AEAT tax code configuration');
        }
        if (!in_array($regimeKey, [
            '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11',
            '14', '15', '17', '18', '19', '20', '21',
        ], true)) {
            throw new \RuntimeException('Invalid UC-004 AEAT regime key configuration');
        }
        if (!in_array($exemptionReason, ['E1', 'E2', 'E3', 'E4', 'E5', 'E6', 'E7', 'E8'], true)) {
            throw new \RuntimeException('Invalid UC-004 AEAT exemption reason configuration');
        }

        $taxableBase = $this->money($input['totals']['taxable_base'] ?? null);
        $input['totals']['exemption_reason'] = $exemptionReason;
        foreach ($input['lines'] ?? [] as $index => $line) {
            if (!is_array($line)) {
                throw SifException::validation("Invalid UC-004 invoice line {$index}");
            }
            $input['lines'][$index]['exemption_reason'] = $exemptionReason;
        }

        $input['aeat_header'] = [
            'ObligadoEmision' => [
                'NIF' => $this->issuerNif,
                'NombreRazon' => $this->issuerName,
            ],
        ];

        $input['aeat_fields'] = [
            'DescripcionOperacion' => $this->description($input),
            'Desglose' => [
                'DetalleDesglose' => [[
                    'Impuesto' => $taxCode,
                    'ClaveRegimen' => $regimeKey,
                    'OperacionExenta' => $exemptionReason,
                    'BaseImponibleOimporteNoSujeto' => $taxableBase,
                ]],
            ],
            'SistemaInformatico' => $this->serverSystemInformation(),
        ];

        return $input;
    }

    private function description(array $input): string
    {
        $context = $input['uc004_context'] ?? [];
        $parts = [];

        foreach (['legacy_concept1', 'legacy_concept2'] as $key) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts === []) {
            foreach ($input['lines'] ?? [] as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $concept = trim((string) ($line['concept'] ?? ''));
                if ($concept !== '') {
                    $parts[] = $concept;
                }
            }
        }

        $description = trim(implode(' · ', array_values(array_unique($parts))));
        if ($description === '') {
            throw SifException::validation('UC-004 AEAT operation description could not be built');
        }

        return mb_substr($description, 0, 500, 'UTF-8');
    }

    private function serverSystemInformation(): array
    {
        $systemName = trim((string) ($this->systemInformation['system_name'] ?? ''));
        $systemId = trim((string) ($this->systemInformation['system_id'] ?? ''));
        $systemVersion = trim((string) ($this->systemInformation['system_version'] ?? ''));
        $installationId = trim((string) ($this->systemInformation['installation_id'] ?? ''));

        if ($systemName === '' || $systemId === '' || $systemVersion === '' || $installationId === '') {
            throw new \RuntimeException(
                'Complete server-side SIF identity is required for UC-004 official AEAT payloads'
            );
        }

        return [
            'NombreRazon' => $this->issuerName,
            'NIF' => $this->issuerNif,
            'NombreSistemaInformatico' => $systemName,
            'IdSistemaInformatico' => $systemId,
            'Version' => $systemVersion,
            'NumeroInstalacion' => $installationId,
            'TipoUsoPosibleSoloVerifactu' => 'S',
            'TipoUsoPosibleMultiOT' => 'N',
            'IndicadorMultiplesOT' => 'N',
        ];
    }

    private function requiredTaxValue(string $key): string
    {
        $value = strtoupper(trim((string) ($this->taxInformation[$key] ?? '')));
        if ($value === '') {
            throw new \RuntimeException("Missing UC-004 AEAT {$key} configuration");
        }

        return $value;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid UC-004 AEAT taxable base');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
