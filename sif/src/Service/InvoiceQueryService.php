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

        $projected = $this->visibility->project($actor, $this->buildView($invoice, $relations));
        if (!isset($projected['invoice']) || !is_array($projected['invoice'])) {
            throw SifException::forbidden('Invoice projection is not authorized');
        }

        return $projected;
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

            $fiscalRecord = $this->invoices->latestFiscalRecord($this->db, $uuid);
            $summary = [
                'ok' => true,
                'invoice' => $this->invoiceProjection($invoice, $fiscalRecord),
                'relations' => $relations,
            ];
            $projected = $this->visibility->project($actor, $summary);
            if (!isset($projected['invoice']) || !is_array($projected['invoice'])) {
                continue;
            }

            $results[] = $projected['invoice'];
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

        $fiscalRecord = $this->invoices->latestFiscalRecord($this->db, $uuid);

        return [
            'ok' => true,
            'invoice' => $this->invoiceProjection($invoice, $fiscalRecord),
            'lines' => $this->invoices->findLines($this->db, $uuid),
            'relations' => $relations,
            'rectifications' => $this->invoices->findRectifications($this->db, $uuid),
            'payments' => $this->invoices->findPayments($this->db, $uuid),
            'fiscal_record' => $fiscalRecord,
            'fiscal_correction_decision' => $this->correctionDecisionProjection(
                $this->invoices->latestFiscalCorrectionDecision($this->db, $uuid)
            ),
            'documents' => $this->invoices->findDocumentMetadata($this->db, $uuid),
        ];
    }

    private function correctionDecisionProjection(?array $event): ?array
    {
        if ($event === null) {
            return null;
        }

        $changeset = [];
        $rawChangeset = $event['CHANGESET_JSON'] ?? null;
        if (is_string($rawChangeset) && trim($rawChangeset) !== '') {
            try {
                $decoded = json_decode($rawChangeset, true, 64, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $changeset = $decoded;
                }
            } catch (\JsonException) {
                return null;
            }
        } elseif (is_array($rawChangeset)) {
            $changeset = $rawChangeset;
        }

        $classification = $changeset['classification'] ?? null;
        $fingerprint = strtolower(trim((string) ($changeset['correction_fingerprint'] ?? '')));
        if (!is_array($classification)
            || preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1
        ) {
            return null;
        }

        $decision = strtoupper(trim((string) ($classification['decision'] ?? '')));
        $sourceUc = strtoupper(trim((string) ($classification['source_uc'] ?? '')));
        $invoiceType = strtoupper(trim((string) ($classification['invoice_type'] ?? '')));
        $mode = strtoupper(trim((string) ($classification['rectification_mode'] ?? '')));

        $correction = $this->correctionSnapshotProjection($changeset['correction'] ?? null);
        $eligible = $decision === 'RECTIFICATION'
            && $sourceUc === 'UC-74'
            && in_array($invoiceType, ['R1', 'R2', 'R3', 'R4', 'R5'], true)
            && in_array($mode, ['DIFERENCIES', 'SUBSTITUCIO'], true);

        return [
            'event_uuid' => strtolower((string) $event['UUID_EVENT']),
            'eligible_for_uc005' => $eligible,
            'ready_for_uc005_ui' => $eligible && $correction !== null,
            'reason_code' => strtoupper(trim((string) ($event['REASON_CODE'] ?? ''))),
            'correction_fingerprint' => $fingerprint,
            'correction' => $correction,
            'classification' => [
                'decision' => $decision,
                'source_uc' => $sourceUc,
                'reason_code' => strtoupper(trim((string) ($classification['reason_code'] ?? ''))),
                'policy_version' => trim((string) ($classification['policy_version'] ?? '')),
                'invoice_type' => $invoiceType,
                'rectification_mode' => $mode,
            ],
            'occurred_at' => $event['OCCURRED_AT'] ?? null,
            'recorded_at' => $event['RECORDED_AT'] ?? null,
        ];
    }

    private function correctionSnapshotProjection(mixed $correction): ?array
    {
        if (!is_array($correction)) {
            return null;
        }

        $result = [];

        foreach ([
            'amount',
            'reason',
            'mode',
            'concept',
            'detail',
            'reference',
        ] as $field) {
            if (!array_key_exists($field, $correction)) {
                continue;
            }
            $value = $correction[$field];
            if (is_scalar($value) || $value === null) {
                $result[$field] = $value;
            }
        }

        if (isset($correction['fiscal']) && is_array($correction['fiscal'])) {
            $result['fiscal'] = array_intersect_key(
                $correction['fiscal'],
                array_flip([
                    'import_base',
                    'taxable_base',
                    'iva_regim',
                    'iva_pct',
                    'iva_import',
                    'total',
                    'exemption_reason',
                ])
            );
        }

        if (isset($correction['billing']) && is_array($correction['billing'])) {
            $result['billing'] = array_intersect_key(
                $correction['billing'],
                array_flip([
                    'name',
                    'nif',
                    'address',
                    'cp',
                    'city',
                    'province',
                    'country',
                    'email',
                ])
            );
        }

        return $result === [] ? null : $result;
    }

    private function invoiceProjection(array $invoice, ?array $fiscalRecord = null): array
    {
        $invoiceAeat = $invoice['ESTAT_AEAT'] ?? null;
        $recordAeat = $fiscalRecord['ESTAT_AEAT'] ?? null;
        $aeatDivergent = $recordAeat !== null
            && $recordAeat !== ''
            && $invoiceAeat !== $recordAeat;

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
            'estat_aeat' => $invoiceAeat,
            'estat_aeat_factura' => $invoiceAeat,
            'estat_aeat_registre' => $recordAeat,
            'estat_aeat_divergent' => $aeatDivergent,
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
