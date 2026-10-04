# UC-002 · Diagrama de classes ACTUAL / FINAL

**Data:** 2026-10-04  
**Base:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757` + correccions de `audit/uc-002-reconciliada-main-2026-10-04`.

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

## 4. ACTUAL — journal funcional connectat al command UC-002

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
class TransactionRunner {
  +run(callback) mixed
  -ownsTransaction bool
}
class ExistingInvoicePaymentCommandService
class PaymentService

PaymentActionGateway --> PaymentActionEventWriter
PaymentActionEventWriter <|.. PaymentActionEventRepository
PaymentActionGateway --> TransactionRunner
PaymentActionGateway --> ExistingInvoicePaymentCommandService
ExistingInvoicePaymentCommandService --> PaymentService
PaymentService --> TransactionRunner
~~~

`TransactionRunner` detecta si la PDO ja està dins una transacció. El gateway és propietari del `BEGIN/COMMIT/ROLLBACK` del command UC-002; el `PaymentService` hi participa sense obrir una transacció imbricada. Per tant:

- `REQUESTED` es registra abans de la mutació;
- el `CHARGE` i `SUCCEEDED/REUSED` comparteixen commit;
- un error de l'operació provoca rollback i `FAILED`;
- `CORRELATION_ID` i `PAYMENT_IDEMPOTENCY_KEY` conserven la clau estable de la intenció.

**Abast:** aquest wiring s'aplica a `action=register_existing_invoice`. El mode low-level `action=''` es manté per compatibilitat de callers interns i no defineix el flux autoritatiu UC-002.

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

`register_existing_invoice` ja connecta `ExistingInvoiceEnrollmentFundAllocationService` amb `EnrollmentFundMovementRepository`: cada `CHARGE` manual queda atribuït idempotentment a les línies `INSCRIPCIO` i a `ID_INSC`, reutilitzant els mateixos moviments en retry.

## 6. ACTUAL — pont autoritatiu Intranet → SIF darrere feature flag

~~~mermaid
classDiagram
direction LR
class AlumnesPagamentsJS {
  +initializeSecureUc002Payment()
  +aplicarPagamentSif(...)
  +uc002PaymentRequestId(storageKey)
}
class SifExistingInvoicePaymentAccess {
  +resolve(user,intranet) actor
  +csrfToken() string
  +assertCsrf(server) void
  +authoritativeEnabled() bool
}
class SifInternalApiClient {
  +registerExistingInvoicePayment(actor,roles,selector,payment) array
}
class SifPaymentProxy {
  <<sifPagamentFactura.php>>
  +POST JSON
  +CSRF
  +request UUID v4
  +manual bank policy
}
class ExistingInvoicePaymentCommandService {
  +register(db,command) array
}
class ManualPaymentService
class PaymentService
class ExistingInvoiceLegacyProjectionService {
  +build(db,uuidFactura) array
}
class Uc002LegacyPaymentProjectionApplier {
  +apply(projection,movementDate,uuidPayment) array
}

AlumnesPagamentsJS --> SifExistingInvoicePaymentAccess : token/flag
AlumnesPagamentsJS --> SifPaymentProxy
SifPaymentProxy --> SifExistingInvoicePaymentAccess
SifPaymentProxy --> SifInternalApiClient
SifInternalApiClient --> ExistingInvoicePaymentCommandService : HMAC API
ExistingInvoicePaymentCommandService --> ManualPaymentService
ManualPaymentService --> PaymentService
ExistingInvoicePaymentCommandService --> ExistingInvoiceLegacyProjectionService
SifPaymentProxy --> Uc002LegacyPaymentProjectionApplier : post-commit
~~~

### Garanties implementades

- `SIF_UC002_AUTHORITATIVE` és desactivat per defecte.
- La UI conserva el mateix UUID de request a `sessionStorage` durant un retry.
- La clau econòmica és `INTRANET|UC002|REQ:<uuid>`.
- El vell endpoint `efectuarPagament.php` falla amb 409 per `efact=1` quan el mode autoritatiu està actiu.
- La projecció llegada és **absoluta** (`PAGAMENT = projectat`), no incremental.
- Una fallada de projecció/sync després del commit retorna `PENDING_RETRY`; no genera un segon `CHARGE`.
- `Caixa` i `BBVA` es tracten com transferència manual. `tpv` falla tancat i s'ha de resoldre pel flux Redsys autoritatiu.

### Límit transversal encara obert

La cerca llegada de `/alumnes/pagaments/` continua depenent de `inscripcions.FACTURA_RELACIONADA` i la taula `factures` antiga. Una factura SIF-only (per exemple emesa per UC-004 sense projecció llegada de relació) pot existir i ser cobrable pel command SIF, però **encara no és descobrible automàticament per aquesta cerca llegada**. Cal un fallback de cerca SIF complet (inclosa confirmació) o una projecció d'índex segura; no s'ha creat una pseudo-factura llegada per resoldre-ho.

## 7. FINAL — arquitectura objectiu

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

## 8. Invariants FINAL

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
