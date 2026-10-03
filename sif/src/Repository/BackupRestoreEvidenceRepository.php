<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class BackupRestoreEvidenceRepository
{
    public function findByUuid(\PDO $db, string $uuid): ?array
    {
        $uuid = strtolower(trim($uuid));
        if (preg_match('/^[0-9a-f-]{36}$/D', $uuid) !== 1) {
            throw SifException::validation('Invalid backup evidence UUID');
        }

        $stmt = $db->prepare('SELECT * FROM backup_restore_evidence WHERE UUID_EVIDENCE = ? LIMIT 1');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function isAcceptable(array $evidence, string $environment): bool
    {
        $status = strtoupper(trim((string) ($evidence['STATUS'] ?? '')));
        $integrity = strtoupper(trim((string) ($evidence['INTEGRITY_RESULT'] ?? '')));
        $actualEnvironment = strtolower(trim((string) ($evidence['ENVIRONMENT'] ?? '')));

        return $actualEnvironment === strtolower(trim($environment))
            && in_array($status, ['SUCCESS', 'COMPLETED', 'VERIFIED'], true)
            && in_array($integrity, ['OK', 'PASS', 'VERIFIED'], true);
    }
}
