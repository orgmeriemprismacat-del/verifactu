<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\InvoiceVisibilityPolicyInterface;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DocumentAccessRepository;
use Prisma\Sif\Repository\FiscalDocumentAccessRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;

final class InvoiceDocumentAccessService
{
    public function __construct(
        private \PDO $db,
        private DocumentAccessRepository $documents,
        private InvoiceReadRepository $invoices,
        private InvoiceVisibilityPolicyInterface $visibility,
        private PrivateDocumentStore $store,
        private FiscalDocumentAccessRepository $accessLog
    ) {
    }

    public function download(array $actor, int $documentId): array
    {
        if ($documentId <= 0) {
            throw SifException::validation('Invalid document id');
        }

        $document = $this->documents->findById($this->db, $documentId);
        if ($document === null) {
            throw SifException::notFound('Document not found');
        }

        $uuidFactura = (string) $document['UUID_FACTURA'];
        $invoice = $this->invoices->findByUuid($this->db, $uuidFactura);
        $relations = $this->invoices->findRelations($this->db, $uuidFactura);

        if ($invoice === null || !$this->visibility->canView($actor, $invoice, $relations)) {
            $this->audit($actor, $document, 'DENIED', 'INVOICE_SCOPE');
            throw SifException::forbidden('Document access denied');
        }

        $status = strtoupper((string) ($document['ESTAT'] ?? ''));
        if (!in_array($status, ['CREATED', 'READY', 'ARCHIVED'], true)) {
            $this->audit($actor, $document, 'FAILED', 'DOCUMENT_STATUS');
            throw SifException::unavailable('Document is not available');
        }

        try {
            $bytes = $this->store->readVerified(
                (string) $document['PATH_FITXER'],
                (string) $document['HASH_FITXER']
            );
        } catch (\Throwable $exception) {
            $reason = $exception->getCode() === 409 ? 'HASH_MISMATCH' : 'STORAGE_UNAVAILABLE';
            $this->audit($actor, $document, 'FAILED', $reason);
            throw $exception;
        }

        $this->audit($actor, $document, 'ALLOWED', 'OK');

        return [
            'ok' => true,
            'document' => [
                'id' => (int) $document['ID'],
                'uuid_factura' => $uuidFactura,
                'type' => strtoupper((string) $document['TIPUS']),
                'hash' => strtolower((string) $document['HASH_FITXER']),
                'status' => $status,
            ],
            'bytes' => $bytes,
        ];
    }

    private function audit(array $actor, array $document, string $result, string $reason): void
    {
        $roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
        $role = $roles !== [] ? (string) reset($roles) : null;

        $this->accessLog->append($this->db, [
            'document_id' => (int) $document['ID'],
            'uuid_factura' => (string) $document['UUID_FACTURA'],
            'action' => 'DOWNLOAD',
            'result' => $result,
            'actor_type' => $actor['actor_type'] ?? 'INTERNAL_USER',
            'actor_id' => $actor['actor_id'] ?? null,
            'actor_role' => $role,
            'source_channel' => $actor['source_channel'] ?? 'INTERNAL_API',
            'request_id' => $actor['request_id'] ?? 'UNTRACKED',
            'correlation_id' => $actor['correlation_id'] ?? ($actor['request_id'] ?? 'UNTRACKED'),
            'reason_code' => $reason,
        ]);
    }
}
