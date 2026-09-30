<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;

final class GiftRedemptionService
{
    public function __construct(private CommercialEntitlementRepository $entitlements)
    {
    }

    public function preview(
        \PDO $db,
        string $code,
        string $holderPartyKey,
        ?\DateTimeImmutable $now = null
    ): array {
        $hash = $this->hashCode($code);
        $holderPartyKey = $this->required($holderPartyKey, 'holder_party_key', 100);
        $entitlement = $this->entitlements->findByCodeHash($db, $hash, false);

        if ($entitlement === null) {
            throw SifException::notFound('Gift cannot be redeemed');
        }

        $this->assertEligible($entitlement, $holderPartyKey, $now);
        $origin = $this->loadPaidGiftOrigin($db, $entitlement);

        return [
            'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
            'status' => (string) $entitlement['STATUS'],
            'face_value' => $this->money($entitlement['FACE_VALUE']),
            'currency' => (string) $entitlement['CURRENCY'],
            'expires_at' => $entitlement['EXPIRES_AT'],
            'origin_operation' => (string) $origin['UUID_OPERATION'],
            'origin_payment' => (string) $origin['UUID_PAYMENT'],
        ];
    }

    public function redeem(
        \PDO $db,
        array $command,
        ?\DateTimeImmutable $now = null
    ): array {
        foreach (['code', 'holder_party_key', 'destination_operation_uuid', 'idempotency_key', 'correlation_id', 'actor_id'] as $field) {
            if (!isset($command[$field]) || trim((string) $command[$field]) === '') {
                throw SifException::validation('Missing gift redemption field: ' . $field);
            }
        }

        $codeHash = $this->hashCode((string) $command['code']);
        $holder = $this->required((string) $command['holder_party_key'], 'holder_party_key', 100);
        $destinationUuid = $this->required((string) $command['destination_operation_uuid'], 'destination_operation_uuid', 36);
        $idempotencyKey = $this->required((string) $command['idempotency_key'], 'idempotency_key', 140);
        $correlationId = $this->required((string) $command['correlation_id'], 'correlation_id', 100);
        $actorId = $this->required((string) $command['actor_id'], 'actor_id', 100);

        if ($db->inTransaction()) {
            throw new \LogicException('Gift redemption owns its transaction');
        }

        $db->beginTransaction();
        try {
            $entitlement = $this->entitlements->findByCodeHash($db, $codeHash, true);
            if ($entitlement === null) {
                throw SifException::notFound('Gift cannot be redeemed');
            }

            if (strtoupper((string) $entitlement['ENTITLEMENT_TYPE']) !== 'GIFT') {
                throw SifException::notFound('Gift cannot be redeemed');
            }

            if ((string) $entitlement['HOLDER_PARTY_KEY'] !== $holder) {
                throw SifException::notFound('Gift cannot be redeemed');
            }

            if (strtoupper((string) $entitlement['STATUS']) === 'CONSUMED') {
                if ((string) ($entitlement['CONSUMED_UUID_OPERATION'] ?? '') !== $destinationUuid) {
                    throw SifException::conflict('Gift was already redeemed for another operation');
                }
                $destination = $this->loadDestinationEnrollment($db, $destinationUuid, $holder);
                $db->commit();

                return [
                    'status' => 'CONSUMED',
                    'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
                    'uuid_operation' => $destinationUuid,
                    'enrollment_id' => (string) $destination['SOURCE_ID'],
                    'idempotency_reused' => true,
                ];
            }

            $this->assertEligible($entitlement, $holder, $now);
            $this->loadPaidGiftOrigin($db, $entitlement);
            $destination = $this->loadDestinationEnrollment($db, $destinationUuid, $holder);

            $reserve = $this->entitlements->reserve(
                $db,
                $entitlement,
                $correlationId,
                $actorId,
                $idempotencyKey,
                $now
            );

            $entitlement = $this->entitlements->findByCodeHash($db, $codeHash, true);
            if ($entitlement === null) {
                throw new \RuntimeException('Reserved gift entitlement disappeared');
            }

            $consume = $this->entitlements->consume(
                $db,
                $entitlement,
                $destinationUuid,
                $correlationId,
                $actorId,
                $idempotencyKey,
                $now
            );

            $db->commit();

            return [
                'status' => 'CONSUMED',
                'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
                'uuid_operation' => $destinationUuid,
                'enrollment_id' => (string) $destination['SOURCE_ID'],
                'idempotency_reused' => (bool) ($reserve['reused'] || $consume['reused']),
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertEligible(
        array $entitlement,
        string $holder,
        ?\DateTimeImmutable $now
    ): void {
        if (strtoupper((string) ($entitlement['ENTITLEMENT_TYPE'] ?? '')) !== 'GIFT') {
            throw SifException::notFound('Gift cannot be redeemed');
        }

        if ((string) ($entitlement['HOLDER_PARTY_KEY'] ?? '') !== $holder) {
            throw SifException::notFound('Gift cannot be redeemed');
        }

        $status = strtoupper((string) ($entitlement['STATUS'] ?? ''));
        if (!in_array($status, ['ISSUED', 'ACTIVE'], true)) {
            throw SifException::conflict('Gift is not available for redemption');
        }

        if ($this->entitlements->isExpired($entitlement, $now)) {
            throw SifException::conflict('Gift is not available for redemption');
        }

        if ($this->money($entitlement['FACE_VALUE'] ?? null) === '0.00') {
            throw SifException::conflict('Gift has no redeemable value');
        }
    }

    private function loadPaidGiftOrigin(\PDO $db, array $entitlement): array
    {
        $originUuid = trim((string) ($entitlement['ORIGIN_UUID_OPERATION'] ?? ''));
        if ($originUuid === '') {
            throw SifException::conflict('Gift has no traceable purchase origin');
        }

        $stmt = $db->prepare(
            "SELECT UUID_OPERATION, SOURCE_TYPE, STATUS, UUID_FACTURA, UUID_PAYMENT, NET_AMOUNT, CURRENCY
             FROM commercial_operation WHERE UUID_OPERATION = ?"
        );
        $stmt->execute([$originUuid]);
        $origin = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($origin)
            || strtoupper((string) $origin['SOURCE_TYPE']) !== 'REGAL'
            || !in_array(strtoupper((string) $origin['STATUS']), ['PAID', 'INVOICED', 'COMPLETED'], true)
            || trim((string) ($origin['UUID_FACTURA'] ?? '')) === ''
            || trim((string) ($origin['UUID_PAYMENT'] ?? '')) === ''
        ) {
            throw SifException::conflict('Gift purchase origin is not fully reconciled');
        }

        $payment = $db->prepare(
            "SELECT TIPUS_MOVIMENT, IMPORT, ESTAT FROM payment_transaction WHERE UUID_PAYMENT = ?"
        );
        $payment->execute([$origin['UUID_PAYMENT']]);
        $row = $payment->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)
            || (string) $row['TIPUS_MOVIMENT'] !== 'CHARGE'
            || (string) $row['ESTAT'] !== 'CONFIRMED'
            || $this->money($row['IMPORT']) !== $this->money($entitlement['FACE_VALUE'])
        ) {
            throw SifException::conflict('Gift purchase payment does not back the entitlement value');
        }

        return $origin;
    }

