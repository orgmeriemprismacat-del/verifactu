# UC-011 · Diagrames de classes ACTUAL / FINAL

**Data de tall:** 03/10/2026.  
**Branca d'auditoria:** audit/uc-011-2026-10-03.

**Objectiu:** separar el codi existent a main en iniciar l'auditoria del reforç implementat en aquesta branca i dels components que continuen sent disseny pendent. UC-011 és avui un flux CLI; no s'ha localitzat cap pàgina JS/intranet que executi la migració.

## 1. ACTUAL a main · preview sense escriptura

~~~plantuml
@startuml
title UC-011 | ACTUAL main | preview CLI
class "preview-historical-invoice-migration.php" as Preview <<cli>>
class HistoricalInvoicePayloadBuilder
class SifException
Preview --> HistoricalInvoicePayloadBuilder : build(input)
HistoricalInvoicePayloadBuilder ..> SifException : validació
note right of HistoricalInvoicePayloadBuilder
  ACTUAL main abans de l'auditoria:
  - deriva sèrie/any/seqüència de NUM_VISIBLE
  - issue_date podia usar la data actual
  - invoice_status podia venir de l'entrada
  - VISIBLE_ALUMNE podia quedar a 1 per defecte
end note
@enduml
~~~

El preview rebutja SIF_ENV=production i no obre connexió d'escriptura. És una ajuda de preparació del payload, no una prova de completitud del lot.

## 2. ACTUAL a main · processament transaccional

~~~plantuml
@startuml
title UC-011 | ACTUAL main | processament
class "process-historical-invoice-migration.php" as Process <<cli>>
class HistoricalInvoiceMigrationService
class HistoricalInvoicePayloadBuilder
class HistoricalInvoiceMigrationRepository
class TransactionRunner
class UuidGenerator
database factura
database factura_linia
database fact_rels
database factura_documents

Process --> HistoricalInvoiceMigrationService
HistoricalInvoiceMigrationService --> HistoricalInvoicePayloadBuilder
HistoricalInvoiceMigrationService --> TransactionRunner
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationRepository
HistoricalInvoiceMigrationRepository --> UuidGenerator
HistoricalInvoiceMigrationRepository --> factura
HistoricalInvoiceMigrationRepository --> factura_linia
HistoricalInvoiceMigrationRepository --> fact_rels
HistoricalInvoiceMigrationRepository --> factura_documents

note bottom of HistoricalInvoiceMigrationRepository
  ACTUAL main abans de l'auditoria:
  si IDEMPOTENCY_KEY ja existia,
  retornava la factura anterior sense
  comparar el payload.
end note
@enduml
~~~

No hi ha dependència d'InvoiceService, FiscalSequenceRepository, factura_registres ni fiscal_queue. Això és coherent amb una importació històrica NO_VERIFACTU.

## 3. ACTUAL · pàgina llegada `alumnes-factura` com a sistema origen

~~~plantuml
@startuml
title UC-011 | ACTUAL upstream | consulta i mutació del llegat
class "alumnes-factura.php" as Page <<page>>
class "alumnes-factura.js" as JS <<javascript>>
class "consultaUsuarisFacturaRelacionada.php" as SearchLegacy <<ajax>>
class "guardarDadesFactura_Factures.php" as SaveLegacy <<ajax>>
class "anularFactura_Factures.php" as CancelLegacy <<ajax>>
class "descarregaFactura.php" as DownloadLegacy <<ajax>>
class LegacyInvoiceReadContext
class LegacyInvoiceMutationAuthorization
class SifLegacyInvoiceMutationGuard
class SifInternalApiClient
class Intranet
database "web.factures" as Legacy
database "SIF factura/fact_rels" as SIF

Page --> JS
JS --> SearchLegacy : fallback consulta llegada
JS --> SaveLegacy : editar dades
JS --> CancelLegacy : anul·lar
JS --> DownloadLegacy : regenerar PDF
SearchLegacy --> LegacyInvoiceReadContext
SaveLegacy --> LegacyInvoiceMutationAuthorization
SaveLegacy --> SifLegacyInvoiceMutationGuard
CancelLegacy --> SifLegacyInvoiceMutationGuard
DownloadLegacy --> SifLegacyInvoiceMutationGuard
SifLegacyInvoiceMutationGuard --> SifInternalApiClient : searchInvoices()
SifInternalApiClient --> SIF
SearchLegacy --> Intranet
SaveLegacy --> Intranet
CancelLegacy --> Intranet
DownloadLegacy --> Intranet : generaFactura(id,true)
Intranet --> Legacy
@enduml
~~~

