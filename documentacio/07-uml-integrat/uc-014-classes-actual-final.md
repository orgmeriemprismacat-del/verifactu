# UC-014 — Diagrames de classes ACTUAL i FINAL

**Data d'auditoria/revalidació:** 02/10/2026  
**Abast:** compra d'un curs ordinari per Redsys. UC-014a (taller) i UC-014b (jornada) continuen separats.  
**Regla d'evidència:** ACTUAL = lectura estàtica del codi disponible al repositori; no acredita desplegament productiu. FINAL = codi SIF existent quan es marca `EXISTENT`; `DISSENY/PENDENT` quan encara no hi ha integració executable completa.

## 1. Classes i components ACTUALS

```mermaid
classDiagram
direction LR

class PagamentCursAutomatic {
  +__construct(idPag)
  +mostrar()
  +mostrarPaginaConfirmacio()
  -obtenirPreuAPagar()
  -obtenirPreuPagat()
  -obtenirIdInsc()
  -obtenirIdPag()
  -__mostrarPagamentTargeta(tipus)
  -__mostrarPagamentTransferencia(tipus)
}

class ConnexioBBDDSTMT {
  +connectarBD()
  +prepare(sql)
  +closeStmt()
  +desconectarBD()
}

class RedsysAPI {
  +setParameter(k,v)
  +createMerchantParameters()
  +createMerchantSignature(key)
  +decodeMerchantParameters(data)
  +createMerchantSignatureNotif(key,data)
  +getParameter(k)
}

class RealitzaPagamentAutomatic {
  <<script PHP callback>>
  +llegeix GET idPag/curs/dni/order/import
  +llegeix POST Redsys
  +INSERT factures
  +UPDATE inscripcions
  +envia correus
}

class PaginaEfectuarPagamentAutomatic {
  <<script PHP>>
  +rep POST del navegador
  +JasomNovicePaymentGate::assertCanPrepare()
  +crea DS_ORDER amb time() [fallback]
  +crea formulari Redsys
}
class JasomNovicePaymentGate {
  <<PHP servidor>>
  +assertCanPrepare(db,post) array
  +authorizeEnrollment(enrollment,post) array
}
class JsConfirmacio {
  <<JavaScript>>
  +AJAX mostrar_confirmacio_inscripcio_automatic
  +valida UX
  +submit frm
}
class JsPagament {
  <<JavaScript>>
  +AJAX mostrar_pagina_pagament_automatic
  +valida UX
  +submit frm
}
class JsEfectuar {
  <<JavaScript>>
  +confirma/cancel·la
  +submit frm
}

class FacturesLegacy {
  <<table>>
  factura_relacionada
  tipus
  ANY
  ORDRE
  NUM
  DATA
  data_pagament
  num_comanda
  IMPORT
}

class InscripcionsLegacy {
  <<table>>
  ID
  IDPAG
  A_PAGAR
  PAGAMENT
  FACTURA_RELACIONADA
  DATA_PAG
  FRACCIO
}

class CursLegacy {
  <<table>>
  ANY
  MES
  CURS
  NOM_CURS
  HORES
  DATAI
  DATAF
}

JsConfirmacio --> PagamentCursAutomatic : AJAX/PHP
JsPagament --> PagamentCursAutomatic : AJAX/PHP
JsEfectuar --> PaginaEfectuarPagamentAutomatic : DOM/form
PagamentCursAutomatic --> ConnexioBBDDSTMT
PagamentCursAutomatic --> InscripcionsLegacy
PagamentCursAutomatic --> CursLegacy
PaginaEfectuarPagamentAutomatic --> JasomNovicePaymentGate : autorització servidor
JasomNovicePaymentGate --> InscripcionsLegacy : saldo/fraccionament/estat
PaginaEfectuarPagamentAutomatic --> RedsysAPI
RealitzaPagamentAutomatic --> RedsysAPI
RealitzaPagamentAutomatic --> ConnexioBBDDSTMT
RealitzaPagamentAutomatic --> FacturesLegacy
RealitzaPagamentAutomatic --> InscripcionsLegacy
RealitzaPagamentAutomatic --> CursLegacy
```

