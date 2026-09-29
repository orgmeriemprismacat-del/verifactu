<?php

namespace Prisma\Sif\Aeat;

/** Read-only consistency verification. Never interprets a hash as AEAT acceptance. */
final class EvidenceVerifier
{
    public function verify(string $directory, string $id): array
    {
        if (!preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{24}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid evidence identifier.');
        }
        $root = realpath($directory);
        $attempt = $root === false ? false : realpath($root . '/' . $id);
        if ($root === false || $attempt === false || !is_dir($attempt)
            || is_link($root . '/' . $id) || dirname($attempt) !== $root) {
            throw new \RuntimeException('Evidence attempt is missing or outside the selected directory.');
        }
        $errors = [];
        $hashes = [];
        foreach (['request', 'response'] as $kind) {
            $xml = $attempt . '/' . $kind . '.xml';
            $json = $attempt . '/' . $kind . '.json';
            $hasXml = file_exists($xml) || is_link($xml);
            $hasJson = file_exists($json) || is_link($json);
            if (!$hasXml && !$hasJson && $kind === 'response') {
                continue;
            }
            if (!$hasXml || !$hasJson) {
                $errors[] = strtoupper($kind) . '_PAIR_INCOMPLETE';
                continue;
            }
            try {
                $this->assertFile($xml, $attempt);
                $this->assertFile($json, $attempt);
                $metadata = $this->metadata($json);
                $expected = $metadata[$kind . '_sha256'] ?? null;
                $actual = hash_file('sha256', $xml);
                if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D', $expected)
                    || !hash_equals($expected, $actual)) {
                    throw new \RuntimeException('Hash mismatch.');
                }
                $hashes[$kind] = $actual;
            } catch (\Throwable) {
                $errors[] = strtoupper($kind) . '_INTEGRITY_FAILED';
            }
        }
        $failed = file_exists($attempt . '/failure.json');
        if ($failed) {
            try {
                $this->assertFile($attempt . '/failure.json', $attempt);
                $failure = $this->metadata($attempt . '/failure.json');
                if (!is_string($failure['code'] ?? null) || !is_string($failure['at_utc'] ?? null)) {
                    throw new \RuntimeException('Invalid failure metadata.');
                }
            } catch (\Throwable) {
                $errors[] = 'FAILURE_METADATA_INVALID';
            }
        }
        return ['attempt_id' => $id, 'integrity_ok' => $errors === [],
            'state' => $errors !== [] ? 'INVALID' : ($failed ? 'FAILED_ATTEMPT'
                : (isset($hashes['response']) ? 'RESPONSE_RECORDED' : 'INCOMPLETE')),
            'hashes' => $hashes, 'errors' => $errors, 'aeat_acceptance_verified' => false];
    }

    private function assertFile(string $file, string $attempt): void
    {
        $real = realpath($file);
        if ($real === false || !is_file($real) || !is_readable($real) || is_link($file)
            || dirname($real) !== $attempt) {
            throw new \RuntimeException('Invalid evidence file.');
        }
    }

    private function metadata(string $file): array
    {
        $raw = file_get_contents($file, false, null, 0, 65537);
        if ($raw === false || strlen($raw) > 65536) {
            throw new \RuntimeException('Invalid evidence metadata size.');
        }
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new \RuntimeException('Invalid evidence metadata.');
        }
        return $data;
    }
}
