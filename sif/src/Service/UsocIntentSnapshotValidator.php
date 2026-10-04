<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class UsocIntentSnapshotValidator
{
    public function validate(array $snapshot, ?int $idpag, string $sourceId, string $expectedAmount): void
    {
        if ($idpag === null) {
            throw SifException::validation('Redsys USOC payment intent requires IDPAG');
        }

        $inscription = $snapshot['inscription'] ?? null;
        $course = $snapshot['course'] ?? null;
        $payment = $snapshot['payment'] ?? null;
        $usoc = $snapshot['usoc'] ?? null;

        if (!is_array($inscription) || !is_array($course) || !is_array($payment) || !is_array($usoc)) {
            throw SifException::validation(
                'Redsys USOC snapshot requires inscription, course, payment and usoc'
            );
        }

        $inscriptionId = $this->positiveInt(
            $inscription['ID'] ?? $inscription['id'] ?? null,
            'inscription.ID'
        );
        if ((string) $inscriptionId !== ltrim($sourceId, '0')) {
            throw SifException::conflict(
                'Redsys USOC snapshot source does not match inscription ID'
            );
        }

        $inscriptionIdpag = $this->positiveInt(
            $inscription['IDPAG'] ?? $inscription['idpag'] ?? null,
            'inscription.IDPAG'
        );
        $paymentIdpag = $this->positiveInt(
            $payment['idpag'] ?? $payment['IDPAG'] ?? null,
            'payment.idpag'
        );
        if ($inscriptionIdpag !== $idpag || $paymentIdpag !== $idpag) {
            throw SifException::conflict(
                'Redsys USOC snapshot IDPAG does not match payment intent'
            );
        }

        foreach (['ANY', 'MES', 'CURS', 'NOM', 'DNI'] as $field) {
            if (!array_key_exists($field, $inscription)
                || $inscription[$field] === ''
                || $inscription[$field] === null
            ) {
                throw SifException::validation(
                    'Missing Redsys USOC inscription field ' . $field
                );
            }
        }

        if ((int) ($inscription['TIPUS_DESC'] ?? 0) !== 4
            || (int) ($inscription['VALID_DESC'] ?? 0) !== 1
        ) {
            throw SifException::conflict(
                'Redsys USOC snapshot requires validated TIPUS_DESC=4 and VALID_DESC=1'
            );
        }

        if ((int) ($usoc['tipus_desc'] ?? 0) !== 4
            || (int) ($usoc['valid_desc'] ?? 0) !== 1
        ) {
            throw SifException::conflict(
                'Redsys USOC metadata does not match validated inscription state'
            );
        }

        $courseTitle = $course['NOM_CURS'] ?? $course['TITOL'] ?? $course['title'] ?? null;
        if (!is_string($courseTitle) || trim($courseTitle) === '') {
            throw SifException::validation('Redsys USOC snapshot course title is required');
        }

        $paymentAmount = $this->positiveMoney(
            $payment['amount'] ?? $payment['IMPORT'] ?? null,
            'Invalid Redsys USOC snapshot payment amount'
        );
        $studentAmount = $this->positiveMoney(
            $usoc['student_amount'] ?? $usoc['STUDENT_AMOUNT'] ?? null,
            'Invalid Redsys USOC student amount'
        );
        $entityAmount = $this->positiveMoney(
            $usoc['entity_amount'] ?? $usoc['ENTITY_AMOUNT'] ?? null,
            'Invalid Redsys USOC entity amount'
        );
        $legacyAmount = $this->positiveMoney(
            $inscription['A_PAGAR'] ?? $inscription['a_pagar'] ?? null,
            'Invalid Redsys USOC legacy A_PAGAR'
        );

        if ($paymentAmount !== $expectedAmount
            || $studentAmount !== $expectedAmount
            || $legacyAmount !== $expectedAmount
        ) {
            throw SifException::conflict(
                'Redsys USOC student amount does not match expected amount'
            );
        }

        // Explicit read keeps the entity contribution mandatory and independently frozen.
        if ((float) $entityAmount <= 0.0) {
            throw SifException::validation('Invalid Redsys USOC entity amount');
        }
    }

    private function positiveInt(mixed $value, string $field): int
    {
        $normalized = trim((string) $value);
        if ($normalized === '' || !ctype_digit($normalized) || (int) $normalized <= 0) {
            throw SifException::validation('Invalid Redsys USOC ' . $field);
        }

        return (int) $normalized;
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation($message);
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
        if ($cents <= 0) {
            throw SifException::validation($message);
        }

        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
