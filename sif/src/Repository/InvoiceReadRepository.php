<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class InvoiceReadRepository
{
    public function findByUuid(\PDO $db, string $uuidFactura): ?array
    {
        $stmt = $db->prepare('SELECT * FROM factura WHERE UUID_FACTURA = ?');
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findLines(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT * FROM factura_linia WHERE UUID_FACTURA = ? ORDER BY ORDRE ASC, ID ASC'
        );
        $stmt->execute([$uuidFactura]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findRelations(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT ID, UUID_FACTURA, FACTURA_RELACIONADA, SOURCE_TYPE, SOURCE_ID, RELATION_TYPE,
                    ID_FACTURA_LINIA, IDPAG, DS_ORDER, VISIBLE_ALUMNE, CREATED_AT
             FROM fact_rels
             WHERE UUID_FACTURA = ?
             ORDER BY ID ASC'
        );
        $stmt->execute([$uuidFactura]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findRectifications(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT fr.ID, fr.UUID_FACTURA_RECTIFICATIVA, fr.UUID_FACTURA_RECTIFICADA,
                    fr.MOTIU, fr.MODE_RECTIFICACIO, fr.DETAILS, fr.CREATED_AT,
                    rect.NUM_VISIBLE AS RECTIFICATIVA_NUM_VISIBLE,
                    orig.NUM_VISIBLE AS RECTIFICADA_NUM_VISIBLE
             FROM factura_rectificacio fr
             JOIN factura rect ON rect.UUID_FACTURA = fr.UUID_FACTURA_RECTIFICATIVA
             JOIN factura orig ON orig.UUID_FACTURA = fr.UUID_FACTURA_RECTIFICADA
             WHERE fr.UUID_FACTURA_RECTIFICATIVA = ? OR fr.UUID_FACTURA_RECTIFICADA = ?
             ORDER BY fr.CREATED_AT ASC, fr.ID ASC'
        );
        $stmt->execute([$uuidFactura, $uuidFactura]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findPayments(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT pt.UUID_PAYMENT, pt.TIPUS_MOVIMENT, pt.METODE, pt.SOURCE_CHANNEL,
                    pt.IMPORT, pt.DATA_MOVIMENT, pt.PROVIDER_REF, pt.DS_ORDER, pt.IDPAG,
                    pt.REFERENCIA_BANCARIA, pt.ESTAT,
                    pa.IMPORT_ASSIGNAT, pa.TIPUS_ASSIGNACIO, pa.CREATED_AT AS ALLOCATION_CREATED_AT
             FROM payment_allocation pa
             JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
             ORDER BY pt.DATA_MOVIMENT ASC, pa.ID ASC'
        );
        $stmt->execute([$uuidFactura]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function latestFiscalRecord(\PDO $db, string $uuidFactura): ?array
    {
        $stmt = $db->prepare(
            'SELECT ID, UUID_FACTURA, FISCAL_ORDER, TIPUS_REGISTRE, HASH_FACT, HASH_FACT_ANT,
                    ESTAT_AEAT, DATE_CREATED, DATE_SENT
             FROM factura_registres
             WHERE UUID_FACTURA = ?
             ORDER BY FISCAL_ORDER DESC
             LIMIT 1'
        );
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findDocumentMetadata(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT ID, UUID_FACTURA, TIPUS, HASH_FITXER, ESTAT, CREATED_AT
             FROM factura_documents
             WHERE UUID_FACTURA = ?
             ORDER BY CREATED_AT ASC, ID ASC'
        );
        $stmt->execute([$uuidFactura]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function search(\PDO $db, array $criteria, int $limit = 50): array
    {
        $allowed = [
            'uuid_factura' => 'f.UUID_FACTURA = ?',
            'num_visible' => 'f.NUM_VISIBLE = ?',
            'billing_nif' => 'f.BILLING_NIF_CIF = ?',
            'billing_email' => 'f.BILLING_EMAIL = ?',
        ];

        $where = [];
        $params = [];

        foreach ($allowed as $key => $clause) {
            if (!array_key_exists($key, $criteria) || $criteria[$key] === null || $criteria[$key] === '') {
                continue;
            }

            $where[] = $clause;
            $params[] = (string) $criteria[$key];
        }

        $hasSourceType = array_key_exists('source_type', $criteria)
            && $criteria['source_type'] !== null
            && $criteria['source_type'] !== '';
        $hasSourceId = array_key_exists('source_id', $criteria)
            && $criteria['source_id'] !== null
            && $criteria['source_id'] !== '';

        if ($hasSourceType xor $hasSourceId) {
            throw SifException::validation('source_type and source_id must be provided together');
        }

        if ($hasSourceType && $hasSourceId) {
            $sourceType = strtoupper(trim((string) $criteria['source_type']));
            if (!preg_match('/^[A-Z0-9_]{1,30}$/D', $sourceType)) {
                throw SifException::validation('Invalid invoice source type');
            }
            if (!is_int($criteria['source_id']) && !ctype_digit((string) $criteria['source_id'])) {
                throw SifException::validation('Invalid invoice source id');
            }

            $where[] = 'EXISTS (
                SELECT 1 FROM fact_rels rel_source
                WHERE rel_source.UUID_FACTURA = f.UUID_FACTURA
                  AND rel_source.SOURCE_TYPE = ?
                  AND rel_source.SOURCE_ID = ?
            )';
            $params[] = $sourceType;
            $params[] = (int) $criteria['source_id'];
        }

        if (array_key_exists('factura_relacionada', $criteria)
            && $criteria['factura_relacionada'] !== null
            && $criteria['factura_relacionada'] !== '') {
            if (!is_int($criteria['factura_relacionada'])
                && !ctype_digit((string) $criteria['factura_relacionada'])) {
                throw SifException::validation('Invalid legacy invoice relation');
            }

            $where[] = 'EXISTS (
                SELECT 1 FROM fact_rels rel
                WHERE rel.UUID_FACTURA = f.UUID_FACTURA
                  AND rel.FACTURA_RELACIONADA = ?
            )';
            $params[] = (int) $criteria['factura_relacionada'];
        }

        if (isset($criteria['source_ids']) && is_array($criteria['source_ids']) && $criteria['source_ids'] !== []) {
            $placeholders = implode(',', array_fill(0, count($criteria['source_ids']), '?'));
            $where[] = "EXISTS (
                SELECT 1 FROM fact_rels rel_source
                WHERE rel_source.UUID_FACTURA = f.UUID_FACTURA
                  AND rel_source.SOURCE_TYPE = 'INSCRIPCIO'
                  AND rel_source.SOURCE_ID IN (" . $placeholders . ")
            )";
            foreach ($criteria['source_ids'] as $sourceId) {
                $params[] = (int) $sourceId;
            }
        }

        if ($where === []) {
            throw SifException::validation('At least one invoice search criterion is required');
        }

        $limit = max(1, min(100, $limit));
        $sql = 'SELECT f.*
                FROM factura f
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY f.ANY_FACT DESC, f.TIPUS_SERIE ASC, f.NUM_SEQ DESC
                LIMIT ' . $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
