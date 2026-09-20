<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\HashCalculator;

final class FiscalRecordRepository
{
    public function __construct(private HashCalculator $hashCalculator)
    {
    }

    public function findQueuedResult(
        \PDO $db,
        string $idempotencyKey,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM fiscal_queue WHERE IDEMPOTENCY_KEY = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute(['AEAT|' . $idempotencyKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $payload = json_decode((string) $row['PAYLOAD_JSON'], true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Could not decode queued fiscal payload.');
        }

        $record = $db->prepare(
            'SELECT HASH_FACT FROM factura_registres WHERE UUID_FACTURA = ? AND FISCAL_ORDER = ?'
        );
        $record->execute([$row['UUID_FACTURA'], $payload['fiscal_order'] ?? 0]);
        $hash = $record->fetchColumn();
        if ($hash === false) {
            throw new \RuntimeException('Queued fiscal record has no immutable registration record.');
        }

        return $this->result($payload, (string) $hash, $idempotencyKey, true);
    }

    public function latestForInvoice(\PDO $db, string $uuidFactura, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM factura_registres WHERE UUID_FACTURA = ? ORDER BY FISCAL_ORDER DESC LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(
        \PDO $db,
        array $invoice,
        string $recordType,
        string $idempotencyKey,
        array $payload,
        bool $markCancelled
    ): array {
        $chain = $this->lockChainState($db);
        $fiscalOrder = (int) $chain['LAST_FISCAL_ORDER'] + 1;
        $previousHash = $chain['LAST_HASH'] ?? null;
        $payload['fiscal_order'] = $fiscalOrder;
        $hash = $this->hashCalculator->calculate($payload, $previousHash);
        $json = $this->encode($payload);

        $db->prepare(
            'INSERT INTO factura_registres (
                UUID_FACTURA, FISCAL_ORDER, TIPUS_REGISTRE, HASH_FACT, HASH_FACT_ANT, PAYLOAD_JSON
            ) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$invoice['UUID_FACTURA'], $fiscalOrder, $recordType, $hash, $previousHash, $json]);

        $db->prepare('UPDATE fiscal_chain_state SET LAST_FISCAL_ORDER = ?, LAST_HASH = ? WHERE ID = 1')
            ->execute([$fiscalOrder, $hash]);

        $db->prepare('INSERT INTO fiscal_queue (UUID_FACTURA, IDEMPOTENCY_KEY, PAYLOAD_JSON) VALUES (?, ?, ?)')
            ->execute([$invoice['UUID_FACTURA'], 'AEAT|' . $idempotencyKey, $json]);

        if ($markCancelled) {
            $db->prepare('UPDATE factura SET ESTAT_FACTURA = ? WHERE UUID_FACTURA = ?')
                ->execute(['CANCELLED', $invoice['UUID_FACTURA']]);
        }

        return $this->result($payload, $hash, $idempotencyKey, false);
    }

    private function lockChainState(\PDO $db): array
    {
        $row = $db->query('SELECT * FROM fiscal_chain_state WHERE ID = 1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $row;
        }

        $db->exec('INSERT INTO fiscal_chain_state (ID, LAST_FISCAL_ORDER, LAST_HASH) VALUES (1, 0, NULL)');

        return ['LAST_FISCAL_ORDER' => 0, 'LAST_HASH' => null];
    }

    private function result(array $payload, string $hash, string $idempotencyKey, bool $reused): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => $reused,
            'uuid_factura' => $payload['uuid_factura'],
            'num_visible' => $payload['num_visible'],
            'record_type' => $payload['record_type'],
            'fiscal_order' => (int) $payload['fiscal_order'],
            'hash' => $hash,
            'queue_idempotency_key' => 'AEAT|' . $idempotencyKey,
        ];
    }

    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Could not encode fiscal payload.');
        }

        return $json;
    }
}
