# UC-002 · Classes ACTUAL / FINAL — Registrar pagament sobre factura existent

**Data d’auditoria:** 2026-10-03  
**Tall de partida:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Criteri:** ACTUAL només representa responsabilitats acreditades al codi del repositori. FINAL separa explícitament disseny objectiu de codi existent.

## 1. Classes ACTUAL — nucli SIF executable

~~~mermaid
classDiagram
direction LR
class PaymentService {
  +registerPayment(payload) array
  -createOrReusePayment(payload) array
  -reusePaymentAfterDuplicateKey(payload) array
  -assertSamePayload(payload,existing) void
}
class PaymentPayloadValidator {
  +validate(payload) array
  -positiveMoneyToCents(value,message) int
}
class PayloadIdempotencyValidator {
  +calculateHash(payload) string
  +assertMatches(payloadOrLegacy,hash) void
}
class TransactionRunner {
  +run(callback) mixed
}
class PaymentRepository {
  +findByIdempotencyKey(db,key,forUpdate) array?
  +createPayment(db,payload) array
  -createAllocation(db,payment,allocation) void
  -refreshInvoicePaymentStatus(db,invoice) void
}
class PaymentStatusCalculator {
  +calculate(invoiceTotal,charges,refunds) string
}
class UuidGenerator {
  +generate() string
}

PaymentService --> PaymentPayloadValidator
PaymentService --> PayloadIdempotencyValidator
PaymentService --> TransactionRunner
PaymentService --> PaymentRepository
PaymentRepository --> PaymentStatusCalculator
PaymentRepository --> UuidGenerator
~~~

### Responsabilitats acreditades

- `PaymentPayloadValidator` valida camps obligatoris, tipus/mètode, imports estrictament positius, precisió monetària de dos decimals i que la suma de les assignacions coincideixi exactament amb l’import del moviment.
- `PaymentService` serialitza per clau idempotent, compara el payload versionat i reutilitza només una petició equivalent.
- `PaymentRepository` persisteix `payment_transaction` i `payment_allocation`, bloqueja la factura i recalcula `ESTAT_COBRAMENT`.
- `PaymentStatusCalculator` resol `PENDING`, `PARTIAL`, `PAID`, `OVERPAID`, `PARTIALLY_REFUNDED` i `REFUNDED`.

## 2. Classes ACTUAL — adaptador manual

~~~mermaid
classDiagram
direction LR
class ManualPaymentService {
  +registerByUuid(db,uuid,input) array
  +registerByNumVisible(db,number,input) array
}
class ManualPaymentInvoiceRepository {
  +findByUuid(db,uuid,forUpdate) array?
  +findByNumVisible(db,number,forUpdate) array?
}
class ManualPaymentPayloadBuilder {
  +forExistingInvoice(uuid,input) array
}
class PaymentService

ManualPaymentService --> ManualPaymentInvoiceRepository
ManualPaymentService --> ManualPaymentPayloadBuilder
ManualPaymentService --> PaymentService
~~~

Aquest adaptador existeix i és executable des dels scripts de preflight/preview/process. No acredita, per si sol, la pantalla llegada de la intranet.

## 3. Classes ACTUAL — infraestructura existent però no connectada al registre genèric

~~~mermaid
classDiagram
direction LR
class PaymentActionGateway {
  +run(auditContext,operation) mixed
}
class PaymentActionEventWriter {
  <<interface>>
  +append(db,event) string
}
class PaymentActionEventRepository {
  +append(db,event) string
}
class EnrollmentFundMovementRepository {
  +lockPayment(db,payment) array
  +findInvoiceLineForInscription(db,invoice,idInsc) array
  +insertOrReuseExternalAllocation(db,movement) array
  +insertOrReuseCompensationAllocation(db,movement) array
}
PaymentActionGateway --> PaymentActionEventWriter
PaymentActionEventWriter <|.. PaymentActionEventRepository
~~~

**Important:** aquestes classes existeixen, però `sif/public/api/payments/register.php` i `PaymentService::registerPayment()` no passen actualment per `PaymentActionGateway`, i `PaymentRepository::createPayment()` no crea moviments a `enrollment_fund_movement`.

## 4. Frontera llegada ACTUAL

~~~mermaid
classDiagram
direction LR
class AlumnesPagamentsJS {
  <<JavaScript>>
  +buscar()
  +mostrarConfirmacio()
  +aplicarPagament()
}
class EfectuarPagamentEndpoint {
  <<PHP script>>
  +POST only
  +valida sessio
  +valida origen AJAX
  +valida rol pagina pagaments
  +valida import data banc
}
class Usuari {
  +tePermisVisualitzacio(roles)
}
class Intranet {
  <<snapshot no auditable>>
  +consultaRolsEdiicio(page)
  +efectuarPagament(...)
}
AlumnesPagamentsJS --> EfectuarPagamentEndpoint
EfectuarPagamentEndpoint --> Usuari
EfectuarPagamentEndpoint --> Intranet
~~~

El fitxer `codi-drive/intranet-actual/Intranet.php` del repositori és buit. Per tant, la signatura anterior reflecteix les crides observables als endpoints, però **la implementació interna de `efectuarPagament()` no es pot considerar verificada des de GitHub**.

## 5. Classes FINAL — contracte objectiu

~~~mermaid
classDiagram
direction LR
class PaymentCommandEndpoint {
  <<FINAL / pendent>>
  +POST signed command
}
class InternalApiAuthenticator {
  +authenticate(server,rawBody,method,path) actor
}
class PaymentAuthorizationPolicy {
  <<FINAL / pendent>>
  +authorize(actor,command) decision
}
class ExternalReceiptReconciler {
  <<FINAL / pendent>>
  +identifyEvidence(command) receipt
}
class PaymentActionGateway {
  +run(context,operation) mixed
}
class PaymentService {
  +registerPayment(payload) array
}
class EnrollmentFundAllocationService {
  <<FINAL / parcial en fluxos especifics>>
  +allocatePerEnrollment(payment,invoice,lines) array
}
class LegacyPaymentSync {
  <<FINAL / pendent>>
  +syncAfterSifCommit(result) void
}

PaymentCommandEndpoint --> InternalApiAuthenticator
PaymentCommandEndpoint --> PaymentAuthorizationPolicy
PaymentCommandEndpoint --> ExternalReceiptReconciler
PaymentCommandEndpoint --> PaymentActionGateway
PaymentActionGateway --> PaymentService
PaymentService --> EnrollmentFundAllocationService
PaymentService --> LegacyPaymentSync : post-commit
~~~

### Invariants FINAL

1. Cap mutació econòmica per GET.
2. Actor autenticat, rol autoritzat i petició correlacionada abans de mutar.
3. Evidència d’ingrés extern identificable; una clau interna no substitueix prova bancària/TPV.
4. Un únic `CHARGE` per fet econòmic; reintents equivalents reutilitzen el resultat i conflictes fallen tancats.
5. `SUM(payment_allocation)=payment_transaction.IMPORT`.
6. Quan hi ha diverses inscripcions, l’atribució individual usa el ledger `enrollment_fund_movement` sense inventar entrades de caixa.
7. Cada acció i resultat deixa `payment_action_event`.
8. La sincronització llegada és posterior al commit SIF i mai autoritat fiscal/econòmica primària.
