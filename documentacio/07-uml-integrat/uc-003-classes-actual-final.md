# UC-003 — Diagrames de classes ACTUAL i FINAL

**Data d'auditoria:** 03/10/2026  
**Abast:** recepció i processament asíncron d'un cobrament Redsys després que existeixi una intenció pre-TPV.  
**Regla d'evidència:** ACTUAL = codi llegat/candidat existent al repositori. FINAL = codi SIF executable existent; els elements encara no acreditats a preproducció es marquen com a pendents d'evidència.

## 1. ACTUAL — callback llegat i pont candidat

```mermaid
classDiagram
direction LR

class PaginaEfectuarPagamentAutomatic {
  <<PHP pre-TPV>>
  +JasomNovicePaymentGate::assertCanPrepare()
  +crea formulari Redsys
  +defineix MerchantURL
}
class MostrarEfectuarPagamentAutomatic {
  <<JavaScript web-actual>>
  +submit #frm
  +cancel·la
  +carrega header/footer
}
class SifRedsysCourseIntentClient {
  <<PHP candidat>>
  +create(idPag,requestedAmount,terminal) array
}
class RedsysAPI {
  +setParameter(k,v)
  +createMerchantParametersV2()
  +createMerchantSignatureV2(key)
  +decodeMerchantParameters(data)
  +createMerchantSignatureNotifForVersion(key,data,version)
}
class LegacyCallback {
  <<realitzaPagamentAutomatic.php / doit.php>>
  +valida signatura
  +valida order/import/terminal/comerç
  +INSERT factures
  +UPDATE inscripcions
  +envia correus
}
class FacturesLegacy {
  <<table>>
}
class InscripcionsLegacy {
  <<table>>
}

MostrarEfectuarPagamentAutomatic --> PaginaEfectuarPagamentAutomatic : submit navegador
PaginaEfectuarPagamentAutomatic --> RedsysAPI
PaginaEfectuarPagamentAutomatic --> SifRedsysCourseIntentClient : només candidat
LegacyCallback --> RedsysAPI
LegacyCallback --> FacturesLegacy : escriptura directa
LegacyCallback --> InscripcionsLegacy : PAGAMENT/DATA PAG/FRACCIO
```

### Lectura ACTUAL

- El JS no processa el callback: només confirma/cancel·la i envia el formulari al TPV.
- La còpia `web-actual` conserva el circuit fiscal llegat, protegit per flags de cutover/drain però amb `INSERT factures` i `UPDATE inscripcions` mentre el fallback segueixi habilitat.
- La candidata `pay-prisma-cat-canvis-verifactu` ja crea/reutilitza una intenció SIF i pot seleccionar la MerchantURL SIF amb flags explícits.
- La candidata referencia `https://pay.prisma.cat/js/mostrarEfectuarPagament.js`, però aquesta còpia JS **no és present** dins del snapshot `codi-drive/pay-prisma-cat-canvis-verifactu`; per tant, el JS candidat no es pot declarar auditat 1:1 des del repositori.
- `doit.php` continua sent callback llegat temporal i muta dades llegades si no s'ha executat el cutover complet.

## 2. FINAL — recepció, cua i worker SIF

```mermaid
classDiagram
direction LR

class RedsysSignatureValidator {
  <<EXISTENT>>
  +decodeAndVerify(post) array
}
class RedsysCallbackService {
  <<EXISTENT>>
  +receiveCallback(db,payload,signatureValid) array
}
class RedsysPaymentIntentRepository {
  <<EXISTENT>>
  +findByDsOrder(db,order,forUpdate) array
}
class RedsysNotificationRepository {
  <<EXISTENT>>
  +recordReceived(...)
  +findByDsOrder(...)
}
class RedsysCallbackQueueRepository {
  <<EXISTENT>>
  +enqueue(...)
  +claimNext(db,workerId,now)
  +markProcessed(...,workerId)
  +markRetry(...,workerId)
  +markIncident(...,workerId)
  +recoverStaleLocks(...)
}
class RedsysCallbackWorker {
  <<EXISTENT>>
  +runOne(db,workerId,now) array
  -assertCompletedResult(result)
}
class RedsysLegacySyncingProcessor {
  <<EXISTENT>>
  +process(db,job) array
}
class RedsysCallbackDispatcher {
  <<EXISTENT>>
  +process(db,job) array
}
class RedsysIntentHandler {
  <<interface>>
  +sourceType() string
  +issueFromIntentSnapshot(db,order,snapshot) array
}
class RedsysCourseInvoiceService {
  <<EXISTENT CURS>>
}
class RedsysPackInvoiceService {
  <<EXISTENT PACK>>
}
class RedsysGroupInvoiceService {
  <<EXISTENT GRUP>>
}
class RedsysGiftInvoiceService {
  <<EXISTENT REGAL>>
}
class RedsysUsocInvoiceService {
  <<EXISTENT USOC_ALUMNE>>
}
class InvoiceService {
  <<EXISTENT>>
  +issueInvoice(payload) array
}
class CourseEnrollmentFundAllocationService {
  <<EXISTENT CURS>>
  +allocate(db,order,snapshot,result) array
}
class PackEnrollmentFundAllocationService {
  <<EXISTENT PACK>>
  +allocate(db,order,snapshot,result) array
}
class CourseLegacyPaymentSyncService {
  <<EXISTENT CURS>>
  +sync(...)
}
class IncidentRepository {
  <<EXISTENT>>
  +open(...)
}

RedsysSignatureValidator --> RedsysCallbackService
RedsysCallbackService --> RedsysPaymentIntentRepository
RedsysCallbackService --> RedsysNotificationRepository
RedsysCallbackService --> RedsysCallbackQueueRepository
RedsysCallbackWorker --> RedsysCallbackQueueRepository
RedsysCallbackWorker --> RedsysLegacySyncingProcessor
RedsysLegacySyncingProcessor --> RedsysCallbackDispatcher
RedsysCallbackDispatcher --> RedsysIntentHandler
RedsysIntentHandler <|.. RedsysCourseInvoiceService
RedsysIntentHandler <|.. RedsysPackInvoiceService
RedsysIntentHandler <|.. RedsysGroupInvoiceService
RedsysIntentHandler <|.. RedsysGiftInvoiceService
RedsysIntentHandler <|.. RedsysUsocInvoiceService
RedsysCourseInvoiceService --> InvoiceService
RedsysPackInvoiceService --> InvoiceService
RedsysGroupInvoiceService --> InvoiceService
RedsysGiftInvoiceService --> InvoiceService
RedsysUsocInvoiceService --> InvoiceService
RedsysCourseInvoiceService --> CourseEnrollmentFundAllocationService
RedsysPackInvoiceService --> PackEnrollmentFundAllocationService
RedsysLegacySyncingProcessor --> CourseLegacyPaymentSyncService
RedsysCallbackWorker --> IncidentRepository
```

