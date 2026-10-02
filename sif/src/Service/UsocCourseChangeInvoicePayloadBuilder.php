<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class UsocCourseChangeInvoicePayloadBuilder
{
    public function __construct(private UsocCourseChangeIdempotency $keys)
    {
    }

    public function student(
        string $requestId,
        int $targetIdInsc,
        int $idpag,
        array $targetMeta,
        array $target,
        array $sourceInvoice,
        string $actorId
    ): array {
        $standard = $this->money($target['target_standard_course_amount'] ?? null, 'standard target amount');
        $studentCourse = $this->money($target['target_student_course_amount'] ?? null, 'student target amount');
        $entity = $this->money($target['target_entity_course_amount'] ?? null, 'entity target amount');
        $fee = $this->money($target['management_fee'] ?? '0.00', 'management fee');
        $total = $this->money($target['target_student_total'] ?? null, 'student target total');
        $base = $this->sum($standard, $fee);

        if ($this->subtract($base, $entity) !== $total || $this->sum($studentCourse, $fee) !== $total) {
            throw SifException::conflict('USOC destination student invoice does not reconcile');
        }

        return [
            'idempotency_key' => $this->keys->key($requestId, 'student', 'target_invoice'),
            'series' => 'A',
            'year' => $this->year($targetMeta),
            'type' => 'F1',
            'source_channel' => 'INTRANET',
            'created_by' => trim($actorId),
            'emesa_abans_cobrament' => 1,
            'billing' => $this->billingFromInvoice($sourceInvoice),
            'totals' => $this->totals($base, $entity, $total),
            'lines' => [[
                'concept' => $this->concept($targetMeta),
                'detail' => $this->detail($targetMeta, 'Alumne'),
                'quantity' => '1.00',
                'unit_price' => $base,
                'base' => $base,
                'import_base' => $base,
                'discount_origin' => 'USOC',
                'discount_mode' => 'AMOUNT',
                'discount_amount' => $entity,
                'discount_text' => 'Descompte USOC',
                'discount_internal_reason' => 'TIPUS_DESC=4;VALID_DESC=1;COURSE_CHANGE',
                'taxable_base' => $total,
                'iva_regim' => 'EXEMPT',
                'exemption_reason' => 'E1',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $total,
                'source_type' => 'INSCRIPCIO',
                'source_id' => $targetIdInsc,
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => $targetIdInsc,
                'relation_type' => 'ORIGIN',
                'idpag' => $idpag,
                'visible_alumne' => 1,
            ]],
        ];
    }

    public function entity(
        string $requestId,
        int $targetIdInsc,
        int $idpag,
        array $targetMeta,
        array $target,
        array $billing,
        string $actorId
    ): array {
        $amount = $this->money($target['target_entity_total'] ?? null, 'entity target total');
        if ((float) $amount <= 0) {
            throw SifException::validation('USOC destination entity amount must be positive');
        }

        return [
            'idempotency_key' => $this->keys->key($requestId, 'entity', 'target_invoice'),
            'series' => 'A',
            'year' => $this->year($targetMeta),
            'type' => 'F1',
            'source_channel' => 'INTRANET',
            'created_by' => trim($actorId),
            'emesa_abans_cobrament' => 1,
            'billing' => $this->normalizeBilling($billing),
            'totals' => $this->totals($amount, '0.00', $amount),
            'lines' => [[
                'concept' => 'Diferència USOC - ' . $this->concept($targetMeta),
                'detail' => $this->detail($targetMeta, 'Entitat USOC'),
                'quantity' => '1.00',
                'unit_price' => $amount,
                'base' => $amount,
                'import_base' => $amount,
                'discount_amount' => '0.00',
                'taxable_base' => $amount,
                'iva_regim' => 'EXEMPT',
                'exemption_reason' => 'E1',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $amount,
                'source_type' => 'INSCRIPCIO',
                'source_id' => $targetIdInsc,
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => $targetIdInsc,
                'relation_type' => 'USOC_ENTITY',
                'idpag' => $idpag,
                'visible_alumne' => 0,
            ]],
        ];
    }

    public function billingFromInvoice(array $invoice): array
    {
        return $this->normalizeBilling([
            'name' => $invoice['BILLING_NOM_RAO'] ?? null,
            'nif' => $invoice['BILLING_NIF_CIF'] ?? null,
            'address' => $invoice['BILLING_ADRECA'] ?? null,
            'cp' => $invoice['BILLING_CP'] ?? null,
            'city' => $invoice['BILLING_POBLACIO'] ?? null,
            'province' => $invoice['BILLING_PROVINCIA'] ?? null,
            'country' => $invoice['BILLING_PAIS'] ?? 'ES',
            'email' => $invoice['BILLING_EMAIL'] ?? null,
        ]);
    }

    public function normalizeBilling(array $billing): array
    {
        $name = trim((string) ($billing['name'] ?? ''));
        $nif = trim((string) ($billing['nif'] ?? ''));
        if ($name === '' || $nif === '') {
            throw SifException::validation('Missing USOC entity billing identity');
        }

        return [
            'name' => $name,
            'nif' => $nif,
            'address' => $this->nullable($billing['address'] ?? null),
            'cp' => $this->nullable($billing['cp'] ?? null),
            'city' => $this->nullable($billing['city'] ?? null),
            'province' => $this->nullable($billing['province'] ?? null),
            'country' => $this->nullable($billing['country'] ?? null) ?? 'ES',
            'email' => $this->nullable($billing['email'] ?? null),
        ];
    }

    private function totals(string $base, string $discount, string $total): array
    {
        return [
            'import_base' => $base,
            'discount' => $discount,
            'taxable_base' => $total,
            'iva_regim' => 'EXEMPT',
            'exemption_reason' => 'E1',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $total,
        ];
    }

    private function year(array $meta): int
    {
        $year = (int) ($meta['year'] ?? 0);
        if ($year < 2000 || $year > 2200) {
            throw SifException::validation('Invalid USOC destination year');
        }
        return $year;
    }

    private function concept(array $meta): string
    {
        $value = trim((string) ($meta['title'] ?? $meta['course'] ?? ''));
        if ($value === '') {
            throw SifException::validation('Missing USOC destination course');
        }
        return $value;
    }

    private function detail(array $meta, string $payer): string
    {
        return 'Canvi de curs USOC · ' . $payer . ' · '
            . trim((string) ($meta['year'] ?? '')) . '/'
            . trim((string) ($meta['month'] ?? '')) . ' · '
            . trim((string) ($meta['course'] ?? ''));
    }

    private function money(mixed $value, string $label): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw SifException::validation('Invalid USOC ' . $label);
        }
        return number_format((float) $text, 2, '.', '');
    }

    private function sum(string $a, string $b): string
    {
        return number_format((float) $a + (float) $b, 2, '.', '');
    }

    private function subtract(string $a, string $b): string
    {
        return number_format((float) $a - (float) $b, 2, '.', '');
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