Aquesta pàgina **no importa** factures al SIF. És la superfície upstream que pot canviar el llegat abans del cut-over. El guard pot bloquejar mutacions/regeneracions quan ja existeix una relació SIF `HISTORIC_WEB_FACTURES`, però depèn dels feature flags de protecció/consulta.

El PDF retornat per `descarregaFactura.php` es genera temporalment amb `Intranet->generaFactura(..., true)`; no és per si sol evidència dels bytes originals emesos.

## 4. FINAL implementat a la branca d'auditoria

~~~plantuml
@startuml
title UC-011 | FINAL branca | import històric reforçat
class HistoricalInvoiceMigrationService
class HistoricalInvoicePayloadBuilder
class HistoricalInvoiceMigrationRepository
interface PayloadIdempotencyValidatorInterface
class PayloadIdempotencyValidator
class TransactionRunner
class UuidGenerator
database "factura.IDEMPOTENCY_PAYLOAD_HASH" as HashColumn
database factura_linia
database fact_rels
database factura_documents

HistoricalInvoiceMigrationService --> HistoricalInvoicePayloadBuilder
HistoricalInvoiceMigrationService --> TransactionRunner
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationRepository
HistoricalInvoiceMigrationRepository --> UuidGenerator
HistoricalInvoiceMigrationRepository --> PayloadIdempotencyValidatorInterface
PayloadIdempotencyValidator ..|> PayloadIdempotencyValidatorInterface
HistoricalInvoiceMigrationRepository --> HashColumn : hash projecció material
HistoricalInvoiceMigrationRepository --> factura_linia
HistoricalInvoiceMigrationRepository --> fact_rels
HistoricalInvoiceMigrationRepository --> factura_documents

note right of HistoricalInvoicePayloadBuilder
  Reforç implementat:
  - issue_date original obligatòria
  - components de número coherents amb NUM_VISIBLE
  - invoice_status forçat a HISTORICAL
  - VISIBLE_ALUMNE per defecte = 0
  - valida dates, billing/totals/línies/rels
  - normalitza emissor i camps fiscals
end note

note right of PayloadIdempotencyValidator
  Reintent material equivalent -> reuse
  Aliases equivalents no creen fals conflicte
  Dades persistides diferents -> 409
  Hash absent/antic -> fail closed
end note
@enduml
~~~

## 5. FINAL requerit però encara pendent

~~~plantuml
@startuml
title UC-011 | OBJECTIU pendent | governança de migració
class HistoricalMigrationPreflight <<pendent>>
class HistoricalInventoryReconciler <<pendent>>
class HistoricalDocumentCustody <<pendent>>
class OperationalEventRepository
database operational_event
database sif_audit_event
database "storage privat immutable" as Storage

HistoricalMigrationPreflight --> HistoricalInvoiceMigrationService : només si compatible
HistoricalInventoryReconciler --> HistoricalInvoiceMigrationService : lot preparat
HistoricalDocumentCustody --> Storage : bytes originals + hash real
HistoricalInvoiceMigrationService ..> OperationalEventRepository : pendent de cablejar
OperationalEventRepository --> operational_event
HistoricalInvoiceMigrationService ..> sif_audit_event : pendent
@enduml
~~~

### Mancances finals que no s'han de presentar com a implementades

- Preflight multiemissor i de col·lisió entre numeració històrica i fiscal_sequence.
- Extracció completa de web.factures i reconciliació per ID d'origen.
- Custòdia dels bytes originals i verificació física del SHA-256.
- Actor, rol, request/correlation IDs i events d'auditoria del procés.
- Canal productiu autoritzat: els dos scripts actuals rebutgen explícitament SIF_ENV=production.
- No hi ha JS ni pàgina d'intranet que executi UC-011; sí hi ha la pàgina llegada `alumnes-factura` com a origen mutable.
- Prova de cut-over dels guards llegats amb feature flags actius.

## 6. Navegació

[Fitxa funcional](../06-fitxes-funcionals/uc-011.md) · [UML integrat](uc-011-importar-factura-historica.md) · [Seqüències](uc-011-sequencies-actual-final.md) · [Activitats](uc-011-activitats-actual-final.md) · [Traçabilitat](uc-011-tracabilitat-implementacio.md)
