<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;

final class EnrollmentFundDispositionService
{
    public function __construct(private EnrollmentFundMovementRepository $movements)
    {
    }

    public function dispose(
        \PDO $db,
        int $idInsc,
        string $uuidFactura,
        string $amount,
        string $correlationId,
        string $reason,
        ?string $uuidPayment = null
    ): array {
        $target = $this->cents($amount);
        if ($idInsc <= 0 || trim($uuidFactura) === '' || $target <= 0) {
            throw SifException::validation('Invalid enrollment fund disposition');
        }

        $rows = $this->movements->movementsForEnrollment($db, $idInsc, $uuidFactura);
        $reversedByOrigin = [];
        foreach ($rows as $row) {
            if ((string) ($row['MOVEMENT_TYPE'] ?? '') !== 'REVERSAL') {
                continue;
            }
            $origin = trim((string) ($row['REVERSES_UUID_MOVEMENT'] ?? ''));
            if ($origin !== '') {
                $reversedByOrigin[$origin] = ($reversedByOrigin[$origin] ?? 0)
                    + $this->cents((string) $row['IMPORT']);
            }
        }

        $eligible = [];
        foreach ($rows as $row) {
            $type = (string) ($row['MOVEMENT_TYPE'] ?? '');
            $isInbound =
                in_array($type, ['EXTERNAL_ALLOCATION', 'COMPENSATION_ALLOCATION'], true)
                && (int) ($row['ID_INSC_DESTI'] ?? 0) === $idInsc;

            if (!$isInbound) {
                continue;
            }

            $originUuid = (string) $row['UUID_MOVEMENT'];
            $available = $this->cents((string) $row['IMPORT'])
                - ($reversedByOrigin[$originUuid] ?? 0);
            if ($available > 0) {
                $eligible[] = [$row, $available];
            }
        }

        $availableTotal = array_sum(array_map(static fn(array $item): int => $item[1], $eligible));
        if ($target > $availableTotal) {
            throw SifException::conflict('Enrollment fund disposition exceeds available attributed funds');
        }

        $remaining = $target;
        $results = [];
        $order = 1;
        foreach ($eligible as [$origin, $available]) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $available);
            $originUuid = (string) $origin['UUID_MOVEMENT'];
            $results[] = $this->movements->insertOrReuseReversal($db, [
                'idempotency_key' => sprintf(
                    'FUND|DISPOSE|%s|ORIGIN:%s|AMOUNT:%s',
                    $this->keyPart($correlationId . '|' . $reason),
                    $originUuid,
                    $this->amount($take)
                ),
                'order' => $order++,
                'reverses_uuid_movement' => $originUuid,
                'amount' => $this->amount($take),
                'uuid_payment' => $uuidPayment,
                'uuid_factura' => $uuidFactura,
                'invoice_line_id' => $origin['ID_FACTURA_LINIA'] ?? null,
                'correlation_id' => $correlationId,
                'notes' => 'UC-016B disposition: ' . strtoupper(trim($reason)),
            ]);
            $remaining -= $take;
        }

        if ($remaining !== 0) {
            throw new \RuntimeException('Enrollment fund disposition could not allocate full amount');
        }

        return [
            'id_insc' => $idInsc,
            'uuid_factura' => $uuidFactura,
            'amount' => $this->amount($target),
            'reason' => strtoupper(trim($reason)),
            'reversals' => $results,
            'remaining_balance' => $this->movements->attributedBalanceForEnrollment(
                $db,
                $idInsc,
                $uuidFactura
            ),
        ];
    }

    private function cents(string $value): int
    {
        $value = trim(str_replace(',', '.', $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw SifException::validation('Invalid enrollment fund disposition amount');
        }
        [$euros, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function keyPart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_.:-]+/', '_', trim($value)) ?? '';
        return substr($value === '' ? 'NOREF' : $value, 0, 80);
    }
}
