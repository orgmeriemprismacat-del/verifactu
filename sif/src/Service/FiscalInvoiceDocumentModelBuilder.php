<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceReadRepository;

final class FiscalInvoiceDocumentModelBuilder
{
    public function __construct(
        private InvoiceReadRepository $invoices,
        private AeatInvoiceQrUrlBuilder $qr,
        private string $issuerNif,
        private string $issuerName
    ) {
        $this->issuerNif = strtoupper(trim($this->issuerNif));
        $this->issuerName = trim($this->issuerName);

        if ($this->issuerName === '') {
            throw new \RuntimeException('Invoice document issuer name is not configured');
        }
    }

    public function build(\PDO $db, string $uuidFactura): array
    {
        $uuidFactura = strtolower(trim($uuidFactura));
        $invoice = $this->invoices->findByUuid($db, $uuidFactura);
        if ($invoice === null) {
            throw SifException::notFound('Invoice not found for fiscal document');
        }

        $lines = $this->invoices->findLines($db, $uuidFactura);
        if ($lines === []) {
            throw SifException::conflict('Fiscal document invoice has no persisted lines');
        }

        $record = $this->invoices->latestFiscalRecord($db, $uuidFactura);
        if ($record === null) {
            throw SifException::conflict('Fiscal document invoice has no fiscal record');
        }

        $qr = $this->qr->build(
            $this->issuerNif,
            (string) $invoice['NUM_VISIBLE'],
            (string) $invoice['DATA_EMISSIO'],
            (string) $invoice['TOTAL']
        );

        return [
            'snapshot_source' => 'SIF_PERSISTED_INVOICE',
            'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            'num_visible' => (string) $invoice['NUM_VISIBLE'],
            'invoice_type' => (string) $invoice['TIPUS_FACTURA'],
            'series' => (string) $invoice['TIPUS_SERIE'],
            'year' => (int) $invoice['ANY_FACT'],
            'sequence' => (int) $invoice['NUM_SEQ'],
            'issued_at' => (string) $invoice['DATA_EMISSIO'],
            'operation_date' => $invoice['DATA_OPERACIO'] ?? null,
            'invoice_status' => (string) $invoice['ESTAT_FACTURA'],
            'payment_status' => (string) $invoice['ESTAT_COBRAMENT'],
            'aeat_status' => (string) $invoice['ESTAT_AEAT'],
            'issued_before_payment' => (int) $invoice['EMESA_ABANS_COBRAMENT'] === 1,
            'issuer' => [
                'name' => $this->issuerName,
                'nif' => $this->issuerNif,
            ],
            'billing' => [
                'name' => (string) $invoice['BILLING_NOM_RAO'],
                'nif' => (string) $invoice['BILLING_NIF_CIF'],
                'address' => $invoice['BILLING_ADRECA'] ?? null,
                'cp' => $invoice['BILLING_CP'] ?? null,
                'city' => $invoice['BILLING_POBLACIO'] ?? null,
                'province' => $invoice['BILLING_PROVINCIA'] ?? null,
                'country' => $invoice['BILLING_PAIS'] ?? null,
                'email' => $invoice['BILLING_EMAIL'] ?? null,
            ],
            'totals' => [
                'import_base' => (string) $invoice['IMPORT_BASE'],
                'discount' => (string) $invoice['DESC_IMPORT'],
                'taxable_base' => (string) $invoice['BASE_IMPOSABLE'],
                'iva_regim' => (string) $invoice['IVA_REGIM'],
                'iva_pct' => (string) $invoice['IVA_PCT'],
                'iva_import' => (string) $invoice['IVA_IMPORT'],
                'exemption_reason' => $invoice['CAUSA_EXEMPCIO_NO_SUBJECTA'] ?? null,
                'total' => (string) $invoice['TOTAL'],
            ],
            'lines' => array_map(
                static fn (array $line): array => [
                    'order' => (int) $line['ORDRE'],
                    'concept' => (string) $line['CONCEPTE'],
                    'detail' => $line['DETALL'] ?? null,
                    'quantity' => (string) $line['QUANTITAT'],
                    'unit_price' => (string) $line['PREU_UNITARI'],
                    'import_base' => (string) $line['IMPORT_BASE'],
                    'discount_amount' => (string) $line['DESC_IMPORT'],
                    'taxable_base' => (string) $line['BASE_IMPOSABLE'],
                    'iva_regim' => (string) $line['IVA_REGIM'],
                    'iva_pct' => (string) $line['IVA_PCT'],
                    'iva_import' => (string) $line['IVA_IMPORT'],
                    'exemption_reason' => $line['CAUSA_EXEMPCIO_NO_SUBJECTA'] ?? null,
                    'total' => (string) $line['TOTAL'],
                    'source_type' => $line['SOURCE_TYPE'] ?? null,
                    'source_id' => isset($line['SOURCE_ID']) ? (int) $line['SOURCE_ID'] : null,
                ],
                $lines
            ),
            'fiscal_record' => [
                'id' => (int) $record['ID'],
                'fiscal_order' => (int) $record['FISCAL_ORDER'],
                'record_type' => (string) $record['TIPUS_REGISTRE'],
                'aeat_status' => (string) $record['ESTAT_AEAT'],
                'created_at' => (string) $record['DATE_CREATED'],
                'sent_at' => $record['DATE_SENT'] ?? null,
            ],
            'qr' => $qr,
        ];
    }
}
