<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\StudentProfileAuthorizationPolicyInterface;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\PersonalDataChangeRepository;
use Prisma\Sif\Repository\StudentProfileReadRepository;

final class StudentProfileService
{
    private const ALLOWED_FIELDS = [
        'nom' => 'NOM',
        'cognoms' => 'COGNOMS',
        'correu' => 'CORREU',
        'dni' => 'DNI',
        'telefon' => 'TELEFON',
        'adreca' => 'ADRECA',
        'codi_postal' => 'CODI_POSTAL',
        'poblacio' => 'POBLACIO',
        'perfil' => 'PERFIL',
        'titulacio' => 'TITULACIO',
    ];

    public function __construct(
        private \PDO $legacyDb,
        private \PDO $sifDb,
        private StudentProfileReadRepository $profiles,
        private PersonalDataChangeRepository $changes,
        private StudentProfileAuthorizationPolicyInterface $authorization
    ) {
    }

    public function view(array $actor, int $idInsc): array
    {
        if ($idInsc <= 0) {
            throw SifException::validation('Enrollment ID must be positive');
        }

        $profile = $this->profiles->findByEnrollmentId($this->legacyDb, $idInsc);
        if ($profile === null) {
            throw SifException::notFound('Student enrollment not found');
        }

        if (!$this->authorization->canView($actor, $profile)) {
            throw SifException::forbidden('Student profile access denied');
        }

        return [
            'ok' => true,
            'profile' => $profile,
        ];
    }

    public function proposeChange(
        array $actor,
        int $idInsc,
        array $changes,
        string $requestId,
        string $correlationId,
        ?string $justification = null
    ): array {
        $requestId = trim($requestId);
        $correlationId = trim($correlationId);
        $actorId = trim((string) ($actor['id'] ?? ''));

        if ($requestId === '' || $correlationId === '' || $actorId === '') {
            throw SifException::validation('Actor, request ID and correlation ID are required');
        }

        $view = $this->view($actor, $idInsc);
        $profile = $view['profile'];
        $normalized = $this->normalizeChanges($profile, $changes);

        if (!$this->authorization->canChange($actor, $profile, $normalized)) {
            throw SifException::forbidden('Student profile change denied');
        }

        $existing = $this->changes->findByRequestId($this->sifDb, $requestId);
        if ($existing !== null) {
            $expectedSubject = 'INSC:' . $idInsc;
            if (
                (string) $existing['SUBJECT_KEY'] !== $expectedSubject
                || (string) $existing['REQUESTER_ACTOR_ID'] !== $actorId
                || $this->canonicalize($existing['CHANGESET']) !== $this->canonicalize($normalized)
                || (string) $existing['CORRELATION_ID'] !== $correlationId
            ) {
                throw SifException::conflict('Request ID already used with different student profile payload');
            }

            return [
                'ok' => true,
                'result' => 'REUSED',
                'request_id' => $requestId,
                'subject_key' => $expectedSubject,
                'changes' => $existing['CHANGESET'],
                'status' => $existing['STATUS'],
            ];
        }

        if ($normalized === []) {
            return [
                'ok' => true,
                'result' => 'NO_CHANGE',
                'request_id' => $requestId,
                'subject_key' => 'INSC:' . $idInsc,
                'changes' => [],
                'status' => 'NO_CHANGE',
            ];
        }

        $requestedAt = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s');

        $this->changes->create($this->sifDb, [
            'request_id' => $requestId,
            'subject_key' => 'INSC:' . $idInsc,
            'requester_actor_id' => $actorId,
            'changeset' => $normalized,
            'justification' => $justification,
            'status' => 'REQUESTED',
            'propagation_status' => 'PENDING',
            'correlation_id' => $correlationId,
            'requested_at' => $requestedAt,
        ]);

        return [
            'ok' => true,
            'result' => 'REQUESTED',
            'request_id' => $requestId,
            'subject_key' => 'INSC:' . $idInsc,
            'changes' => $normalized,
            'status' => 'REQUESTED',
        ];
    }


    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    private function normalizeChanges(array $profile, array $changes): array
    {
        if ($changes === []) {
            return [];
        }

        $normalized = [];
        foreach ($changes as $field => $value) {
            if (!is_string($field) || !array_key_exists($field, self::ALLOWED_FIELDS)) {
                throw SifException::validation('Unsupported student profile field: ' . (string) $field);
            }
            if (!is_scalar($value) && $value !== null) {
                throw SifException::validation('Student profile values must be scalar or null');
            }

            $currentField = self::ALLOWED_FIELDS[$field];
            $current = $profile[$currentField] ?? null;
            $newValue = $value === null ? null : trim((string) $value);
            $currentValue = $current === null ? null : trim((string) $current);

            if ($newValue !== $currentValue) {
                $normalized[$field] = [
                    'from' => $currentValue,
                    'to' => $newValue,
                ];
            }
        }

        ksort($normalized);
        return $normalized;
    }
}
