<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackEnrollmentAtomicityBoundaryTest
{
    public function testPackEnrollmentPersistsAllComponentsInsideOneTransaction(): void
    {
        $root = dirname(__DIR__, 3);
        $endpointPath = $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';
        $connectionPath = $root . '/codi-drive/web-actual/ConnexioBBDD_PreparedStatment.php';

        $endpoint = file_get_contents($endpointPath);
        $connection = file_get_contents($connectionPath);
        if (!is_string($endpoint) || !is_string($connection)) {
            Assert::fail('Could not load PACK enrollment atomicity boundary');
        }

        Assert::stringContainsString('function beginTransaction()', $connection);
        Assert::stringContainsString('function commitTransaction()', $connection);
        Assert::stringContainsString('function rollbackTransaction()', $connection);

        $reserve = strpos($endpoint, '$connexio->reserveIdPag()');
        $begin = strpos($endpoint, '$connexio->beginTransaction()');
        $insert = strpos($endpoint, 'if (!$stmt->execute())');
        $lastInsert = strpos($endpoint, '$idInserit = $connexio->lastInsertId()');
        $commit = strpos($endpoint, '$connexio->commitTransaction()');
        $release = strpos($endpoint, '$connexio->releaseIdPag()');

        Assert::same(true, $reserve !== false);
        Assert::same(true, $begin !== false);
        Assert::same(true, $insert !== false);
        Assert::same(true, $lastInsert !== false);
        Assert::same(true, $commit !== false);
        Assert::same(true, $release !== false);

        Assert::same(true, $reserve < $begin);
        Assert::same(true, $begin < $insert);
        Assert::same(true, $insert < $lastInsert);
        Assert::same(true, $lastInsert < $commit);
        Assert::same(true, $commit < $release);
    }

    public function testPackEnrollmentRollsBackAndReleasesIdpagOnFailure(): void
    {
        $path = dirname(__DIR__, 3)
            . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';

        $source = file_get_contents($path);
        if (!is_string($source)) {
            Assert::fail('Could not load PACK enrollment endpoint');
        }

        Assert::stringContainsString('$idPagReserved = false;', $source);
        Assert::stringContainsString('$packTransactionStarted = false;', $source);
        Assert::stringContainsString('$idPagReserved = true;', $source);
        Assert::stringContainsString('$packTransactionStarted = true;', $source);
        Assert::stringContainsString('catch(Exception $e)', $source);
        Assert::stringContainsString('finally {', $source);
        Assert::stringContainsString('$connexio->rollbackTransaction();', $source);
        Assert::stringContainsString('$connexio->releaseIdPag();', $source);

        $finally = strrpos($source, 'finally {');
        $rollback = strrpos($source, '$connexio->rollbackTransaction();');
        $release = strrpos($source, '$connexio->releaseIdPag();');

        Assert::same(true, $finally !== false);
        Assert::same(true, $rollback !== false);
        Assert::same(true, $release !== false);
        Assert::same(true, $finally < $rollback);
        Assert::same(true, $rollback < $release);
    }
}
