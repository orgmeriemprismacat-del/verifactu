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

## 5. Decisions

- `InvoiceQueryService` mai serveix bytes.
- `InvoiceDocumentAccessService` mai genera una factura/document fiscal nou.
- La política de lectura no rep scope fiable del navegador.
- El fallback llegat no forma part del model FINAL.
- Els adaptadors externs d'alumne/empresa requereixen política per recurs pròpia; el scope intern “all + projection” és exclusiu d'actors interns autoritzats.


## 3. ACTUAL — frontera intranet/llegat detallada

```mermaid
classDiagram
direction LR
class AlumnesFacturaJs {
  +uc007SifSearch()
  +cercarFacturesLlegat()
  +mostraLlistatUsuaris()
  +cercarUSuari()
  +mostrarModalConsultaInformacio()
  +mostrarModalPrevisualitzaFactura()
}
class AlumnesMostrarAlumneJs {
  +viewByEnrollment()
  +mostrarModalConsultaFacturaLlegat()
}
class LegacyInvoiceReadContext {
  +open()
  +persist()
}
class LegacyInvoiceReadAuthorization {
  +assertCanView()
}
class LegacyInvoiceMutationAuthorization {
  +assertCanEdit()
  +assertSameOrigin()
}
class SifAuthenticatedActor {
  +fromUser()
}
class SifLegacyInvoiceMutationGuard {
  +assertLegacyMutationAllowed()
  +assertLegacyEnrollmentAllowed()
  +assertLegacyRelationAllowed()
}
class Intranet {
  +buscarUsuaris_Factures()
  +mostrarTaulaUsuaris2_Alumnes()
  +mostrarTotesFacturesUsuari_Factures()
  +modalConsultaInformacio_Factures()
  +modalPrevisualitzaFactura_Factures()
  +generaFactura(factura,descarrega,marcaGenerada)
  +guardarDadesFactura_Factures()
}
class SifFacturesBridge
class SifDocumentBridge

AlumnesFacturaJs --> SifFacturesBridge
AlumnesFacturaJs --> LegacyInvoiceReadContext : fallback
AlumnesMostrarAlumneJs --> SifFacturesBridge
AlumnesMostrarAlumneJs --> LegacyInvoiceReadContext : NO_SIF
LegacyInvoiceReadContext --> LegacyInvoiceReadAuthorization
LegacyInvoiceReadContext --> Intranet
SifFacturesBridge --> SifAuthenticatedActor
SifDocumentBridge --> SifAuthenticatedActor
LegacyInvoiceReadContext ..> LegacyInvoiceMutationAuthorization : POST protected reads/writes
LegacyInvoiceReadContext ..> SifLegacyInvoiceMutationGuard : no legacy path for SIF-owned invoice
SifLegacyInvoiceMutationGuard --> SifAuthenticatedActor
```

## 4. Mapatge de classes per superfície/apartat

| Superfície/apartat | Classes/adaptadors principals |
| --- | --- |
| F01 càrrega/rol | `comprovarSessio.php`, `LegacyInvoiceReadContext`, `LegacyInvoiceReadAuthorization`, `SifAuthenticatedActor`, `InternalInvoiceScopeResolver` |
| F02 cerca | `alumnes-factura.js`, `sifFactures.php`, `InvoiceQueryGateway`, `InvoiceQueryService`, `InvoiceQueryCriteriaValidator`, fallback `Intranet::buscarUsuaris_Factures` |
| F03 selector | `alumnes-factura.js`, `mostrarTaulaUsuaris2.php`, `Intranet::mostrarTaulaUsuaris2_Alumnes` |
| F04 llistat | `InvoiceQueryService` / fallback `Intranet::mostrarTotesFacturesUsuari_Factures` |
| F05 detall | `InvoiceQueryService::view` / fallback `Intranet::modalConsultaInformacio_Factures` + guard |
| F06 preview | metadata `InvoiceReadRepository` + UC-080 / fallback `Intranet::generaFactura(...,false)` |
| F07 download | `sifDocument.php`, `InvoiceDocumentAccessService`, `PrivateDocumentStore`, `FiscalDocumentAccessRepository`; fallback `generaFactura(...,true,false)` |
| AL-16/17/18 | `alumnes-mostrar-alumne.js`, `sifFactures.php`, `SifDocumentBridge`, fallback llegat |
