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

        $total = $this->money($inscription['A_PAGAR'] ?? null, 'A_PAGAR');
        $paid = $this->money($inscription['PAGAMENT'] ?? 0, 'PAGAMENT');
        $pending = $this->money(max(0.0, (float) $total - (float) $paid), 'pending amount');

        if ((float) $pending <= 0.0) {
            throw SifException::conflict('Course inscription is already fully paid');
        }

        $requested = array_key_exists('requested_amount', $input)
            ? $this->money($input['requested_amount'], 'requested amount')
            : $pending;

        if ((float) $requested <= 0.0 || (float) $requested - (float) $pending > 0.009) {
            throw SifException::validation('Requested course payment amount is outside the pending balance');
        }

        $fractional = (int) ($inscription['FRACCIONAT'] ?? 0) === 1;
        if (!$fractional && $requested !== $pending) {
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
            if ($fractional || (float) $paid > 0.0 || $requested !== $pending) {
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

    private function money(mixed $value, string $label): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation("Invalid {$label}");
        }

        return number_format((float) $value, 2, '.', '');
    }
}
