<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class AeatInvoiceQrUrlBuilder
{
    public function __construct(
        private string $baseUrl,
        private string $specVersion = '0.5.0'
    ) {
        $this->baseUrl = rtrim(trim($this->baseUrl), '?&');
        $this->specVersion = trim($this->specVersion);

        if (
            $this->baseUrl === ''
            || filter_var($this->baseUrl, FILTER_VALIDATE_URL) === false
            || !str_starts_with(strtolower($this->baseUrl), 'https://')
            || str_contains($this->baseUrl, '?')
        ) {
            throw new \RuntimeException('AEAT invoice QR base URL is not configured safely');
        }

        if ($this->specVersion === '' || strlen($this->specVersion) > 20) {
            throw new \RuntimeException('AEAT invoice QR specification version is invalid');
        }
    }

    public function build(
        string $issuerNif,
        string $invoiceNumber,
        string|\DateTimeInterface $issuedAt,
        string|int|float $total
    ): array {
        $issuerNif = strtoupper(trim($issuerNif));
        $invoiceNumber = trim($invoiceNumber);

        if (
            strlen($issuerNif) !== 9
            || preg_match('/^[A-Z0-9]{9}$/D', $issuerNif) !== 1
        ) {
            throw SifException::validation('Invalid issuer NIF for AEAT invoice QR');
        }

        if (
            $invoiceNumber === ''
            || strlen($invoiceNumber) > 60
            || !$this->isPrintableAscii($invoiceNumber)
        ) {
            throw SifException::validation('Invalid invoice number for AEAT invoice QR');
        }

        $date = $this->date($issuedAt);
        $amount = $this->amount($total);

        $parameters = [
            'nif' => $issuerNif,
            'numserie' => $invoiceNumber,
            'fecha' => $date,
            'importe' => $amount,
        ];

        return [
            'url' => $this->baseUrl
                . '?'
                . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986),
            'parameters' => $parameters,
            'spec_version' => $this->specVersion,
            'error_correction' => 'M',
            'size_mm_min' => 30,
            'size_mm_max' => 40,
            'label' => 'QR tributario:',
            'verifactu_legend' => 'VERI*FACTU',
        ];
    }

    private function date(string|\DateTimeInterface $issuedAt): string
    {
        if ($issuedAt instanceof \DateTimeInterface) {
            return $issuedAt->format('d-m-Y');
        }

        $value = trim($issuedAt);
        if ($value === '') {
            throw SifException::validation('Invalid issue date for AEAT invoice QR');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date instanceof \DateTimeImmutable) {
            throw SifException::validation('Invalid issue date for AEAT invoice QR');
        }

        return $date->format('d-m-Y');
    }

    private function amount(string|int|float $total): string
    {
        if (!is_numeric($total)) {
            throw SifException::validation('Invalid total for AEAT invoice QR');
        }

        $normalised = number_format((float) $total, 2, '.', '');
        [$integer, $decimals] = array_pad(explode('.', $normalised, 2), 2, '');

        $integer = ltrim($integer, '+');
        if (
            $integer === ''
            || preg_match('/^-?\d{1,12}$/D', $integer) !== 1
        ) {
            throw SifException::validation('Invoice total exceeds AEAT QR format');
        }

        $canonical = rtrim(rtrim($integer . '.' . $decimals, '0'), '.');

        return $canonical === '-0' ? '0' : $canonical;
    }

    private function isPrintableAscii(string $value): bool
    {
        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $ord = ord($value[$i]);
            if ($ord < 32 || $ord > 126) {
                return false;
            }
        }

        return true;
    }
}
