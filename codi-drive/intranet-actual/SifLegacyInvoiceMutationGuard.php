<?php

final class SifLegacyInvoiceMutationGuard
{
    public function __construct(
        private ?SifInternalApiClient $client = null
    ) {
        $this->client ??= new SifInternalApiClient();
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

        [$actorId, $roles] = $this->actor($user);
        $response = $this->client->searchInvoices(
            $actorId,
            $roles,
            ['factura_relacionada' => $legacyRelation],
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
                'Factura governada pel SIF: la modificació directa llegada està bloquejada',
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
        if (!is_object($user)
            || !method_exists($user, 'getUsuari')
            || !method_exists($user, 'getRols')) {
            throw new RuntimeException('Context d’usuari no vàlid', 401);
        }

        $actor = $user->getUsuari();
        $actorId = is_object($actor) && method_exists($actor, 'get')
            ? trim((string) $actor->get())
            : trim((string) $actor);

        $roles = $user->getRols();
        if ($actorId === '' || !is_array($roles) || $roles === []) {
            throw new RuntimeException('Identitat o rols d’usuari no disponibles', 401);
        }

        return [$actorId, $roles];
    }

    private function enabled(): bool
    {
        return filter_var(
            getenv('SIF_BLOCK_LEGACY_INVOICE_MUTATIONS') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
