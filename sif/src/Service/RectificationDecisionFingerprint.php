<?php

namespace Prisma\Sif\Service;

final class RectificationDecisionFingerprint
{
    public function __construct(private ?PayloadIdempotencyValidator $hashes = null)
    {
        $this->hashes ??= new PayloadIdempotencyValidator();
    }

    public function calculate(array $input): string
    {
        $candidate = $input;

        // These values are either server-owned or ignored by the UC-005 command.
        foreach ([
            'created_by',
            'user',
            'usuari',
            'aeat_header',
            'aeat_fields',
            'type',
            'tipus_factura',
        ] as $field) {
            unset($candidate[$field]);
        }

        return $this->hashes->calculateHash($candidate);
    }
}
