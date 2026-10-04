<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentAeatPayloadEnricher
{
    public function __construct(
        private string $environment,
        private array $issuer,
        private array $aeat
    ) {
    }

    public function enrich(array $payload): array
    {
        if (!$this->requiresOfficialSnapshot()) {
            return $payload;
        }

        $issuerName = $this->required($this->issuer, 'name', 120, 'SIF issuer name');
        $issuerNif = strtoupper($this->required($this->issuer, 'nif', 20, 'SIF issuer NIF'));
        $aeatIssuerNif = strtoupper($this->required($this->aeat, 'issuer_nif', 20, 'AEAT issuer NIF'));

        if (!hash_equals($issuerNif, $aeatIssuerNif)) {
            throw SifException::conflict(
                'SIF issuer NIF and AEAT issuer NIF do not match'
            );
        }

        $systemName = $this->required($this->aeat, 'system_name', 30, 'AEAT system name');
        $systemId = $this->required($this->aeat, 'system_id', 2, 'AEAT system ID');
        $systemVersion = $this->required($this->aeat, 'system_version', 50, 'AEAT system version');
        $installationId = $this->required($this->aeat, 'installation_id', 100, 'AEAT installation ID');

        if (preg_match('/^[A-Za-z0-9]{1,2}$/D', $systemId) !== 1) {
            throw SifException::validation('AEAT system ID must contain 1-2 alphanumeric characters');
        }

        $payload['aeat_header'] = [
            'ObligadoEmision' => [
                'NombreRazon' => $issuerName,
                'NIF' => $issuerNif,
            ],
        ];

        $payload['aeat_fields'] = [
            'DescripcionOperacion' => $this->description($payload),
            'Desglose' => [
                'DetalleDesglose' => $this->exemptBreakdown($payload),
            ],
            'SistemaInformatico' => [
                'NombreRazon' => $issuerName,
                'NIF' => $issuerNif,
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

    private function requiresOfficialSnapshot(): bool
    {
        return in_array(
            strtoupper(trim($this->environment)),
            ['PREPROD', 'PREPRODUCTION', 'PROD', 'PRODUCTION'],
            true
        );
    }

    private function exemptBreakdown(array $payload): array
    {
        $groups = [];

        foreach (($payload['lines'] ?? []) as $index => $line) {
            if (!is_array($line)) {
                throw SifException::validation("Invalid UC-021 AEAT line {$index}");
            }

            if (strtoupper(trim((string) ($line['iva_regim'] ?? ''))) !== 'EXEMPT') {
                throw SifException::validation(
                    'UC-021 official AEAT snapshot currently requires EXEMPT course lines'
                );
            }

            $reason = strtoupper(trim((string) ($line['exemption_reason'] ?? '')));
            if (!in_array($reason, ['E1', 'E2', 'E3', 'E4', 'E5', 'E6'], true)) {
                throw SifException::validation(
                    'UC-021 official AEAT snapshot requires a valid exemption reason'
                );
            }

            $amount = $this->cents($line['taxable_base'] ?? $line['total'] ?? null);
            $groups[$reason] = ($groups[$reason] ?? 0) + $amount;
        }

        if ($groups === []) {
            throw SifException::validation('UC-021 official AEAT snapshot requires invoice lines');
        }

        ksort($groups, SORT_STRING);
        $details = [];
        foreach ($groups as $reason => $amountCents) {
            $details[] = [
                'Impuesto' => '01',
                'OperacionExenta' => $reason,
                'BaseImponibleOimporteNoSujeto' => $this->amount($amountCents),
            ];
        }

        return $details;
    }

    private function description(array $payload): string
    {
        $context = $payload['uc004_context'] ?? [];
        $parts = [];

        if (is_array($context)) {
            foreach (['legacy_concept1', 'legacy_concept2'] as $key) {
                $value = trim((string) ($context[$key] ?? ''));
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        if ($parts === []) {
            foreach (($payload['lines'] ?? []) as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $concept = trim((string) ($line['concept'] ?? ''));
                if ($concept !== '') {
                    $parts[] = $concept;
                }
            }
        }

        $description = trim(implode('. ', array_values(array_unique($parts))));
        if ($description === '') {
            throw SifException::validation('UC-021 official AEAT snapshot requires operation description');
        }

        if (mb_strlen($description, 'UTF-8') > 500) {
            $description = mb_substr($description, 0, 500, 'UTF-8');
        }

        return $description;
    }

    private function required(array $source, string $key, int $maxLength, string $label): string
    {
        $value = trim((string) ($source[$key] ?? ''));
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            throw SifException::validation("Missing or invalid {$label}");
        }

        return $value;
    }

    private function cents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid UC-021 AEAT monetary amount');
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimals, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function amount(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '')
            . intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
