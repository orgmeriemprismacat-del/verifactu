<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\PersonalDataChangeRepository;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class PersonalDataChangeRepositoryTest
{
    public function testStoresAndReadsStructuredPersonalDataChangeRequest(): void
    {
        $db = TestDatabase::fresh();
        $repository = new PersonalDataChangeRepository();
        $requestId = '11111111-1111-4111-8111-111111111111';

        $repository->create($db, [
            'request_id' => $requestId,
            'subject_key' => 'INSC:42',
            'requester_actor_id' => 'operator-1',
            'changeset' => [
                'correu' => [
                    'from' => 'old@example.test',
                    'to' => 'new@example.test',
                ],
            ],
            'justification' => 'Correcció de contacte',
            'status' => 'REQUESTED',
            'propagation_status' => 'PENDING',
            'correlation_id' => 'corr-42',
            'requested_at' => '2026-09-29 10:00:00',
        ]);

        $stored = $repository->findByRequestId($db, $requestId);

        Assert::same($requestId, $stored['UUID_PERSONAL_CHANGE_REQUEST']);
        Assert::same('INSC:42', $stored['SUBJECT_KEY']);
        Assert::same('operator-1', $stored['REQUESTER_ACTOR_ID']);
        Assert::same('new@example.test', $stored['CHANGESET']['correu']['to']);
        Assert::same('REQUESTED', $stored['STATUS']);
        Assert::same('PENDING', $stored['PROPAGATION_STATUS']);
        Assert::same('corr-42', $stored['CORRELATION_ID']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM personal_data_change_request')->fetchColumn());
    }
}
