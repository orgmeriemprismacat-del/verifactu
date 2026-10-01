<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;

/**
 * Resolves every authoritative UC-018 value inside the SIF boundary.
 *
 * The signed web caller is allowed to identify only the committed enrollment
 * and prove possession of the gift code. It cannot declare the canonical
 * participant nor any price/tax amount.
 */
final class GiftRedemptionTrustedContextResolver
{
    public const PRICE_RULE_VERSION = 'GIFT_REDEMPTION_EXACT_V1';

    public function __construct(private CommercialEntitlementRepository $entitlements)
    {
    }

    public function resolve(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $enrollmentId,
        string $giftCode
    ): array {
        $giftCode = trim($giftCode);
        if ($enrollmentId <= 0 || $giftCode === '' || strlen($giftCode) > 200) {
            throw SifException::validation('Invalid gift redemption lookup');
        }

        $enrollment = $this->one(
            $legacyDb,
            'SELECT ID, ANY, MES, CURS, DNI, A_PAGAR, FACTURA_RELACIONADA,
                    pag_observacions, OBSERVACIONS
             FROM inscripcions WHERE ID = ?',
            [$enrollmentId]
        );
        if ($enrollment === null) {
            throw SifException::conflict('Committed gift enrollment was not found');
        }

        $gift = $this->one(
            $legacyDb,
            'SELECT ID, CODI, IMPORT, FACT_REL, USAT, CCURS
             FROM regal WHERE CODI = ?',
            [$giftCode]
        );
        if ($gift === null) {
            throw SifException::conflict('Gift was not found');
        }

        $this->assertLegacyContract($enrollment, $gift, $enrollmentId, $giftCode);

        $identity = $this->normalisedIdentity((string) ($enrollment['DNI'] ?? ''));
        if ($identity === '') {
            throw SifException::conflict('Committed participant identity is incomplete');
        }
        $partyKey = 'person:id:' . hash('sha256', $identity);

        $codeHash = hash('sha256', $giftCode);
        $entitlement = $this->entitlements->findByCodeHash($sifDb, $codeHash, false);
        if ($entitlement === null
            || strtoupper((string) ($entitlement['ENTITLEMENT_TYPE'] ?? '')) !== 'GIFT'
        ) {
            throw SifException::conflict('Active SIF gift entitlement was not found');
        }

        $status = strtoupper((string) ($entitlement['STATUS'] ?? ''));
        if (!in_array($status, ['ISSUED', 'ACTIVE', 'RESERVED', 'CONSUMED'], true)) {
            throw SifException::conflict('Gift entitlement cannot be redeemed');
        }

        $holder = trim((string) ($entitlement['HOLDER_PARTY_KEY'] ?? ''));
        $unclaimed = CommercialEntitlementRepository::unclaimedGiftHolderKey($codeHash);
        if ($holder !== $partyKey && $holder !== $unclaimed) {
            throw SifException::conflict(
                'Gift entitlement belongs to another canonical participant'
            );
        }

        $giftAmount = $this->money($gift['IMPORT'] ?? null, 'legacy gift amount');
        $faceValue = $this->money(
            $entitlement['FACE_VALUE'] ?? null,
            'gift entitlement face value'
        );
        if ($giftAmount !== $faceValue) {
            throw SifException::conflict(
                'Legacy gift amount does not match SIF entitlement value'
            );
        }

        $productCode = strtoupper(trim((string) ($enrollment['CURS'] ?? '')));
        $giftProduct = strtoupper(trim((string) ($gift['CCURS'] ?? '')));
        if ($productCode === ''
            || ($giftProduct !== '' && $giftProduct !== $productCode)
        ) {
            throw SifException::conflict(
                'Committed gift product does not match the purchased gift'
            );
        }

        $year = trim((string) ($enrollment['ANY'] ?? ''));
        $month = trim((string) ($enrollment['MES'] ?? ''));
        if ($year === '' || $month === '') {
            throw SifException::conflict('Committed gift edition is incomplete');
        }

        $currency = strtoupper(trim((string) ($entitlement['CURRENCY'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw SifException::conflict('Gift entitlement currency is invalid');
        }

        return [
            'holder_party_key' => $partyKey,
            'trusted_price_snapshot' => [
                'product_code' => $productCode,
                'product_edition' => $year . '/' . $month,
                'gross_amount' => $faceValue,
                'currency' => $currency,
                'price_rule_version' => self::PRICE_RULE_VERSION,
                'tax_snapshot' => [
                    'regime' => 'NON_BILLABLE',
                    'taxable_base' => '0.00',
                    'tax' => '0.00',
                    'reason' => 'GIFT_REDEMPTION_NON_BILLABLE',
                ],
            ],
            'entitlement' => [
                'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
                'status' => $status,
                'holder_state' => $holder === $unclaimed ? 'UNCLAIMED' : 'CLAIMED',
            ],
        ];
    }

    private function assertLegacyContract(
        array $enrollment,
        array $gift,
        int $enrollmentId,
        string $giftCode
    ): void {
        if ((int) ($enrollment['ID'] ?? 0) !== $enrollmentId
            || trim((string) ($gift['CODI'] ?? '')) !== $giftCode
            || $this->moneyAllowZero($enrollment['A_PAGAR'] ?? null, 'A_PAGAR') !== '0.00'
            || trim((string) ($enrollment['pag_observacions'] ?? '')) !== $giftCode
            || (string) ($enrollment['FACTURA_RELACIONADA'] ?? '')
                !== (string) ($gift['FACT_REL'] ?? '')
            || stripos((string) ($enrollment['OBSERVACIONS'] ?? ''), 'CURS REGAL') === false
        ) {
            throw SifException::conflict(
                'Committed enrollment does not satisfy the gift redemption contract'
            );
        }

        $usedBy = $gift['USAT'] ?? null;
        if ($usedBy !== null
            && $usedBy !== ''
            && (int) $usedBy !== 0
            && (int) $usedBy !== $enrollmentId
        ) {
            throw SifException::conflict('Gift is already linked to another enrollment');
        }
    }

    private function normalisedIdentity(string $identity): string
    {
        $identity = strtoupper(trim($identity));
        $identity = preg_replace('/[^A-Z0-9]/', '', $identity);

        return is_string($identity) ? $identity : '';
    }

    private function money(mixed $value, string $field): string
    {
        $money = $this->moneyAllowZero($value, $field);
        if ((float) $money <= 0.0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return $money;
    }

    private function moneyAllowZero(mixed $value, string $field): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid ' . $field);
        }

        $amount = number_format((float) $value, 2, '.', '');
        if ((float) $amount < 0.0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return $amount;
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
