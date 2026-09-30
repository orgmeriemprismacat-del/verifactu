<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentCommandService
{
    public function __construct(
        private \PDO $legacyWebDb,
        private \PDO $legacyIntranetDb,
        private InvoiceBeforePaymentLegacyPreparationService $preparation,
        private ?InvoiceBeforePaymentService $issuer = null
    ) {
    }

    public function preview(
        array $inscriptionIds,
        int $entityId,
        string $actorId,
        array $context = []
    ): array {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw SifException::forbidden('Invoice-before-payment actor is required');
        }

        $context['created_by'] = $actorId;

        $prepared = $this->preparation->prepare(
            $this->legacyWebDb,
            $this->legacyIntranetDb,
            $inscriptionIds,
            $entityId,
            $context
        );

        return [
            'ok' => true,
            'action' => 'preview',
            'fingerprint' => $prepared['fingerprint'],
            'selection' => $prepared['selection'],
            'billing' => $prepared['billing'],
            'totals' => $prepared['payload']['totals'],
            'lines' => $prepared['payload']['lines'],
            'context' => $prepared['payload']['uc004_context'] ?? [],
            'payload' => $prepared['payload'],
        ];
    }

    public function confirm(
        array $inscriptionIds,
        int $entityId,
        string $actorId,
        string $expectedFingerprint,
        array $context = []
    ): array {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw SifException::forbidden('Invoice-before-payment actor is required');
        }

        $expectedFingerprint = strtolower(trim($expectedFingerprint));
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedFingerprint) !== 1) {
            throw SifException::validation('Invalid invoice-before-payment expected fingerprint');
        }

        $context['created_by'] = $actorId;

        $prepared = $this->preparation->prepare(
            $this->legacyWebDb,
            $this->legacyIntranetDb,
            $inscriptionIds,
            $entityId,
            $context
        );

        if (!hash_equals($expectedFingerprint, $prepared['fingerprint'])) {
            throw SifException::conflict(
                'Invoice-before-payment preview changed before confirmation; create a new preview'
            );
        }

        if ($this->issuer === null) {
            throw new \RuntimeException('Invoice-before-payment issuer is not configured');
        }

        $result = $this->issuer->issueBeforePayment($prepared['input']);

        $result['action'] = 'confirm';
        $result['fingerprint'] = $prepared['fingerprint'];
        $result['fingerprint_verified'] = true;
        $result['payment_registered'] = false;
        $result['selection'] = $prepared['selection'];
        $result['billing'] = $prepared['billing'];

        return $result;
    }
}
