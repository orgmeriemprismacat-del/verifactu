<?php

namespace Prisma\Sif\Service;

final class RuntimeConfigFingerprint
{
    public function hash(array $config): string
    {
        // These UC-010 fields locate evidence or gate the operator action; they
        // do not describe the business/fiscal runtime itself.
        if (isset($config['version_governance']) && is_array($config['version_governance'])) {
            foreach (['runtime_git_revision', 'release_manifest_path', 'activation_enabled'] as $key) {
                unset($config['version_governance'][$key]);
            }
        }

        $normalized = $this->normalize($config);
        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return hash('sha256', $json);
    }

    private function normalize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return $this->secretPresence($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalize($item),
                $value
            );
        }

        ksort($value, SORT_STRING);
        foreach ($value as $itemKey => $item) {
            $value[$itemKey] = $this->normalize($item, (string) $itemKey);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower(trim($key));
        if ($key === '') {
            return false;
        }

        if (in_array($key, [
            'password',
            'secret',
            'token',
            'api_key',
            'merchant_key',
            'wrapping_key_hex',
            'private_key',
            'certificate_password',
        ], true)) {
            return true;
        }

        foreach ([
            '_password',
            '_secret',
            '_token',
            '_api_key',
            '_merchant_key',
            '_private_key',
            '_wrapping_key',
            '_wrapping_key_hex',
        ] as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function secretPresence(mixed $value): string
    {
        if ($value === null) {
            return '__SECRET_EMPTY__';
        }

        if (is_string($value)) {
            return trim($value) === '' ? '__SECRET_EMPTY__' : '__SECRET_SET__';
        }

        if (is_array($value)) {
            return $value === [] ? '__SECRET_EMPTY__' : '__SECRET_SET__';
        }

        return $value === false ? '__SECRET_EMPTY__' : '__SECRET_SET__';
    }
}
