<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\PersonalDataChangeRepository;
use Prisma\Sif\Repository\StudentProfileReadRepository;
use Prisma\Sif\Service\ResolvedStudentProfileAuthorizationPolicy;
use Prisma\Sif\Service\StudentProfileService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class StudentProfileServiceTest
{
    public function testViewFailsClosedWithoutResolvedEnrollmentScope(): void
    {
        [$db, $service] = $this->service();

        Assert::throws(SifException::class, static function () use ($service): void {
            $service->view(['id' => 'operator-1'], 42);
        });
    }

    public function testProposeChangeStoresOnlyRealDiffAndReusesSameRequest(): void
    {
        [$db, $service] = $this->service();
        $actor = $this->writeActor();
        $requestId = '22222222-2222-4222-8222-222222222222';

        $first = $service->proposeChange(
            $actor,
            42,
            [
                'nom' => 'Meriem',
                'correu' => 'new@example.test',
            ],
            $requestId,
            'corr-profile-42',
            'Canvi sol·licitat des de la intranet'
        );
        $second = $service->proposeChange(
            $actor,
            42,
            [
                'nom' => 'Meriem',
                'correu' => 'new@example.test',
            ],
            $requestId,
            'corr-profile-42',
            'Canvi sol·licitat des de la intranet'
        );

        Assert::same('REQUESTED', $first['result']);
        Assert::same('REUSED', $second['result']);
        Assert::same(false, array_key_exists('nom', $first['changes']));
        Assert::same('old@example.test', $first['changes']['correu']['from']);
        Assert::same('new@example.test', $first['changes']['correu']['to']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM personal_data_change_request')->fetchColumn());
    }

    public function testSameRequestWithDifferentPayloadIsConflict(): void
    {
        [, $service] = $this->service();
        $actor = $this->writeActor();
        $requestId = '33333333-3333-4333-8333-333333333333';

        $service->proposeChange(
            $actor,
            42,
            ['correu' => 'one@example.test'],
            $requestId,
            'corr-conflict-42'
        );

        Assert::throws(SifException::class, static function () use ($service, $actor, $requestId): void {
            $service->proposeChange(
                $actor,
                42,
                ['correu' => 'two@example.test'],
                $requestId,
                'corr-conflict-42'
            );
        });
    }

    public function testNoChangeDoesNotCreateRequest(): void
    {
        [$db, $service] = $this->service();

        $result = $service->proposeChange(
            $this->writeActor(),
            42,
            ['correu' => 'old@example.test'],
            '44444444-4444-4444-8444-444444444444',
            'corr-no-change-42'
        );

        Assert::same('NO_CHANGE', $result['result']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM personal_data_change_request')->fetchColumn());
    }

    public function testUnsupportedFieldIsRejectedBeforePersistence(): void
    {
        [$db, $service] = $this->service();

        Assert::throws(SifException::class, function () use ($service): void {
            $service->proposeChange(
                $this->writeActor(),
                42,
                ['pagament' => '999.00'],
                '55555555-5555-4555-8555-555555555555',
                'corr-invalid-field-42'
            );
        });

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM personal_data_change_request')->fetchColumn());
    }

    private function service(): array
    {
        $db = TestDatabase::fresh();
        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT NOT NULL PRIMARY KEY,
                NOM VARCHAR(100) NULL,
                COGNOMS VARCHAR(150) NULL,
                CORREU VARCHAR(190) NULL,
                DNI VARCHAR(30) NULL,
                TELEFON VARCHAR(30) NULL,
                ADRECA VARCHAR(255) NULL,
                Codi_Postal VARCHAR(20) NULL,
                Poblacio VARCHAR(120) NULL,
                PERFIL VARCHAR(120) NULL,
                Titulacio VARCHAR(180) NULL
            )'
        );
        $insert = $db->prepare(
            'INSERT INTO inscripcions
             (ID, NOM, COGNOMS, CORREU, DNI, TELEFON, ADRECA, Codi_Postal, Poblacio, PERFIL, Titulacio)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            42,
            'Meriem',
            'Prova',
            'old@example.test',
            '00000000T',
            '600000000',
            'Carrer Test 1',
            '08001',
            'Barcelona',
            'DOCENT',
            'GRAU',
        ]);

        $service = new StudentProfileService(
            $db,
            $db,
            new StudentProfileReadRepository(),
            new PersonalDataChangeRepository(),
            new ResolvedStudentProfileAuthorizationPolicy()
        );

        return [$db, $service];
    }

    private function writeActor(): array
    {
        return [
            'id' => 'operator-1',
            'student_scope' => [
                'enrollments' => [
                    '42' => 'WRITE',
                ],
            ],
        ];
    }
}
