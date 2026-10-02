<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class InvoiceRepository
{
    private const REGISTER_CONTROL_GENERATOR_VERSION = 'invoice-repository-v1';
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private HashCalculator $hashCalculator,
        private ?OperationLineInvoiceLinkRepository $operationLineLinks = null
    ) {
        $this->operationLineLinks ??= new OperationLineInvoiceLinkRepository();
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

    public function statusProjection(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT f.ESTAT_FACTURA, f.ESTAT_COBRAMENT, f.ESTAT_AEAT,
                    (SELECT fr.FISCAL_ORDER
                     FROM factura_registres fr
                     WHERE fr.UUID_FACTURA = f.UUID_FACTURA
                     ORDER BY fr.FISCAL_ORDER DESC
                     LIMIT 1) AS FISCAL_ORDER,
                    (SELECT fq.STATUS
                     FROM fiscal_queue fq
                     WHERE fq.UUID_FACTURA = f.UUID_FACTURA
                     ORDER BY fq.ID DESC
                     LIMIT 1) AS FISCAL_QUEUE_STATUS,
                    (SELECT fd.ESTAT
                     FROM factura_documents fd
                     WHERE fd.UUID_FACTURA = f.UUID_FACTURA
                     ORDER BY fd.CREATED_AT DESC, fd.ID DESC
                     LIMIT 1) AS DOCUMENT_STATUS,
                    (SELECT fd.TIPUS
                     FROM factura_documents fd
                     WHERE fd.UUID_FACTURA = f.UUID_FACTURA
                     ORDER BY fd.CREATED_AT DESC, fd.ID DESC
                     LIMIT 1) AS DOCUMENT_TYPE
             FROM factura f
             WHERE f.UUID_FACTURA = ?
             LIMIT 1'
        );
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException('Invoice status projection could not be loaded.');
        }

        return [
            'invoice_status' => (string) $row['ESTAT_FACTURA'],
            'payment_status' => (string) $row['ESTAT_COBRAMENT'],
            'aeat_status' => (string) $row['ESTAT_AEAT'],
            'fiscal_order' => $row['FISCAL_ORDER'] === null ? null : (int) $row['FISCAL_ORDER'],
            'fiscal_queue_status' => $row['FISCAL_QUEUE_STATUS'] === null
                ? null
                : (string) $row['FISCAL_QUEUE_STATUS'],
            'document_status' => $row['DOCUMENT_STATUS'] === null ? null : (string) $row['DOCUMENT_STATUS'],
            'document_type' => $row['DOCUMENT_TYPE'] === null ? null : (string) $row['DOCUMENT_TYPE'],
        ];
    }

    public function lockChainState(\PDO $db): array
    {
        $row = $db->query('SELECT * FROM fiscal_chain_state WHERE ID = 1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);

        if ($row !== false) {
            return $row;
        }

        $db->exec('INSERT INTO fiscal_chain_state (ID, LAST_FISCAL_ORDER, LAST_HASH) VALUES (1, 0, NULL)');

        return [
            'LAST_FISCAL_ORDER' => 0,
            'LAST_HASH' => null,
        ];
    }

    public function createInvoiceGraph(\PDO $db, array $payload, int $seq, array $chainState): array
    {
        $uuid = $this->uuidGenerator->generate();
        $year = (int) ($payload['year'] ?? date('Y'));
        $numVisible = sprintf('%s%d/%06d', $payload['series'], $year, $seq);
        $fiscalOrder = (int) $chainState['LAST_FISCAL_ORDER'] + 1;
        $previousHash = $chainState['LAST_HASH'] ?? null;

        $recordPayload = $this->recordPayload($payload, $uuid, $numVisible, $fiscalOrder);
        $issuedAt = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        $aeat = (new \Prisma\Sif\Aeat\RegistrationSnapshot())->invoice(
            $db, $chainState, $payload, $numVisible, $issuedAt
        );
        if ($aeat !== null) {
            $recordPayload['aeat'] = $aeat;
        }
        $hash = $this->hashCalculator->calculate($recordPayload, $previousHash);
        $jsonPayload = $this->encodePayload($recordPayload);

        $this->insertInvoice($db, $payload, $uuid, $year, $seq, $numVisible, $issuedAt);
        $lineIdsBySource = $this->insertLines($db, $payload, $uuid);
        $fiscalRecordId = $this->insertFiscalRecord(
            $db,
            $uuid,
            $fiscalOrder,
            $hash,
            $previousHash,
            $jsonPayload
        );
        $this->insertFiscalRecordControl($db, $fiscalRecordId, $chainState, $payload);
        $this->updateChainState($db, $fiscalOrder, $hash);
        $this->insertFiscalQueue($db, $uuid, $payload['idempotency_key'], $jsonPayload);
        $this->insertRelations($db, $payload, $uuid, $lineIdsBySource);

        return [
            'uuid_factura' => $uuid,
            'num_visible' => $numVisible,
            'fiscal_order' => $fiscalOrder,
            'fiscal_record_id' => $fiscalRecordId,
            'hash' => $hash,
        ];
    }

    private function insertInvoice(\PDO $db, array $payload, string $uuid, int $year, int $seq, string $numVisible,
        \DateTimeImmutable $issuedAt): void
    {
        $stmt = $db->prepare(
            'INSERT INTO factura (
                UUID_FACTURA, IDEMPOTENCY_KEY, IDEMPOTENCY_PAYLOAD_HASH, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
                TIPUS_FACTURA, DATA_EMISSIO, EMESA_ABANS_COBRAMENT, E_FACT, ESTAT_COBRAMENT,
                ESTAT_FACTURA, ESTAT_AEAT, BILLING_NOM_RAO, BILLING_NIF_CIF, BILLING_ADRECA,
                BILLING_CP, BILLING_POBLACIO, BILLING_PROVINCIA, BILLING_PAIS, BILLING_EMAIL,
                IMPORT_BASE, DESC_IMPORT, BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT,
                CAUSA_EXEMPCIO_NO_SUBJECTA, TOTAL, SOURCE_CHANNEL, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, \'ISSUED\', \'PENDING\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $uuid,
            $payload['idempotency_key'],
            (new PayloadIdempotencyValidator())->calculateHash($payload),
            $payload['series'],
            $year,
            $seq,
            $numVisible,
            $payload['type'],
            $issuedAt->format('Y-m-d H:i:s'),
            !empty($payload['emesa_abans_cobrament']) ? 1 : 0,
            isset($payload['payment']) ? 'PAID' : 'PENDING',
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
            $payload['totals']['exemption_reason'] ?? null,
            $payload['totals']['total'],
            $payload['source_channel'],
            $payload['created_by'] ?? null,
        ]);
    }

    private function insertLines(\PDO $db, array $payload, string $uuid): array
    {
        $stmt = $db->prepare(
            'INSERT INTO factura_linia (
                UUID_FACTURA, ORDRE, CONCEPTE, DETALL, QUANTITAT, PREU_UNITARI,
                IMPORT_BASE, DESC_ORIGEN, DESC_MODE, DESC_ID, DESC_CODI_PROMO,
                DESC_PCT, DESC_IMPORT, DESC_TEXT_VISIBLE, DESC_MOTIU_INTERN,
                BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT, CAUSA_EXEMPCIO_NO_SUBJECTA, TOTAL,
                SOURCE_TYPE, SOURCE_ID
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $lineIdsBySource = [];

        foreach ($payload['lines'] as $index => $line) {
            $stmt->execute([
                $uuid, $index + 1, $line['concept'], $line['detail'] ?? null,
                $line['quantity'], $line['unit_price'], $line['import_base'] ?? $line['base'],
                $line['discount_origin'] ?? null, $line['discount_mode'] ?? null,
                $line['discount_id'] ?? null, $line['discount_code'] ?? null,
                $line['discount_pct'] ?? null, $line['discount_amount'] ?? '0.00',
                $line['discount_text'] ?? null, $line['discount_internal_reason'] ?? null,
                $line['taxable_base'] ?? $line['base'], $line['iva_regim'] ?? 'EXEMPT',
                $line['iva_pct'] ?? '0.00', $line['iva_import'] ?? '0.00',
                $line['exemption_reason'] ?? null, $line['total'],
                $line['source_type'] ?? null, $line['source_id'] ?? null,
            ]);

            $invoiceLineId = (int) $db->lastInsertId();
            $sourceKey = $this->sourceKey($line['source_type'] ?? null, $line['source_id'] ?? null);
            if ($sourceKey !== null) {
                $lineIdsBySource[$sourceKey][] = $invoiceLineId;
            }

            $uuidOperationLine = trim((string) ($line['uuid_operation_line'] ?? ''));
            if ($uuidOperationLine !== '') {
                $this->operationLineLinks->link(
                    $db,
                    $uuidOperationLine,
                    $invoiceLineId,
                    (string) $line['total']
                );
            }
        }

        return $lineIdsBySource;
    }

    private function insertFiscalRecord(
        \PDO $db,
        string $uuid,
        int $fiscalOrder,
        string $hash,
        ?string $previousHash,
        string $jsonPayload
    ): int {
        $db->prepare(
            'INSERT INTO factura_registres (
                UUID_FACTURA, FISCAL_ORDER, TIPUS_REGISTRE, HASH_FACT, HASH_FACT_ANT, PAYLOAD_JSON
            ) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$uuid, $fiscalOrder, 'ALTA', $hash, $previousHash, $jsonPayload]);

        return (int) $db->lastInsertId();
    }

    private function insertFiscalRecordControl(
        \PDO $db,
        int $fiscalRecordId,
        array $chainState,
        array $payload
    ): void {
        $previousRecordId = null;
        $previousOrder = (int) ($chainState['LAST_FISCAL_ORDER'] ?? 0);
        if ($previousOrder > 0) {
            $stmt = $db->prepare(
                'SELECT ID FROM factura_registres WHERE FISCAL_ORDER = ? LIMIT 1'
            );
            $stmt->execute([$previousOrder]);
            $previous = $stmt->fetchColumn();
            if ($previous === false) {
                throw new \RuntimeException('Previous fiscal registration record could not be resolved.');
            }
            $previousRecordId = (int) $previous;
        }

        $correlationId = trim((string) ($payload['correlation_id'] ?? ''));
        if ($correlationId === '') {
            $correlationId = (string) $payload['idempotency_key'];
        }
        $correlationId = mb_substr($correlationId, 0, 120, 'UTF-8');

        $db->prepare(
            'INSERT INTO factura_registre_control (
                FACTURA_REGISTRE_ID, PREVIOUS_REGISTRE_ID, RECORD_ACTION,
                CORRECTION_KIND, REJECTION_PREVIOUS, WITHOUT_PREVIOUS_RECORD,
                XML_HASH, GENERATOR_VERSION, CORRELATION_ID
            ) VALUES (?, ?, ?, NULL, 0, 0, NULL, ?, ?)'
        )->execute([
            $fiscalRecordId,
            $previousRecordId,
            'ALTA',
            self::REGISTER_CONTROL_GENERATOR_VERSION,
            $correlationId,
        ]);
    }

    private function updateChainState(\PDO $db, int $fiscalOrder, string $hash): void
    {
        $db->prepare('UPDATE fiscal_chain_state SET LAST_FISCAL_ORDER = ?, LAST_HASH = ? WHERE ID = 1')
            ->execute([$fiscalOrder, $hash]);
    }

    private function insertFiscalQueue(\PDO $db, string $uuid, string $idempotencyKey, string $jsonPayload): void
    {
        $db->prepare('INSERT INTO fiscal_queue (UUID_FACTURA, IDEMPOTENCY_KEY, PAYLOAD_JSON) VALUES (?, ?, ?)')
            ->execute([$uuid, 'AEAT|' . $idempotencyKey, $jsonPayload]);
    }

    private function insertRelations(
        \PDO $db,
        array $payload,
        string $uuid,
        array $lineIdsBySource = []
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO fact_rels (
                UUID_FACTURA, FACTURA_RELACIONADA, SOURCE_TYPE, SOURCE_ID, ID_FACTURA_LINIA, RELATION_TYPE,
                IDPAG, DS_ORDER, VISIBLE_ALUMNE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($payload['relations'] ?? [] as $rel) {
            $sourceKey = $this->sourceKey($rel['source_type'] ?? null, $rel['source_id'] ?? null);
            $lineCandidates = $sourceKey === null ? [] : ($lineIdsBySource[$sourceKey] ?? []);
            $lineId = count($lineCandidates) === 1 ? $lineCandidates[0] : null;

            $stmt->execute([
                $uuid, $rel['factura_relacionada'] ?? null, $rel['source_type'],
                $rel['source_id'] ?? null, $lineId, $rel['relation_type'] ?? 'ORIGIN',
                $rel['idpag'] ?? null, $rel['ds_order'] ?? null, $rel['visible_alumne'] ?? 1,
            ]);
        }
    }

    private function sourceKey(mixed $sourceType, mixed $sourceId): ?string
    {
        if ($sourceType === null || $sourceId === null || $sourceId === '') {
            return null;
        }

        $type = strtoupper(trim((string) $sourceType));
        if ($type === '') {
            return null;
        }

        return $type . '|' . trim((string) $sourceId);
    }

    private function recordPayload(array $payload, string $uuid, string $numVisible, int $fiscalOrder): array
    {
        return [
            'uuid_factura' => $uuid,
            'num_visible' => $numVisible,
            'fiscal_order' => $fiscalOrder,
            'type' => $payload['type'],
            'billing' => $payload['billing'],
            'totals' => $payload['totals'],
            'lines' => $payload['lines'],
            'relations' => $payload['relations'] ?? [],
        ];
    }

    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($json === false) {
            throw new \RuntimeException('Could not encode fiscal payload.');
        }

        return $json;
    }
}
