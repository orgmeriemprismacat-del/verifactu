<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class LegacyGiftInvoicePayloadBuilder
{
    public function build(array $snapshot): array
    {
        $gift = $this->requiredArray($snapshot, 'gift');
        $giftId = (int) $this->required($gift, ['ID', 'id'], 'gift.ID');
        if ($giftId <= 0) {
            throw SifException::validation('Invalid gift.ID');
        }

        $amount = $this->money($this->required($gift, ['IMPORT', 'import', 'amount'], 'gift.IMPORT'));
        $code = $this->requiredString($gift, ['CODI', 'code', 'codi'], 'gift.CODI');
        $courseTitle = $this->requiredString($gift, ['NOM_CURS', 'course_title', 'nom_curs'], 'gift.NOM_CURS');

        return [
            'idempotency_key' => 'LEGACY|REGAL|ID:' . $giftId,
            'series' => 'A',
            'year' => (int) ($gift['ANY'] ?? date('Y')),
            'type' => 'F1',
            'source_type' => 'REGAL',
            'source_channel' => 'REDSYS',
            'created_by' => 'legacy-gift',
            'billing' => $this->billing($gift),
            'totals' => $this->totals($amount),
            'lines' => [$this->line($giftId, $courseTitle, $code, $amount)],
            'relations' => [$this->relation($gift, $giftId)],
            'gift' => $this->giftMetadata($gift, $code),
        ];
    }

    private function billing(array $gift): array
    {
        $name = $this->requiredString($gift, ['NOMC', 'buyer_name', 'nomc'], 'gift.NOMC');

        return [
            'name' => $name,
            'nif' => $this->requiredString($gift, ['NIFC', 'buyer_nif', 'nifc'], 'gift.NIFC'),
            'address' => $this->optionalString($gift, ['ADRECAC', 'address', 'adrecac']),
            'cp' => $this->optionalString($gift, ['CPC', 'cp', 'cpc']),
            'city' => $this->optionalString($gift, ['POBLEC', 'city', 'poblec']),
            'province' => $this->optionalString($gift, ['PROVINCIA', 'province']),
            'country' => $this->optionalString($gift, ['PAIS', 'country'], 'ES'),
            'email' => $this->optionalString($gift, ['MAILC', 'email', 'mailc']),
        ];
    }

    private function totals(string $amount): array
    {
        return [
            'import_base' => $amount,
            'discount' => '0.00',
            'taxable_base' => $amount,
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $amount,
        ];
    }

    private function line(int $giftId, string $courseTitle, string $code, string $amount): array
    {
        return [
            'concept' => 'Curs regal ' . $courseTitle,
            'detail' => 'Codi regal ' . $code,
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
            'source_type' => 'REGAL',
            'source_id' => $giftId,
        ];
    }

    private function relation(array $gift, int $giftId): array
    {
        $relation = [
            'source_type' => 'REGAL',
            'source_id' => $giftId,
            'visible_alumne' => 0,
        ];

        $factRel = $this->optional($gift, ['FACT_REL', 'fact_rel', 'FACTURA_RELACIONADA']);
        if ($factRel !== null && $factRel !== '') {
            $relation['factura_relacionada'] = (int) $factRel;
        }

        return $relation;
    }

    private function giftMetadata(array $gift, string $code): array
    {
        return [
            'code' => $code,
            'buyer_name' => $this->requiredString($gift, ['NOMC', 'buyer_name', 'nomc'], 'gift.NOMC'),
            'recipient_name' => $this->optionalString($gift, ['DESTI', 'recipient_name', 'desti']),
            'origin_name' => $this->optionalString($gift, ['ORIGEN', 'origin_name', 'origen']),
            'legacy_fact_rel' => $this->optional($gift, ['FACT_REL', 'fact_rel']),
            'observations' => $this->optionalString($gift, ['OBSERVACIONS', 'observations']),
        ];
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid gift amount');
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation('Invalid gift amount');
        }

        return number_format($amount, 2, '.', '');
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
}
