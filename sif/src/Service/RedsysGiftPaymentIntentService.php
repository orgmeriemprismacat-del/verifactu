<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;

final class RedsysGiftPaymentIntentService
{
    private RedsysPaymentIntentRepository $intentRepository;

    public function __construct(
        private LegacyGiftSnapshotRepository $legacySnapshots,
        private RedsysPaymentIntentService $intents,
        private RedsysDsOrderGenerator $orders,
        ?RedsysPaymentIntentRepository $intentRepository = null
    ) {
        $this->intentRepository = $intentRepository ?? new RedsysPaymentIntentRepository();
    }

    public function create(\PDO $sifDb, \PDO $legacyDb, array $input): array
    {
        $giftId = $this->positiveInt($input['gift_id'] ?? null, 'gift_id');

        $terminal = trim((string) ($input['terminal'] ?? ''));
        if (!preg_match('/^[0-9]{1,3}$/D', $terminal)) {
            throw SifException::validation('Invalid Redsys gift terminal');
        }

        $lockName = 'uc017_gift_intent_' . $giftId;
        $this->acquireLock($sifDb, $lockName);

        try {
            $snapshot = $this->legacySnapshots->loadById($legacyDb, $giftId);
            $gift = $snapshot['gift'] ?? null;
            if (!is_array($gift)) {
                throw SifException::validation('Invalid legacy gift snapshot');
            }

            $snapshotGiftId = $this->positiveInt($gift['ID'] ?? null, 'gift.ID');
            if ($snapshotGiftId !== $giftId) {
                throw SifException::conflict('Gift ID does not match authoritative legacy snapshot');
            }
            $amount = $this->money($gift['IMPORT'] ?? null, 'gift.IMPORT');
            if (trim((string) ($gift['CODI'] ?? '')) === '') {
                throw SifException::conflict('Authoritative legacy gift code is missing');
            }

            $factRel = $gift['FACT_REL'] ?? null;
            if ($factRel !== null && $factRel !== '' && is_numeric($factRel) && (int) $factRel > 0) {
                throw SifException::conflict('Gift purchase is already invoiced');
            }

            if ($this->intentRepository->hasValidatedNotificationForSource(
                $sifDb,
                'REGAL',
                (string) $giftId
            )) {
                throw SifException::conflict(
                    'Gift purchase already has a validated Redsys payment and must not be charged again'
                );
            }

            $pending = $this->intentRepository->findUnnotifiedBySource(
                $sifDb,
                'REGAL',
                (string) $giftId
            );
            if (count($pending) > 1) {
                throw SifException::conflict(
                    'Gift purchase has multiple pending Redsys intents and requires reconciliation'
                );
            }

            $requestedOrder = trim((string) ($input['ds_order'] ?? ''));
            if ($pending !== []) {
                $existingOrder = trim((string) ($pending[0]['DS_ORDER'] ?? ''));
                if ($existingOrder === '') {
                    throw SifException::conflict('Pending gift payment intent has no DS_ORDER');
                }
                if ($requestedOrder !== '' && !hash_equals($existingOrder, $requestedOrder)) {
                    throw SifException::conflict(
                        'Gift purchase already has another pending Redsys intent'
                    );
                }
                $dsOrder = $existingOrder;
            } else {
                $dsOrder = $requestedOrder !== '' ? $requestedOrder : $this->orders->generate();
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
        } finally {
            $this->releaseLock($sifDb, $lockName);
        }
    }

    private function acquireLock(\PDO $db, string $lockName): void
    {
        $stmt = $db->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute([$lockName]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw SifException::conflict('Could not serialize gift payment intent creation');
        }
    }

    private function releaseLock(\PDO $db, string $lockName): void
    {
        try {
            $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockName]);
        } catch (\Throwable) {
            // Session-scoped MySQL locks are released when the connection closes.
        }
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
