<?php

namespace Prisma\Sif\Service;

final class InvoiceQueryGateway
{
    public function __construct(
        private InternalInvoiceScopeResolver $scopeResolver,
        private InvoiceQueryService $query
    ) {
    }

    public function view(array $authenticatedActor, string $uuidFactura): array
    {
        return $this->query->view(
            $this->scopeResolver->resolve($authenticatedActor),
            $uuidFactura
        );
    }

    public function search(array $authenticatedActor, array $criteria, int $limit = 50): array
    {
        return $this->query->search(
            $this->scopeResolver->resolve($authenticatedActor),
            $criteria,
            $limit
        );
    }
}
