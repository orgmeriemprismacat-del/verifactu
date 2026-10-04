# UC-011 · Diagrames de classes ACTUAL / FINAL

## 1. ACTUAL abans de l'auditoria

~~~mermaid
classDiagram
direction LR
class HistoricalInvoiceMigrationService
class HistoricalInvoicePayloadBuilder
class HistoricalInvoiceMigrationRepository
class TransactionRunner
class UuidGenerator

HistoricalInvoiceMigrationService --> HistoricalInvoicePayloadBuilder
HistoricalInvoiceMigrationService --> TransactionRunner
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationRepository
HistoricalInvoiceMigrationRepository --> UuidGenerator
~~~

**Limitacions de l'ACTUAL original:** reús per clau sense contrast material, data original amb default, visibilitat permissiva, sense preflight i sense auditoria operativa.

## 2. FINAL implementat a la branca

~~~mermaid
classDiagram
direction LR
class HistoricalInvoiceMigrationService {
 +importHistoricalInvoice(input) array
 -appendAudit(db,payload,result) array
}
class HistoricalInvoicePayloadBuilder {
 +build(input) array
}
class HistoricalInvoiceMigrationPreflight {
 +assertSafe(db,payload) void
}
class HistoricalInvoiceMigrationRepository {
 +importHistoricalInvoice(db,payload) array
 +findByIdempotencyKey(db,key,forUpdate) array
}
class PayloadIdempotencyValidator {
 +calculateHash(payload) string
 +assertMatches(payload,storedHash) void
}
class OperationalEventRepository {
 +append(db,event) string
}
class SifAuditEventRepository {
 +append(db,event) string
}
class TransactionRunner
class UuidGenerator

HistoricalInvoiceMigrationService --> HistoricalInvoicePayloadBuilder
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationPreflight
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationRepository
HistoricalInvoiceMigrationService --> OperationalEventRepository
HistoricalInvoiceMigrationService --> SifAuditEventRepository
HistoricalInvoiceMigrationService --> TransactionRunner
HistoricalInvoiceMigrationRepository --> PayloadIdempotencyValidator
HistoricalInvoiceMigrationRepository --> UuidGenerator
~~~

## 3. Superfície llegada ACTUAL

~~~mermaid
classDiagram
direction LR
class alumnes_factura_sif_js
class sifFactures_php
class SifInternalApiClient
class SifLegacyInvoiceMutationGuard
class LegacyInvoiceMutationAuthorization
class Intranet

alumnes_factura_sif_js --> sifFactures_php : consulta
sifFactures_php --> SifInternalApiClient
SifLegacyInvoiceMutationGuard --> SifInternalApiClient : comprovar govern SIF
LegacyInvoiceMutationAuthorization --> SifLegacyInvoiceMutationGuard
SifLegacyInvoiceMutationGuard --> Intranet : permet/bloqueja llegat
~~~

Aquesta superfície no executa UC-011; només consulta i governa el cut-over.

## 4. FINAL productiu encara pendent

Components encara no implementats com a bloc productiu:

- inventari/extractor de lot;
- model multiemissor;
- custòdia immutable de bytes originals;
- autorització/aprovació de lot;
- reconciliació final de completitud.

## 5. Estat

El FINAL de codi d'importació unitària ja incorpora preflight i auditoria. El FINAL productiu de migració massiva continua pendent.
