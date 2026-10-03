<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * Read-only UC-111 projection for the intranet student detail.
 *
 * It never exposes the redeemable token. The UI sees the entitlement state,
 * monetary ledger and where the promotion was reserved/applied.
 */
final class NovicePromotionStudentSummaryService
{
    public function byIdentity(\PDO $db, string $identity): array
    {
        $identity = $this->normalizeIdentity($identity);
        if ($identity === '') {
            throw SifException::validation('Student identity is required.');
        }

        $rights = $this->many(
            $db,
            "SELECT DISTINCT
                    e.UUID_ENTITLEMENT,
                    e.STATUS AS ENTITLEMENT_STATUS,
                    e.ISSUED_AT,
                    e.EXPIRES_AT,
                    g.ORIGINAL_CASH_AMOUNT,
                    g.AVAILABLE_AMOUNT,
                    op.SOURCE_ID AS ORIGIN_ENROLLMENT_ID,
                    op.PRODUCT_CODE AS ORIGIN_PRODUCT_CODE,
                    op.PRODUCT_EDITION AS ORIGIN_PRODUCT_EDITION,
                    o.STATUS AS DELIVERY_STATUS
             FROM commercial_operation_party p
             JOIN novice_promotion_grant g ON g.HOLDER_PARTY_KEY = p.PARTY_KEY
             JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = g.UUID_ENTITLEMENT
             JOIN commercial_operation op ON op.UUID_OPERATION = g.ORIGIN_UUID_OPERATION
             JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
             LEFT JOIN novice_promotion_code_outbox o ON o.UUID_ENTITLEMENT = g.UUID_ENTITLEMENT
             WHERE p.PARTY_ROLE = 'PARTICIPANT'
               AND UPPER(REPLACE(REPLACE(REPLACE(p.NIF_CIF, ' ', ''), '-', ''), '.', '')) = ?
             ORDER BY e.ISSUED_AT DESC, e.UUID_ENTITLEMENT",
            [$identity]
        );

        $result = [];
        foreach ($rights as $right) {
            $uuid = (string) $right['UUID_ENTITLEMENT'];
            $applications = $this->many(
                $db,
                "SELECT a.STATUS, a.AMOUNT,
                        a.RESERVED_AT, a.RESERVATION_EXPIRES_AT,
                        a.APPLIED_AT, a.RELEASED_AT, a.REVERSED_AT,
                        a.UUID_DESTINATION_FACTURA,
                        op.SOURCE_ID AS DESTINATION_ENROLLMENT_ID,
                        op.PRODUCT_CODE AS DESTINATION_PRODUCT_CODE,
                        op.PRODUCT_EDITION AS DESTINATION_PRODUCT_EDITION,
                        f.NUM_VISIBLE AS DESTINATION_INVOICE_NUMBER
                 FROM novice_promotion_application a
                 JOIN commercial_operation op
                   ON op.UUID_OPERATION = a.UUID_DESTINATION_OPERATION
                 LEFT JOIN factura f
                   ON f.UUID_FACTURA = a.UUID_DESTINATION_FACTURA
                 WHERE a.UUID_ENTITLEMENT = ?
                 ORDER BY a.CREATED_AT, a.UUID_APPLICATION",
                [$uuid]
            );

            $applied = 0;
            $reserved = 0;
            foreach ($applications as $application) {
                $amount = $this->cents((string) $application['AMOUNT']);
                if ((string) $application['STATUS'] === 'APPLIED') {
                    $applied += $amount;
                } elseif ((string) $application['STATUS'] === 'RESERVED') {
                    $reserved += $amount;
                }
            }

            $original = $this->cents((string) $right['ORIGINAL_CASH_AMOUNT']);
            $available = $this->cents((string) $right['AVAILABLE_AMOUNT']);

            $result[] = [
                'display_status' => $this->displayStatus(
                    (string) $right['ENTITLEMENT_STATUS'],
                    (string) ($right['DELIVERY_STATUS'] ?? ''),
                    $original,
                    $available,
                    $applied,
                    $reserved
                ),
                'delivery_status' => $right['DELIVERY_STATUS'] === null ? null : (string) $right['DELIVERY_STATUS'],
                'original_amount' => $this->money($original),
                'applied_amount' => $this->money($applied),
                'reserved_amount' => $this->money($reserved),
                'available_amount' => $this->money($available),
                'issued_at' => (string) $right['ISSUED_AT'],
                'expires_at' => (string) $right['EXPIRES_AT'],
                'origin' => [
                    'enrollment_id' => (string) $right['ORIGIN_ENROLLMENT_ID'],
                    'product_code' => (string) $right['ORIGIN_PRODUCT_CODE'],
                    'product_edition' => (string) $right['ORIGIN_PRODUCT_EDITION'],
                ],
                'applications' => array_map(
                    static fn (array $application): array => [
                        'status' => (string) $application['STATUS'],
                        'amount' => (string) $application['AMOUNT'],
                        'destination_enrollment_id' => (string) $application['DESTINATION_ENROLLMENT_ID'],
                        'destination_product_code' => (string) $application['DESTINATION_PRODUCT_CODE'],
                        'destination_product_edition' => (string) $application['DESTINATION_PRODUCT_EDITION'],
                        'invoice_number' => $application['DESTINATION_INVOICE_NUMBER'] === null
                            ? null
                            : (string) $application['DESTINATION_INVOICE_NUMBER'],
                        'reserved_at' => (string) $application['RESERVED_AT'],
                        'applied_at' => $application['APPLIED_AT'] === null ? null : (string) $application['APPLIED_AT'],
                        'released_at' => $application['RELEASED_AT'] === null ? null : (string) $application['RELEASED_AT'],
                        'reversed_at' => $application['REVERSED_AT'] === null ? null : (string) $application['REVERSED_AT'],
                    ],
                    $applications
                ),
            ];
        }

        return ['rights' => $result];
    }

    private function displayStatus(
        string $entitlementStatus,
        string $deliveryStatus,
        int $original,
        int $available,
        int $applied,
        int $reserved
    ): string {
        if ($entitlementStatus === 'CANCELLED') {
            return 'CANCELLED';
        }
        if ($entitlementStatus === 'EXPIRED') {
            return 'EXPIRED';
        }
        if ($original > 0 && $available === 0 && $reserved === 0 && $applied >= $original) {
            return 'EXHAUSTED';
        }
        if ($applied > 0) {
            return 'PARTIALLY_USED';
        }
        if ($reserved > 0) {
            return 'PARTIALLY_RESERVED';
        }
        if ($deliveryStatus === 'SENT') {
            return 'DELIVERED';
        }
        if (in_array($deliveryStatus, ['PREPARED', 'SENDING', 'FAILED'], true)) {
            return 'CODE_PREPARED';
        }
        return $entitlementStatus === 'ACTIVE' ? 'ACTIVE' : 'GRANTED';
    }

    private function normalizeIdentity(string $identity): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]+/u', '', trim($identity)));
    }

    private function cents(string $value): int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($value), $match)) {
            throw SifException::validation('Invalid monetary value in novice promotion ledger.');
        }

        return ((int) $match[1] * 100) + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function many(\PDO $db, string $sql, array $parameters): array
    {
        $statement = $db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
