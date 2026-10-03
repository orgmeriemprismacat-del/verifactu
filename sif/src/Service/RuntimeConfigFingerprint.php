<?php

namespace Prisma\Sif\Service;

final class RuntimeConfigFingerprint
{
    public function hash(array $config): string
    {
        // These UC-010 fields describe how evidence is located or whether the
        // operator is currently allowed to record an activation. They are not
        // part of the executable business/fiscal configuration represented by
        // CONFIG_HASH. Git revision and artifact bytes have dedicated fields.
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

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
