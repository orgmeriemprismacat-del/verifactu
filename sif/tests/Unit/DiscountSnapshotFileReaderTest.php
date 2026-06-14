<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\DiscountSnapshotFileReader;
use Prisma\Sif\Tests\Support\Assert;

final class DiscountSnapshotFileReaderTest
{
    public function testReturnsNullWhenNoDiscountFileIsProvided(): void
    {
        Assert::same(null, (new DiscountSnapshotFileReader())->read(null));
        Assert::same(null, (new DiscountSnapshotFileReader())->read(''));
    }

    public function testReadsDiscountSnapshotJsonFile(): void
    {
        $path = $this->writeTempFile(json_encode([
            'origin' => 'CODI_PROMO',
            'mode' => 'PERCENT',
            'code' => 'MACABODETITULAR#700',
            'pct' => '25.00',
            'amount' => '30.00',
            'base' => '120.00',
        ], JSON_THROW_ON_ERROR));

        try {
            $snapshot = (new DiscountSnapshotFileReader())->read($path);
        } finally {
            unlink($path);
        }

        Assert::same('CODI_PROMO', $snapshot['origin']);
        Assert::same('MACABODETITULAR#700', $snapshot['code']);
        Assert::same('30.00', $snapshot['amount']);
    }

    public function testRejectsMissingDiscountSnapshotFile(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new DiscountSnapshotFileReader())->read(__DIR__ . '/missing-discount.json');
        }, 422);
    }

    public function testRejectsInvalidDiscountSnapshotJson(): void
    {
        $path = $this->writeTempFile('{invalid');

        try {
            Assert::throws(SifException::class, function () use ($path): void {
                (new DiscountSnapshotFileReader())->read($path);
            }, 422);
        } finally {
            unlink($path);
        }
    }

    public function testRejectsNonObjectDiscountSnapshotJson(): void
    {
        $path = $this->writeTempFile('"not-an-object"');

        try {
            Assert::throws(SifException::class, function () use ($path): void {
                (new DiscountSnapshotFileReader())->read($path);
            }, 422);
        } finally {
            unlink($path);
        }
    }

    private function writeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sif-discount-');
        if ($path === false) {
            Assert::fail('Could not create temporary discount file');
        }

        if (file_put_contents($path, $contents) === false) {
            Assert::fail('Could not write temporary discount file');
        }

        return $path;
    }
}
