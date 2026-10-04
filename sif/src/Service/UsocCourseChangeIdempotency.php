<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class UsocCourseChangeIdempotency
{
    public function key(
        string $requestId,
        string $payerRole,
        string $effect
    ): string {
        $requestId = trim($requestId);
        $role = strtoupper(trim($payerRole));
        $effect = strtoupper(trim($effect));

        if (
            $requestId === ''
            || strlen($requestId) > 120
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
        ) {
            throw SifException::validation(
                'Invalid USOC course change request id for idempotency'
            );
        }

        if (!in_array($role, ['STUDENT', 'ENTITY'], true)) {
            throw SifException::validation(
                'Invalid USOC course change payer role for idempotency'
            );
        }

        if (!in_array($effect, [
            'RECTIFY_SOURCE',
            'TARGET_INVOICE',
            'COMPENSATE',
            'EXCESS_CREDIT',
            'EXCESS_REFUND',
        ], true)) {
            throw SifException::validation(
                'Invalid USOC course change effect for idempotency'
            );
        }

        return 'USOC|COURSE_CHANGE|'
            . substr(hash('sha256', $requestId), 0, 32)
            . '|ROLE:' . $role
            . '|' . $effect;
    }
}
