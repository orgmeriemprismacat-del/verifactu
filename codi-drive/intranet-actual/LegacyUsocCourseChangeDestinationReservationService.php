<?php

require_once __DIR__ . '/LegacyUsocCourseChangeDestinationStoreInterface.php';

final class LegacyUsocCourseChangeDestinationReservationService
{
    public function __construct(
        private LegacyUsocCourseChangeDestinationStoreInterface $store
    ) {
    }

    public function reserve(
        string $requestId,
        int $sourceId,
        string $targetYear,
        string $targetMonth,
        string $targetCourse,
        string $targetStudentTotal
    ): array {
        $requestId = trim($requestId);
        $targetYear = trim($targetYear);
        $targetMonth = trim($targetMonth);
        $targetCourse = trim($targetCourse);
        $targetStudentTotal = $this->money($targetStudentTotal);

        if (
            $requestId === ''
            || strlen($requestId) > 120
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
            || $sourceId <= 0
            || preg_match('/^20\d{2}$/D', $targetYear) !== 1
            || $targetMonth === ''
            || strlen($targetMonth) > 16
            || $targetCourse === ''
            || strlen($targetCourse) > 64
        ) {
            throw new InvalidArgumentException(
                'Invalid USOC destination reservation request'
            );
        }

        $fingerprint = substr(
            hash('sha256', $requestId . '|SOURCE:' . $sourceId),
            0,
            32
        );
        $marker = 'SIF-USOC-CC:' . $fingerprint;
        $lock = 'sif:usoc:cc:' . $fingerprint;

        $this->store->acquire($lock);
        try {
            $source = $this->store->source($sourceId);
            $this->assertSource($source, $sourceId);

            $existing = $this->store->findByMarker($marker);
            if ($existing !== null) {
                $this->assertReservation(
                    $existing,
                    $source,
                    $targetYear,
                    $targetMonth,
                    $targetCourse,
                    $targetStudentTotal,
                    $marker
                );

                return $this->result($existing, $source, $sourceId, true);
            }

            $created = $this->store->insertFromSource(
                $sourceId,
                $targetYear,
                $targetMonth,
                $targetCourse,
                $targetStudentTotal,
                $marker
            );
            $this->assertReservation(
                $created,
                $source,
                $targetYear,
                $targetMonth,
                $targetCourse,
                $targetStudentTotal,
                $marker
            );

            return $this->result($created, $source, $sourceId, false);
        } finally {
            $this->store->release($lock);
        }
    }

    private function assertSource(array $source, int $sourceId): void
    {
        if (
            (int) ($source['id'] ?? 0) !== $sourceId
            || (int) ($source['idpag'] ?? 0) <= 0
            || (int) ($source['tipus_desc'] ?? 0) !== 4
            || (int) ($source['valid_desc'] ?? 0) !== 1
            || !in_array((string) ($source['status'] ?? ''), ['0', '1', 'M'], true)
        ) {
            throw new RuntimeException(
                'USOC source enrollment is not eligible for destination reservation',
                409
            );
        }
    }

    private function assertReservation(
        array $row,
        array $source,
        string $year,
        string $month,
        string $course,
        string $amount,
        string $marker
    ): void {
        if (
            (int) ($row['id'] ?? 0) <= 0
            || (int) ($row['idpag'] ?? 0) <= 0
            || (int) ($row['idpag'] ?? 0) === (int) $source['idpag']
            || (string) ($row['year'] ?? '') !== $year
            || (string) ($row['month'] ?? '') !== $month
            || (string) ($row['course'] ?? '') !== $course
            || (string) ($row['a_pagar'] ?? '') !== $amount
            || (string) ($row['pagament'] ?? '') !== '0.00'
            || (int) ($row['tipus_desc'] ?? 0) !== 4
            || (int) ($row['valid_desc'] ?? 0) !== 1
            || (string) ($row['status'] ?? '') !== '0'
            || (string) ($row['marker'] ?? '') !== $marker
        ) {
            throw new RuntimeException(
                'Existing USOC destination reservation differs from request',
                409
            );
        }
    }

    private function result(
        array $row,
        array $source,
        int $sourceId,
        bool $reused
    ): array
    {
        return [
            'ok' => true,
            'source_id_insc' => $sourceId,
            'destination_id_insc' => (int) $row['id'],
            'source_idpag' => (int) $source['idpag'],
            'destination_idpag' => (int) $row['idpag'],
            'target_student_total' => (string) $row['a_pagar'],
            'legacy_payment' => (string) $row['pagament'],
            'legacy_status' => (string) $row['status'],
            'reservation_marker' => (string) $row['marker'],
            'idempotency_reused' => $reused,
            'effects_applied' => false,
            'source_closed' => false,
        ];
    }

    private function money(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw new InvalidArgumentException(
                'Invalid USOC destination reservation amount'
            );
        }

        [$whole, $decimals] = array_pad(explode('.', $text, 2), 2, '');
        $cents = ((int) $whole * 100)
            + (int) substr(str_pad($decimals, 2, '0'), 0, 2);

        if ($cents <= 0) {
            throw new InvalidArgumentException(
                'USOC destination reservation amount must be positive'
            );
        }

        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