## 3. Persistència FINAL

```mermaid
classDiagram
direction LR

class redsys_payment_intent {
  DS_ORDER
  SOURCE_TYPE
  SOURCE_ID
  EXPECTED_AMOUNT
  CURRENCY
  TERMINAL
  SNAPSHOT_JSON
}
class redsys_notifications {
  DS_ORDER
  IMPORT
  RESPONSE_CODE
  STATUS
  SIGNATURE_VALID
  PAYLOAD_HASH
}
class redsys_callback_queue {
  UUID_JOB
  STATUS
  ATTEMPTS
  AVAILABLE_AT
  LOCKED_AT
  LOCKED_BY
  UUID_FACTURA
  UUID_PAYMENT
  RESULT_JSON
}
class factura {
  UUID_FACTURA
  NUM_VISIBLE
}
class payment_transaction {
  UUID_PAYMENT
  IDPAG
  IMPORT
  ESTAT
}
class payment_allocation {
  UUID_PAYMENT
  UUID_FACTURA
}
class enrollment_fund_movement {
  UUID_PAYMENT
  UUID_FACTURA
  ID_INSC
  IMPORT
}
class errors_verifactu {
  UUID_INCIDENT
  IDEMPOTENCY_KEY
  ESTAT
}

redsys_payment_intent --> redsys_notifications : DS_ORDER
redsys_notifications --> redsys_callback_queue : notification_id
redsys_callback_queue --> factura : UUID_FACTURA
redsys_callback_queue --> payment_transaction : UUID_PAYMENT
payment_transaction --> payment_allocation
factura --> payment_allocation
payment_transaction --> enrollment_fund_movement : CURS/PACK
factura --> enrollment_fund_movement : CURS/PACK
redsys_callback_queue --> errors_verifactu : error funcional/exhaurit
```

## 4. Correspondència ACTUAL → FINAL

| Responsabilitat | ACTUAL | FINAL |
| --- | --- | --- |
| Preparar ordre | PHP del canal; candidat ja pot demanar intenció SIF | UC-063 + `redsys_payment_intent` |
| Validar resposta Redsys | callback monolític | `RedsysSignatureValidator` |
| Correlacionar import/divisa/terminal | dades/signatura del callback + context llegat | intenció bloquejada per `DS_ORDER` |
| Dedupe notificació | no acreditat globalment al llegat | `RedsysNotificationRepository` |
| Desacoblar HTTP | no | `redsys_callback_queue` |
| Exclusió entre workers | no | `claimNext()` + `LOCKED_BY`; endurit en aquesta auditoria també a transicions terminals |
| Factura/cobrament | callback escriu directament | handlers → `InvoiceService` |
| Resultat complet | implícit | worker exigeix `ok=true`, `uuid_factura` i `uuid_payment` abans de `PROCESSED` |
| Retry/incidència | ad hoc | `RETRY`/backoff/`INCIDENT` |
| Atribució per inscripció | camps acumulatius llegats | `enrollment_fund_movement` implementat per CURS i PACK |
| Sync llegat | dins callback | postprocés posterior al SIF; específic de CURS i modes amb `legacy_sync` |

## 5. Estat

**DOCUMENTAT:** classes ACTUAL i FINAL separades.  
**IMPLEMENTAT:** callback SIF, validació criptogràfica, intenció/notificació/cua, worker, dispatcher, cinc handlers, factura+cobrament, incidències, sync i atribució CURS/PACK. L'enduriment de resultat complet i propietat del lock està implementat a la branca d'auditoria.  
**VERIFICAT:** existeixen proves d'integració i unitàries específiques; la verificació CI del head d'aquesta auditoria queda pendent fins que s'executin els workflows del PR.  
**PENDENT:** cutover/preproducció Redsys real; resolució segura de factura preexistent amb clau diferent; evidència d'operació/cron; completar o justificar atribució quantitativa per les variants on sigui funcionalment necessària; JS candidat absent del snapshot.
