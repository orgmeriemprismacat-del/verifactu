<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;

final class HistoricalInvoiceDocumentCustodyService
{
    private string $root;
    private PrivateDocumentStore $verifier;
    private OperationalEventRepository $operationalEvents;
    private SifAuditEventRepository $auditEvents;

    public function __construct(
        private TransactionRunner $transactions,
        string $root,
        private int $maxBytes = 20971520,
        ?OperationalEventRepository $operationalEvents = null,
        ?SifAuditEventRepository $auditEvents = null
    ) {
        $resolved = realpath(trim($root));
        if ($resolved === false || !is_dir($resolved) || !is_writable($resolved)) {
            throw new \RuntimeException('Historical invoice private document root is unavailable');
        }

        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
        $this->maxBytes = max(1024, $this->maxBytes);
        $this->verifier = new PrivateDocumentStore($this->root, $this->maxBytes);
        $this->operationalEvents = $operationalEvents ?? new OperationalEventRepository(new UuidGenerator());
        $this->auditEvents = $auditEvents ?? new SifAuditEventRepository(new UuidGenerator());
    }

    public function custody(
        string $uuidFactura,
        string $type,
        string $sourceLocalPath,
        array $context = []
    ): array {
        $uuidFactura = strtolower(trim($uuidFactura));
        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',
            $uuidFactura
        ) !== 1) {
            throw SifException::validation('Invalid historical invoice UUID');
        }

        $type = strtoupper(trim($type));
        if (!in_array($type, ['PDF', 'XML'], true)) {
            throw SifException::validation('Historical custody supports PDF or XML originals');
        }

        $source = realpath(trim($sourceLocalPath));
        if ($source === false || !is_file($source) || !is_readable($source)) {
            throw SifException::unavailable('Historical document source bytes are unavailable');
        }

        $size = filesize($source);
        $hash = hash_file('sha256', $source);
        if ($size === false || $size < 1 || $size > $this->maxBytes || !is_string($hash)) {
            throw SifException::validation('Historical document source size or hash is invalid');
        }
        $hash = strtolower($hash);

        $storageRef = sprintf(
            'historical-invoices/%s/%s.%s',
            $uuidFactura,
            $hash,
            strtolower($type)
        );

        $createdStorage = false;
        try {
            $result = $this->transactions->run(function (\PDO $db) use (
                $uuidFactura,
                $type,
                $source,
                $size,
                $hash,
                $storageRef,
                $context,
                &$createdStorage
            ): array {
                $invoice = $this->historicalInvoiceForUpdate($db, $uuidFactura);
                $documents = $this->documentsForTypeForUpdate($db, $uuidFactura, $type);

                $same = array_values(array_filter(
                    $documents,
                    static fn (array $row): bool =>
                        hash_equals($hash, strtolower((string) ($row['HASH_FITXER'] ?? '')))
                ));
                $different = array_values(array_filter(
                    $documents,
                    static fn (array $row): bool =>
                        !hash_equals($hash, strtolower((string) ($row['HASH_FITXER'] ?? '')))
                ));

                if ($different !== []) {
                    throw SifException::conflict(
                        'Historical invoice already has different document bytes for this type'
                    );
                }
                if (count($same) > 1) {
                    throw SifException::conflict(
                        'Historical invoice has ambiguous duplicate document metadata for this type'
                    );
                }

                $document = $same[0] ?? null;
                $metadataReused = $document !== null;
                $storageReused = false;

                if ($document !== null) {
                    $existingStorageRef = trim((string) ($document['STORAGE_REF'] ?? ''));
                    if ($existingStorageRef !== '') {
                        try {
                            $this->verifier->readVerified($existingStorageRef, $hash);
                            $storageRef = $existingStorageRef;
                            $storageReused = true;
                        } catch (\Throwable $exception) {
                            if ($existingStorageRef !== $storageRef) {
                                throw SifException::conflict(
                                    'Existing historical storage reference is unavailable or inconsistent'
                                );
                            }
                        }
                    }
                }

                if (!$storageReused) {
                    $createdStorage = $this->putPrivate(
                        $source,
                        $storageRef,
                        $hash,
                        (int) $size
                    );
                    $storageReused = !$createdStorage;

                    if ($document === null) {
                        $db->prepare(
                            'INSERT INTO factura_documents (
                                UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER,
                                ESTAT, STORAGE_REF
                             ) VALUES (?, ?, ?, ?, ?, ?)'
                        )->execute([
                            $uuidFactura,
                            $type,
                            $storageRef,
                            $hash,
                            'ARCHIVED',
                            $storageRef,
                        ]);
                        $documentId = (int) $db->lastInsertId();
                    } else {
                        $documentId = (int) $document['ID'];
                        $db->prepare(
                            'UPDATE factura_documents
                             SET STORAGE_REF = ?, ESTAT = ?
                             WHERE ID = ?'
                        )->execute([$storageRef, 'ARCHIVED', $documentId]);
                    }
                } else {
                    $documentId = (int) $document['ID'];
                }

                $this->verifier->readVerified($storageRef, $hash);

                $result = [
                    'ok' => true,
                    'uuid_factura' => $uuidFactura,
                    'document_id' => $documentId,
                    'type' => $type,
                    'hash' => $hash,
                    'size_bytes' => (int) $size,
                    'storage_ref' => $storageRef,
                    'metadata_reused' => $metadataReused,
                    'storage_reused' => $storageReused,
                    'invoice_status' => (string) $invoice['ESTAT_FACTURA'],
                    'aeat_status' => (string) $invoice['ESTAT_AEAT'],
                ];

                return $this->appendAudit($db, $result, $context);
            });
        } catch (\Throwable $exception) {
            if ($createdStorage) {
                $this->deletePrivate($storageRef);
            }
            throw $exception;
        }

        $this->verifier->readVerified((string) $result['storage_ref'], $hash);

        return $result;
    }

    private function historicalInvoiceForUpdate(\PDO $db, string $uuidFactura): array
    {
        $stmt = $db->prepare(
            'SELECT UUID_FACTURA, NUM_VISIBLE, ESTAT_FACTURA, ESTAT_AEAT
             FROM factura
             WHERE UUID_FACTURA = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw SifException::notFound('Historical invoice was not found');
        }
        if (strtoupper((string) $row['ESTAT_FACTURA']) !== 'HISTORICAL'
            || strtoupper((string) $row['ESTAT_AEAT']) !== 'NO_VERIFACTU'
        ) {
            throw SifException::conflict(
                'Document custody is only valid for HISTORICAL/NO_VERIFACTU invoices'
            );
        }

        return $row;
    }

    private function documentsForTypeForUpdate(
        \PDO $db,
        string $uuidFactura,
        string $type
    ): array {
        $stmt = $db->prepare(
            'SELECT ID, UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER,
                    ESTAT, STORAGE_REF
             FROM factura_documents
             WHERE UUID_FACTURA = ? AND TIPUS = ?
             ORDER BY ID
             FOR UPDATE'
        );
        $stmt->execute([$uuidFactura, $type]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function putPrivate(
        string $source,
        string $storageRef,
        string $expectedHash,
        int $expectedSize
    ): bool {
        $this->assertStorageRef($storageRef);
        $destination = $this->root . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $storageRef);
        $directory = dirname($destination);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw SifException::unavailable('Could not create historical private document directory');
        }

        if (is_file($destination)) {
            $this->assertFileSnapshot($destination, $expectedHash, $expectedSize);
            return false;
        }

        $input = fopen($source, 'rb');
        $output = @fopen($destination, 'x+b');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_file($destination)) {
                $this->assertFileSnapshot($destination, $expectedHash, $expectedSize);
                return false;
            }
            throw SifException::unavailable('Could not create historical private document');
        }

        $ok = false;
        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw SifException::unavailable('Could not copy historical document bytes');
            }
            fflush($output);
            @chmod($destination, 0600);
            fclose($output);
            $output = null;

            $this->assertFileSnapshot($destination, $expectedHash, $expectedSize);
            $ok = true;
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
            }
            if (!$ok && is_file($destination)) {
                @unlink($destination);
            }
        }

        return true;
    }

    private function assertFileSnapshot(
        string $path,
        string $expectedHash,
        int $expectedSize
    ): void {
        $size = filesize($path);
        $hash = hash_file('sha256', $path);
        if ($size === false
            || $hash === false
            || (int) $size !== $expectedSize
            || !hash_equals($expectedHash, strtolower($hash))
        ) {
            throw SifException::conflict(
                'Historical private document does not match the expected immutable snapshot'
            );
        }
    }

    private function deletePrivate(string $storageRef): void
    {
        try {
            $this->assertStorageRef($storageRef);
            $path = $this->root . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $storageRef);
            if (is_file($path)) {
                @unlink($path);
            }
        } catch (\Throwable) {
            // Best effort cleanup after database rollback.
        }
    }

    private function assertStorageRef(string $storageRef): void
    {
        if (preg_match(
            '/^historical-invoices\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\/[a-f0-9]{64}\.(pdf|xml)$/D',
            strtolower(trim($storageRef))
        ) !== 1) {
            throw SifException::validation('Invalid historical private storage reference');
        }
    }

    private function appendAudit(\PDO $db, array $result, array $context): array
    {
        $requestId = $this->contextId(
            $context['request_id'] ?? null,
            'CUSTODY|' . $result['document_id'] . '|' . substr((string) $result['hash'], 0, 16)
        );
        $correlationId = $this->contextId($context['correlation_id'] ?? null, $requestId);
        $actorType = strtoupper(trim((string) ($context['actor_type'] ?? 'HUMAN')));
        if (!in_array($actorType, ['HUMAN', 'SYSTEM', 'PROCESS'], true)) {
            $actorType = 'HUMAN';
        }
        $actorId = $this->nullableContext($context['actor_id'] ?? null, 120);
        $actorRole = $this->nullableContext($context['actor_role'] ?? null, 80);
        $reused = (bool) $result['metadata_reused'] && (bool) $result['storage_reused'];
        $reason = $reused
            ? 'HISTORICAL_DOCUMENT_CUSTODY_REUSED'
            : 'HISTORICAL_DOCUMENT_CUSTODIED';
        $occurredAt = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s.u');

        $snapshot = [
            'document_id' => $result['document_id'],
            'type' => $result['type'],
            'hash' => $result['hash'],
            'storage_ref' => $result['storage_ref'],
            'size_bytes' => $result['size_bytes'],
            'reused' => $reused,
        ];

        $this->operationalEvents->append($db, [
            'operation_type' => 'HISTORICAL_DOCUMENT_CUSTODY',
            'source_type' => 'HISTORIC_WEB_FACTURES',
            'source_id' => null,
            'uuid_factura' => (string) $result['uuid_factura'],
            'uuid_payment' => null,
            'fiscal_impact' => 'HISTORICAL_NO_VERIFACTU',
            'economic_impact' => 'NONE',
            'status' => 'COMPLETED',
            'reason_code' => $reason,
            'before_snapshot' => null,
            'after_snapshot' => $snapshot,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'source_channel' => 'MIGRACIO',
            'correlation_id' => $correlationId,
            'occurred_at' => $occurredAt,
        ]);

        $json = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $this->auditEvents->append($db, [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'action' => 'CUSTODY_HISTORICAL_DOCUMENT',
            'result' => $reused ? 'REUSED' : 'SUCCEEDED',
            'resource_type' => 'FACTURA_DOCUMENT',
            'resource_id' => (string) $result['document_id'],
            'source_environment' => $this->sourceEnvironment(),
            'source_channel' => 'MIGRACIO',
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'reason_code' => $reason,
            'before_hash' => null,
            'after_hash' => hash('sha256', $json),
            'changeset' => $snapshot,
            'occurred_at' => $occurredAt,
        ]);

        $result['correlation_id'] = $correlationId;
        return $result;
    }

    private function contextId(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));
        return mb_substr($value === '' ? $fallback : $value, 0, 120, 'UTF-8');
    }

    private function nullableContext(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    private function sourceEnvironment(): string
    {
        return match (strtoupper(trim((string) (getenv('SIF_ENV') ?: 'DEVELOPMENT')))) {
            'PROD', 'PRODUCTION' => 'PRODUCTION',
            'PREPROD', 'PREPRODUCTION' => 'PREPRODUCTION',
            'TEST', 'TESTING' => 'TEST',
            'MIGRATION' => 'MIGRATION',
            default => 'DEVELOPMENT',
        };
    }
}
