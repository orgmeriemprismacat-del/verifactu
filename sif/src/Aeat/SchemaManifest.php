<?php

namespace Prisma\Sif\Aeat;

/** Checks package consistency, not the authenticity of a replaced package/manifest. */
final class SchemaManifest
{
    public const FILES = ['SuministroLR.xsd', 'SuministroInformacion.xsd',
        'RespuestaSuministro.xsd', 'SistemaFacturacion.wsdl', 'xmldsig-core-schema.xsd'];

    public function verify(string $directory): void
    {
        $raw = @file_get_contents($directory . '/manifest.json');
        if ($raw === false) {
            throw new \RuntimeException('Missing AEAT schema manifest.');
        }
        try {
            $manifest = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('Invalid AEAT schema manifest.');
        }
        $entries = $manifest['files'] ?? null;
        if (!is_array($entries) || count($entries) !== count(self::FILES)) {
            throw new \RuntimeException('Incomplete AEAT schema manifest.');
        }
        $seen = [];
        foreach ($entries as $entry) {
            $name = $entry['file'] ?? null;
            $hash = $entry['sha256'] ?? null;
            if (!is_string($name) || !in_array($name, self::FILES, true) || isset($seen[$name])
                || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                throw new \RuntimeException('Invalid AEAT manifest entry.');
            }
            $actual = @hash_file('sha256', $directory . '/' . $name);
            if ($actual === false || !hash_equals($hash, $actual)) {
                throw new \RuntimeException('AEAT schema integrity mismatch: ' . $name);
            }
            $seen[$name] = true;
        }
    }
}
