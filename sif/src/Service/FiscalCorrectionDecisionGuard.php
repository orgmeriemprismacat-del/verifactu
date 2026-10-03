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

        return [
            'decision' => $decision,
            'source_uc' => $sourceUc,
            'reason_code' => $reasonCode,
            'policy_version' => $policyVersion,
            'rectification_mode' => $classifiedMode,
        ];
    }
}
