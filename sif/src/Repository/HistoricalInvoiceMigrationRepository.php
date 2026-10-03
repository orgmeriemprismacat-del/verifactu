<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class HistoricalInvoiceMigrationRepository
{
    private PayloadIdempotencyValidatorInterface $idempotency;

    public function __construct(
        private UuidGenerator $uuidGenerator,
        ?PayloadIdempotencyValidatorInterface $idempotency = null
    ) {
        $this->idempotency = $idempotency ?? new PayloadIdempotencyValidator();
    }

    public function importHistoricalInvoice(\PDO $db, array $payload): array
    {
        $existing = $this->findByIdempotencyKey($db, $payload['idempotency_key'], true);
        if ($existing !== null) {
            $this->idempotency->assertMatches(
                $this->idempotencyPayload($payload),
                (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? '')
            );

            return $this->existingResult($existing);
        }

        $uuid = $this->uuidGenerator->generate();
        $this->insertInvoice($db, $payload, $uuid);
        $this->insertLines($db, $payload, $uuid);
        $this->insertRelations($db, $payload, $uuid);

        if (isset($payload['document']) && is_array($payload['document'])) {
            $this->insertDocument($db, $payload, $uuid);
        }

        return [
            'ok' => true,
            'idempotency_reused' => false,
            'uuid_factura' => $uuid,
            'num_visible' => $payload['num_visible'],
            'aeat_status' => 'NO_VERIFACTU',
        ];
    }

    public function findByIdempotencyKey(\PDO $db, string $key, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM factura WHERE IDEMPOTENCY_KEY = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function insertInvoice(\PDO $db, array $payload, string $uuid): void
    {
        $issuer = is_array($payload['issuer'] ?? null) ? $payload['issuer'] : [];
        $totals = $payload['totals'];

        $stmt = $db->prepare(
            'INSERT INTO factura (
                UUID_FACTURA, IDEMPOTENCY_KEY, IDEMPOTENCY_PAYLOAD_HASH, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
                TIPUS_FACTURA, DATA_EMISSIO, DATA_OPERACIO, DATA_PAGAMENT,
                EMESA_ABANS_COBRAMENT, E_FACT, EMISSOR_NIF, EMISSOR_NOM,
                ESTAT_COBRAMENT, ESTAT_FACTURA, ESTAT_AEAT,
                BILLING_NOM_RAO, BILLING_NIF_CIF, BILLING_ADRECA, BILLING_CP,
                BILLING_POBLACIO, BILLING_PROVINCIA, BILLING_PAIS, BILLING_EMAIL, DESCRIPCIO_OPERACIO,
                IMPORT_BASE, DESC_IMPORT, BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT,
                INVERSIO_SUBJECTE_PASSIU, CAUSA_EXEMPCIO_NO_SUBJECTA,
                RECARREC_EQUIVALENCIA_PCT, RECARREC_EQUIVALENCIA_IMPORT,
                TOTAL, SOURCE_CHANNEL, CREATED_BY
            ) VALUES (
                :uuid, :idempotency_key, :idempotency_payload_hash, :series, :year, :num_seq, :num_visible,
                :type, :issue_date, :operation_date, :payment_date,
                0, 0, :issuer_nif, :issuer_name,
                :payment_status, :invoice_status, :aeat_status,
                :billing_name, :billing_nif, :billing_address, :billing_cp,
                :billing_city, :billing_province, :billing_country, :billing_email, :operation_description,
                :import_base, :discount, :taxable_base, :iva_regim, :iva_pct, :iva_import,
                :reverse_charge, :exemption_reason, :rec_equivalence_pct, :rec_equivalence_import,
                :total, :source_channel, :created_by
            )'
        );

        $stmt->execute([
            'uuid' => $uuid,
            'idempotency_key' => $payload['idempotency_key'],
            'idempotency_payload_hash' => $this->idempotency->calculateHash($this->idempotencyPayload($payload)),
            'series' => $payload['series'],
            'year' => $payload['year'],
            'num_seq' => $payload['num_seq'],
            'num_visible' => $payload['num_visible'],
            'type' => $payload['type'],
            'issue_date' => $payload['issue_date'],
            'operation_date' => $payload['operation_date'] ?? null,
            'payment_date' => $payload['payment_date'] ?? null,
            'issuer_nif' => $issuer['nif'] ?? null,
            'issuer_name' => $issuer['name'] ?? null,
            'payment_status' => $payload['payment_status'],
            'invoice_status' => $payload['invoice_status'],
            'aeat_status' => $payload['aeat_status'],
            'billing_name' => $payload['billing']['name'],
            'billing_nif' => $payload['billing']['nif'],
            'billing_address' => $payload['billing']['address'] ?? null,
            'billing_cp' => $payload['billing']['cp'] ?? null,
            'billing_city' => $payload['billing']['city'] ?? null,
            'billing_province' => $payload['billing']['province'] ?? null,
            'billing_country' => $payload['billing']['country'] ?? 'ES',
            'billing_email' => $payload['billing']['email'] ?? null,
            'operation_description' => $payload['operation_description'] ?? null,
            'import_base' => $totals['import_base'],
            'discount' => $totals['discount'] ?? '0.00',
            'taxable_base' => $totals['taxable_base'],
            'iva_regim' => $totals['iva_regim'] ?? 'EXEMPT',
            'iva_pct' => $totals['iva_pct'] ?? '0.00',
            'iva_import' => $totals['iva_import'] ?? '0.00',
            'reverse_charge' => $totals['inversion_subjecte_passiu'] ?? 0,
            'exemption_reason' => $totals['exemption_reason'] ?? null,
            'rec_equivalence_pct' => $totals['rec_equivalence_pct'] ?? null,
            'rec_equivalence_import' => $totals['rec_equivalence_import'] ?? null,
            'total' => $totals['total'],
            'source_channel' => $payload['source_channel'],
            'created_by' => $payload['created_by'],
        ]);
    }

    private function insertLines(\PDO $db, array $payload, string $uuid): void
    {
        $stmt = $db->prepare(
            'INSERT INTO factura_linia (
                UUID_FACTURA, ORDRE, CONCEPTE, DETALL, QUANTITAT, PREU_UNITARI,
                IMPORT_BASE, DESC_ORIGEN, DESC_MODE, DESC_ID, DESC_CODI_PROMO,
                DESC_PCT, DESC_IMPORT, DESC_TEXT_VISIBLE, DESC_MOTIU_INTERN,
                BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT,
                INVERSIO_SUBJECTE_PASSIU, CAUSA_EXEMPCIO_NO_SUBJECTA,
                RECARREC_EQUIVALENCIA_PCT, RECARREC_EQUIVALENCIA_IMPORT,
                TOTAL, SOURCE_TYPE, SOURCE_ID
            ) VALUES (
                :uuid, :ordre, :concept, :detail, :quantity, :unit_price,
                :import_base, :discount_origin, :discount_mode, :discount_id, :discount_code,
                :discount_pct, :discount_amount, :discount_text, :discount_internal_reason,
                :taxable_base, :iva_regim, :iva_pct, :iva_import,
                :reverse_charge, :exemption_reason, :rec_equivalence_pct, :rec_equivalence_import,
                :total, :source_type, :source_id
            )'
        );

        foreach ($payload['lines'] as $index => $line) {
            $stmt->execute([
                'uuid' => $uuid,
                'ordre' => $index + 1,
                'concept' => $line['concept'],
                'detail' => $line['detail'] ?? null,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'import_base' => $line['import_base'] ?? $line['base'],
                'discount_origin' => $line['discount_origin'] ?? null,
                'discount_mode' => $line['discount_mode'] ?? null,
                'discount_id' => $line['discount_id'] ?? null,
                'discount_code' => $line['discount_code'] ?? null,
                'discount_pct' => $line['discount_pct'] ?? null,
                'discount_amount' => $line['discount_amount'] ?? '0.00',
                'discount_text' => $line['discount_text'] ?? null,
                'discount_internal_reason' => $line['discount_internal_reason'] ?? null,
                'taxable_base' => $line['taxable_base'] ?? $line['base'],
                'iva_regim' => $line['iva_regim'] ?? 'EXEMPT',
                'iva_pct' => $line['iva_pct'] ?? '0.00',
                'iva_import' => $line['iva_import'] ?? '0.00',
                'reverse_charge' => $line['inversion_subjecte_passiu'] ?? 0,
                'exemption_reason' => $line['exemption_reason'] ?? null,
                'rec_equivalence_pct' => $line['rec_equivalence_pct'] ?? null,
                'rec_equivalence_import' => $line['rec_equivalence_import'] ?? null,
                'total' => $line['total'],
                'source_type' => $line['source_type'] ?? null,
                'source_id' => $line['source_id'] ?? null,
            ]);
        }
    }

    private function insertRelations(\PDO $db, array $payload, string $uuid): void
    {
        $stmt = $db->prepare(
            'INSERT INTO fact_rels (
                UUID_FACTURA, FACTURA_RELACIONADA, SOURCE_TYPE, SOURCE_ID, RELATION_TYPE,
                IDPAG, DS_ORDER, VISIBLE_ALUMNE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($payload['relations'] as $rel) {
            $stmt->execute([
                $uuid,
                $rel['factura_relacionada'] ?? null,
                $rel['source_type'],
                $rel['source_id'] ?? null,
                $rel['relation_type'] ?? 'HISTORIC_LINK',
                $rel['idpag'] ?? null,
                $rel['ds_order'] ?? null,
                $rel['visible_alumne'] ?? 0,
            ]);
        }
    }

    private function insertDocument(\PDO $db, array $payload, string $uuid): void
    {
        $document = $payload['document'];
        $db->prepare(
            'INSERT INTO factura_documents (UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER, ESTAT)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
            $document['type'],
            $document['path'],
            $document['hash'],
            $document['status'],
        ]);
    }

    private function idempotencyPayload(array $payload): array
    {
        $issuer = is_array($payload['issuer'] ?? null) ? $payload['issuer'] : [];
        $billing = $payload['billing'];
        $totals = $payload['totals'];

        $relations = array_map(
            static fn (array $rel): array => [
                'factura_relacionada' => $rel['factura_relacionada'] ?? null,
                'source_type' => $rel['source_type'],
                'source_id' => $rel['source_id'] ?? null,
                'relation_type' => $rel['relation_type'] ?? 'HISTORIC_LINK',
                'idpag' => $rel['idpag'] ?? null,
                'ds_order' => $rel['ds_order'] ?? null,
                'visible_alumne' => $rel['visible_alumne'] ?? 0,
            ],
            $payload['relations']
        );
        usort($relations, static function (array $a, array $b): int {
            return strcmp(
                json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
        });

        $document = null;
        if (isset($payload['document']) && is_array($payload['document'])) {
            $document = [
                'type' => $payload['document']['type'],
                'path' => $payload['document']['path'],
                'hash' => $payload['document']['hash'],
                'status' => $payload['document']['status'],
            ];
        }

        return [
            'num_visible' => $payload['num_visible'],
            'series' => $payload['series'],
            'year' => $payload['year'],
            'num_seq' => $payload['num_seq'],
            'type' => $payload['type'],
            'issue_date' => $payload['issue_date'],
            'operation_date' => $payload['operation_date'] ?? null,
            'payment_date' => $payload['payment_date'] ?? null,
            'payment_status' => $payload['payment_status'],
            'invoice_status' => $payload['invoice_status'],
            'aeat_status' => $payload['aeat_status'],
            'issuer' => [
                'nif' => $issuer['nif'] ?? null,
                'name' => $issuer['name'] ?? null,
            ],
            'operation_description' => $payload['operation_description'] ?? null,
            'billing' => [
                'name' => $billing['name'],
                'nif' => $billing['nif'],
                'address' => $billing['address'] ?? null,
                'cp' => $billing['cp'] ?? null,
                'city' => $billing['city'] ?? null,
                'province' => $billing['province'] ?? null,
                'country' => $billing['country'] ?? 'ES',
                'email' => $billing['email'] ?? null,
            ],
            'totals' => [
                'import_base' => $this->decimalValue($totals['import_base']),
                'discount' => $this->decimalValue($totals['discount'] ?? '0.00'),
                'taxable_base' => $this->decimalValue($totals['taxable_base']),
                'iva_regim' => $totals['iva_regim'] ?? 'EXEMPT',
                'iva_pct' => $this->decimalValue($totals['iva_pct'] ?? '0.00'),
                'iva_import' => $this->decimalValue($totals['iva_import'] ?? '0.00'),
                'inversion_subjecte_passiu' => $totals['inversion_subjecte_passiu'] ?? 0,
                'exemption_reason' => $totals['exemption_reason'] ?? null,
                'rec_equivalence_pct' => $this->decimalValue($totals['rec_equivalence_pct'] ?? null),
                'rec_equivalence_import' => $this->decimalValue($totals['rec_equivalence_import'] ?? null),
                'total' => $this->decimalValue($totals['total']),
            ],
            'lines' => array_map(static fn (array $line): array => [
                'concept' => $line['concept'],
                'detail' => $line['detail'] ?? null,
                'quantity' => $this->decimalValue($line['quantity']),
                'unit_price' => $this->decimalValue($line['unit_price']),
                'import_base' => $this->decimalValue($line['import_base'] ?? $line['base']),
                'discount_origin' => $line['discount_origin'] ?? null,
                'discount_mode' => $line['discount_mode'] ?? null,
                'discount_id' => $line['discount_id'] ?? null,
                'discount_code' => $line['discount_code'] ?? null,
                'discount_pct' => $this->decimalValue($line['discount_pct'] ?? null),
                'discount_amount' => $this->decimalValue($line['discount_amount'] ?? '0.00'),
                'discount_text' => $line['discount_text'] ?? null,
                'discount_internal_reason' => $line['discount_internal_reason'] ?? null,
                'taxable_base' => $this->decimalValue($line['taxable_base'] ?? $line['base']),
                'iva_regim' => $line['iva_regim'] ?? 'EXEMPT',
                'iva_pct' => $this->decimalValue($line['iva_pct'] ?? '0.00'),
                'iva_import' => $this->decimalValue($line['iva_import'] ?? '0.00'),
                'inversion_subjecte_passiu' => $line['inversion_subjecte_passiu'] ?? 0,
                'exemption_reason' => $line['exemption_reason'] ?? null,
                'rec_equivalence_pct' => $this->decimalValue($line['rec_equivalence_pct'] ?? null),
                'rec_equivalence_import' => $this->decimalValue($line['rec_equivalence_import'] ?? null),
                'total' => $this->decimalValue($line['total']),
                'source_type' => $line['source_type'] ?? null,
                'source_id' => $line['source_id'] ?? null,
            ], $payload['lines']),
            'relations' => $relations,
            'document' => $document,
        ];
    }

    private function decimalValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function existingResult(array $existing): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => true,
            'uuid_factura' => $existing['UUID_FACTURA'],
            'num_visible' => $existing['NUM_VISIBLE'],
            'aeat_status' => 'NO_VERIFACTU',
        ];
    }
}
