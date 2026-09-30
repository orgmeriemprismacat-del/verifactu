<?php

require_once __DIR__ . '/SifAuthenticatedActor.php';

final class SifLegacyInvoiceMutationGuard
{
    public function __construct(
        private ?SifInternalApiClient $client = null
    ) {
    }

    public function assertLegacyMutationAllowed($user, $legacyInvoiceId): void
    {
        if (!$this->enabled()) {
            return;
        }

        if (!filter_var(getenv('SIF_UC007_QUERY_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'La protecció de factures SIF està activada però la consulta UC-007 està desactivada',
                503
            );
        }

        $invoiceId = (string) $legacyInvoiceId;
        if (!ctype_digit($invoiceId) || (int) $invoiceId <= 0) {
            throw new InvalidArgumentException('Identificador de factura llegat no vàlid', 422);
        }

        $legacyRelation = $this->legacyInvoiceRelation((int) $invoiceId);
        if ($legacyRelation === null) {
            return;
        }

        $this->assertLegacyRelationAllowed($user, $legacyRelation);
    }

    public function assertLegacyEnrollmentAllowed($user, $enrollmentId): void
    {
        if (!$this->enabled()) {
            return;
        }

        $id = (string) $enrollmentId;
        if (!ctype_digit($id) || (int) $id <= 0) {
            throw new InvalidArgumentException('Identificador d’inscripció no vàlid', 422);
        }

        $connection = new ConnexioWeb();

        try {
            $connection->connectarBD();
            $stmt = $connection->prepare(
                'SELECT FACTURA_RELACIONADA FROM inscripcions WHERE ID = ? LIMIT 1'
            );
            $value = (int) $id;
            $stmt->bind_param('i', $value);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows() <= 0) {
                $connection->closeStmt();
                return;
            }

            $stmt->bind_result($relation);
            $stmt->fetch();
            $connection->closeStmt();

            if ($relation === null || $relation === '' || !ctype_digit((string) $relation)) {
                return;
            }

            $this->assertLegacyRelationAllowed($user, (int) $relation);
        } finally {
            if (isset($connection->connexio) && $connection->connexio instanceof mysqli) {
                $connection->desconectarBD();
            }
        }
    }

    public function assertLegacyRelationAllowed($user, $legacyRelation): void
    {
        if (!$this->enabled()) {
            return;
        }

        $relation = (string) $legacyRelation;
        if (!ctype_digit($relation) || (int) $relation <= 0) {
            throw new InvalidArgumentException('Factura relacionada llegada no vàlida', 422);
        }

        if (!filter_var(getenv('SIF_UC007_QUERY_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'La protecció de factures SIF està activada però la consulta UC-007 està desactivada',
                503
            );
        }

        [$actorId, $roles] = $this->actor($user);
        $client = $this->client ?? new SifInternalApiClient();
        $response = $client->searchInvoices(
            $actorId,
            $roles,
            ['factura_relacionada' => (int) $relation],
            5
        );

        $status = (int) ($response['_http_status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                'No s’ha pogut verificar si la factura ja està governada pel SIF',
                503
            );
        }

        $results = is_array($response['results'] ?? null) ? $response['results'] : [];
        if ($results !== []) {
            throw new RuntimeException(
                'Factura governada pel SIF: el flux llegat està bloquejat',
                409
            );
        }
    }

    private function legacyInvoiceRelation(int $invoiceId): ?int
    {
        $connection = new ConnexioWeb();

        try {
            $connection->connectarBD();
            $stmt = $connection->prepare(
                'SELECT factura_relacionada FROM factures WHERE ID = ? LIMIT 1'
            );
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows() <= 0) {
                $connection->closeStmt();
                return null;
            }

            $stmt->bind_result($relation);
            $stmt->fetch();
            $connection->closeStmt();

            if ($relation === null || $relation === '' || !ctype_digit((string) $relation)) {
                return null;
            }

            return (int) $relation;
        } finally {
            if (isset($connection->connexio) && $connection->connexio instanceof mysqli) {
                $connection->desconectarBD();
            }
        }
    }

    private function actor($user): array
    {
        return SifAuthenticatedActor::fromUser($user);
    }

    private function enabled(): bool
    {
        return filter_var(
            getenv('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
