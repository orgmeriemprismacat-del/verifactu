<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class RedsysSignatureValidator
{
    public function __construct(private string $merchantKey)
    {
    }

    public function decodeAndVerify(array $request, array $context = []): array
    {
        $signatureVersion = $this->field($request, 'Ds_SignatureVersion');
        if ($signatureVersion !== 'HMAC_SHA256_V1') {
            throw SifException::validation('Unsupported Redsys signature version');
        }

        $merchantParameters = $this->field($request, 'Ds_MerchantParameters');
        $receivedSignature = $this->field($request, 'Ds_Signature');

        if ($merchantParameters === null || $merchantParameters === '') {
            throw SifException::validation('Missing Redsys merchant parameters');
        }

        if ($receivedSignature === null || $receivedSignature === '') {
            throw SifException::validation('Missing Redsys signature');
        }

        $decoded = $this->decodeMerchantParameters($merchantParameters);
        $expectedSignature = $this->createNotificationSignature($merchantParameters, $decoded);

        if (!hash_equals($this->normalizeSignature($expectedSignature), $this->normalizeSignature($receivedSignature))) {
            throw SifException::validation('Invalid Redsys signature');
        }

        return $this->toSifPayload($decoded, $request, $merchantParameters, $signatureVersion);
    }

    private function decodeMerchantParameters(string $merchantParameters): array
    {
        $base64 = strtr($merchantParameters, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $json = base64_decode($base64, true);

        if ($json === false) {
            throw SifException::validation('Invalid Redsys merchant parameters');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw SifException::validation('Invalid Redsys merchant parameters JSON');
        }

        return $decoded;
    }

    private function createNotificationSignature(string $merchantParameters, array $decoded): string
    {
        $key = base64_decode($this->merchantKey, true);
        if ($key === false || $key === '') {
            throw SifException::validation('Missing Redsys merchant key');
        }

        $order = $this->field($decoded, 'Ds_Order');
        if ($order === null || $order === '') {
            throw SifException::validation('Missing Redsys order');
        }

        $derivedKey = $this->encrypt3DesZeroPadded($order, $key);
        $mac = hash_hmac('sha256', $merchantParameters, $derivedKey, true);

        return strtr(base64_encode($mac), '+/', '-_');
    }

    private function encrypt3DesZeroPadded(string $message, string $key): string
    {
        $remainder = strlen($message) % 8;
        if ($remainder !== 0) {
            $message .= str_repeat("\0", 8 - $remainder);
        }

        $encrypted = openssl_encrypt(
            $message,
            'des-ede3-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            str_repeat("\0", 8)
        );

        if ($encrypted === false) {
            throw SifException::validation('Could not verify Redsys signature');
        }

        return $encrypted;
    }

    private function toSifPayload(
        array $decoded,
        array $request,
        string $merchantParameters,
        string $signatureVersion
    ): array
    {
        $order = $this->field($decoded, 'Ds_Order');
        $amount = $this->field($decoded, 'Ds_Amount');
        $responseCode = $this->field($decoded, 'Ds_Response');
        $currencyCode = $this->field($decoded, 'Ds_Currency');
        if ($currencyCode !== '978') {
            throw SifException::validation('Unsupported Redsys currency');
        }

        $terminal = $this->field($decoded, 'Ds_Terminal');
        if ($terminal === null || trim($terminal) === '') {
            throw SifException::validation('Missing Redsys terminal');
        }

        return [
            'ds_order' => $order,
            'amount' => $this->normalizeAmount($amount),
            'response_code' => $responseCode,
            'currency_code' => $currencyCode,
            'currency' => 'EUR',
            'terminal' => $terminal,
            'signature_version' => $signatureVersion,
            'payload_hash' => hash('sha256', $merchantParameters),
            'redsys' => [
                'signature_version' => $signatureVersion,
                'merchant_parameters' => $merchantParameters,
                'decoded' => $decoded,
            ],
        ];
    }

    private function normalizeAmount(?string $amount): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            throw SifException::validation('Invalid Redsys amount');
        }

        if (str_contains($amount, '.') || str_contains($amount, ',')) {
            return number_format((float) str_replace(',', '.', $amount), 2, '.', '');
        }

        return number_format(((int) $amount) / 100, 2, '.', '');
    }

    private function normalizeSignature(string $signature): string
    {
        return rtrim(str_replace(' ', '+', trim($signature)), '=');
    }

    private function field(array $values, string $name): ?string
    {
        $upper = strtoupper($name);

        foreach ($values as $key => $value) {
            if (strtoupper((string) $key) !== $upper) {
                continue;
            }

            if ($value === null) {
                return null;
            }

            return (string) $value;
        }

        return null;
    }
}
