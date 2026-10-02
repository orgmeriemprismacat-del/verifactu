<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PrivateDocumentWriter;
use Prisma\Sif\Tests\Support\Assert;

final class PrivateDocumentWriterTest
{
    public function testWritesVerifiesAndReusesImmutablePrivateBytes(): void
    {
        $root = $this->temporaryDirectory();

        try {
            $writer = new PrivateDocumentWriter($root, 1024 * 1024);
            $contents = '%PDF-1.4 immutable-test';
            $key = 'factures/test/pdf/document.pdf';

            $first = $writer->writeVerified($key, $contents);
            $second = $writer->writeVerified($key, $contents);

            Assert::same(false, $first['reused']);
            Assert::same(true, $second['reused']);
            Assert::same(hash('sha256', $contents), $first['hash']);
            Assert::same($first['hash'], $second['hash']);
            Assert::same(strlen($contents), $first['size']);

            $stored = file_get_contents($root . '/factures/test/pdf/document.pdf');
            Assert::same($contents, $stored);

            Assert::throws(SifException::class, function () use ($writer, $key): void {
                $writer->writeVerified($key, '%PDF-1.4 different');
            }, 409);
        } finally {
            $this->removeDirectory($root);
        }
    }

    public function testRejectsTraversalStorageKey(): void
    {
        $root = $this->temporaryDirectory();

        try {
            $writer = new PrivateDocumentWriter($root);

            Assert::throws(SifException::class, function () use ($writer): void {
                $writer->writeVerified('../outside.pdf', '%PDF-1.4 test');
            }, 422);
        } finally {
            $this->removeDirectory($root);
        }
    }

    private function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'sif-document-writer-'
            . bin2hex(random_bytes(8));

        if (!mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Could not create test document directory');
        }

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $candidate = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($candidate) && !is_link($candidate)) {
                $this->removeDirectory($candidate);
            } else {
                @unlink($candidate);
            }
        }

        @rmdir($path);
    }
}
