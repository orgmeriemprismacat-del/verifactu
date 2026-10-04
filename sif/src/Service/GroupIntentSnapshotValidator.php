<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class GroupIntentSnapshotValidator
{
    public function validate(array $snapshot, ?int $idpag, string $sourceId, string $expectedAmount): void
    {
        if ($idpag === null) {
            throw SifException::validation('Redsys group payment intent requires IDPAG');
        }

        if (!ctype_digit($sourceId) || (int) $sourceId !== $idpag) {
            throw SifException::validation('Redsys group snapshot source does not match IDPAG');
        }

        $responsible = $snapshot['responsible'] ?? null;
        $items = $snapshot['items'] ?? null;
        if (!is_array($responsible) || !is_array($items) || $items === []) {
            throw SifException::validation('Redsys group snapshot requires responsible and participant items');
        }

        $responsibleName = trim((string) ($responsible['NOM'] ?? $responsible['nom'] ?? ''));
        $responsibleNif = trim((string) ($responsible['DNI'] ?? $responsible['dni'] ?? $responsible['nif'] ?? ''));
        if ($responsibleName === '' || $responsibleNif === '') {
            throw SifException::validation('Redsys group snapshot responsible identity is incomplete');
        }

        $seen = [];
        $sumCents = 0;

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw SifException::validation('Invalid Redsys group snapshot item ' . $index);
            }

            $inscription = $item['inscription'] ?? null;
            $course = $item['course'] ?? null;
            if (!is_array($inscription) || !is_array($course)) {
                throw SifException::validation('Redsys group snapshot item requires inscription and course');
            }

            $inscriptionId = $inscription['ID'] ?? $inscription['id'] ?? null;
            if (!is_numeric($inscriptionId) || (int) $inscriptionId <= 0) {
                throw SifException::validation('Invalid Redsys group inscription ID');
            }
            $inscriptionId = (int) $inscriptionId;
            if (isset($seen[$inscriptionId])) {
                throw SifException::validation('Duplicated Redsys group inscription ID');
            }
            $seen[$inscriptionId] = true;

            $itemIdpag = $inscription['IDPAG'] ?? $inscription['idpag'] ?? $idpag;
            if (!is_numeric($itemIdpag) || (int) $itemIdpag !== $idpag) {
                throw SifException::validation('Redsys group snapshot item IDPAG mismatch');
            }

            foreach (['ANY', 'MES', 'NOM'] as $field) {
                if (!array_key_exists($field, $inscription) || $inscription[$field] === '' || $inscription[$field] === null) {
                    throw SifException::validation('Missing Redsys group inscription field ' . $field);
                }
            }

            $courseTitle = $course['NOM_CURS'] ?? $course['TITOL'] ?? $course['title'] ?? null;
            if (!is_string($courseTitle) || trim($courseTitle) === '') {
                throw SifException::validation('Redsys group snapshot course title is required');
            }

            $total = $inscription['TOTAL'] ?? $inscription['total'] ?? $inscription['A_PAGAR'] ?? $inscription['a_pagar'] ?? null;
            $totalCents = $this->cents($total, 'Invalid Redsys group snapshot line total');
            if ($totalCents < 0) {
                throw SifException::validation('Invalid Redsys group snapshot line total');
            }

            $base = $inscription['IMPORT_BASE'] ?? $inscription['import_base'] ?? $inscription['BASE'] ?? $inscription['base'] ?? null;
            $discount = $inscription['DESC_IMPORT'] ?? $inscription['discount_amount'] ?? $inscription['DESCOMPTE'] ?? $inscription['descompte'] ?? null;
            if ($base !== null && $base !== '') {
                $baseCents = $this->cents($base, 'Invalid Redsys group snapshot line base');
                $discountCents = ($discount === null || $discount === '')
                    ? $baseCents - $totalCents
                    : $this->cents($discount, 'Invalid Redsys group snapshot line discount');

                if ($discountCents < 0 || $baseCents - $discountCents !== $totalCents) {
                    throw SifException::conflict('Redsys group snapshot line amounts are inconsistent');
                }
            }

            $sumCents += $totalCents;
        }

        if ($this->amount($sumCents) !== $expectedAmount) {
            throw SifException::conflict('Redsys group snapshot total does not match expected amount');
        }
    }

    private function cents(mixed $value, string $message): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation($message);
        }

        $negative = str_starts_with($raw, '-');
        $normalized = ltrim($raw, '-');
        [$euros, $decimals] = array_pad(explode('.', $normalized, 2), 2, '');
        $cents = (int) $euros * 100 + (int) str_pad($decimals, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
