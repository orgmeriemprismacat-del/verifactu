<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentServerPayloadAssembler
{
    public function buildInput(array $selection, array $billingParty, array $context = []): array
    {
        if ($selection === []) {
            throw SifException::validation('Invoice before payment requires a non-empty server selection');
        }

        $normalisedRows = [];
        $firstContext = null;
        $totalCents = 0;
        $relations = [];
        $lines = [];
        $participantNames = [];

        foreach ($selection as $index => $row) {
            if (!is_array($row)) {
                throw SifException::validation("Invalid server selection row {$index}");
            }

            $id = $this->positiveInt($row['ID'] ?? null, 'Invalid selected inscription ID');
            $status = strtoupper(trim((string) ($row['INSC_CURS'] ?? '')));
            if (!in_array($status, ['0', '1', 'M'], true)) {
                throw SifException::conflict(
                    "Selected inscription {$id} is no longer eligible for invoice-before-payment"
                );
            }

            $existingInvoice = $row['FACTURA_RELACIONADA'] ?? null;
            if ($existingInvoice !== null && trim((string) $existingInvoice) !== '') {
                throw SifException::conflict(
                    "Selected inscription {$id} already has a legacy related invoice"
                );
            }

            $paid = $this->money($row['PAGAMENT'] ?? '0.00', 'Invalid selected inscription payment amount');
            if ($this->moneyToCents($paid) !== 0) {
                throw SifException::conflict(
                    "Selected inscription {$id} already has a registered payment"
                );
            }

            $amount = $this->positiveMoney(
                $row['A_PAGAR'] ?? null,
                "Selected inscription {$id} has no positive amount to invoice"
            );

            $year = $this->positiveInt($row['ANY'] ?? null, 'Invalid selected inscription edition year');
            $month = $this->month($row['MES'] ?? null);
            $courseCode = $this->requiredString($row['CURS'] ?? null, 'Missing selected course code');
            $courseTitle = $this->requiredString($row['NOM_CURS'] ?? null, 'Missing selected course title');
            $hours = $this->nullableString($row['HORES'] ?? null);
            $name = trim(
                $this->requiredString($row['NOM'] ?? null, 'Missing selected participant name')
                . ' '
                . ($this->nullableString($row['COGNOMS'] ?? null) ?? '')
            );

            $rowContext = [$year, $month, strtoupper($courseCode)];
            if ($firstContext === null) {
                $firstContext = $rowContext;
            } elseif ($rowContext !== $firstContext) {
                throw SifException::validation(
                    'Invoice before payment selection must belong to the same course and edition'
                );
            }

            $participantNames[] = $name;
            $totalCents += $this->moneyToCents($amount);

            $line = [
                'concept' => 'Curs ' . $courseTitle,
                'detail' => $this->editionText($month, $year) . '. Participant: ' . $name,
                'quantity' => '1.00',
                'unit_price' => $amount,
                'base' => $amount,
                'import_base' => $amount,
                'discount_amount' => '0.00',
                'taxable_base' => $amount,
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $amount,
                'source_type' => 'INSCRIPCIO',
                'source_id' => $id,
            ];

            if ($hours !== null) {
                $line['legacy_course_hours'] = $hours;
            }

            $relation = [
                'source_type' => 'INSCRIPCIO',
                'source_id' => $id,
                'relation_type' => 'ORIGIN',
                'visible_alumne' => 1,
            ];

            $idpag = $row['IDPAG'] ?? null;
            if ($idpag !== null && $idpag !== '' && is_numeric($idpag) && (int) $idpag > 0) {
                $relation['idpag'] = (int) $idpag;
            }

            $normalisedRows[] = [
                'id' => $id,
                'year' => $year,
                'month' => $month,
                'course_code' => $courseCode,
                'course_title' => $courseTitle,
                'hours' => $hours,
                'participant' => $name,
                'amount' => $amount,
            ];
            $lines[] = $line;
            $relations[] = $relation;
        }

        $entityId = $this->positiveInt(
            $billingParty['entity_id'] ?? null,
            'Invalid invoice before payment billing entity ID'
        );

        $billing = [
            'name' => $this->requiredString($billingParty['name'] ?? null, 'Missing billing name'),
            'nif' => $this->requiredString($billingParty['nif'] ?? null, 'Missing billing NIF/CIF'),
            'address' => $this->nullableString($billingParty['address'] ?? null),
            'cp' => $this->nullableString($billingParty['cp'] ?? null),
            'city' => $this->nullableString($billingParty['city'] ?? null),
            'province' => $this->nullableString($billingParty['province'] ?? null),
            'country' => $this->nullableString($billingParty['country'] ?? null) ?? 'ES',
            'email' => $this->nullableString($billingParty['email'] ?? null),
        ];

        $fiscalYear = $this->fiscalYear($context['fiscal_year'] ?? null);
        $ids = array_column($normalisedRows, 'id');
        sort($ids, SORT_NUMERIC);

        $selectionIdentity = json_encode(
            ['entity_id' => $entityId, 'inscription_ids' => $ids],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $idempotencyKey = $this->nullableString($context['idempotency_key'] ?? null)
            ?? 'INTRANET|FACTURA_ABANS_COBRAR|SEL:' . substr(hash('sha256', $selectionIdentity), 0, 40);

        if (strlen($idempotencyKey) > 100) {
            throw SifException::validation('Invoice before payment idempotency key exceeds 100 characters');
        }

        $course = $normalisedRows[0];
        $legacyConcept1 = 'Curs ' . $course['course_title']
            . ', realitzat per: ' . $this->joinPeople($participantNames);
        $legacyConcept2 = $this->editionText($course['month'], $course['year']);
        $total = $this->centsToMoney($totalCents);

        return [
            'idempotency_key' => $idempotencyKey,
            'series' => 'A',
            'year' => $fiscalYear,
            'type' => 'F1',
            'source_channel' => 'INTRANET',
            'created_by' => $this->nullableString($context['created_by'] ?? null)
                ?? 'intranet-factura-abans-cobrar',
            'billing' => $billing,
            'totals' => [
                'import_base' => $total,
                'discount' => '0.00',
                'taxable_base' => $total,
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $total,
            ],
            'lines' => $lines,
            'relations' => $relations,
            'uc004_context' => [
                'billing_entity_id' => $entityId,
                'inscription_ids' => $ids,
                'course_year' => $course['year'],
                'course_month' => $course['month'],
                'course_code' => $course['course_code'],
                'course_title' => $course['course_title'],
                'legacy_concept1' => $legacyConcept1,
                'legacy_concept2' => $legacyConcept2,
                'observations' => $this->nullableString($context['observations'] ?? null),
                'pricing_source' => 'legacy.inscripcions.A_PAGAR',
            ],
        ];
    }

    private function fiscalYear(mixed $value): int
    {
        if ($value === null || $value === '') {
            return (int) (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y');
        }

        if (!is_numeric($value)) {
            throw SifException::validation('Invalid fiscal year');
        }

        $year = (int) $value;
        if ($year < 2000 || $year > 2100) {
            throw SifException::validation('Invalid fiscal year');
        }

        return $year;
    }

    private function month(mixed $value): string
    {
        if (is_numeric($value)) {
            $month = (int) $value;
            if ($month < 1 || $month > 12) {
                throw SifException::validation('Invalid selected inscription edition month');
            }

            return str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        }

        $month = trim((string) $value);
        if ($month === '') {
            throw SifException::validation('Invalid selected inscription edition month');
        }

        return $month;
    }

    private function editionText(string $month, int $year): string
    {
        $months = [
            '01' => 'gener',
            '02' => 'febrer',
            '03' => 'març',
            '04' => 'abril',
            '05' => 'maig',
            '06' => 'juny',
            '07' => 'juliol',
            '08' => 'agost',
            '09' => 'setembre',
            '10' => 'octubre',
            '11' => 'novembre',
            '12' => 'desembre',
        ];

        return 'Convocatòria ' . ($months[$month] ?? $month) . ' ' . $year;
    }

    private function joinPeople(array $people): string
    {
        $people = array_values(array_filter(array_map('trim', $people), static fn (string $v): bool => $v !== ''));
        if ($people === []) {
            throw SifException::validation('Invoice before payment requires participant names');
        }
        if (count($people) === 1) {
            return $people[0];
        }
        if (count($people) === 2) {
            return $people[0] . ' i ' . $people[1];
        }

        $last = array_pop($people);

        return implode(', ', $people) . ' i ' . $last;
    }

    private function positiveInt(mixed $value, string $message): int
    {
        if (!is_numeric($value)) {
            throw SifException::validation($message);
        }

        $int = (int) $value;
        if ($int <= 0) {
            throw SifException::validation($message);
        }

        return $int;
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        $money = $this->money($value, $message);
        if ($this->moneyToCents($money) <= 0) {
            throw SifException::validation($message);
        }

        return $money;
    }

    private function money(mixed $value, string $message): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation($message);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function moneyToCents(string $money): int
    {
        return (int) round(((float) $money) * 100);
    }

    private function centsToMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function requiredString(mixed $value, string $message): string
    {
        $string = $this->nullableString($value);
        if ($string === null) {
            throw SifException::validation($message);
        }

        return $string;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
