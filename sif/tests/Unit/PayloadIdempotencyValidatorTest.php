<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PayloadIdempotencyValidator;
use Prisma\Sif\Tests\Support\Assert;

final class PayloadIdempotencyValidatorTest
{
    public function testCanonicalAssociativeKeysHaveSameHash(): void
    {
        $validator = new PayloadIdempotencyValidator();
        $first = ['billing' => ['name' => 'A', 'nif' => '123'], 'amount' => '72.00'];
        $second = ['amount' => '72.00', 'billing' => ['nif' => '123', 'name' => 'A']];
        Assert::same($validator->calculateHash($first), $validator->calculateHash($second));
        $validator->assertMatches($second, $validator->calculateHash($first));
    }

    public function testChangingMoneyOrListOrderIsConflict(): void
    {
        $validator = new PayloadIdempotencyValidator();
        $original = ['amount' => '72.00', 'lines' => [['id' => 1], ['id' => 2]]];
        $stored = $validator->calculateHash($original);
        Assert::throws(SifException::class, fn () => $validator->assertMatches(
            ['amount' => '80.00', 'lines' => $original['lines']], $stored
        ), 409);
        Assert::throws(SifException::class, fn () => $validator->assertMatches(
            ['amount' => '72.00', 'lines' => array_reverse($original['lines'])], $stored
        ), 409);
    }

    public function testRejectsMissingHashAndMalformedPayload(): void
    {
        $validator = new PayloadIdempotencyValidator();
        Assert::throws(SifException::class, fn () => $validator->assertMatches(['a' => 1], ''), 409);
        Assert::throws(SifException::class, fn () => $validator->calculateHash(['bad' => "\xB1"]), 422);
    }

    public function testLiteralLegacyBytesHaveDifferentMeaningFromCanonicalArray(): void
    {
        $validator = new PayloadIdempotencyValidator();
        $payload = ['z' => 1, 'a' => 2];
        $legacyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        Assert::notSame($validator->calculateHash($legacyJson), $validator->calculateHash($payload));
        $validator->assertMatches($legacyJson, $validator->calculateHash($legacyJson));
    }
}
