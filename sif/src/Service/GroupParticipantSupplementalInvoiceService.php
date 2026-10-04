<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class GroupParticipantSupplementalInvoiceService
{
    public function __construct(private InvoiceService $invoices)
    {
    }

    public function issue(
        \PDO $db,
        string $uuidOriginal,
        array $candidate,
        array $context = []
    ): array {
        $uuidOriginal = trim($uuidOriginal);
        if ($uuidOriginal === '') {
            throw SifException::validation('Supplemental group invoice requires original invoice');
        }

        $idInsc = $this->positiveInt($candidate['id_insc'] ?? null, 'Invalid supplemental enrollment ID');
        $idpag = $this->positiveInt($candidate['idpag'] ?? null, 'Invalid supplemental group IDPAG');
        $concept = trim((string) ($candidate['concept'] ?? ''));
        if ($concept === '') {
            throw SifException::validation('Supplemental participant concept is required');
        }

        $base = $this->money($candidate['base'] ?? null);
        $discount = $this->money($candidate['discount'] ?? '0.00');
        $total = $this->money($candidate['total'] ?? null);
        if ($this->cents($base) <= 0
            || $this->cents($discount) < 0
            || $this->cents($base) - $this->cents($discount) !== $this->cents($total)
        ) {
            throw SifException::validation('Supplemental participant amounts are inconsistent');
        }

        $original = $this->originalInvoice($db, $uuidOriginal, $idpag);
        $this->assertParticipantNotAlreadyInOriginal($db, $uuidOriginal, $idInsc);
        $tax = $this->homogeneousGroupTaxProfile($db, $uuidOriginal);

        // The current group builder emits exempt training lines. Do not infer a
        // taxable breakdown for a supplemental invoice from only base/discount/total.
        if ($tax['iva_regim'] !== 'EXEMPT' || $tax['iva_pct'] !== '0.00') {
            throw SifException::conflict(
                'Supplemental participant invoice requires explicit tax breakdown for non-exempt group'
            );
        }

        $requestId = $this->optionalString($context['request_id'] ?? null);
        $correlationId = $this->optionalString($context['correlation_id'] ?? null);
        $actorId = $this->optionalString($context['actor_id'] ?? null);
        $actorRole = $this->optionalString($context['actor_role'] ?? null);
        $operationReference = $this->optionalString($context['operation_reference'] ?? null);

        $payload = [
            'idempotency_key' => $this->idempotencyKey($uuidOriginal, $idInsc, $operationReference),
            'series' => 'A',
            'year' => (int) $original['ANY_FACT'],
            'type' => 'F1',
            'source_type' => 'GRUP',
            'source_channel' => 'INTRANET',
            'created_by' => $actorId,
            'billing' => [
                'name' => (string) $original['BILLING_NOM_RAO'],
                'nif' => (string) $original['BILLING_NIF_CIF'],
                'address' => $original['BILLING_ADRECA'],
                'cp' => $original['BILLING_CP'],
                'city' => $original['BILLING_POBLACIO'],
                'province' => $original['BILLING_PROVINCIA'],
                'country' => $original['BILLING_PAIS'] ?: 'ES',
                'email' => $original['BILLING_EMAIL'],
            ],
            'totals' => [
                'import_base' => $base,
                'discount' => $discount,
                'taxable_base' => $total,
                'iva_regim' => $tax['iva_regim'],
                'iva_pct' => $tax['iva_pct'],
                'iva_import' => '0.00',
                'exemption_reason' => $tax['exemption_reason'],
                'total' => $total,
            ],
            'lines' => [[
                'concept' => $concept,
                'detail' => 'Participant afegit al grup després de la factura ' . $original['NUM_VISIBLE'],
                'quantity' => '1.00',
                'unit_price' => $base,
                'base' => $base,
                'import_base' => $base,
                'discount_origin' => $this->cents($discount) > 0 ? 'GRUP' : null,
                'discount_mode' => $this->cents($discount) > 0 ? 'AMOUNT' : null,
                'discount_amount' => $discount,
                'discount_text' => $this->cents($discount) > 0 ? 'Descompte grup congelat' : null,
                'discount_internal_reason' => $this->cents($discount) > 0
                    ? 'UC-016A: preu del nou participant aprovat sense reprecificar membres existents'
                    : null,
                'taxable_base' => $total,
                'iva_regim' => $tax['iva_regim'],
                'iva_pct' => $tax['iva_pct'],
                'iva_import' => '0.00',
                'exemption_reason' => $tax['exemption_reason'],
                'total' => $total,
                'source_type' => 'INSCRIPCIO',
                'source_id' => $idInsc,
            ]],
            'relations' => [
                [
                    'source_type' => 'GRUP',
                    'source_id' => $idpag,
                    'idpag' => $idpag,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ],
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => $idInsc,
                    'idpag' => $idpag,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ],
            ],
        ];

        if ($requestId !== null) {
            $payload['request_id'] = $requestId;
        }
        if ($correlationId !== null) {
            $payload['correlation_id'] = $correlationId;
        }
        if ($actorId !== null) {
            $payload['actor_type'] = 'HUMAN';
        }
        if ($actorRole !== null) {
            $payload['actor_role'] = $actorRole;
        }

        $result = $this->invoices->issueInvoice($payload);
        $result['uuid_factura_origen_grup'] = $uuidOriginal;
        $result['id_insc_afegida'] = $idInsc;
        $result['idpag'] = $idpag;

        return $result;
    }

    private function originalInvoice(\PDO $db, string $uuidOriginal, int $idpag): array
    {
        $stmt = $db->prepare(
            "SELECT f.*
             FROM factura f
             INNER JOIN fact_rels fr
               ON fr.UUID_FACTURA=f.UUID_FACTURA
              AND fr.SOURCE_TYPE='GRUP'
              AND fr.IDPAG=?
             WHERE f.UUID_FACTURA=?"
        );
        $stmt->execute([$idpag, $uuidOriginal]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw SifException::conflict('Original invoice is not uniquely linked to requested group');
        }

        return $rows[0];
    }

    private function assertParticipantNotAlreadyInOriginal(
        \PDO $db,
        string $uuidOriginal,
        int $idInsc
    ): void {
        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM fact_rels
             WHERE UUID_FACTURA=?
               AND SOURCE_TYPE='INSCRIPCIO'
               AND SOURCE_ID=?"
        );
        $stmt->execute([$uuidOriginal, $idInsc]);
        if ((int) $stmt->fetchColumn() !== 0) {
            throw SifException::conflict('Participant is already linked to original group invoice');
        }
    }

    private function homogeneousGroupTaxProfile(\PDO $db, string $uuidOriginal): array
    {
        $stmt = $db->prepare(
            "SELECT DISTINCT
                    UPPER(COALESCE(IVA_REGIM, '')) AS IVA_REGIM,
                    CAST(COALESCE(IVA_PCT, 0) AS DECIMAL(8,2)) AS IVA_PCT,
                    COALESCE(CAUSA_EXEMPCIO_NO_SUBJECTA, '') AS EXEMPTION_REASON
             FROM factura_linia
             WHERE UUID_FACTURA=?
               AND SOURCE_TYPE='INSCRIPCIO'"
        );
        $stmt->execute([$uuidOriginal]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw SifException::conflict(
                'Original group invoice has heterogeneous or missing fiscal line profile'
            );
        }

        $regime = strtoupper(trim((string) $rows[0]['IVA_REGIM']));
        $pct = number_format((float) $rows[0]['IVA_PCT'], 2, '.', '');
        $reason = trim((string) $rows[0]['EXEMPTION_REASON']);

        return [
            'iva_regim' => $regime,
            'iva_pct' => $pct,
            'exemption_reason' => $reason === '' ? null : strtoupper($reason),
        ];
    }

    private function idempotencyKey(
        string $uuidOriginal,
        int $idInsc,
        ?string $operationReference
    ): string {
        $seed = $uuidOriginal . '|' . ($operationReference ?? '');
        return 'UC016A|SUPP|ORIG:' . substr(hash('sha256', $seed), 0, 16)
            . '|INSC:' . $idInsc;
    }

    private function positiveInt(mixed $value, string $message): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
            throw SifException::validation($message);
        }

        return (int) $raw;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid supplemental participant amount');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function cents(string $value): int
    {
        return (int) round((float) $value * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
