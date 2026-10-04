<?php

namespace Prisma\Sif\Service;

final class GroupParticipantChangeFingerprint
{
    public function calculate(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    private function canonicalJson(array $value): string
    {
        $normalized = $this->normalize($value);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
        );
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
