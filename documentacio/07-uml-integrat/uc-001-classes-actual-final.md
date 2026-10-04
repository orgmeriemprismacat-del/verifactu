# UC-001 · Classes ACTUAL / FINAL

**Base:** `main` reauditat el 2026-10-02 i reconciliació canònica `audit/uc-001-reconciled-2026-10-03` / PR #145.

## 1. ACTUAL

```mermaid
classDiagram
direction LR
class InternalApiAuthenticator { +authenticate(server,rawBody,method,path) array }
class InternalInvoiceIssueScopeResolver { +resolve(actor) array }
class InternalInvoiceIssuePayloadPolicy { +prepare(payload,actor) array }
class InvoicePayloadValidator { +validate(payload) array }
class InvoiceService { +issueInvoice(payload) array
-requiredInitialPaymentMovementDate(payment) string }
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
class CommercialOperationRepository { +findByIntentUuid(db,uuidIntent,forUpdate) array
+linkInvoice(db,uuidOperation,uuidFactura) void }
class OperationLineInvoiceLinkRepository { +link(db,uuidOperationLine,facturaLineId,amount) void }

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
InvoiceService --> CommercialOperationRepository : link opcional operació-factura
InvoiceRepository --> OperationLineInvoiceLinkRepository : línia comercial-factura
InvoiceRepository --> RegistrationSnapshot
RegistrationSnapshot --> RecordFactory
RecordFactory --> RecordHash
InvoiceRepository --> HashCalculator
```

**Límits ACTUAL:** l'autenticador acredita petició interna i anti-replay; el resolver comprova rol; la policy impedeix bypass Redsys/UC-004 i fixa actor/emissor servidor al generic endpoint. `InvoiceService` retorna projecció d’estats, persisteix events i, si rep `uuid_operation`, enllaça l'operació comercial transaccionalment. `InvoiceRepository` persisteix `factura_registre_control` i materialitza `operation_line_invoice_link` quan existeix `uuid_operation_line`. `HashCalculator` i `RecordHash` són empremtes diferents.

## 1.1. Frontera intranet ACTUAL relacionada

```mermaid
classDiagram
direction LR
class LegacyInvoiceMutationAuthorization {
 +assertSameOrigin()
 +assertCanEdit(user,intranet,page)
}
class SifLegacyInvoiceMutationGuard {
 +assertLegacyMutationAllowed(user,invoiceId)
 +assertLegacyEnrollmentAllowed(user,enrollmentId)
 +assertLegacyRelationAllowed(user,relation)
}
class SifInternalApiClient
class InvoiceBeforePaymentAccess
class InvoiceService

LegacyInvoiceMutationAuthorization --> SifLegacyInvoiceMutationGuard : abans de mutar llegat
SifLegacyInvoiceMutationGuard --> SifInternalApiClient : consulta cobertura SIF
InvoiceBeforePaymentAccess --> SifInternalApiClient : proxy UC-004
SifInternalApiClient --> InvoiceService : via endpoint signat
```

**Límit:** el guard de mutació llegada és executable però feature-gated; la seva presència al repositori no demostra que `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1` estigui activat a preproducció/producció.

## 1.2. Traça comercial Redsys / Alumne PrisMa ACTUAL

```mermaid
classDiagram
direction LR
class PrismaStudentCourseCheckoutService { +stageAndCreateIntent(...) array
-ensureOperationLine(...) string }
class RedsysPaymentIntentService
class RedsysInvoicePayloadBuilder { +buildFromValidatedNotification(...) array }
class LegacyCourseInvoicePayloadBuilder { +build(snapshot) array }
class CommercialOperationRepository { +findByIntentUuid(...)
+linkInvoice(...) }
class InvoiceService
class InvoiceRepository
class OperationLineInvoiceLinkRepository
class commercial_operation_line { <<table>> }
class redsys_payment_intent { <<table>> }

PrismaStudentCourseCheckoutService --> commercial_operation_line : crea/reutilitza trusted line
PrismaStudentCourseCheckoutService --> RedsysPaymentIntentService : snapshot operation/line_uuid
RedsysPaymentIntentService --> redsys_payment_intent
RedsysInvoicePayloadBuilder --> redsys_payment_intent : DS_ORDER
RedsysInvoicePayloadBuilder --> CommercialOperationRepository : UUID_INTENT -> UUID_OPERATION
LegacyCourseInvoicePayloadBuilder --> InvoiceService : uuid_operation + uuid_operation_line
InvoiceService --> CommercialOperationRepository : UUID_FACTURA
InvoiceService --> InvoiceRepository
InvoiceRepository --> OperationLineInvoiceLinkRepository
OperationLineInvoiceLinkRepository --> commercial_operation_line
```

**Cobertura ACTUAL:** aquesta cadena està implementada per checkout Alumne PrisMa. No acredita encara que pack/grup/regal/USOC/manual creïn una operació i línies comercials equivalents.

## 2. FINAL pendent

```mermaid
classDiagram
direction LR
class CommercialOperationCoverageGuard { <<PARCIAL>>
+assertIssueAllowed(operation,payload) }
class ServerFiscalSnapshotAssembler { <<PENDENT>>
+build(operation,issuerConfig) array }
class CommercialOperationRepository { <<IMPLEMENTAT core>> }
class CommercialOperationLineFactory { <<PENDENT transversal>> }
class InvoiceService
class InvoiceRepository

CommercialOperationCoverageGuard --> InvoiceService : abans de numerar
ServerFiscalSnapshotAssembler --> InvoiceService : snapshot oficial
CommercialOperationLineFactory --> InvoiceService : línies comercials tots els productes
InvoiceService --> CommercialOperationRepository : operació origen
```

**No acreditat:** implementació completa del diagrama FINAL, prova AEAT productiva ni desplegament.

## 3. Revalidació 03/10

`InvoiceService` exigeix ara una `movement_date` explícita i no buida per al payment inicial. Aquesta dada forma part del payload econòmic material i evita que un mateix reintent de negoci generi fingerprints diferents només pel rellotge del servidor.
