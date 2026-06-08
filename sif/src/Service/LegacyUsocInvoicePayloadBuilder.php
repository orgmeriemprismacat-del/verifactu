<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class LegacyUsocInvoicePayloadBuilder
{
    public function buildStudentPayload(array $snapshot): array
    {
        $inscription = $this->requiredArray($snapshot, 'inscription');
        $course = $this->requiredArray($snapshot, 'course');
        $this->assertValidatedUsocInscription($inscription);

        $inscriptionId = $this->inscriptionId($inscription);
        $idpag = $this->idpag($snapshot, $inscription);
        $year = $this->year($inscription);
        $studentAmount = $this->studentAmount($snapshot, $inscription);
        $amounts = $this->studentLineAmounts($snapshot, $inscription, $studentAmount);

        return [
            'idempotency_key' => 'LEGACY|USOC_ALUMNE|IDPAG:' . $idpag,
            'series' => 'A',
            'year' => $year,
            'type' => 'F1',
            'source_type' => 'USOC_ALUMNE',
            'source_channel' => 'REDSYS',
            'created_by' => 'redsys-usoc-student',
            'billing' => $this->studentBilling($inscription),
            'totals' => $this->totals($amounts['import_base'], $amounts['discount_amount'], $studentAmount),
            'lines' => [$this->studentLine($inscription, $course, $inscriptionId, $amounts, $studentAmount)],
            'relations' => [$this->studentRelation($inscription, $inscriptionId, $idpag)],
            'usoc' => $this->studentMetadata($snapshot, $studentAmount),
        ];
    }

    public function buildEntityPayload(array $snapshot, array $entityInput): array
    {
        $inscription = $this->requiredArray($snapshot, 'inscription');
        $course = $this->requiredArray($snapshot, 'course');
        $this->assertValidatedUsocInscription($inscription);

        $inscriptionId = $this->inscriptionId($inscription);
        $idpag = $this->idpag($snapshot, $inscription);
        $year = $this->year($inscription);
        $amount = $this->positiveMoney(
            $this->required($entityInput, ['amount'], 'USOC entity amount'),
            'Invalid USOC entity amount'
        );
        $studentInvoiceUuid = $this->requiredString(
            $entityInput,
            ['student_invoice_uuid'],
            'USOC student_invoice_uuid'
        );

        return [
            'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:' . $inscriptionId
                . '|FACT_ALUMNE:' . $studentInvoiceUuid,
            'series' => 'A',
            'year' => $year,
            'type' => 'F1',
            'source_type' => 'USOC_ENTITAT',
            'source_channel' => 'INTRANET',
            'created_by' => $this->optionalString($entityInput, ['created_by'], 'usoc-entity'),
            'billing' => $this->entityBilling($entityInput),
            'totals' => $this->totals($amount, '0.00', $amount),
            'lines' => [$this->entityLine($inscription, $course, $inscriptionId, $amount)],
            'relations' => [$this->entityRelation($inscription, $inscriptionId, $idpag)],
            'usoc' => $this->entityMetadata($snapshot, $amount, $studentInvoiceUuid),
        ];
    }

    private function assertValidatedUsocInscription(array $inscription): void
    {
        $tipusDesc = (int) $this->required($inscription, ['TIPUS_DESC', 'tipus_desc'], 'inscription.TIPUS_DESC');
        if ($tipusDesc !== 4) {
            throw SifException::conflict('Legacy inscription is not a USOC discount');
        }

        $validDesc = (int) $this->required($inscription, ['VALID_DESC', 'valid_desc'], 'inscription.VALID_DESC');
        if ($validDesc !== 1) {
            throw SifException::conflict('Legacy USOC discount is not validated');
        }
    }

    private function studentBilling(array $inscription): array
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

    private function entityBilling(array $entityInput): array
    {
        $billing = $this->requiredArray($entityInput, 'billing');

        return [
            'name' => $this->requiredString($billing, ['name', 'NOM', 'nom'], 'USOC billing.name'),
            'nif' => $this->requiredString($billing, ['nif', 'NIF', 'cif', 'CIF'], 'USOC billing.nif'),
            'address' => $this->optionalString($billing, ['address', 'ADRECA', 'adreca']),
            'cp' => $this->optionalString($billing, ['cp', 'CP', 'Codi_Postal']),
            'city' => $this->optionalString($billing, ['city', 'POBLACIO', 'Poblacio']),
            'province' => $this->optionalString($billing, ['province', 'PROVINCIA', 'Provincia']),
            'country' => $this->optionalString($billing, ['country', 'PAIS', 'Pais'], 'ES'),
            'email' => $this->optionalString($billing, ['email', 'CORREU', 'correu']),
        ];
    }

    private function studentLine(
        array $inscription,
        array $course,
        int $inscriptionId,
        array $amounts,
        string $studentAmount
    ): array {
        $line = [
            'concept' => $this->courseTitle($course),
            'detail' => $this->detail($inscription),
            'quantity' => '1.00',
            'unit_price' => $amounts['import_base'],
            'base' => $amounts['import_base'],
            'import_base' => $amounts['import_base'],
            'discount_amount' => $amounts['discount_amount'],
            'taxable_base' => $studentAmount,
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $studentAmount,
            'source_type' => 'INSCRIPCIO',
            'source_id' => $inscriptionId,
        ];

        if ((float) $amounts['discount_amount'] > 0.0) {
            $line['discount_origin'] = 'USOC';
            $line['discount_mode'] = 'AMOUNT';
            $line['discount_text'] = 'Descompte USOC';
            $line['discount_internal_reason'] = 'TIPUS_DESC=4;VALID_DESC=1';
        }

        return $line;
    }

    private function entityLine(array $inscription, array $course, int $inscriptionId, string $amount): array
    {
        return [
            'concept' => 'Diferencia USOC - ' . $this->courseTitle($course),
            'detail' => $this->detail($inscription),
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
            'source_id' => $inscriptionId,
        ];
    }

    private function totals(string $importBase, string $discount, string $total): array
    {
        return [
            'import_base' => $importBase,
            'discount' => $discount,
            'taxable_base' => $total,
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $total,
        ];
    }

    private function studentLineAmounts(array $snapshot, array $inscription, string $studentAmount): array
    {
        $baseInput = $this->optional($inscription, ['IMPORT_BASE', 'import_base', 'BASE', 'base', 'PREU_BASE', 'preu_base'])
            ?? $this->optional($snapshot['usoc'] ?? [], ['base_amount', 'BASE_AMOUNT']);
        $discountInput = $this->optional($inscription, ['DESC_IMPORT', 'discount_amount', 'DESCOMPTE', 'descompte'])
            ?? $this->optional($snapshot['usoc'] ?? [], ['discount_amount', 'DISCOUNT_AMOUNT']);

        if ($baseInput !== null && $baseInput !== '') {
            $base = $this->positiveMoney($baseInput, 'Invalid USOC base amount');
            $discount = $discountInput === null || $discountInput === ''
                ? $this->money((float) $base - (float) $studentAmount)
                : $this->money($discountInput);
        } else {
            $entityAmount = $this->optional($snapshot['usoc'] ?? [], ['entity_amount', 'ENTITY_AMOUNT']);
            if ($entityAmount !== null && $entityAmount !== '') {
                $discount = $this->positiveMoney($entityAmount, 'Invalid USOC entity amount');
                $base = $this->money((float) $studentAmount + (float) $discount);
            } else {
                $base = $studentAmount;
                $discount = '0.00';
            }
        }

        if ((float) $discount < 0.0 || (float) $base < (float) $studentAmount) {
            throw SifException::validation('Invalid USOC discount amount');
        }

        return [
            'import_base' => $base,
            'discount_amount' => $discount,
        ];
    }

    private function studentAmount(array $snapshot, array $inscription): string
    {
        $usoc = $snapshot['usoc'] ?? [];
        if (!is_array($usoc)) {
            throw SifException::validation('Invalid USOC snapshot block');
        }

        $payment = $snapshot['payment'] ?? [];
        if (!is_array($payment)) {
            throw SifException::validation('Invalid payment snapshot');
        }

        $value = $this->optional($usoc, ['student_amount', 'STUDENT_AMOUNT'])
            ?? $this->optional($payment, ['amount', 'IMPORT'])
            ?? $this->optional($inscription, ['A_PAGAR', 'a_pagar']);

        return $this->positiveMoney($value, 'Invalid USOC student amount');
    }

    private function studentRelation(array $inscription, int $inscriptionId, int $idpag): array
    {
        $relation = [
            'source_type' => 'INSCRIPCIO',
            'source_id' => $inscriptionId,
            'idpag' => $idpag,
            'visible_alumne' => 1,
        ];

        $facturaRelacionada = $this->optional($inscription, ['FACTURA_RELACIONADA', 'factura_relacionada']);
        if ($facturaRelacionada !== null && $facturaRelacionada !== '') {
            $relation['factura_relacionada'] = (int) $facturaRelacionada;
        }

        return $relation;
    }

    private function entityRelation(array $inscription, int $inscriptionId, int $idpag): array
    {
        $relation = [
            'source_type' => 'INSCRIPCIO',
            'source_id' => $inscriptionId,
            'relation_type' => 'USOC_ENTITY',
            'idpag' => $idpag,
            'visible_alumne' => 0,
        ];

        $facturaRelacionada = $this->optional($inscription, ['FACTURA_RELACIONADA', 'factura_relacionada']);
        if ($facturaRelacionada !== null && $facturaRelacionada !== '') {
            $relation['factura_relacionada'] = (int) $facturaRelacionada;
        }

        return $relation;
    }

    private function studentMetadata(array $snapshot, string $studentAmount): array
    {
        $usoc = $snapshot['usoc'] ?? [];
        if (!is_array($usoc)) {
            $usoc = [];
        }

        return [
            'tipus_desc' => 4,
            'valid_desc' => 1,
            'student_amount' => $studentAmount,
            'entity_amount' => $this->optionalString($usoc, ['entity_amount', 'ENTITY_AMOUNT']),
        ];
    }

    private function entityMetadata(array $snapshot, string $amount, string $studentInvoiceUuid): array
    {
        $usoc = $snapshot['usoc'] ?? [];
        if (!is_array($usoc)) {
            $usoc = [];
        }

        return [
            'tipus_desc' => 4,
            'valid_desc' => 1,
            'student_amount' => $this->optionalString($usoc, ['student_amount', 'STUDENT_AMOUNT']),
            'entity_amount' => $amount,
            'student_invoice_uuid' => $studentInvoiceUuid,
        ];
    }

    private function detail(array $inscription): string
    {
        $year = $this->year($inscription);
        $month = $this->month($this->required($inscription, ['MES', 'mes'], 'inscription.MES'));
        $courseCode = $this->requiredString($inscription, ['CURS', 'curs'], 'inscription.CURS');

        return 'Convocatoria ' . $year . '/' . $month . ' - ' . $courseCode;
    }

    private function courseTitle(array $course): string
    {
        return $this->requiredString($course, ['NOM_CURS', 'TITOL', 'title', 'nom_curs'], 'course.NOM_CURS');
    }

    private function inscriptionId(array $inscription): int
    {
        $id = (int) $this->required($inscription, ['ID', 'id'], 'inscription.ID');
        if ($id <= 0) {
            throw SifException::validation('Invalid inscription.ID');
        }

        return $id;
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
            throw SifException::validation('Missing USOC IDPAG');
        }

        $idpag = (int) $value;
        if ($idpag <= 0) {
            throw SifException::validation('Invalid USOC IDPAG');
        }

        return $idpag;
    }

    private function year(array $inscription): int
    {
        $year = (int) $this->required($inscription, ['ANY', 'any', 'year'], 'inscription.ANY');
        if ($year <= 0) {
            throw SifException::validation('Invalid inscription.ANY');
        }

        return $year;
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
