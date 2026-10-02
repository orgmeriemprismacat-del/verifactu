<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Exception\SifException;

final class InvoiceDocumentSnapshotRepository
{
    public function __construct(private ?HashCalculator $hashCalculator = null)
    {
        $this->hashCalculator ??= new HashCalculator();
    }

    public function loadFrozen(\PDO $db, string $uuidFactura): array
    {
        $uuidFactura = strtolower(trim($uuidFactura));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuidFactura) !== 1) {
            throw SifException::validation('Invalid invoice UUID for document snapshot');
        }

        $invoiceStmt = $db->prepare(
            'SELECT UUID_FACTURA, NUM_VISIBLE, TIPUS_FACTURA, TIPUS_SERIE, ANY_FACT,
                    DATA_EMISSIO, ESTAT_FACTURA, ESTAT_COBRAMENT, ESTAT_AEAT
             FROM factura
             WHERE UUID_FACTURA = ?
             LIMIT 1'
        );
        $invoiceStmt->execute([$uuidFactura]);
        $invoice = $invoiceStmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($invoice)) {
            throw SifException::notFound('Invoice not found for document generation');
        }

        $recordStmt = $db->prepare(
            "SELECT ID, UUID_FACTURA, FISCAL_ORDER, TIPUS_REGISTRE,
                    HASH_FACT, HASH_FACT_ANT, PAYLOAD_JSON, DATE_CREATED
             FROM factura_registres
             WHERE UUID_FACTURA = ? AND TIPUS_REGISTRE = 'ALTA'
             ORDER BY FISCAL_ORDER ASC
             LIMIT 1"
        );
        $recordStmt->execute([$uuidFactura]);
        $record = $recordStmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($record)) {
            throw SifException::conflict('Immutable ALTA record not found for document generation');
        }

        $payload = json_decode((string) ($record['PAYLOAD_JSON'] ?? ''), true);
        if (!is_array($payload)) {
            throw SifException::conflict('Invalid immutable fiscal payload for document generation');
        }

        $expectedHash = strtolower(trim((string) ($record['HASH_FACT'] ?? '')));
        if (preg_match('/^[0-9a-f]{64}$/D', $expectedHash) !== 1) {
            throw SifException::conflict('Invalid immutable fiscal hash for document generation');
        }

        $previousHash = $record['HASH_FACT_ANT'] === null
            ? null
            : strtolower(trim((string) $record['HASH_FACT_ANT']));
        if ($previousHash !== null && preg_match('/^[0-9a-f]{64}$/D', $previousHash) !== 1) {
            throw SifException::conflict('Invalid previous fiscal hash for document generation');
        }

        $actualHash = $this->hashCalculator->calculate($payload, $previousHash);
        if (!hash_equals($expectedHash, $actualHash)) {
            throw SifException::conflict('Immutable fiscal payload hash mismatch for document generation');
        }

        if (
            strtolower(trim((string) ($payload['uuid_factura'] ?? ''))) !== $uuidFactura
            || trim((string) ($payload['num_visible'] ?? '')) !== trim((string) $invoice['NUM_VISIBLE'])
        ) {
            throw SifException::conflict('Fiscal payload identity does not match invoice metadata');
        }

        return [
            'invoice' => [
                'uuid_factura' => $uuidFactura,
                'num_visible' => (string) $invoice['NUM_VISIBLE'],
                'type' => (string) $invoice['TIPUS_FACTURA'],
                'series' => (string) $invoice['TIPUS_SERIE'],
                'year' => (int) $invoice['ANY_FACT'],
                'issued_at' => (string) $invoice['DATA_EMISSIO'],
                'invoice_status' => (string) $invoice['ESTAT_FACTURA'],
                'payment_status' => (string) $invoice['ESTAT_COBRAMENT'],
                'aeat_status' => (string) $invoice['ESTAT_AEAT'],
            ],
            'record' => [
                'id' => (int) $record['ID'],
                'fiscal_order' => (int) $record['FISCAL_ORDER'],
                'record_type' => (string) $record['TIPUS_REGISTRE'],
                'hash' => $expectedHash,
                'previous_hash' => $previousHash,
                'created_at' => (string) $record['DATE_CREATED'],
            ],
            'payload' => $payload,
        ];
    }
}
