# UC-014 — Diagrames de classes ACTUAL i FINAL

**Data d'auditoria:** 29/09/2026  
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
  +crea DS_ORDER amb time()
  +crea formulari Redsys
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

PagamentCursAutomatic --> ConnexioBBDDSTMT
PagamentCursAutomatic --> InscripcionsLegacy
PagamentCursAutomatic --> CursLegacy
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
- A la còpia revisada es calcula una signatura de notificació Redsys, però no s'ha localitzat la comparació posterior amb la signatura rebuda.
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

class EnrollmentFundMovementRepository {
  <<DISSENY/PENDENT UC-014>>
  +append(db,movement) string
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
RedsysCallbackWorker ..> EnrollmentFundMovementRepository : atribució quantitativa [pendent d'acreditar]
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
| Assignar a inscripció | implícit per `IDPAG` | relació + moviment quantitatiu per inscripció |
| Numeració fiscal | càlcul al canal web | seqüència central SIF |
| Reintents | no acreditats | idempotència per intenció/notificació/factura/pagament |
| Postprocessat acadèmic | barrejat amb callback | `RedsysLegacySyncingProcessor` / `CourseLegacyPaymentSyncService` posterior al SIF |
| Notificació de pagament | correus immediats dins callback | `CoursePaymentNotificationService` → `notification_outbox`; lliurament UC-58 separat |

## 4. Estat

**DOCUMENTAT:** ACTUAL i FINAL.  
**IMPLEMENTAT:** nucli Redsys/SIF, pont candidat d'intenció, sync llegada de curs, productor durable d'outbox CURS i retorn navegador read-only contra estat SIF. L'atribució quantitativa addicional per inscripció continua pendent d'acreditar dins UC-014.  
**VERIFICAT:** CI amb E2E intern simulat, retorn autoritatiu i boundaries de preproducció; lectura estàtica del pont candidat.  
**PENDENT:** desplegament/preproducció real, activació de MerchantURL SIF i retirada del callback fiscal llegat després de l'evidència.
