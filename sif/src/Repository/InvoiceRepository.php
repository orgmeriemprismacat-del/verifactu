<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

final class InvoiceRepository
{
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private HashCalculator $hashCalculator
    ) {
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
        $this->insertLines($db, $payload, $uuid);
        $this->insertFiscalRecord($db, $uuid, $fiscalOrder, $hash, $previousHash, $jsonPayload);
        $this->updateChainState($db, $fiscalOrder, $hash);
        $this->insertFiscalQueue($db, $uuid, $payload['idempotency_key'], $jsonPayload);
        $this->insertRelations($db, $payload, $uuid);

        return [
            'uuid_factura' => $uuid,
            'num_visible' => $numVisible,
            'fiscal_order' => $fiscalOrder,
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
                TOTAL, SOURCE_CHANNEL, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, \'ISSUED\', \'PENDING\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            $payload['totals']['total'],
            $payload['source_channel'],
            $payload['created_by'] ?? null,
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

    private function insertFiscalRecord(
        \PDO $db,
        string $uuid,
        int $fiscalOrder,
        string $hash,
        ?string $previousHash,
        string $jsonPayload
    ): void {
        $db->prepare(
            'INSERT INTO factura_registres (
                UUID_FACTURA, FISCAL_ORDER, TIPUS_REGISTRE, HASH_FACT, HASH_FACT_ANT, PAYLOAD_JSON
            ) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$uuid, $fiscalOrder, 'ALTA', $hash, $previousHash, $jsonPayload]);
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

    private function insertRelations(\PDO $db, array $payload, string $uuid): void
    {
        $stmt = $db->prepare(
            'INSERT INTO fact_rels (
                UUID_FACTURA, FACTURA_RELACIONADA, SOURCE_TYPE, SOURCE_ID, RELATION_TYPE,
                IDPAG, DS_ORDER, VISIBLE_ALUMNE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($payload['relations'] ?? [] as $rel) {
            $stmt->execute([
                $uuid,
                $rel['factura_relacionada'] ?? null,
                $rel['source_type'],
                $rel['source_id'] ?? null,
                $rel['relation_type'] ?? 'ORIGIN',
                $rel['idpag'] ?? null,
                $rel['ds_order'] ?? null,
                $rel['visible_alumne'] ?? 1,
            ]);
        }
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
