<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Aeat\RecordFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class AeatRectificationMapper
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private string $issuerNif,
        private string $issuerName,
        private array $systemInformation = [],
        private bool $requireOfficialSnapshot = false
    ) {
        $this->issuerNif = strtoupper(trim($this->issuerNif));
        $this->issuerName = trim($this->issuerName);
    }

    public function map(
        \PDO $db,
        array $originalInvoice,
        array $rectificationPayload,
        array $classification
    ): ?array {
        $uuid = trim((string) ($originalInvoice['UUID_FACTURA'] ?? ''));
        if ($uuid === '') {
            throw SifException::validation('Original invoice UUID is required for AEAT rectification mapping');
        }

        $record = $this->invoices->findLatestFiscalRecordByUuid($db, $uuid);
        if ($record === null) {
            if ($this->requireOfficialSnapshot) {
                throw SifException::conflict('Original invoice has no fiscal record for official rectification');
            }

            return null;
        }

        $stored = $this->decodePayload($record['PAYLOAD_JSON'] ?? null);
        $original = $stored['aeat'] ?? null;
        if (!is_array($original)) {
            if ($this->requireOfficialSnapshot) {
                throw SifException::conflict(
                    'Original invoice has no official AEAT snapshot; rectification cannot be issued'
                );
            }

            return null;
        }

        $identity = $this->originalIdentity($original, $originalInvoice);
        $mode = strtoupper(trim((string) ($classification['rectification_mode'] ?? '')));
        $invoiceType = strtoupper(trim((string) ($classification['invoice_type'] ?? '')));
        if (!in_array($invoiceType, ['R1', 'R2', 'R3', 'R4', 'R5'], true)) {
            throw SifException::validation('UC-74 invoice_type R1-R5 is required for AEAT rectification');
        }
        if (!in_array($mode, ['DIFERENCIES', 'SUBSTITUCIO'], true)) {
            throw SifException::validation('Invalid UC-74 rectification mode for AEAT mapping');
        }

        $originalRecord = $original['record'] ?? null;
        if (!is_array($originalRecord)) {
            throw SifException::conflict('Original AEAT snapshot has no record payload');
        }

        $originalDetails = $originalRecord['Desglose']['DetalleDesglose'] ?? null;
        if (!is_array($originalDetails) || count($originalDetails) !== 1 || !is_array($originalDetails[0] ?? null)) {
            throw SifException::conflict(
                'UC-005 AEAT mapper currently requires one authoritative original tax breakdown line'
            );
        }

        $lines = $rectificationPayload['lines'] ?? null;
        if (!is_array($lines) || count($lines) !== 1 || !is_array($lines[0] ?? null)) {
            throw SifException::conflict(
                'UC-005 AEAT mapper currently requires one rectification line'
            );
        }

        $detail = $this->correctedDetail($originalDetails[0], $rectificationPayload);
        $fields = [
            'TipoRectificativa' => $mode === 'SUBSTITUCIO' ? 'S' : 'I',
            'FacturasRectificadas' => [
                'IDFacturaRectificada' => [$identity],
            ],
            'DescripcionOperacion' => $this->description($lines[0]),
            'Desglose' => [
                'DetalleDesglose' => [$detail],
            ],
            'SistemaInformatico' => $this->serverSystemInformation(),
        ];

        if ($mode === 'SUBSTITUCIO') {
            $fields['ImporteRectificacion'] = $this->originalAmounts($originalDetails);
        }

        return [
            'aeat_header' => [
                'ObligadoEmision' => [
                    'NIF' => $this->issuerNif,
                    'NombreRazon' => $this->issuerName,
                ],
            ],
            'aeat_fields' => $fields,
        ];
    }

    private function originalIdentity(array $original, array $invoice): array
    {
        try {
            $identity = (new RecordFactory())->identity($original);
        } catch (\Throwable) {
            throw SifException::conflict('Original AEAT snapshot has an invalid invoice identity');
        }

        $expectedNumber = trim((string) ($invoice['NUM_VISIBLE'] ?? ''));
        if ($expectedNumber === '' || !hash_equals($expectedNumber, (string) $identity['NumSerieFactura'])) {
            throw SifException::conflict('Original AEAT identity does not match the SIF invoice');
        }

        return $identity;
    }

    private function correctedDetail(array $originalDetail, array $payload): array
    {
        if (!array_key_exists('BaseImponibleOimporteNoSujeto', $originalDetail)) {
            throw SifException::conflict('Original AEAT breakdown has no rectifiable base');
        }

        foreach (['TipoRecargoEquivalencia', 'CuotaRecargoEquivalencia'] as $unsupported) {
            if (array_key_exists($unsupported, $originalDetail)) {
                throw SifException::conflict(
                    'Equivalent surcharge rectification requires a dedicated AEAT fiscal mapping'
                );
            }
        }

        $totals = $payload['totals'] ?? null;
        if (!is_array($totals)) {
            throw SifException::validation('Rectification totals are required for AEAT mapping');
        }

        $detail = $originalDetail;
        $detail['BaseImponibleOimporteNoSujeto'] = $this->money(
            $totals['taxable_base'] ?? null,
            'rectification taxable base'
        );

        $rectificationRate = $this->money($totals['iva_pct'] ?? '0.00', 'rectification IVA percentage');
        $rectificationQuota = $this->money($totals['iva_import'] ?? '0.00', 'rectification IVA amount');

        if (array_key_exists('TipoImpositivo', $originalDetail)) {
            $originalRate = $this->money($originalDetail['TipoImpositivo'], 'original AEAT tax rate');
            if (!hash_equals($originalRate, $rectificationRate)) {
                throw SifException::conflict(
                    'Changing the AEAT tax rate requires an explicit UC-74 fiscal mapping'
                );
            }
            $detail['TipoImpositivo'] = $rectificationRate;
        } elseif ($rectificationRate !== '0.00') {
            throw SifException::conflict(
                'Rectification tax rate is incompatible with the original AEAT breakdown'
            );
        }

        if (array_key_exists('CuotaRepercutida', $originalDetail)) {
            $detail['CuotaRepercutida'] = $rectificationQuota;
        } elseif ($rectificationQuota !== '0.00') {
            throw SifException::conflict(
                'Rectification tax amount is incompatible with the original AEAT breakdown'
            );
        }

        return $detail;
    }

    private function originalAmounts(array $details): array
    {
        $base = 0;
        $quota = 0;
        $surcharge = 0;
        $hasSurcharge = false;

        foreach ($details as $detail) {
            if (!is_array($detail) || !array_key_exists('BaseImponibleOimporteNoSujeto', $detail)) {
                throw SifException::conflict('Original AEAT breakdown cannot determine substituted base');
            }

            $base += $this->cents($detail['BaseImponibleOimporteNoSujeto'], 'original AEAT base');
            if (array_key_exists('CuotaRepercutida', $detail)) {
                $quota += $this->cents($detail['CuotaRepercutida'], 'original AEAT quota');
            }
            if (array_key_exists('CuotaRecargoEquivalencia', $detail)) {
                $hasSurcharge = true;
                $surcharge += $this->cents(
                    $detail['CuotaRecargoEquivalencia'],
                    'original AEAT equivalent surcharge'
                );
            }
        }

        $amounts = [
            'BaseRectificada' => $this->fromCents($base),
            'CuotaRectificada' => $this->fromCents($quota),
        ];
        if ($hasSurcharge) {
            $amounts['CuotaRecargoRectificado'] = $this->fromCents($surcharge);
        }

        return $amounts;
    }

    private function serverSystemInformation(): array
    {
        if ($this->issuerNif === '' || $this->issuerNif === 'G00000000' || $this->issuerName === '') {
            throw new \RuntimeException(
                'Configured non-placeholder SIF issuer is required for official AEAT rectifications'
            );
        }

        $systemName = trim((string) ($this->systemInformation['system_name'] ?? ''));
        $systemId = trim((string) ($this->systemInformation['system_id'] ?? ''));
        $systemVersion = trim((string) ($this->systemInformation['system_version'] ?? ''));
        $installationId = trim((string) ($this->systemInformation['installation_id'] ?? ''));

        if ($systemName === '' || $systemId === '' || $systemVersion === '' || $installationId === '') {
            throw new \RuntimeException(
                'Complete server-side SIF identity is required for official AEAT rectifications'
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

    private function description(array $line): string
    {
        $concept = trim((string) ($line['concept'] ?? ''));
        $detail = trim((string) ($line['detail'] ?? ''));
        $description = trim($concept . ($detail === '' ? '' : ' - ' . $detail));

        if ($description === '') {
            throw SifException::validation('Rectification operation description is required for AEAT');
        }

        return mb_substr($description, 0, 500, 'UTF-8');
    }

    private function decodePayload(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            throw SifException::conflict('Original fiscal record payload is empty');
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw SifException::conflict('Original fiscal record payload is malformed');
        }

        if (!is_array($decoded)) {
            throw SifException::conflict('Original fiscal record payload is malformed');
        }

        return $decoded;
    }

    private function money(mixed $value, string $label): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid ' . $label);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function cents(mixed $value, string $label): int
    {
        return (int) round(((float) $this->money($value, $label)) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function fromCents(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }
}
