<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class DiscountSnapshotFileReader
{
    public function read(?string $path): ?array
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (!is_file($path) || !is_readable($path)) {
            throw SifException::validation('Could not read course discount snapshot file');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw SifException::validation('Could not read course discount snapshot file');
        }

        try {
            $discount = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw SifException::validation('Invalid course discount snapshot file');
        }

        if (!is_array($discount)) {
            throw SifException::validation('Invalid course discount snapshot file');
        }

        return $discount;
    }
}
