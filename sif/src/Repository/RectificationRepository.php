<?php

namespace Prisma\Sif\Repository;

final class RectificationRepository
{
    public function linkRectification(\PDO $db, string $uuidRectification, string $uuidOriginal, array $input): void
    {
        $db->prepare(
            'INSERT INTO factura_rectificacio (
                UUID_FACTURA_RECTIFICATIVA, UUID_FACTURA_RECTIFICADA, MOTIU, MODE_RECTIFICACIO, DETAILS
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE MOTIU = MOTIU'
        )->execute([
            $uuidRectification,
            $uuidOriginal,
            strtoupper(trim((string) $input['reason'])),
            strtoupper(trim((string) $input['mode'])),
            $input['details'] ?? $input['detail'] ?? null,
        ]);
    }

    public function markOriginalRectified(\PDO $db, string $uuidOriginal): void
    {
        $db->prepare('UPDATE factura SET ESTAT_FACTURA = \'RECTIFIED\' WHERE UUID_FACTURA = ?')
            ->execute([$uuidOriginal]);
    }
}
