<?php

namespace Prisma\Sif\Service;

final class AeatPreflight
{
    public function check(array $config): array
    {
        $checks = [
            'soap_extension' => extension_loaded('soap'),
            'dom_extension' => extension_loaded('dom'),
            'openssl_extension' => extension_loaded('openssl'),
            'wsdl_https' => $this->isHttpsUrl($config['wsdl'] ?? null),
            'endpoint_https' => $this->isHttpsUrl($config['endpoint'] ?? null),
            'xsd_readable' => $this->isReadableFile($config['xsd_path'] ?? null),
            'certificate_readable' => $this->isReadableFile($config['certificate_path'] ?? null),
            'certificate_password_present' => $this->hasValue($config['certificate_password'] ?? null),
            'issuer_nif_present' => $this->hasValue($config['issuer_nif'] ?? null),
            'system_id_present' => $this->hasValue($config['system_id'] ?? null),
            'system_version_present' => $this->hasValue($config['system_version'] ?? null),
            'installation_id_present' => $this->hasValue($config['installation_id'] ?? null),
        ];

        return [
            'ready' => !in_array(false, $checks, true),
            'checks' => $checks,
        ];
    }

    private function isHttpsUrl(mixed $value): bool
    {
        return is_string($value)
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https';
    }

    private function isReadableFile(mixed $value): bool
    {
        return is_string($value) && $value !== '' && is_file($value) && is_readable($value);
    }

    private function hasValue(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
