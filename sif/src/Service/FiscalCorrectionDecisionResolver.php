<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalCorrectionDecisionRepository;

final class FiscalCorrectionDecisionResolver
{
    public function __construct(
        private FiscalCorrectionDecisionRepository $decisions,
        private FiscalCorrectionDecisionGuard $guard,
        private ?RectificationDecisionFingerprint $correctionFingerprints = null
    ) {
        $this->correctionFingerprints ??= new RectificationDecisionFingerprint();
    }

    public function resolve(
        \PDO $db,
        string $decisionEventUuid,
        string $uuidFactura,
        array $input
    ): array {
        $decisionEventUuid = trim($decisionEventUuid);
        $uuidFactura = trim($uuidFactura);

        if ($decisionEventUuid === '') {
            throw SifException::validation('UC-74 classification event UUID is required');
        }
        if ($uuidFactura === '') {
            throw SifException::validation('SIF invoice UUID is required for UC-74 classification');
        }

        $event = $this->decisions->findApprovedByUuid($db, $decisionEventUuid);
        if ($event === null) {
            throw SifException::validation('UC-74 classification event was not found');
        }

        if (
            strtoupper(trim((string) ($event['ACTION'] ?? ''))) !== 'FISCAL_CORRECTION_CLASSIFIED'
            || strtoupper(trim((string) ($event['RESULT'] ?? ''))) !== 'SUCCEEDED'
            || strtoupper(trim((string) ($event['RESOURCE_TYPE'] ?? ''))) !== 'FACTURA'
        ) {
            throw SifException::conflict('Referenced audit event is not an approved UC-74 classification');
        }

        $resourceId = trim((string) ($event['RESOURCE_ID'] ?? ''));
        if ($resourceId === '' || !hash_equals($uuidFactura, $resourceId)) {
            throw SifException::conflict('UC-74 classification belongs to a different invoice');
        }

        $changeset = $this->decodeChangeset($event['CHANGESET_JSON'] ?? null);
        $classification = $changeset['classification'] ?? null;
        if (!is_array($classification)) {
            throw SifException::conflict('UC-74 classification event has no immutable classification payload');
        }

        $expectedCorrectionFingerprint = strtolower(trim((string) (
            $changeset['correction_fingerprint'] ?? ''
        )));
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedCorrectionFingerprint) !== 1) {
            throw SifException::conflict(
                'UC-74 classification event has no valid correction fingerprint'
            );
        }

        $actualCorrectionFingerprint = $this->correctionFingerprints->calculate($input);
        if (!hash_equals($expectedCorrectionFingerprint, $actualCorrectionFingerprint)) {
            throw SifException::conflict(
                'Rectification request differs from the correction classified by UC-74'
            );
        }

        $resolved = $this->guard->assertRectification($classification, $input);
        $resolved['correction_fingerprint'] = $expectedCorrectionFingerprint;

        $eventReason = strtoupper(trim((string) ($event['REASON_CODE'] ?? '')));
        if ($eventReason === '' || !hash_equals($resolved['reason_code'], $eventReason)) {
            throw SifException::conflict('UC-74 classification reason does not match audit evidence');
        }

        $resolved['decision_event_uuid'] = $decisionEventUuid;
        $resolved['decision_recorded_at'] = (string) ($event['RECORDED_AT'] ?? '');

        return $resolved;
    }

    private function decodeChangeset(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            throw SifException::conflict('UC-74 classification evidence is empty');
        }

        try {
            $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw SifException::conflict('UC-74 classification evidence is malformed');
        }

        if (!is_array($decoded)) {
            throw SifException::conflict('UC-74 classification evidence is malformed');
        }

        return $decoded;
    }
}
