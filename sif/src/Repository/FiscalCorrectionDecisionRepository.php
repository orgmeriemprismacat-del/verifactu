<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class FiscalCorrectionDecisionRepository
{
    public function findApprovedByUuid(\PDO $db, string $uuidEvent): ?array
    {
        $uuidEvent = trim($uuidEvent);
        if ($uuidEvent === '') {
            throw SifException::validation('Missing fiscal correction decision event UUID');
        }

        $statement = $db->prepare(
            'SELECT UUID_EVENT, ACTION, RESULT, RESOURCE_TYPE, RESOURCE_ID,
                    REASON_CODE, CHANGESET_JSON, OCCURRED_AT, RECORDED_AT
             FROM sif_audit_event
             WHERE UUID_EVENT = ?
             LIMIT 1'
        );
        $statement->execute([$uuidEvent]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
