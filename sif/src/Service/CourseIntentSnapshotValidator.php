<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class CourseIntentSnapshotValidator
{
    public function validate(array $snapshot, ?int $idpag, string $sourceId, string $expectedAmount): void
    {
        if ($idpag === null) {
            throw SifException::validation('Redsys course payment intent requires IDPAG');
        }

        $inscription = $snapshot['inscription'] ?? null;
        $course = $snapshot['course'] ?? null;
        $payment = $snapshot['payment'] ?? null;

        if (!is_array($inscription) || !is_array($course) || !is_array($payment)) {
            throw SifException::validation('Redsys course snapshot requires inscription, course and payment');
        }

        $inscriptionId = $this->positiveInt($inscription['ID'] ?? $inscription['id'] ?? null, 'inscription.ID');
        if ((string) $inscriptionId !== ltrim($sourceId, '0')) {
            throw SifException::conflict('Redsys course snapshot source does not match inscription ID');
        }

        $snapshotIdpag = $this->positiveInt(
            $inscription['IDPAG'] ?? $inscription['idpag'] ?? null,
            'inscription.IDPAG'
        );
        if ($snapshotIdpag !== $idpag) {
            throw SifException::conflict('Redsys course snapshot IDPAG does not match payment intent');
        }

        foreach (['ANY', 'MES', 'CURS', 'NOM', 'DNI'] as $field) {
            if (!array_key_exists($field, $inscription) || $inscription[$field] === '' || $inscription[$field] === null) {
                throw SifException::validation('Missing Redsys course inscription field ' . $field);
            }
        }

        $courseTitle = $course['NOM_CURS'] ?? $course['TITOL'] ?? $course['title'] ?? null;
        if (!is_string($courseTitle) || trim($courseTitle) === '') {
            throw SifException::validation('Redsys course snapshot course title is required');
        }

        $paymentAmount = $payment['amount'] ?? $payment['IMPORT'] ?? $payment['import'] ?? null;
        if (!is_numeric($paymentAmount) || (float) $paymentAmount <= 0) {
            throw SifException::validation('Invalid Redsys course snapshot payment amount');
        }
        $paymentAmount = number_format((float) $paymentAmount, 2, '.', '');
        if ($paymentAmount !== $expectedAmount) {
            throw SifException::conflict('Redsys course snapshot payment amount does not match expected amount');
        }

        $discount = $snapshot['discount'] ?? null;
        if ($discount === null) {
            return;
        }
        if (!is_array($discount)) {
            throw SifException::validation('Invalid Redsys course discount snapshot');
        }

        $origin = strtoupper(trim((string) ($discount['origin'] ?? $discount['DESC_ORIGEN'] ?? '')));
        $mode = strtoupper(trim((string) ($discount['mode'] ?? $discount['DESC_MODE'] ?? '')));
        if ($origin === '' || $mode === '') {
            throw SifException::validation('Redsys course discount snapshot requires origin and mode');
        }

        $base = $discount['base'] ?? $discount['IMPORT_BASE'] ?? $discount['import_base'] ?? null;
        $amount = $discount['amount'] ?? $discount['DESC_IMPORT'] ?? $discount['discount_amount'] ?? null;
        if (!is_numeric($base) || (float) $base <= 0 || !is_numeric($amount) || (float) $amount < 0) {
            throw SifException::validation('Invalid Redsys course discount amounts');
        }

        $base = number_format((float) $base, 2, '.', '');
        $amount = number_format((float) $amount, 2, '.', '');
        if (abs(((float) $base - (float) $amount) - (float) $paymentAmount) > 0.01) {
            throw SifException::conflict('Redsys course discount snapshot does not match payment amount');
        }
    }

    private function positiveInt(mixed $value, string $field): int
    {
        $normalized = trim((string) $value);
        if ($normalized === '' || !ctype_digit($normalized) || (int) $normalized <= 0) {
            throw SifException::validation('Invalid Redsys course ' . $field);
        }

        return (int) $normalized;
    }
}
