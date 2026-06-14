<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;

final class HistoricalInvoiceMigrationRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function importHistoricalInvoice(\PDO $db, array $payload): array
    {
        $existing = $this->findByIdempotencyKey($db, $payload['idempotency_key'], true);
        if ($existing !== null) {
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
        $stmt = $db->prepare(
            'INSERT INTO factura (
                UUID_FACTURA, IDEMPOTENCY_KEY, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
                TIPUS_FACTURA, DATA_EMISSIO, DATA_OPERACIO, DATA_PAGAMENT,
                EMESA_ABANS_COBRAMENT, E_FACT, ESTAT_COBRAMENT, ESTAT_FACTURA, ESTAT_AEAT,
                BILLING_NOM_RAO, BILLING_NIF_CIF, BILLING_ADRECA, BILLING_CP,
                BILLING_POBLACIO, BILLING_PROVINCIA, BILLING_PAIS, BILLING_EMAIL,
                IMPORT_BASE, DESC_IMPORT, BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT,
                TOTAL, SOURCE_CHANNEL, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $uuid,
            $payload['idempotency_key'],
            $payload['series'],
            $payload['year'],
            $payload['num_seq'],
            $payload['num_visible'],
            $payload['type'],
            $payload['issue_date'],
            $payload['operation_date'] ?? null,
            $payload['payment_date'] ?? null,
            $payload['payment_status'],
            $payload['invoice_status'],
            $payload['aeat_status'],
            $payload['billing']['name'],
            $payload['billing']['nif'],
            $payload['billing']['address'] ?? null,
            $payload['billing']['cp'] ?? null,
            $payload['billing']['city'] ?? null,
            $payload['billing']['province'] ?? null,
            $payload['billing']['country'] ?? 'ES',
            $payload['billing']['email'] ?? null,
            $payload['totals']['import_base'],
            $payload['totals']['discount'] ?? '0.00',
            $payload['totals']['taxable_base'],
            $payload['totals']['iva_regim'] ?? 'EXEMPT',
            $payload['totals']['iva_pct'] ?? '0.00',
            $payload['totals']['iva_import'] ?? '0.00',
            $payload['totals']['total'],
            $payload['source_channel'],
            $payload['created_by'],
        ]);
    }

    private function insertLines(\PDO $db, array $payload, string $uuid): void
    {
        $stmt = $db->prepare(
            'INSERT INTO factura_linia (
                UUID_FACTURA, ORDRE, CONCEPTE, DETALL, QUANTITAT, PREU_UNITARI,
                IMPORT_BASE, DESC_ORIGEN, DESC_MODE, DESC_ID, DESC_CODI_PROMO,
                DESC_PCT, DESC_IMPORT, DESC_TEXT_VISIBLE, DESC_MOTIU_INTERN,
                BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT, TOTAL,
                SOURCE_TYPE, SOURCE_ID
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($payload['lines'] as $index => $line) {
            $stmt->execute([
                $uuid,
                $index + 1,
                $line['concept'],
                $line['detail'] ?? null,
                $line['quantity'],
                $line['unit_price'],
                $line['import_base'] ?? $line['base'],
                $line['discount_origin'] ?? null,
                $line['discount_mode'] ?? null,
                $line['discount_id'] ?? null,
                $line['discount_code'] ?? null,
                $line['discount_pct'] ?? null,
                $line['discount_amount'] ?? '0.00',
                $line['discount_text'] ?? null,
                $line['discount_internal_reason'] ?? null,
                $line['taxable_base'] ?? $line['base'],
                $line['iva_regim'] ?? 'EXEMPT',
                $line['iva_pct'] ?? '0.00',
                $line['iva_import'] ?? '0.00',
                $line['total'],
                $line['source_type'] ?? null,
                $line['source_id'] ?? null,
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
                $rel['visible_alumne'] ?? 1,
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
