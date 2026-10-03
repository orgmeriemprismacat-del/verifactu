# UC-007 · Diagrames de classes ACTUAL / FINAL

## 1. ACTUAL — convivència

```mermaid
classDiagram
direction LR
class BrowserInvoicePage {
  +search()
  +view(uuid)
  +download(documentId)
}
class BrowserStudentPage {
  +viewByEnrollment(id)
  +legacyFallback(id)
}
class SifFacturesBridge {
  +view()
  +search()
  +view_by_enrollment()
}
class SifInternalApiClient {
  +viewInvoice()
  +searchInvoices()
}
class InternalApiAuthenticator {
  +authenticate()
}
class InvoiceQueryGateway {
  +view()
  +search()
}
class InternalInvoiceScopeResolver {
  +resolve(actor)
}
class InvoiceQueryService {
  +view(actor, uuid)
  +search(actor, criteria, limit)
}
class ResolvedInvoiceVisibilityPolicy {
  +canView()
  +project()
}
class InvoiceReadRepository {
  +findByUuid()
  +search()
  +findLines()
  +findRelations()
  +findRectifications()
  +findPayments()
  +latestFiscalRecord()
  +findDocumentMetadata()
}
class SifDocumentBridge {
  +download(documentId)
}
class InvoiceDocumentAccessService {
  +download(actor, documentId)
}
class PrivateDocumentStore {
  +readVerified(path, hash)
}
class FiscalDocumentAccessRepository {
  +append(event)
}
class LegacyInvoiceReadContext
class SifLegacyInvoiceMutationGuard

BrowserInvoicePage --> SifFacturesBridge
BrowserStudentPage --> SifFacturesBridge
SifFacturesBridge --> SifInternalApiClient
SifInternalApiClient --> InternalApiAuthenticator : HMAC boundary
InternalApiAuthenticator --> InvoiceQueryGateway
InvoiceQueryGateway --> InternalInvoiceScopeResolver
InvoiceQueryGateway --> InvoiceQueryService
InvoiceQueryService --> ResolvedInvoiceVisibilityPolicy
InvoiceQueryService --> InvoiceReadRepository
BrowserInvoicePage --> SifDocumentBridge
BrowserStudentPage --> SifDocumentBridge
SifDocumentBridge --> InvoiceDocumentAccessService
InvoiceDocumentAccessService --> PrivateDocumentStore
InvoiceDocumentAccessService --> FiscalDocumentAccessRepository
BrowserStudentPage ..> LegacyInvoiceReadContext : NO_SIF/flag
LegacyInvoiceReadContext ..> SifLegacyInvoiceMutationGuard
```

## 2. FINAL — frontera estable

```mermaid
classDiagram
direction LR
class InvoiceQueryPort {
  <<application>>
  +search(actor, criteria)
  +view(actor, uuid)
}
class InvoiceVisibilityPolicy {
  <<authorization>>
  +canView(actor, invoice, relations)
  +project(actor, view)
}
class InvoiceReadRepository {
  <<read model>>
}
class DocumentAccessPort {
  <<UC-080>>
  +download(actor, documentId)
}
class DocumentAuthorizationPolicy
class PrivateDocumentStore
class AccessAuditRepository
class InternalIntranetAdapter {
  <<adapter>>
}
class ExternalActorAdapter {
  <<future UC-102/126>>
}

InternalIntranetAdapter --> InvoiceQueryPort
ExternalActorAdapter --> InvoiceQueryPort
InvoiceQueryPort --> InvoiceVisibilityPolicy
InvoiceQueryPort --> InvoiceReadRepository
InternalIntranetAdapter --> DocumentAccessPort
ExternalActorAdapter --> DocumentAccessPort
DocumentAccessPort --> DocumentAuthorizationPolicy
DocumentAccessPort --> PrivateDocumentStore
DocumentAccessPort --> AccessAuditRepository
```

## 3. Decisions

- `InvoiceQueryService` mai serveix bytes.
- `InvoiceDocumentAccessService` mai genera una factura/document fiscal nou.
- La política de lectura no rep scope fiable del navegador.
- El fallback llegat no forma part del model FINAL.
- Els adaptadors externs d'alumne/empresa requereixen política per recurs pròpia; el scope intern “all + projection” és exclusiu d'actors interns autoritzats.
