<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\InvoiceVisibilityPolicyInterface;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceReadRepository;

final class InvoiceQueryService
{
    public function __construct(
        private \PDO $db,
        private InvoiceReadRepository $invoices,
        private InvoiceVisibilityPolicyInterface $visibility,
        private ?InvoiceQueryCriteriaValidator $criteriaValidator = null
    ) {
        $this->criteriaValidator ??= new InvoiceQueryCriteriaValidator();
    }

    public function view(array $actor, string $uuidFactura): array
    {
        $criteria = $this->criteriaValidator->validate(['uuid_factura' => $uuidFactura]);
        $uuidFactura = $criteria['uuid_factura'];

        $invoice = $this->invoices->findByUuid($this->db, $uuidFactura);
        if ($invoice === null) {
            throw SifException::notFound('Invoice not found');
        }

        $relations = $this->invoices->findRelations($this->db, $uuidFactura);
        if (!$this->visibility->canView($actor, $invoice, $relations)) {
            throw SifException::forbidden('Invoice access denied');
        }

        return $this->visibility->project($actor, $this->buildView($invoice, $relations));
    }

    public function search(array $actor, array $criteria, int $limit = 50): array
    {
        $criteria = $this->criteriaValidator->validate($criteria);
        $rows = $this->invoices->search($this->db, $criteria, $limit);
        $results = [];

        foreach ($rows as $invoice) {
            $uuid = (string) $invoice['UUID_FACTURA'];
            $relations = $this->invoices->findRelations($this->db, $uuid);

            if (!$this->visibility->canView($actor, $invoice, $relations)) {
                continue;
            }

            $summary = [
                'ok' => true,
                'invoice' => $this->invoiceProjection($invoice),
                'relations' => $relations,
            ];
            $projected = $this->visibility->project($actor, $summary);
            $results[] = $projected['invoice'] ?? $projected;
        }

        return [
            'ok' => true,
            'results' => $results,
            'count' => count($results),
        ];
    }

    private function buildView(array $invoice, array $relations): array
    {
        $uuid = (string) $invoice['UUID_FACTURA'];

        return [
            'ok' => true,
            'invoice' => $this->invoiceProjection($invoice),
            'lines' => $this->invoices->findLines($this->db, $uuid),
            'relations' => $relations,
            'rectifications' => $this->invoices->findRectifications($this->db, $uuid),
            'payments' => $this->invoices->findPayments($this->db, $uuid),
            'fiscal_record' => $this->invoices->latestFiscalRecord($this->db, $uuid),
            'documents' => $this->invoices->findDocumentMetadata($this->db, $uuid),
        ];
    }

    private function invoiceProjection(array $invoice): array
    {
        return [
            'uuid_factura' => $invoice['UUID_FACTURA'],
            'num_visible' => $invoice['NUM_VISIBLE'],
            'tipus_serie' => $invoice['TIPUS_SERIE'],
            'any_fact' => (int) $invoice['ANY_FACT'],
            'num_seq' => (int) $invoice['NUM_SEQ'],
            'tipus_factura' => $invoice['TIPUS_FACTURA'],
            'data_emissio' => $invoice['DATA_EMISSIO'],
            'data_operacio' => $invoice['DATA_OPERACIO'],
            'emesa_abans_cobrament' => (int) $invoice['EMESA_ABANS_COBRAMENT'],
            'e_fact' => (int) $invoice['E_FACT'],
            'estat_factura' => $invoice['ESTAT_FACTURA'],
            'estat_cobrament' => $invoice['ESTAT_COBRAMENT'],
            'estat_aeat' => $invoice['ESTAT_AEAT'],
            'billing' => [
                'name' => $invoice['BILLING_NOM_RAO'],
                'nif' => $invoice['BILLING_NIF_CIF'],
                'address' => $invoice['BILLING_ADRECA'],
                'cp' => $invoice['BILLING_CP'],
                'city' => $invoice['BILLING_POBLACIO'],
                'province' => $invoice['BILLING_PROVINCIA'],
                'country' => $invoice['BILLING_PAIS'],
                'email' => $invoice['BILLING_EMAIL'],
            ],
            'totals' => [
                'import_base' => $invoice['IMPORT_BASE'],
                'discount' => $invoice['DESC_IMPORT'],
                'taxable_base' => $invoice['BASE_IMPOSABLE'],
                'iva_regim' => $invoice['IVA_REGIM'],
                'iva_pct' => $invoice['IVA_PCT'],
                'iva_import' => $invoice['IVA_IMPORT'],
                'total' => $invoice['TOTAL'],
            ],
            'source_channel' => $invoice['SOURCE_CHANNEL'],
            'created_by' => $invoice['CREATED_BY'],
            'created_at' => $invoice['CREATED_AT'],
        ];
    }
}
