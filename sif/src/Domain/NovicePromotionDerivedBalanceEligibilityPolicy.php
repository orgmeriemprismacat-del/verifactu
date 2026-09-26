<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure eligibility rules for a cancellation-derived promotional balance.
 * No DB/authentication side effects: HOLDER_PARTY_KEY must already come from
 * an authenticated account, and the caller must separately verify the JASOM
 * root payment and fiscal lineage.
 */
final class NovicePromotionDerivedBalanceEligibilityPolicy
{
    public function assertReservable(
        array $balance,
        string $authenticatedPartyKey,
        \DateTimeImmutable $nowUtc,
        \DateTimeImmutable $reservationExpiresUtc
    ): void {
        $now = $nowUtc->setTimezone(new \DateTimeZone('UTC'));
        $reserveUntil = $reservationExpiresUtc->setTimezone(new \DateTimeZone('UTC'));
        $issued = $this->utc((string) ($balance['ISSUED_AT'] ?? ''));
        $expires = $this->utc((string) ($balance['EXPIRES_AT'] ?? ''));

        if (($balance['STATUS'] ?? null) !== 'ACTIVE'
            || (string) ($balance['HOLDER_PARTY_KEY'] ?? '') !== $authenticatedPartyKey
            || $authenticatedPartyKey === ''
            || $issued === null || $expires === null
            || $issued > $now || $expires <= $now
            || $reserveUntil <= $now || $reserveUntil > $expires
            || $this->cents((string) ($balance['AVAILABLE_PROMOTIONAL_AMOUNT'] ?? '')) <= 0
        ) {
            throw new \InvalidArgumentException('Derived promotional balance is not reservable.');
        }
    }

    public function assertConfirmable(
        array $balance,
        array $application,
        string $authenticatedPartyKey,
        \DateTimeImmutable $nowUtc
    ): void {
        $now = $nowUtc->setTimezone(new \DateTimeZone('UTC'));
        $expires = $this->utc((string) ($balance['EXPIRES_AT'] ?? ''));
        $reservationExpires = $this->utc((string) ($application['RESERVATION_EXPIRES_AT'] ?? ''));

        if (($balance['STATUS'] ?? null) !== 'ACTIVE'
            || (string) ($balance['HOLDER_PARTY_KEY'] ?? '') !== $authenticatedPartyKey
            || $authenticatedPartyKey === ''
            || $expires === null || $expires <= $now
            || ($application['STATUS'] ?? null) !== 'RESERVED'
            || (string) ($application['UUID_DERIVED_BALANCE'] ?? '')
                !== (string) ($balance['UUID_DERIVED_BALANCE'] ?? '')
            || $reservationExpires === null || $reservationExpires <= $now
        ) {
            throw new \InvalidArgumentException('Derived promotional reservation is not confirmable.');
        }
    }

    private function utc(string $value): ?\DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $value) !== 1) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new \DateTimeZone('UTC')
        );
        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match) !== 1) {
            throw new \InvalidArgumentException('Invalid derived promotional amount.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }
}
