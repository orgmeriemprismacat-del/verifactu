<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class LegacyGroupInvoicePayloadBuilder
{
    public function build(array $snapshot): array
    {
        $responsible = $this->requiredArray($snapshot, 'responsible');
        $items = $this->requiredArray($snapshot, 'items');

        if ($items === []) {
            throw SifException::validation('Group invoice requires at least one participant line');
        }

        $firstInscription = $this->itemInscription($items[0], 0);
        $year = (int) $this->required($firstInscription, ['ANY', 'any', 'year'], 'inscription.ANY');
        if ($year <= 0) {
            throw SifException::validation('Invalid inscription.ANY');
        }

        $idpag = $this->idpag($snapshot, $firstInscription);
        $lines = [];
        $relations = [[
            'source_type' => 'GRUP',
            'source_id' => $idpag,
            'idpag' => $idpag,
            'visible_alumne' => 0,
        ]];

        foreach ($items as $index => $item) {
            $inscription = $this->itemInscription($item, $index);
            $course = $this->itemCourse($item, $index);
            $inscriptionId = (int) $this->required($inscription, ['ID', 'id'], 'inscription.ID');
            if ($inscriptionId <= 0) {
                throw SifException::validation('Invalid inscription.ID');
            }

            $lines[] = $this->line($inscription, $course, $inscriptionId);
            $relations[] = $this->relation($inscription, $inscriptionId, $idpag);
        }

        return [
            'idempotency_key' => 'LEGACY|GRUP|IDPAG:' . $idpag,
            'series' => 'A',
            'year' => $year,
            'type' => 'F1',
            'source_type' => 'GRUP',
            'source_channel' => 'REDSYS',
            'created_by' => 'legacy-group',
            'billing' => $this->billing($responsible),
            'totals' => $this->totals($lines),
            'lines' => $lines,
            'relations' => $relations,
        ];
    }

    private function billing(array $responsible): array
    {
        $name = trim($this->requiredString($responsible, ['NOM', 'nom'], 'responsible.NOM')
            . ' '
            . $this->optionalString($responsible, ['COGNOMS', 'cognoms'], ''));

        if ($name === '') {
            throw SifException::validation('Missing billing name');
        }

        return [
            'name' => $name,
            'nif' => $this->requiredString($responsible, ['DNI', 'dni', 'nif'], 'responsible.DNI'),
            'address' => $this->optionalString($responsible, ['ADRECA', 'adreca', 'address']),
            'cp' => $this->optionalString($responsible, ['Codi_Postal', 'CODI_POSTAL', 'cp']),
            'city' => $this->optionalString($responsible, ['Poblacio', 'POBLACIO', 'poblacio', 'city']),
            'province' => $this->optionalString($responsible, ['Provincia', 'PROVINCIA', 'province']),
            'country' => $this->optionalString($responsible, ['Pais', 'PAIS', 'country'], 'ES'),
            'email' => $this->optionalString($responsible, ['CORREU', 'correu', 'email']),
        ];
    }

    private function totals(array $lines): array
    {
        $importBase = 0.0;
        $discount = 0.0;
        $total = 0.0;

        foreach ($lines as $line) {
            $importBase += (float) $line['import_base'];
            $discount += (float) $line['discount_amount'];
            $total += (float) $line['total'];
        }

        $total = $this->money($total);

        return [
            'import_base' => $this->money($importBase),
            'discount' => $this->money($discount),
            'taxable_base' => $total,
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $total,
        ];
    }

    private function line(array $inscription, array $course, int $inscriptionId): array
    {
        $courseTitle = $this->requiredString($course, ['NOM_CURS', 'TITOL', 'title', 'nom_curs'], 'course.NOM_CURS');
        $participantName = $this->participantName($inscription);
        $amounts = $this->lineAmounts($inscription);

        $line = [
            'concept' => 'Grup ' . $courseTitle . ' - ' . $participantName,
            'detail' => $this->detail($inscription),
            'quantity' => '1.00',
            'unit_price' => $amounts['import_base'],
            'base' => $amounts['import_base'],
            'import_base' => $amounts['import_base'],
            'discount_amount' => $amounts['discount_amount'],
            'taxable_base' => $amounts['taxable_base'],
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $amounts['total'],
            'source_type' => 'INSCRIPCIO',
            'source_id' => $inscriptionId,
        ];

        if ((float) $amounts['discount_amount'] > 0.0) {
            $line['discount_origin'] = $this->optionalString($inscription, ['DESC_ORIGEN', 'discount_origin'], 'GRUP');
            $line['discount_mode'] = $this->optionalString($inscription, ['DESC_MODE', 'discount_mode'], 'AMOUNT');
            $line['discount_pct'] = $this->optionalString($inscription, ['DESC_PCT', 'discount_pct']);
            $line['discount_text'] = $this->optionalString($inscription, ['DESC_TEXT', 'discount_text'], 'Descompte grup');
            $line['discount_internal_reason'] = 'Preu/descompte de grup congelat per participant';
        }

        return $line;
    }

    private function lineAmounts(array $inscription): array
    {
        $total = $this->money($this->required($inscription, ['TOTAL', 'total', 'A_PAGAR', 'a_pagar'], 'inscription.A_PAGAR'));
        $explicitBase = $this->optional($inscription, ['IMPORT_BASE', 'import_base', 'BASE', 'base', 'PREU_BASE', 'preu_base']);
        $explicitDiscount = $this->optional($inscription, ['DESC_IMPORT', 'discount_amount', 'DESCOMPTE', 'descompte']);

        if ($explicitBase !== null && $explicitBase !== '') {
            $base = $this->money($explicitBase);
            $discount = $explicitDiscount === null || $explicitDiscount === ''
                ? $this->money((float) $base - (float) $total)
                : $this->money($explicitDiscount);
        } else {
            $base = $total;
            $discount = '0.00';
        }

        if ((float) $discount < 0.0) {
            throw SifException::validation('Invalid group discount amount');
        }

        return [
            'import_base' => $base,
            'discount_amount' => $discount,
            'taxable_base' => $total,
            'total' => $total,
        ];
    }

    private function relation(array $inscription, int $inscriptionId, int $idpag): array
    {
        $relation = [
            'source_type' => 'INSCRIPCIO',
            'source_id' => $inscriptionId,
            'idpag' => $idpag,
            'visible_alumne' => 0,
        ];

        $facturaRelacionada = $this->optional($inscription, ['FACTURA_RELACIONADA', 'factura_relacionada']);
        if ($facturaRelacionada !== null && $facturaRelacionada !== '') {
            $relation['factura_relacionada'] = (int) $facturaRelacionada;
        }

        return $relation;
    }

    private function participantName(array $inscription): string
    {
        $name = trim($this->requiredString($inscription, ['NOM', 'nom'], 'inscription.NOM')
            . ' '
            . $this->optionalString($inscription, ['COGNOMS', 'cognoms'], ''));

        if ($name === '') {
            throw SifException::validation('Missing participant name');
        }

        return $name;
    }

    private function detail(array $inscription): string
    {
        $year = (int) $this->required($inscription, ['ANY', 'any', 'year'], 'inscription.ANY');
        $month = $this->month($this->required($inscription, ['MES', 'mes'], 'inscription.MES'));

        return 'Convocatoria ' . $month . ' ' . $year;
    }

    private function idpag(array $snapshot, array $inscription): int
    {
        $payment = $snapshot['payment'] ?? [];
        if (!is_array($payment)) {
            throw SifException::validation('Invalid payment snapshot');
        }

        $value = $this->optional($payment, ['idpag', 'IDPAG'])
            ?? $this->optional($inscription, ['IDPAG', 'idpag']);

        if ($value === null || $value === '' || !is_numeric($value)) {
            throw SifException::validation('Missing group IDPAG');
        }

        $idpag = (int) $value;
        if ($idpag <= 0) {
            throw SifException::validation('Invalid group IDPAG');
        }

        return $idpag;
    }

    private function itemInscription(mixed $item, int $index): array
    {
        if (!is_array($item) || !isset($item['inscription']) || !is_array($item['inscription'])) {
            throw SifException::validation('Missing group item inscription ' . $index);
        }

        return $item['inscription'];
    }

    private function itemCourse(mixed $item, int $index): array
    {
        if (!is_array($item) || !isset($item['course']) || !is_array($item['course'])) {
            throw SifException::validation('Missing group item course ' . $index);
        }

        return $item['course'];
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

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid money amount');
        }

        return number_format((float) $value, 2, '.', '');
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
