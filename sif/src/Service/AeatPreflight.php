<?php

namespace Prisma\Sif\Service;

final class AeatPreflight
{
    public function check(array $config): array
    {
        $checks = [
            'soap_extension' => extension_loaded('soap'),
            'curl_extension' => extension_loaded('curl'),
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

        $checks['test_endpoint_allowed'] = ($config['endpoint'] ?? '') === \Prisma\Sif\Aeat\SoapTransport::TEST_ENDPOINT;
        $checks['system_id_xsd_length'] = is_string($config['system_id'] ?? null)
            && preg_match('/^[A-Za-z0-9]{1,2}$/D', $config['system_id']) === 1;
        $checks['certificate_usable'] = false;
        $checks['evidence_directory_private'] = false;
        try {
            (new \Prisma\Sif\Aeat\ClientCertificate((string) ($config['certificate_path'] ?? ''),
                (string) ($config['certificate_password'] ?? '')))->inspect();
            $checks['certificate_usable'] = true;
        } catch (\Throwable) {
            // Never return certificate content, password, paths or exception traces.
        }
        try {
            new \Prisma\Sif\Aeat\EvidenceStore((string) ($config['evidence_directory'] ?? ''));
            $checks['evidence_directory_private'] = true;
        } catch (\Throwable) {
        }
        $checks['bundled_schemas_readable'] = true;
        foreach (['SuministroLR.xsd', 'SuministroInformacion.xsd', 'RespuestaSuministro.xsd', 'xmldsig-core-schema.xsd'] as $file) {
            $checks['bundled_schemas_readable'] = $checks['bundled_schemas_readable']
                && is_readable(dirname(__DIR__, 2) . '/resources/aeat/' . $file);
        }
        $checks['bundled_schemas_integrity'] = false;
        try {
            (new \Prisma\Sif\Aeat\SchemaManifest())->verify(dirname(__DIR__, 2) . '/resources/aeat');
            $checks['bundled_schemas_integrity'] = true;
        } catch (\Throwable) {
        }
        // SOAP uses cURL and local pinned schemas. Retain legacy diagnostic keys,
        // but a remote WSDL and the PHP SOAP extension are not runtime dependencies.
        $required = array_diff_key($checks, array_flip(['soap_extension', 'wsdl_https', 'xsd_readable']));

        return [
            'ready' => !in_array(false, $required, true),
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
