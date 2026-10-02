<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackEnrollmentIdempotencyBoundaryTest
{
    public function testServerUsesRequestLockFingerprintAndAtomicPackInsert(): void
    {
        $root = dirname(__DIR__, 3);
        $endpoint = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php'
        );
        if (!is_string($endpoint)) {
            Assert::fail('Could not load PACK enrollment endpoint');
        }

        foreach ([
            "$_POST['requestId']",
            'PACK_REQUEST|',
            'PACK_REQUEST_HASH|',
            'GET_LOCK(?, 10)',
            'LOCATE(?, OBSERVACIONS)',
            'begin_transaction()',
            '->commit()',
            '->rollback()',
            'hash_equals(',
            'count($existingRequestRows) !== count($edicions)',
            'releaseIdPag()',
        ] as $needle) {
            Assert::stringContainsString($needle, $endpoint);
        }

        Assert::stringContainsString(
            "if (!$stmt->execute())",
            $endpoint
        );
        Assert::stringContainsString(
            "if (!$stmt2->execute())",
            $endpoint
        );
    }

    public function testBrowserReusesRequestIdAndBlocksConcurrentSubmit(): void
    {
        $root = dirname(__DIR__, 3);
        $js = file_get_contents(
            $root . '/codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js'
        );
        if (!is_string($js)) {
            Assert::fail('Could not load PACK enrollment JavaScript');
        }

        foreach ([
            'packEnrollmentSubmitting = false',
            'packEnrollmentRequestId',
            'getPackEnrollmentRequestId()',
            'clearPackEnrollmentRequestId()',
            'window.sessionStorage.getItem',
            'window.sessionStorage.setItem',
            'window.sessionStorage.removeItem',
            'window.crypto.randomUUID',
            'if (packEnrollmentSubmitting)',
            'requestId: getPackEnrollmentRequestId()',
        ] as $needle) {
            Assert::stringContainsString($needle, $js);
        }
    }
}
