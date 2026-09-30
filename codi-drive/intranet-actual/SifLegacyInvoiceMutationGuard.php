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

        $this->assertQueryProtectionReady();

        $invoiceId = (string) $legacyInvoiceId;
        if (!ctype_digit($invoiceId) || (int) $invoiceId <= 0) {
            throw new InvalidArgumentException('Identificador de factura llegat no vàlid', 422);
        }

        [$actorId, $roles] = $this->actor($user);
        $client = $this->client ?? new SifInternalApiClient();

        if ($this->hasSifInvoice(
            $client,
            $actorId,
            $roles,
            [
                'source_type' => 'HISTORIC_WEB_FACTURES',
                'source_ids' => [(int) $invoiceId],
            ]
        )) {
            $this->blocked();
        }

        $legacyRelation = $this->legacyInvoiceRelation((int) $invoiceId);
        if ($legacyRelation !== null
            && $this->hasSifInvoice(
                $client,
                $actorId,
                $roles,
                ['factura_relacionada' => $legacyRelation]
            )) {
            $this->blocked();
        }
    }

    public function assertLegacyEnrollmentAllowed($user, $enrollmentId): void
    {
        if (!$this->enabled()) {
            return;
        }

        $this->assertQueryProtectionReady();

        $id = (string) $enrollmentId;
        if (!ctype_digit($id) || (int) $id <= 0) {
            throw new InvalidArgumentException('Identificador d’inscripció no vàlid', 422);
        }

        [$actorId, $roles] = $this->actor($user);
        $client = $this->client ?? new SifInternalApiClient();

        if ($this->hasSifInvoice(
            $client,
            $actorId,
            $roles,
            [
                'source_type' => 'INSCRIPCIO',
                'source_ids' => [(int) $id],
            ]
        )) {
            $this->blocked();
        }

        $relation = $this->legacyEnrollmentRelation((int) $id);
        if ($relation !== null
            && $this->hasSifInvoice(
                $client,
                $actorId,
                $roles,
                ['factura_relacionada' => $relation]
            )) {
            $this->blocked();
        }
    }

    public function assertLegacyRelationAllowed($user, $legacyRelation): void
    {
        if (!$this->enabled()) {
            return;
        }

        $this->assertQueryProtectionReady();

        $relation = (string) $legacyRelation;
        if (!ctype_digit($relation) || (int) $relation <= 0) {
            throw new InvalidArgumentException('Factura relacionada llegada no vàlida', 422);
        }

        [$actorId, $roles] = $this->actor($user);
        $client = $this->client ?? new SifInternalApiClient();

        if ($this->hasSifInvoice(
            $client,
            $actorId,
            $roles,
            ['factura_relacionada' => (int) $relation]
        )) {
            $this->blocked();
        }
    }

    private function hasSifInvoice(
        SifInternalApiClient $client,
        string $actorId,
        array $roles,
        array $criteria
    ): bool {
        $response = $client->searchInvoices($actorId, $roles, $criteria, 5);
        $status = (int) ($response['_http_status'] ?? 0);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                'No s’ha pogut verificar si la factura ja està governada pel SIF',
                503
            );
        }

        return is_array($response['results'] ?? null)
            && $response['results'] !== [];
    }

    private function legacyInvoiceRelation(int $invoiceId): ?int
    {
        return $this->legacyRelation(
            'SELECT factura_relacionada FROM factures WHERE ID = ? LIMIT 1',
            $invoiceId
        );
    }

    private function legacyEnrollmentRelation(int $enrollmentId): ?int
    {
        return $this->legacyRelation(
            'SELECT FACTURA_RELACIONADA FROM inscripcions WHERE ID = ? LIMIT 1',
            $enrollmentId
        );
    }

    private function legacyRelation(string $sql, int $id): ?int
    {
        $connection = new ConnexioWeb();

        try {
            $connection->connectarBD();
            $stmt = $connection->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows() <= 0) {
                $connection->closeStmt();
                return null;
            }

            $stmt->bind_result($relation);
            $stmt->fetch();
            $connection->closeStmt();

            if ($relation === null
                || $relation === ''
                || !ctype_digit((string) $relation)
                || (int) $relation <= 0) {
                return null;
            }

            return (int) $relation;
        } finally {
            if (isset($connection->connexio)
                && $connection->connexio instanceof mysqli) {
                $connection->desconectarBD();
            }
        }
    }

    private function assertQueryProtectionReady(): void
    {
        if (!filter_var(
            getenv('SIF_UC007_QUERY_ENABLED') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        )) {
            throw new RuntimeException(
                'La protecció de factures SIF està activada però la consulta UC-007 està desactivada',
                503
            );
        }
    }

    private function blocked(): never
    {
        throw new RuntimeException(
            'Factura governada pel SIF: el flux llegat està bloquejat',
            409
        );
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