### Lectura de riscos de l'ACTUAL

- El callback llegat concentra validació bancària, facturació, actualització de la inscripció i correus.
- La numeració fiscal es calcula amb patrons `SELECT últim + 1`.
- `idPag`, `order`, `import` i altres dades funcionals viatgen a la MerchantURL.
- A la base `main` prèvia a aquesta auditoria el fallback no acreditava la comparació efectiva de signatura/order/import. La branca 02/10 ho endureix abans de qualsevol efecte; el risc arquitectònic restant és que el fallback encara factura fora del SIF fins al cutover.
- El flux actual usa camps acumulatius de la inscripció per representar cobrament/fraccionament.

## 2. Classes i components FINAL — implementats o previstos

```mermaid
classDiagram
direction LR

class SifRedsysCourseIntentClient {
  <<EXISTENT PONT CANDIDAT>>
  +create(idPag,requestedAmount,terminal) array
}

class RedsysCoursePaymentIntentService {
  <<EXISTENT>>
  +create(sifDb,legacyDb,input) array
}

class RedsysPaymentIntentService {
  <<EXISTENT>>
  +create(db,input) array
}

class RedsysCallbackService {
  <<EXISTENT>>
  +receiveCallback(db,payload,signatureValid) array
}

class RedsysCallbackQueueRepository {
  <<EXISTENT>>
  +claimNext(db,workerId,now)
}

class RedsysCallbackWorker {
  <<EXISTENT>>
  +runOne(db,workerId,now) array
}

class RedsysCallbackDispatcher {
  <<EXISTENT>>
  +process(db,job) array
}

class RedsysCourseInvoiceService {
  <<EXISTENT>>
  +sourceType() string
  +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}

class InvoiceService {
  <<EXISTENT>>
  +issueInvoice(payload) array
}

class CourseLegacyPaymentSyncService {
  <<EXISTENT>>
  +sync(sifDb,legacyDb,idpag,idInsc,uuidFactura,numVisible) array
}

class CoursePaymentNotificationService {
  <<EXISTENT>>
  +enqueue(db,dsOrder,snapshot,invoiceResult,legacyPaymentSync) array
}

class NotificationOutboxRepository {
  <<EXISTENT · enqueue>>
  +enqueue(db,message) array
  +findByIdempotencyKey(db,key) array
}

class RedsysCoursePaymentStatusService {
  <<EXISTENT>>
  +status(db,dsOrder,idpag) array
}

class SifRedsysCourseStatusClient {
  <<EXISTENT PONT CANDIDAT>>
  +get(dsOrder,idPag) array
}

class CoursePaymentReturnStatus {
  <<EXISTENT PONT CANDIDAT>>
  +uc014ResolvePaymentReturn(browserReturn) array
  +uc014RenderPaymentReturn(browserReturn)
}

class PaymentRepository {
  <<EXISTENT>>
  +create(db,payload) array
  +createAllocation(db,uuidPayment,allocation)
}
class CourseEnrollmentFundAllocationService {
  <<EXISTENT>>
  +allocate(db,dsOrder,snapshot,invoiceResult) array
}
class EnrollmentFundMovementRepository {
  <<EXISTENT>>
  +lockPayment(db,uuidPayment) array
  +findInvoiceLineForInscription(db,uuidFactura,idInsc) array
  +insertOrReuseExternalAllocation(db,input) array
}

SifRedsysCourseIntentClient --> RedsysCoursePaymentIntentService : POST HMAC
RedsysCoursePaymentIntentService --> RedsysPaymentIntentService
RedsysCallbackWorker --> RedsysCallbackQueueRepository
RedsysCallbackWorker --> RedsysCallbackDispatcher
RedsysCallbackDispatcher --> RedsysCourseInvoiceService : sourceType=CURS
RedsysCourseInvoiceService --> InvoiceService
RedsysCallbackWorker --> RedsysLegacySyncingProcessor : postprocés
RedsysLegacySyncingProcessor --> CourseLegacyPaymentSyncService : projecció idempotent
RedsysLegacySyncingProcessor --> CoursePaymentNotificationService : després de sync CURS
CoursePaymentNotificationService --> NotificationOutboxRepository : enqueue idempotent
CoursePaymentReturnStatus --> SifRedsysCourseStatusClient
SifRedsysCourseStatusClient --> RedsysCoursePaymentStatusService : POST HMAC read-only
RedsysCoursePaymentStatusService --> RedsysPaymentIntentService : correlació per DS_ORDER/IDPAG
InvoiceService --> PaymentRepository : CHARGE + payment_allocation
RedsysCourseInvoiceService --> CourseEnrollmentFundAllocationService : postcommit idempotent
CourseEnrollmentFundAllocationService --> EnrollmentFundMovementRepository : EXTERNAL_ALLOCATION
CourseLegacyPaymentSyncService --> PaymentRepository : suma CONFIRMED per IDPAG
```
## 3. Correspondència ACTUAL → FINAL

