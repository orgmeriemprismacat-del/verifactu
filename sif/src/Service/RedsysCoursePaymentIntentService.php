<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;

final class RedsysCoursePaymentIntentService
{
    public function __construct(
        private LegacyCourseSnapshotRepository $legacySnapshots,
        private RedsysPaymentIntentService $intents,
        private RedsysDsOrderGenerator $orders,
        private ?PrismaStudentCourseCheckoutService $prismaStudentCheckout = null,
        private ?LegacyPrismaStudentPriceSnapshotResolver $prismaStudentPrices = null
    ) {
    }

    public function create(\PDO $sifDb, \PDO $legacyDb, array $input): array
    {
        $idpag = $this->positiveInt($input['idpag'] ?? null, 'IDPAG');
        $context = $this->legacySnapshots->loadCourseContextByIdpag($legacyDb, $idpag);
        $inscription = $context['inscription'];

        $totalCents = $this->cents($inscription['A_PAGAR'] ?? null, 'A_PAGAR');
        $paidCents = $this->cents($inscription['PAGAMENT'] ?? 0, 'PAGAMENT');
        $pendingCents = max(0, $totalCents - $paidCents);

        if ($pendingCents <= 0) {
            throw SifException::conflict('Course inscription is already fully paid');
        }

        $requestedCents = array_key_exists('requested_amount', $input)
            ? $this->cents($input['requested_amount'], 'requested amount')
            : $pendingCents;

        if ($requestedCents <= 0 || $requestedCents > $pendingCents) {
            throw SifException::validation('Requested course payment amount is outside the pending balance');
        }

        $total = $this->amount($totalCents);
        $paid = $this->amount($paidCents);
        $pending = $this->amount($pendingCents);
        $requested = $this->amount($requestedCents);

        $fractional = (int) ($inscription['FRACCIONAT'] ?? 0) === 1;
        if (!$fractional && $requestedCents !== $pendingCents) {
            throw SifException::conflict('Partial payment is not enabled for this course inscription');
        }

        $dsOrder = trim((string) ($input['ds_order'] ?? ''));
        if ($dsOrder === '') {
            $dsOrder = $this->orders->generate();
        }

        if ((int) ($inscription['TIPUS_DESC'] ?? 0) === 1) {
            if ($this->prismaStudentCheckout === null || $this->prismaStudentPrices === null) {
                throw SifException::conflict('Alumne PrisMa checkout staging is not configured.');
            }
            if ((int) ($inscription['VALID_DESC'] ?? 0) !== 1) {
                throw SifException::conflict('Alumne PrisMa discount is not in a payable state.');
            }
            if ($fractional || $paidCents > 0 || $requestedCents !== $pendingCents) {
                throw SifException::conflict(
                    'Alumne PrisMa fractional or resumed payment requires an explicit fiscal checkout model.'
                );
            }

            $trustedPrice = $this->prismaStudentPrices->resolve($legacyDb, $context);
            $canonicalPartyKey = 'legacy-dni-sha256:' . hash(
                'sha256',
                strtoupper((string) preg_replace('/[\s.\-]+/u', '', trim((string) $inscription['DNI'])))
            );

            $staged = $this->prismaStudentCheckout->stageAndCreateIntent(
                $sifDb,
                $legacyDb,
                (int) $inscription['ID'],
                $canonicalPartyKey,
                $trustedPrice,
                [
                    'ds_order' => $dsOrder,
                    'terminal' => trim((string) ($input['terminal'] ?? '1')),
                    'created_by' => trim((string) ($input['created_by'] ?? 'pay-prisma-cat')),
                    'expires_at' => isset($input['expires_at']) ? trim((string) $input['expires_at']) : null,
                ]
            );

            return $staged + [
                'idpag' => $idpag,
                'source_id' => (int) $inscription['ID'],
                'amount' => $trustedPrice['net_amount'],
                'pending_before' => $pending,
                'currency' => 'EUR',
                'terminal' => trim((string) ($input['terminal'] ?? '1')),
            ];
        }

        $snapshot = $context;
        $snapshot['payment'] = [
            'idpag' => $idpag,
            'amount' => $requested,
            'pending_before' => $pending,
            'paid_before' => $paid,
            'contract_total' => $total,
            'fractional' => $fractional,
        ];

        $result = $this->intents->create($sifDb, [
            'ds_order' => $dsOrder,
            'idpag' => $idpag,
            'source_type' => 'CURS',
            'source_id' => (string) $this->positiveInt($inscription['ID'] ?? null, 'inscription.ID'),
            'expected_amount' => $requested,
            'currency' => 'EUR',
            'terminal' => trim((string) ($input['terminal'] ?? '1')),
            'snapshot' => $snapshot,
            'created_by' => trim((string) ($input['created_by'] ?? 'pay-prisma-cat')),
            'expires_at' => isset($input['expires_at']) ? trim((string) $input['expires_at']) : null,
        ]);

        return $result + [
            'idpag' => $idpag,
            'source_id' => (int) $inscription['ID'],
            'amount' => $requested,
            'pending_before' => $pending,
            'currency' => 'EUR',
            'terminal' => trim((string) ($input['terminal'] ?? '1')),
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

    private function cents(mixed $value, string $label): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation("Invalid {$label}");
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');

        return (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
