<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackEnrollmentIdempotencyBoundaryTest
{
    public function testPublicPackEnrollmentPersistsRequestIdAndPayloadHash(): void
    {
        $root = dirname(__DIR__, 3);
        $endpoint = file_get_contents($root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php');
        $connection = file_get_contents($root . '/codi-drive/web-actual/ConnexioBBDD_PreparedStatment.php');

        if (!is_string($endpoint) || !is_string($connection)) {
            Assert::fail('Could not load PACK idempotency boundary');
        }

        Assert::stringContainsString('function uc015PackRequestHash(array $request)', $endpoint);
        Assert::stringContainsString("hash('sha256', \$json)", $endpoint);
        Assert::stringContainsString("REQUEST_ID|%s REQUEST_HASH_V|1 REQUEST_HASH|%s", $endpoint);
        Assert::stringContainsString('function reserveNamedLock($lockName', $connection);
        Assert::stringContainsString('function releaseNamedLock($lockName)', $connection);
        Assert::stringContainsString('$connexio->reserveNamedLock($packRequestLockName);', $endpoint);
        Assert::stringContainsString('$connexio->releaseNamedLock($packRequestLockName);', $endpoint);
    }

    public function testSameRequestIsResolvedBeforeCreatingAnotherIdpag(): void
    {
        $path = dirname(__DIR__, 3)
            . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';

        $source = file_get_contents($path);
        if (!is_string($source)) {
            Assert::fail('Could not load PACK enrollment endpoint');
        }

        $requestLock = strpos($source, '$connexio->reserveNamedLock($packRequestLockName)');
        $existingLookup = strpos($source, "OBSERVACIONS LIKE ?");
        $sameHash = strpos($source, 'hash_equals($existingHash, $requestHash)');
        $replay = strpos($source, 'uc015PackConfirmationHash($existingId, $keyEncr)');
        $idpag = strpos($source, '$connexio->reserveIdPag()');

        Assert::same(true, $requestLock !== false);
        Assert::same(true, $existingLookup !== false);
        Assert::same(true, $sameHash !== false);
        Assert::same(true, $replay !== false);
        Assert::same(true, $idpag !== false);

        Assert::same(true, $requestLock < $existingLookup);
        Assert::same(true, $existingLookup < $sameHash);
        Assert::same(true, $sameHash < $replay);
        Assert::same(true, $replay < $idpag);

        Assert::stringContainsString(
            "Error: REQUEST_ID PACK reutilitzat amb un payload diferent",
            $source
        );
        Assert::stringContainsString('http_response_code(409);', $source);
    }

    public function testBrowserReusesRequestIdAfterUncertainNetworkFailure(): void
    {
        $root = dirname(__DIR__, 3);
        $js = file_get_contents($root . '/codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js');
        $page = file_get_contents($root . '/codi-drive/web-actual/pagina_inscripcio_pack.php');

        if (!is_string($js) || !is_string($page)) {
            Assert::fail('Could not load PACK browser idempotency boundary');
        }

        Assert::stringContainsString('crypto.randomUUID', $js);
        Assert::stringContainsString('sessionStorage.getItem(clauRequestIdPack())', $js);
        Assert::stringContainsString('sessionStorage.setItem(clauRequestIdPack(), inscripcioPackRequestId)', $js);
        Assert::stringContainsString('requestId: requestId', $js);
        Assert::stringContainsString('if (jqXHRMailing.status === 422)', $js);
        Assert::stringContainsString('netejarRequestIdPack();', $js);
        Assert::stringContainsString('mostrarInscripcioPack.min.js?ver=7.4', $page);

        $fail = strpos($js, 'sendInscr.fail(function');
        $networkComment = strpos($js, 'En error de xarxa/5xx es conserva REQUEST_ID', $fail);
        Assert::same(true, $fail !== false);
        Assert::same(true, $networkComment !== false);
    }

    public function testRequestIdIsValidatedAsUuidV4BeforeMutation(): void
    {
        $path = dirname(__DIR__, 3)
            . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';

        $source = file_get_contents($path);
        if (!is_string($source)) {
            Assert::fail('Could not load PACK enrollment endpoint');
        }

        Assert::stringContainsString("http_response_code(422);", $source);
        Assert::stringContainsString("identificador de petició PACK invàlid", $source);

        $validation = strpos($source, "preg_match('/^[a-f0-9]{8}-");
        $connection = strpos($source, '$connexio = new ConnexioBBDDSTMT()');

        Assert::same(true, $validation !== false);
        Assert::same(true, $connection !== false);
        Assert::same(true, $validation < $connection);
    }
}
