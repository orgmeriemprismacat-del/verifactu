# UC-002 · Diagrama de classes ACTUAL / FINAL

**Data:** 2026-10-04  
**Base:** `main@2bd2a751832fc3f767b1e250b955922145a5577a` + correccions de `audit/uc-002-reconciliada-2026-10-04`.

## 1. ACTUAL — nucli SIF executable

~~~mermaid
classDiagram
direction LR
class PaymentService {
  +registerPayment(payload) array
  -createOrReusePayment(payload) array
  -reusePaymentAfterDuplicateKey(payload) array
  -assertSamePayload(payload, existing) void
}
class PaymentPayloadValidator {
  +validate(payload) array
  -positiveMoneyToCents(value, message) int
}
class PayloadIdempotencyValidator {
  +calculateHash(payload) string
  +assertMatches(payloadOrJson, expectedHash) void
  -withoutTraceMetadata(payload) array
}
class TransactionRunner {
  +run(callback) mixed
}
class PaymentRepository {
  +findByIdempotencyKey(db,key,forUpdate) array?
  +createPayment(db,payload) array
  -createAllocation(db,payment,allocation) void
  -refreshInvoicePaymentStatus(db,uuidFactura) void
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

### Estat

- **DOCUMENTAT:** sí.
- **IMPLEMENTAT:** sí.
- **VERIFICAT:** inspecció directa; proves automatitzades definides. CI de la branca encara és criteri de tancament.
- **PENDENT:** E2E/preproducció.

## 2. ACTUAL — frontera HTTP SIF reforçada a la branca

~~~mermaid
classDiagram
direction LR
class PaymentRegisterEndpoint {
  <<PHP endpoint>>
  +POST only
  +read raw body
  +authenticate signed request
  +authorize write role
  +registerPayment(payload)
}
class InternalApiAuthenticator {
  +authenticate(server,rawBody,method,path) actor
}
class InternalApiRequestRepository {
  +claim(...)
}
class PaymentService
class PaymentConfig {
  +payments.write_roles
  +internal_api.payment_signed_path
}
PaymentRegisterEndpoint --> InternalApiAuthenticator
InternalApiAuthenticator --> InternalApiRequestRepository
PaymentRegisterEndpoint --> PaymentConfig
PaymentRegisterEndpoint --> PaymentService
~~~

**Garanties:** HMAC, timestamp, UUID de request, replay guard, actor signat i allowlist de rols. Una allowlist buida denega l'escriptura.

## 3. ACTUAL — superfície llegada `/alumnes/pagaments/`

~~~mermaid
classDiagram
direction LR
class AlumnesPagamentsJS {
  <<JavaScript>>
  +buscar()
  +mostrarModalConfirmacio()
  +aplicarPagament()
}
class EfectuarPagamentEndpoint {
  <<PHP>>
  +POST only
  +checkSession()
  +checkOriginAndXHR()
  +checkPageRole()
  +validateInput()
}
class Usuari {
  +tePermisVisualitzacio(roles) bool
}
class Intranet {
  +mostrarPagaments(...)
  +consultaRolsEdiicio(page)
  +efectuarPagament(...)
  -efectuarPagamentFacturaGenerada(...)
  -efectuarPagamentInscripcio(...)
  -efectuarPagamentGrupal(...)
  -efectuarPagamentPack(...)
  -efectuarPagamentRegal(...)
}

AlumnesPagamentsJS --> EfectuarPagamentEndpoint
EfectuarPagamentEndpoint --> Usuari
EfectuarPagamentEndpoint --> Intranet
~~~

### Responsabilitats verificades al codi real

`Intranet.php` és un blob gran i sí conté la implementació. Per UC-002, la branca rellevant és:

`efectuarPagament(..., efact=1) -> efectuarPagamentFacturaGenerada(...)`.

Aquesta rutina llegeix factura/inscripcions, actualitza resum de cobrament llegat, distribueix imports i envia correus. A la branca d'auditoria s'han corregit la preservació de `factures.IMPORT`, l'acumulació de fraccions i els echoes de depuració.

## 4. ACTUAL — infraestructura d'auditoria existent però no wired

~~~mermaid
classDiagram
direction LR
class PaymentActionGateway {
  +run(auditContext, operation) mixed
}
class PaymentActionEventWriter {
  <<interface>>
  +append(db,event) string
}
class PaymentActionEventRepository {
  +append(db,event) string
}
class PaymentService

PaymentActionGateway --> PaymentActionEventWriter
PaymentActionEventWriter <|.. PaymentActionEventRepository
PaymentActionGateway ..> PaymentService : dissenyat per envoltar operacions
~~~

**Mancança:** `api/payments/register.php` crida actualment `PaymentService` directament. No s'ha connectat `PaymentActionGateway` perquè el gateway i `PaymentService` obren transaccions pròpies amb el mateix `TransactionRunner`; fer-ho sense redisseny provocaria transaccions imbricades no suportades pel runner actual.

## 5. ACTUAL — ledger per inscripció parcial

~~~mermaid
classDiagram
direction LR
class EnrollmentFundMovementRepository {
  +lockPayment(db,uuidPayment) array
  +findInvoiceLineForInscription(db,uuidFactura,idInsc) array
  +insertOrReuseExternalAllocation(db,movement) array
  +insertOrReuseCompensationAllocation(db,movement) array
}
class CourseEnrollmentFundAllocationService {
  +allocate(db,dsOrder,snapshot,invoiceResult) array
}
class PaymentRepository {
  +createPayment(db,payload) array
}
CourseEnrollmentFundAllocationService --> EnrollmentFundMovementRepository
PaymentRepository ..> EnrollmentFundMovementRepository : no integrat genèricament
~~~

La infraestructura existeix i és utilitzada en fluxos específics, però no hi ha una atribució genèrica UC-002 des de `payment_allocation` cap a `ID_INSC`.

## 6. FINAL — arquitectura objectiu

~~~mermaid
classDiagram
direction LR
class PaymentCommandEndpoint {
  +POST signed command
}
class InternalApiAuthenticator
class PaymentAuthorizationPolicy {
  +authorize(actor, invoice, command)
}
class ExternalReceiptReconciler {
  +resolveUniqueReceipt(command)
}
class PaymentCommandService {
  +registerExistingInvoicePayment(command) result
}
class PaymentActionGateway
class PaymentService
class EnrollmentFundAllocationService {
  +allocatePerEnrollment(result, context)
}
class LegacyPaymentSync {
  +syncAfterSifCommit(result)
}
class PaymentNotificationOutbox {
  +enqueue(result)
}

PaymentCommandEndpoint --> InternalApiAuthenticator
PaymentCommandEndpoint --> PaymentAuthorizationPolicy
PaymentCommandEndpoint --> ExternalReceiptReconciler
PaymentCommandEndpoint --> PaymentCommandService
PaymentCommandService --> PaymentActionGateway
PaymentActionGateway --> PaymentService
PaymentCommandService --> EnrollmentFundAllocationService
PaymentCommandService --> LegacyPaymentSync
PaymentCommandService --> PaymentNotificationOutbox
~~~

## 7. Invariants FINAL

1. Cap mutació econòmica via GET.
2. Actor/rol/request-id autenticats abans de mutar.
3. Evidència externa identificable abans de crear un `CHARGE`.
4. Reintent equivalent → mateix `UUID_PAYMENT`; payload diferent → conflicte.
5. `SUM(payment_allocation.IMPORT) = payment_transaction.IMPORT`.
6. El pagament no canvia dades fiscals de la factura emesa.
7. Atribució per inscripció reconciliada quan aplica.
8. `payment_action_event` registra intent i resultat.
9. Sync llegada i notificacions passen després del commit SIF i són retryables.
10. Cap fallada de sync/correu crea un segon cobrament.
