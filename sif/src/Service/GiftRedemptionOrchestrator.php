<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

final class GiftRedemptionOrchestrator
{
    public function __construct(
        private GiftRedemptionTrustedContextResolver $contextResolver,
        private GiftEnrollmentStager $stager,
        private GiftRedemptionService $redemption,
        private LegacyGiftUsageReconciler $legacyReconciler,
        private ?GiftRedemptionNotificationService $notifications = null
    ) {
    }

    public function execute(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $enrollmentId,
        string $giftCode,
        string $sourceChannel,
        string $correlationId,
        string $actorId
    ): array {
        $trustedContext = $this->contextResolver->resolve(
            $sifDb,
            $legacyDb,
            $enrollmentId,
            $giftCode
        );

        $holderPartyKey = (string) $trustedContext['holder_party_key'];
        $trustedPrice = (array) $trustedContext['trusted_price_snapshot'];

        $stage = $this->stager->stage(
            $sifDb,
            $legacyDb,
            $enrollmentId,
            $giftCode,
            $holderPartyKey,
            $trustedPrice,
            $sourceChannel
        );

        $result = $this->redemption->redeem(
            $sifDb,
            [
                'code' => $giftCode,
                'holder_party_key' => $holderPartyKey,
                'destination_operation_uuid' => (string) $stage['uuid_operation'],
                'idempotency_key' => (string) $stage['redemption_idempotency_key'],
                'correlation_id' => $correlationId,
                'actor_id' => $actorId,
            ]
        );

        $legacyReconciliation = $this->legacyReconciler->reconcile(
            $legacyDb,
            $giftCode,
            $enrollmentId
        );

        $notificationOutbox = null;
        if ($this->notifications !== null) {
            $notificationOutbox = $this->notifications->enqueue(
                $sifDb,
                $legacyDb,
                $enrollmentId,
                $giftCode,
                (string) $stage['uuid_entitlement'],
                (string) $stage['uuid_operation']
            );
        }

        return [
            'stage' => $stage,
            'redemption' => $result,
            'legacy_reconciliation' => $legacyReconciliation,
            'notification_outbox' => $notificationOutbox,
        ];
    }
}
