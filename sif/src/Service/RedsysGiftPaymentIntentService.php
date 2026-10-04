<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;

final class RedsysGiftPaymentIntentService
{
    public function __construct(
        private LegacyGiftSnapshotRepository $legacySnapshots,
        private RedsysPaymentIntentService $intents,
        private RedsysDsOrderGenerator $orders
    ) {
    }

    public function create(\PDO $sifDb, \PDO $legacyDb, array $input): array
    {
        $giftCode = trim((string) ($input['gift_code'] ?? ''));
        if ($giftCode === '' || strlen($giftCode) > 200) {
            throw SifException::validation('Invalid gift code');
        }

        $terminal = trim((string) ($input['terminal'] ?? ''));
        if (!preg_match('/^[0-9]{1,3}$/D', $terminal)) {
            throw SifException::validation('Invalid Redsys gift terminal');
        }

        $snapshot = $this->legacySnapshots->loadByCode($legacyDb, $giftCode);
        $gift = $snapshot['gift'] ?? null;
        if (!is_array($gift)) {
            throw SifException::validation('Invalid legacy gift snapshot');
        }

        $giftId = $this->positiveInt($gift['ID'] ?? null, 'gift.ID');
        $amount = $this->money($gift['IMPORT'] ?? null, 'gift.IMPORT');
        $canonicalCode = trim((string) ($gift['CODI'] ?? ''));
        if ($canonicalCode === '' || !hash_equals($canonicalCode, $giftCode)) {
            throw SifException::conflict('Gift code does not match authoritative legacy snapshot');
        }

        $factRel = $gift['FACT_REL'] ?? null;
        if ($factRel !== null && $factRel !== '' && is_numeric($factRel) && (int) $factRel > 0) {
            throw SifException::conflict('Gift purchase is already invoiced');
        }

        $dsOrder = trim((string) ($input['ds_order'] ?? ''));
        if ($dsOrder === '') {
            $dsOrder = $this->orders->generate();
        }
        if (!preg_match('/^[0-9]{4}[A-Za-z0-9]{0,8}$/D', $dsOrder)) {
            throw SifException::validation(
                'Redsys gift DS_ORDER must be 4-12 alphanumeric characters and start with four digits'
            );
        }

        $result = $this->intents->create($sifDb, [
            'ds_order' => $dsOrder,
            'idpag' => null,
            'source_type' => 'REGAL',
            'source_id' => (string) $giftId,
            'expected_amount' => $amount,
            'currency' => 'EUR',
            'terminal' => $terminal,
            'snapshot' => $snapshot,
            'created_by' => trim((string) ($input['created_by'] ?? 'pay-prisma-cat')),
            'expires_at' => isset($input['expires_at']) ? trim((string) $input['expires_at']) : null,
        ]);

        return $result + [
            'gift_id' => $giftId,
            'amount' => $amount,
            'currency' => 'EUR',
            'terminal' => $terminal,
        ];
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw < 1) {
            throw SifException::validation("Invalid {$label}");
        }

        return (int) $raw;
    }

    private function money(mixed $value, string $label): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation("Invalid {$label}");
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
        if ($cents <= 0) {
            throw SifException::validation("Invalid {$label}");
        }

        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
