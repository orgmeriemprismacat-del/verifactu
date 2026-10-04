<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class HistoricalInvoiceInventoryService
{
    public function inventory(
        \PDO $sifDb,
        \PDO $legacyDb,
        ?int $legacyInvoiceId = null,
        ?PrivateDocumentStore $documentStore = null
    ): array {
        if ($legacyInvoiceId !== null && $legacyInvoiceId <= 0) {
            throw SifException::validation('Invalid historical invoice legacy ID');
        }

        $sql = 'SELECT id, factura_relacionada, any, ordre, num, data, data_pagament,
                       generada, rao, cif, adreca, cp, poblacio, concepte1, concepte2,
                       import, entitat, forma_pagament, curs, hores, observacions, E_FACT
                FROM factures';
        $params = [];
        if ($legacyInvoiceId !== null) {
            $sql .= ' WHERE id = ?';
            $params[] = $legacyInvoiceId;
        }
        $sql .= ' ORDER BY id';

        $stmt = $legacyDb->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = $this->assess($sifDb, $row, $documentStore);
        }

        $summary = [
            'total' => count($items),
            'imported_match' => 0,
            'not_imported' => 0,
            'mismatch' => 0,
            'ambiguous' => 0,
            'invalid_legacy' => 0,
            'documents_total' => 0,
            'documents_verified' => 0,
            'documents_unverified' => 0,
            'blocking' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            if ($status === 'IMPORTED_MATCH') {
                $summary['imported_match']++;
            } elseif ($status === 'NOT_IMPORTED') {
                $summary['not_imported']++;
            } elseif ($status === 'IMPORTED_MISMATCH') {
                $summary['mismatch']++;
                $summary['blocking']++;
            } elseif ($status === 'AMBIGUOUS_SIF_MATCH') {
                $summary['ambiguous']++;
                $summary['blocking']++;
            } elseif ($status === 'INVALID_LEGACY_INVOICE') {
                $summary['invalid_legacy']++;
                $summary['blocking']++;
            }

            foreach ((array) ($item['documents'] ?? []) as $document) {
                $summary['documents_total']++;
                if (($document['verification'] ?? '') === 'VERIFIED') {
                    $summary['documents_verified']++;
                } else {
                    $summary['documents_unverified']++;
                }
            }
        }

        return [
            'ok' => true,
            'read_only' => true,
            'document_verification_enabled' => $documentStore !== null,
            'fully_reconciled' => $summary['blocking'] === 0
                && $summary['not_imported'] === 0
                && $summary['documents_unverified'] === 0,
            'summary' => $summary,
            'items' => $items,
        ];
    }

    private function assess(
        \PDO $sifDb,
        array $legacy,
        ?PrivateDocumentStore $documentStore
    ): array {
        $legacyId = $this->positiveInt($legacy['id'] ?? null);
        $numVisible = trim((string) ($legacy['num'] ?? ''));
        $issueDate = trim((string) ($legacy['data'] ?? ''));
        $billingNif = strtoupper(trim((string) ($legacy['cif'] ?? '')));
        $total = $this->moneyOrNull($legacy['import'] ?? null);
        $relation = $this->nullablePositiveInt($legacy['factura_relacionada'] ?? null);

        $base = [
            'legacy_id' => $legacyId,
            'num_visible' => $numVisible,
            'issue_date' => $issueDate,
            'billing_nif' => $billingNif,
            'total' => $total,
            'factura_relacionada' => $relation,
            'legacy_e_fact' => $legacy['E_FACT'] ?? null,
        ];

        if ($legacyId <= 0
            || $numVisible === ''
            || $issueDate === ''
            || $billingNif === ''
            || $total === null
        ) {
            return $base + [
                'status' => 'INVALID_LEGACY_INVOICE',
                'reason' => 'Legacy invoice is missing identity, date, billing NIF or amount.',
                'documents' => [],
            ];
        }

        $matches = $this->sifMatches($sifDb, $legacyId);
        if ($matches === []) {
            return $base + [
                'status' => 'NOT_IMPORTED',
                'documents' => [],
            ];
        }

        if (count($matches) !== 1) {
            return $base + [
                'status' => 'AMBIGUOUS_SIF_MATCH',
                'match_count' => count($matches),
                'documents' => [],
            ];
        }

        $invoice = $matches[0];
        $differences = [];

        $this->compare(
            $differences,
            'num_visible',
            $numVisible,
            trim((string) ($invoice['NUM_VISIBLE'] ?? ''))
        );
        $this->compare(
            $differences,
            'issue_date',
            substr($issueDate, 0, 10),
            substr((string) ($invoice['DATA_EMISSIO'] ?? ''), 0, 10)
        );
        $this->compare(
            $differences,
            'billing_nif',
            $billingNif,
            strtoupper(trim((string) ($invoice['BILLING_NIF_CIF'] ?? '')))
        );
        $this->compare(
            $differences,
            'total',
            $total,
            $this->moneyOrNull($invoice['TOTAL'] ?? null)
        );
        $this->compare(
            $differences,
            'invoice_status',
            'HISTORICAL',
            strtoupper(trim((string) ($invoice['ESTAT_FACTURA'] ?? '')))
        );
        $this->compare(
            $differences,
            'aeat_status',
            'NO_VERIFACTU',
            strtoupper(trim((string) ($invoice['ESTAT_AEAT'] ?? '')))
        );

        $sifRelation = $this->nullablePositiveInt($invoice['FACTURA_RELACIONADA'] ?? null);
        if ($relation !== null || $sifRelation !== null) {
            $this->compare($differences, 'factura_relacionada', $relation, $sifRelation);
        }

        $documents = $this->documents(
            $sifDb,
            (string) $invoice['UUID_FACTURA'],
            $documentStore
        );

        return $base + [
            'status' => $differences === [] ? 'IMPORTED_MATCH' : 'IMPORTED_MISMATCH',
            'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            'differences' => $differences,
            'documents' => $documents,
        ];
    }

    private function sifMatches(\PDO $db, int $legacyId): array
    {
        $stmt = $db->prepare(
            "SELECT DISTINCT
                    f.UUID_FACTURA, f.NUM_VISIBLE, f.DATA_EMISSIO,
                    f.BILLING_NIF_CIF, f.TOTAL, f.ESTAT_FACTURA, f.ESTAT_AEAT,
                    fr.FACTURA_RELACIONADA
             FROM fact_rels fr
             INNER JOIN factura f ON f.UUID_FACTURA = fr.UUID_FACTURA
             WHERE fr.SOURCE_TYPE = 'HISTORIC_WEB_FACTURES'
               AND fr.SOURCE_ID = ?
             ORDER BY f.UUID_FACTURA"
        );
        $stmt->execute([$legacyId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function documents(
        \PDO $db,
        string $uuidFactura,
        ?PrivateDocumentStore $documentStore
    ): array {
        $stmt = $db->prepare(
            'SELECT ID, TIPUS, PATH_FITXER, HASH_FITXER, ESTAT
             FROM factura_documents
             WHERE UUID_FACTURA = ?
             ORDER BY ID'
        );
        $stmt->execute([$uuidFactura]);

        $documents = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $path = (string) ($row['PATH_FITXER'] ?? '');
            $hash = strtolower(trim((string) ($row['HASH_FITXER'] ?? '')));
            $assessment = [
                'id' => (int) ($row['ID'] ?? 0),
                'type' => strtoupper(trim((string) ($row['TIPUS'] ?? ''))),
                'status' => strtoupper(trim((string) ($row['ESTAT'] ?? ''))),
                'hash' => $hash,
                'path_hash' => hash('sha256', $path),
                'verification' => $documentStore === null ? 'NOT_CHECKED' : 'UNVERIFIED',
            ];

            if ($documentStore !== null) {
                try {
                    $bytes = $documentStore->readVerified($path, $hash);
                    $assessment['verification'] = 'VERIFIED';
                    $assessment['size_bytes'] = strlen($bytes);
                } catch (\Throwable $exception) {
                    $assessment['verification'] = match ((int) $exception->getCode()) {
                        403 => 'PATH_OUTSIDE_STORAGE',
                        409 => 'HASH_MISMATCH',
                        default => 'UNAVAILABLE',
                    };
                }
            }

            $documents[] = $assessment;
        }

        return $documents;
    }

    private function compare(array &$differences, string $field, mixed $legacy, mixed $sif): void
    {
        if ($legacy === $sif) {
            return;
        }

        $differences[] = [
            'field' => $field,
            'legacy' => $legacy,
            'sif' => $sif,
        ];
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function moneyOrNull(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