| Responsabilitat | ACTUAL | FINAL |
| --- | --- | --- |
| Mostrar estat/preu del curs | `PagamentCursAutomatic` | pont candidat + serveis SIF amb rellegida autoritativa del servidor |
| Crear ordre TPV | `time()` a la pàgina PHP | `SifRedsysCourseIntentClient` → `RedsysCoursePaymentIntentService` → intenció persistent |
| Transportar dades al callback | GET de MerchantURL | `DS_ORDER` + snapshot de la intenció |
| Validar callback | `RedsysAPI` dins script monolític | `RedsysCallbackService` |
| Facturar | `INSERT factures` al callback | `InvoiceService` |
| Registrar cobrament | camps `PAGAMENT/FRACCIO` | `payment_transaction/payment_allocation` |
| Assignar a inscripció | implícit per `IDPAG` | `CourseEnrollmentFundAllocationService` → `enrollment_fund_movement.EXTERNAL_ALLOCATION` idempotent per `DS_ORDER + ID_INSC` |
| Assignar a inscripció | implícit per `IDPAG` | relació + moviment quantitatiu per inscripció |
| Numeració fiscal | càlcul al canal web | seqüència central SIF |
| Reintents | no acreditats | idempotència per intenció/notificació/factura/pagament |
| Postprocessat acadèmic | barrejat amb callback | `RedsysLegacySyncingProcessor` / `CourseLegacyPaymentSyncService` posterior al SIF |
| Notificació de pagament | correus immediats dins callback | `CoursePaymentNotificationService` → `notification_outbox`; lliurament UC-58 separat |

## 4. Estat

**DOCUMENTAT:** ACTUAL i FINAL.  
**IMPLEMENTAT:** nucli Redsys/SIF, pont candidat d'intenció, sync llegada de curs, productor durable d'outbox CURS, retorn navegador read-only, atribució quantitativa `EXTERNAL_ALLOCATION` amb `CourseEnrollmentFundAllocationService` / `EnrollmentFundMovementRepository` i hardening del fallback a la branca 02/10.  
**VERIFICAT:** CI amb E2E intern simulat incloent `notification_outbox` CURS, `EXTERNAL_ALLOCATION` per inscripció, duplicat i parcial→complet, retorn autoritatiu i boundaries de preproducció; PR #95 amb `CourseEnrollmentFundAllocationServiceTest`, suites SIF **841 passed / 0 failed** i quatre workflows verds. El hardening ACTUAL del PR #105 també ha passat `SIF PHP MySQL tests`, `SIF checks` i `UC-111 integration verification` al head de codi `56d32d600d26d39d94b8a7227e4d732f07d35ce5`.  
**PENDENT:** desplegament/preproducció Redsys real, activació de MerchantURL SIF/cutover, rotació de secrets històrics, delivery UC-58 i retirada del callback fiscal llegat després de l'evidència.


**Traça detallada de codi:** [inventari PHP/JS ACTUAL, pont candidat i SIF 02/10](uc-014-inventari-codi-php-js-actual-final-2026-10-02.md).
