# UC-001 · Classes ACTUAL / FINAL

**Base:** `main` reauditat el 2026-10-02 i branca `audit/uc-001-hardening-2026-10-02`.

## 1. ACTUAL

```mermaid
classDiagram
direction LR
class InternalApiAuthenticator { +authenticate(server,rawBody,method,path) array }
class InternalInvoiceIssueScopeResolver { +resolve(actor) array }
class InternalInvoiceIssuePayloadPolicy { +prepare(payload,actor) array }
class InvoicePayloadValidator { +validate(payload) array }
class InvoiceService { +issueInvoice(payload) array }
class FiscalSequenceRepository { +next(db,series,year) int }
class InvoiceRepository { +findByIdempotencyKey(db,key,forUpdate) array
+lockChainState(db) array
+createInvoiceGraph(db,payload,seq,chainState) array }
class RegistrationSnapshot { +invoice(db,chain,payload,number,issuedAt) array }
class RecordFactory { +freeze(type,header,fields,previous,generatedAt) array }
class RecordHash { +calculate(type,record) string }
class HashCalculator { +calculate(payload,previousHash) string }
class PaymentPayloadValidator { +validate(payload) array }
class PaymentRepository { +createPayment(db,payload) array }
class OperationalEventRepository { +append(db,event) string }
class SifAuditEventRepository { +append(db,event) string }

InternalApiAuthenticator --> InternalInvoiceIssueScopeResolver
InternalInvoiceIssueScopeResolver --> InternalInvoiceIssuePayloadPolicy
InternalInvoiceIssuePayloadPolicy --> InvoiceService
InvoiceService --> InvoicePayloadValidator
InvoiceService --> FiscalSequenceRepository
InvoiceService --> InvoiceRepository
InvoiceService --> PaymentPayloadValidator
InvoiceService --> PaymentRepository
InvoiceService --> OperationalEventRepository
InvoiceService --> SifAuditEventRepository
InvoiceRepository --> RegistrationSnapshot
RegistrationSnapshot --> RecordFactory
RecordFactory --> RecordHash
InvoiceRepository --> HashCalculator
```

**Límits ACTUAL:** l'autenticador acredita petició interna i anti-replay; el resolver comprova rol; la policy impedeix bypass Redsys/UC-004 i fixa actor/emissor servidor al generic endpoint. `InvoiceService` retorna projecció d’estats i persisteix `operational_event` + `sif_audit_event`; `InvoiceRepository` persisteix també `factura_registre_control` per l’ALTA. `HashCalculator` i `RecordHash` són empremtes diferents.

## 2. FINAL pendent

```mermaid
classDiagram
direction LR
class CommercialOperationCoverageGuard { <<PENDENT>>
+assertIssueAllowed(operation,payload) }
class ServerFiscalSnapshotAssembler { <<PENDENT>>
+build(operation,issuerConfig) array }
class CommercialOperationRepository { <<PARCIAL esquema existent>> }
class OperationLineInvoiceLinkRepository { <<PENDENT writer UC-001>> }
class InvoiceService
class InvoiceRepository

CommercialOperationCoverageGuard --> InvoiceService : abans de numerar
ServerFiscalSnapshotAssembler --> InvoiceService : snapshot oficial
InvoiceService --> CommercialOperationRepository : operació origen
InvoiceRepository --> OperationLineInvoiceLinkRepository : línia comercial↔fiscal
```

**No acreditat:** implementació completa del diagrama FINAL, prova AEAT productiva ni desplegament.
