<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class FiscalCorrectionDecisionGuard
{
    public function assertRectification(array $classification, array $input): array
    {
        $decision = strtoupper(trim((string) ($classification['decision'] ?? '')));
        if ($decision !== 'RECTIFICATION') {
            throw SifException::conflict(
                'UC-005 can only execute corrections classified as RECTIFICATION'
            );
        }

        $sourceUc = strtoupper(trim((string) ($classification['source_uc'] ?? '')));
        if ($sourceUc !== 'UC-74') {
            throw SifException::validation('Rectification requires a UC-74 classification source');
        }

        $reasonCode = strtoupper(trim((string) ($classification['reason_code'] ?? '')));
        if ($reasonCode === '' || mb_strlen($reasonCode, 'UTF-8') > 80) {
            throw SifException::validation('Invalid fiscal correction reason code');
        }

        $policyVersion = trim((string) ($classification['policy_version'] ?? ''));
        if ($policyVersion === '' || mb_strlen($policyVersion, 'UTF-8') > 40) {
            throw SifException::validation('Invalid fiscal correction policy version');
        }

        $classifiedMode = strtoupper(trim((string) ($classification['rectification_mode'] ?? '')));
        if (!in_array($classifiedMode, ['DIFERENCIES', 'SUBSTITUCIO'], true)) {
            throw SifException::validation('Invalid classified rectification mode');
        }

        $requestedMode = strtoupper(trim((string) (
            $input['mode'] ?? $input['mode_rectificacio'] ?? ''
        )));
        if ($requestedMode === '' || !hash_equals($classifiedMode, $requestedMode)) {
            throw SifException::conflict(
                'Requested rectification mode differs from the UC-74 classification'
            );
        }

        $resolved = [
            'decision' => $decision,
            'source_uc' => $sourceUc,
            'reason_code' => $reasonCode,
            'policy_version' => $policyVersion,
            'rectification_mode' => $classifiedMode,
        ];

        $decisionEventUuid = trim((string) ($classification['decision_event_uuid'] ?? ''));
        if ($decisionEventUuid !== '') {
            if (preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di',
                $decisionEventUuid
            ) !== 1) {
                throw SifException::validation('Invalid UC-74 decision event UUID');
            }

            $resolved['decision_event_uuid'] = strtolower($decisionEventUuid);
            $resolved['decision_recorded_at'] = trim((string) (
                $classification['decision_recorded_at'] ?? ''
            ));
        }

        return $resolved;
    }
}
