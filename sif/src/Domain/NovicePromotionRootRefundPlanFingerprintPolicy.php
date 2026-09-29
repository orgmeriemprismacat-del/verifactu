<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Canonical immutable representation of a UC-111 root-refund plan.
 * Used both when freezing a review and immediately before execution so any
 * lineage drift changes PLAN_HASH and fails closed.
 */
final class NovicePromotionRootRefundPlanFingerprintPolicy
{
    public function fingerprint(array $plan): array
    {
        $cancel = $plan['cancel_available'] ?? [];
        $recover = $plan['recover_active_applications'] ?? [];
        if (!is_array($cancel) || !is_array($recover)
            || !isset($plan['root']) || !is_array($plan['root'])
        ) {
            throw new \InvalidArgumentException('Root refund plan has invalid structure.');
        }

        usort($cancel, static fn (array $a, array $b): int
            => strcmp((string) ($a['right_id'] ?? ''), (string) ($b['right_id'] ?? '')));
        usort($recover, static fn (array $a, array $b): int
            => strcmp(
                (string) ($a['application_id'] ?? ''),
                (string) ($b['application_id'] ?? '')
            ));

        foreach ($cancel as $entry) {
            $this->assertEntry($entry, 'right_id');
        }
        foreach ($recover as $entry) {
            $this->assertEntry($entry, 'application_id');
        }

        $canonical = [
            'root_uuid_entitlement' => (string) ($plan['root']['uuid_entitlement'] ?? ''),
            'holder_party_key' => (string) ($plan['root']['holder_party_key'] ?? ''),
            'origin_uuid_operation' => (string) ($plan['root']['origin_uuid_operation'] ?? ''),
            'cancel_available' => $cancel,
            'recover_active_applications' => $recover,
            'total_cancel_available' => (string) ($plan['total_cancel_available'] ?? ''),
            'total_recover_active' => (string) ($plan['total_recover_active'] ?? ''),
        ];
        if ($canonical['root_uuid_entitlement'] === ''
            || $canonical['holder_party_key'] === ''
            || $canonical['origin_uuid_operation'] === ''
        ) {
            throw new \InvalidArgumentException('Root refund plan lacks root identity.');
        }
        $this->cents($canonical['total_cancel_available']);
        $this->cents($canonical['total_recover_active']);

        $json = json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        return [
            'canonical' => $canonical,
            'json' => $json,
            'hash' => hash('sha256', $json),
        ];
    }

    private function assertEntry(array $entry, string $idKey): void
    {
        if (trim((string) ($entry[$idKey] ?? '')) === '') {
            throw new \InvalidArgumentException('Root refund plan item lacks logical identifier.');
        }
        $this->cents((string) ($entry['amount'] ?? ''));
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $m) !== 1) {
            throw new \InvalidArgumentException('Root refund plan contains invalid monetary amount.');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
}
