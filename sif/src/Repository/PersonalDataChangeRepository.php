<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class PersonalDataChangeRepository
{
    public function findByRequestId(\PDO $db, string $requestId): ?array
    {
        $stmt = $db->prepare(
            'SELECT UUID_PERSONAL_CHANGE_REQUEST, SUBJECT_KEY, REQUESTER_ACTOR_ID,
                    CHANGESET_JSON, JUSTIFICATION, STATUS, PROPAGATION_STATUS,
                    PROPAGATION_RESULT_JSON, CORRELATION_ID, REQUESTED_AT, COMPLETED_AT
             FROM personal_data_change_request
             WHERE UUID_PERSONAL_CHANGE_REQUEST = ?
             LIMIT 1'
        );
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['CHANGESET'] = json_decode((string) $row['CHANGESET_JSON'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['CHANGESET_JSON']);

        if ($row['PROPAGATION_RESULT_JSON'] !== null) {
            $row['PROPAGATION_RESULT'] = json_decode(
                (string) $row['PROPAGATION_RESULT_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } else {
            $row['PROPAGATION_RESULT'] = null;
        }
        unset($row['PROPAGATION_RESULT_JSON']);

        return $row;
    }

    public function create(\PDO $db, array $request): void
    {
        foreach ([
            'request_id',
            'subject_key',
            'requester_actor_id',
            'changeset',
            'status',
            'correlation_id',
            'requested_at',
        ] as $field) {
            if (!array_key_exists($field, $request)) {
                throw SifException::validation('Missing personal data change field: ' . $field);
            }
        }

        if (!is_array($request['changeset']) || $request['changeset'] === []) {
            throw SifException::validation('Personal data changeset cannot be empty');
        }

        $changeset = json_encode(
            $request['changeset'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $stmt = $db->prepare(
            'INSERT INTO personal_data_change_request (
                UUID_PERSONAL_CHANGE_REQUEST, SUBJECT_KEY, REQUESTER_ACTOR_ID,
                CHANGESET_JSON, JUSTIFICATION, STATUS, PROPAGATION_STATUS,
                CORRELATION_ID, REQUESTED_AT
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) $request['request_id']),
            trim((string) $request['subject_key']),
            trim((string) $request['requester_actor_id']),
            $changeset,
            $request['justification'] ?? null,
            strtoupper(trim((string) $request['status'])),
            $request['propagation_status'] ?? null,
            trim((string) $request['correlation_id']),
            trim((string) $request['requested_at']),
        ]);
    }
}
