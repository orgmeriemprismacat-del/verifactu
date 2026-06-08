<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class LegacyCourseInvoicePayloadBuilder
{
    public function build(array $snapshot): array
    {
        $inscription = $this->requiredArray($snapshot, 'inscription');
        $course = $this->requiredArray($snapshot, 'course');

        $inscriptionId = (int) $this->required($inscription, ['ID', 'id'], 'inscription.ID');
        if ($inscriptionId <= 0) {
            throw SifException::validation('Invalid inscription.ID');
        }

        $year = (int) $this->required($inscription, ['ANY', 'any', 'year'], 'inscription.ANY');
        if ($year <= 0) {
            throw SifException::validation('Invalid inscription.ANY');
        }

        $month = $this->month($this->required($inscription, ['MES', 'mes'], 'inscription.MES'));
        $courseCode = $this->requiredString($inscription, ['CURS', 'curs'], 'inscription.CURS');
        $courseTitle = $this->requiredString($course, ['NOM_CURS', 'nom_curs', 'title'], 'course.NOM_CURS');
        $amount = $this->amount($snapshot, $inscription);
        $amounts = $this->lineAmounts($snapshot, $inscription, $amount);

        return [
            'idempotency_key' => 'LEGACY|CURS|INSCRIPCIO:' . $inscriptionId,
            'series' => 'A',
            'year' => $year,
            'type' => 'F1',
            'source_channel' => 'REDSYS',
            'created_by' => 'redsys-curs-normal',
            'billing' => $this->billing($inscription),
            'totals' => $this->totals($amounts),
            'lines' => [
                $this->line(
                    $inscription,
                    $course,
                    $inscriptionId,
                    $year,
                    $month,
                    $courseCode,
                    $courseTitle,
                    $amounts
                ),
            ],
            'relations' => [
                $this->relation($inscription, $inscriptionId),
            ],
        ];
    }

    private function billing(array $inscription): array
    {
        $name = trim($this->requiredString($inscription, ['NOM', 'nom'], 'inscription.NOM')
            . ' '
            . $this->optionalString($inscription, ['COGNOMS', 'cognoms'], ''));

        if ($name === '') {
            throw SifException::validation('Missing billing name');
        }

        return [
            'name' => $name,
            'nif' => $this->requiredString($inscription, ['DNI', 'dni', 'nif'], 'inscription.DNI'),
            'address' => $this->optionalString($inscription, ['ADRECA', 'adreca', 'address']),
            'cp' => $this->optionalString($inscription, ['Codi_Postal', 'CODI_POSTAL', 'cp']),
            'city' => $this->optionalString($inscription, ['Poblacio', 'POBLACIO', 'poblacio', 'city']),
            'province' => $this->optionalString($inscription, ['Provincia', 'PROVINCIA', 'province']),
            'country' => $this->optionalString($inscription, ['Pais', 'PAIS', 'country'], 'ES'),
            'email' => $this->optionalString($inscription, ['CORREU', 'correu', 'email']),
        ];
    }

    private function totals(array $amounts): array
    {
        return [
            'import_base' => $amounts['import_base'],
            'discount' => $amounts['discount_amount'],
            'taxable_base' => $amounts['total'],
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $amounts['total'],
        ];
    }

    private function line(
        array $inscription,
        array $course,
        int $inscriptionId,
        int $year,
        string $month,
        string $courseCode,
        string $courseTitle,
        array $amounts
    ): array {
        $line = [
            'concept' => $this->concept($courseCode, $courseTitle),
            'detail' => $this->detail($inscription, $course, $courseCode, $year, $month, $amounts['total']),
            'quantity' => '1.00',
            'unit_price' => $amounts['import_base'],
            'base' => $amounts['import_base'],
            'import_base' => $amounts['import_base'],
            'discount_amount' => $amounts['discount_amount'],
            'taxable_base' => $amounts['total'],
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $amounts['total'],
            'source_type' => 'INSCRIPCIO',
            'source_id' => $inscriptionId,
        ];

        if ((float) $amounts['discount_amount'] > 0.0) {
            $line = array_replace($line, $this->discountFields($amounts['discount'] ?? []));
        }

        return $line;
    }

    private function relation(array $inscription, int $inscriptionId): array
    {
        $relation = [
            'source_type' => 'INSCRIPCIO',
            'source_id' => $inscriptionId,
            'visible_alumne' => 1,
        ];

        $facturaRelacionada = $this->optional($inscription, ['FACTURA_RELACIONADA', 'factura_relacionada']);
        if ($facturaRelacionada !== null && $facturaRelacionada !== '') {
            $relation['factura_relacionada'] = (int) $facturaRelacionada;
        }

        return $relation;
    }

    private function concept(string $courseCode, string $courseTitle): string
    {
        if (ctype_alpha($courseCode)) {
            return 'Curs ' . $courseTitle;
        }

        return $courseTitle;
    }

    private function detail(
        array $inscription,
        array $course,
        string $courseCode,
        int $year,
        string $month,
        string $amount
    ): string {
        if (ctype_alpha($courseCode)) {
            $detail = 'Convocatoria ' . $month . ' ' . $year;
        } else {
            $startDate = $this->optionalString($course, ['DATAI', 'datai', 'start_date'], $month . ' ' . $year);
            $detail = 'Convocatoria ' . $startDate;
        }

        if ($this->isFractionalPayment($inscription, $amount)) {
            $detail .= '. Pagament fraccionat';
        }

        return $detail;
    }

    private function amount(array $snapshot, array $inscription): string
    {
        $payment = $snapshot['payment'] ?? [];
        if (!is_array($payment)) {
            throw SifException::validation('Invalid payment snapshot');
        }

        $value = $this->optional($payment, ['amount', 'IMPORT', 'import', 'import_pagament', 'current_amount']);
        if ($value === null || $value === '') {
            $value = $this->optional($snapshot, ['amount', 'import_pagament', 'current_amount']);
        }
        if ($value === null || $value === '') {
            throw SifException::validation('Missing payment.amount');
        }

        if (!is_numeric($value)) {
            throw SifException::validation('Invalid course payment amount');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function lineAmounts(array $snapshot, array $inscription, string $total): array
    {
        $discount = $this->discountSnapshot($snapshot, $inscription);
        if ($discount === null) {
            return [
                'import_base' => $total,
                'discount_amount' => '0.00',
                'total' => $total,
            ];
        }

        $discountAmountInput = $this->optional($discount, ['amount', 'DESC_IMPORT', 'discount_amount']);
        $baseInput = $this->optional($discount, ['base', 'IMPORT_BASE', 'import_base', 'BASE']);

        if ($discountAmountInput === null || $discountAmountInput === '') {
            if ($baseInput === null || $baseInput === '') {
                throw SifException::validation('Missing course discount amount');
            }

            $base = $this->positiveMoney($baseInput, 'Invalid course discount base');
            $discountAmount = $this->money((float) $base - (float) $total);
        } else {
            $discountAmount = $this->money($discountAmountInput);
            $base = $baseInput === null || $baseInput === ''
                ? $this->money((float) $total + (float) $discountAmount)
                : $this->positiveMoney($baseInput, 'Invalid course discount base');
        }

        if ((float) $discountAmount < 0.0 || (float) $base < (float) $total) {
            throw SifException::validation('Invalid course discount snapshot');
        }

        if (abs(((float) $base - (float) $discountAmount) - (float) $total) > 0.01) {
            throw SifException::validation('Course discount snapshot does not match payment amount');
        }

        return [
            'import_base' => $base,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'discount' => $discount,
        ];
    }

    private function discountSnapshot(array $snapshot, array $inscription): ?array
    {
        $discount = $snapshot['discount'] ?? null;
        if (is_array($discount)) {
            return $discount;
        }

        $base = $this->optional($inscription, ['IMPORT_BASE', 'import_base', 'BASE', 'base', 'PREU_BASE', 'preu_base'])
            ?? $this->optional($snapshot, ['IMPORT_BASE', 'import_base', 'BASE', 'base']);
        $amount = $this->optional($inscription, ['DESC_IMPORT', 'discount_amount', 'DESCOMPTE', 'descompte'])
            ?? $this->optional($snapshot, ['DESC_IMPORT', 'discount_amount', 'DESCOMPTE', 'descompte']);
        $code = $this->optional($inscription, ['DESC_CODI_PROMO', 'CODI_DESCOMPTE', 'PROMO_CODE', 'promo_code'])
            ?? $this->optional($snapshot, ['DESC_CODI_PROMO', 'CODI_DESCOMPTE', 'PROMO_CODE', 'promo_code']);
        $id = $this->optional($inscription, ['DESC_ID', 'discount_id'])
            ?? $this->optional($snapshot, ['DESC_ID', 'discount_id']);
        $pct = $this->optional($inscription, ['DESC_PCT', 'discount_pct', 'PERCENTATGE'])
            ?? $this->optional($snapshot, ['DESC_PCT', 'discount_pct', 'PERCENTATGE']);

        if ($base === null && $amount === null && $code === null && $id === null && $pct === null) {
            return null;
        }

        return [
            'origin' => $this->optionalString($inscription, ['DESC_ORIGEN', 'discount_origin'])
                ?? ($code === null || $code === '' ? 'PROMOCIO_TEMPORAL' : 'CODI_PROMO'),
            'mode' => $this->optionalString($inscription, ['DESC_MODE', 'discount_mode'], $pct === null ? 'AMOUNT' : 'PERCENT'),
            'code' => $code,
            'id' => $id,
            'pct' => $pct,
            'amount' => $amount,
            'base' => $base,
            'text' => $this->optionalString($inscription, ['DESC_TEXT', 'discount_text'], 'Descompte promocional aplicat'),
            'internal_reason' => $this->optionalString(
                $inscription,
                ['DESC_MOTIU_INTERN', 'discount_internal_reason'],
                'Snapshot de descompte validat abans de Redsys'
            ),
        ];
    }

    private function discountFields(array $discount): array
    {
        $fields = [
            'discount_origin' => $this->optionalString($discount, ['origin', 'DESC_ORIGEN'], 'CODI_PROMO'),
            'discount_mode' => $this->optionalString($discount, ['mode', 'DESC_MODE'], 'AMOUNT'),
            'discount_text' => $this->optionalString($discount, ['text', 'DESC_TEXT'], 'Descompte promocional aplicat'),
            'discount_internal_reason' => $this->optionalString(
                $discount,
                ['internal_reason', 'DESC_MOTIU_INTERN'],
                'Snapshot de descompte validat abans de Redsys'
            ),
        ];

        $id = $this->optional($discount, ['id', 'DESC_ID', 'discount_id']);
        if ($id !== null && $id !== '') {
            if (!is_numeric($id) || (int) $id <= 0) {
                throw SifException::validation('Invalid course discount ID');
            }

            $fields['discount_id'] = (int) $id;
        }

        $code = $this->optionalString($discount, ['code', 'DESC_CODI_PROMO', 'CODI_DESCOMPTE', 'promo_code']);
        if ($code !== null) {
            $fields['discount_code'] = $code;
        }

        $pct = $this->optional($discount, ['pct', 'DESC_PCT', 'discount_pct', 'PERCENTATGE']);
        if ($pct !== null && $pct !== '') {
            $fields['discount_pct'] = $this->money($pct);
        }

        return $fields;
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        $amount = $this->money($value);
        if ((float) $amount <= 0.0) {
            throw SifException::validation($message);
        }

        return $amount;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid money amount');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function isFractionalPayment(array $inscription, string $amount): bool
    {
        $fraccio = $this->optional($inscription, ['FRACCIO', 'fraccio']);
        if ($this->truthy($fraccio)) {
            return true;
        }

        $total = $this->optional($inscription, ['A_PAGAR', 'a_pagar']);
        if ($total === null || $total === '' || !is_numeric($total)) {
            return false;
        }

        return (float) $total > (float) $amount;
    }

    private function month(mixed $value): string
    {
        if (is_numeric($value)) {
            return str_pad((string) (int) $value, 2, '0', STR_PAD_LEFT);
        }

        $month = trim((string) $value);
        if ($month === '') {
            throw SifException::validation('Invalid inscription.MES');
        }

        return $month;
    }

    private function requiredArray(array $data, string $key): array
    {
        if (!array_key_exists($key, $data) || !is_array($data[$key])) {
            throw SifException::validation("Missing snapshot {$key}");
        }

        return $data[$key];
    }

    private function requiredString(array $data, array $keys, string $label): string
    {
        $value = $this->required($data, $keys, $label);
        $string = trim((string) $value);
        if ($string === '') {
            throw SifException::validation("Missing {$label}");
        }

        return $string;
    }

    private function required(array $data, array $keys, string $label): mixed
    {
        $value = $this->optional($data, $keys);
        if ($value === null || $value === '') {
            throw SifException::validation("Missing {$label}");
        }

        return $value;
    }

    private function optionalString(array $data, array $keys, ?string $default = null): ?string
    {
        $value = $this->optional($data, $keys);
        if ($value === null || $value === '') {
            return $default;
        }

        return trim((string) $value);
    }

    private function optional(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function truthy(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        return true;
    }
}