    private function loadDestinationEnrollment(\PDO $db, string $uuidOperation, string $holder): array
    {
        $stmt = $db->prepare(
            "SELECT o.UUID_OPERATION, o.OPERATION_TYPE, o.SOURCE_TYPE, o.SOURCE_ID, o.STATUS
             FROM commercial_operation o
             WHERE o.UUID_OPERATION = ? FOR UPDATE"
        );
        $stmt->execute([$uuidOperation]);
        $operation = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($operation)
            || strtoupper((string) $operation['OPERATION_TYPE']) !== 'ENROLLMENT'
            || strtoupper((string) $operation['SOURCE_TYPE']) !== 'INSCRIPCIO'
            || trim((string) ($operation['SOURCE_ID'] ?? '')) === ''
            || !in_array(strtoupper((string) $operation['STATUS']), ['RESERVED', 'CONFIRMED', 'COMPLETED'], true)
        ) {
            throw SifException::conflict('Destination enrollment operation is not materialized');
        }

        $party = $db->prepare(
            "SELECT PARTY_KEY FROM commercial_operation_party
             WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT'"
        );
        $party->execute([$uuidOperation]);
        $participants = $party->fetchAll(\PDO::FETCH_ASSOC);
        if (count($participants) !== 1 || (string) $participants[0]['PARTY_KEY'] !== $holder) {
            throw SifException::conflict('Destination enrollment does not belong to gift holder');
        }

        return $operation;
    }

    private function hashCode(string $code): string
    {
        $code = trim($code);
        if ($code === '' || strlen($code) > 200) {
            throw SifException::validation('Invalid gift code');
        }

        return hash('sha256', $code);
    }

    private function required(string $value, string $field, int $max): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $max) {
            throw SifException::validation('Invalid gift redemption field: ' . $field);
        }
        return $value;
    }

    private function money(mixed $value): string
    {
        $raw = trim((string) $value);
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid gift monetary amount');
        }
        [$euros, $decimal] = array_pad(explode('.', $raw, 2), 2, '');
        return $euros . '.' . str_pad($decimal, 2, '0');
    }
}
