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

class RedsysIntentHandler {
  <<interface EXISTENT>>
  +sourceType() string
  +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}

class RedsysCourseInvoiceService {
  <<EXISTENT>>
  +sourceType() string
  +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}

class LegacyCourseInvoicePayloadBuilder {
  <<EXISTENT>>
  +build(snapshot) array
}

class RedsysInvoicePayloadBuilder {
  <<EXISTENT>>
  +buildFromValidatedNotification(db,dsOrder,payload) array
}

class InvoiceService {
  <<EXISTENT>>
  +issueInvoice(payload) array
}

class EcommerceCoursePaymentAdapter {
  <<DISSENY/PENDENT>>
  +prepareCoursePayment(request)
  +createIntent(snapshot)
  +redirectToRedsys()
}

class EnrollmentFundMovementRepository {
  <<DISSENY/PENDENT>>
  +append(db,movement) string
}

RedsysCallbackWorker --> RedsysCallbackQueueRepository
RedsysCallbackWorker --> RedsysCallbackDispatcher
RedsysCallbackDispatcher --> RedsysIntentHandler
RedsysCourseInvoiceService ..|> RedsysIntentHandler
RedsysCourseInvoiceService --> LegacyCourseInvoicePayloadBuilder
RedsysCourseInvoiceService --> RedsysInvoicePayloadBuilder
RedsysCourseInvoiceService --> InvoiceService
EcommerceCoursePaymentAdapter --> RedsysPaymentIntentService
RedsysCallbackDispatcher --> RedsysCourseInvoiceService : sourceType=CURS
RedsysCallbackWorker ..> EnrollmentFundMovementRepository : atribució per inscripció [pendent]
```

## 3. Correspondència ACTUAL → FINAL

| Responsabilitat | ACTUAL | FINAL |
| --- | --- | --- |
| Mostrar estat/preu del curs | `PagamentCursAutomatic` | Adaptador ecommerce amb lectura autoritativa del servidor |
| Crear ordre TPV | `time()` a la pàgina PHP | `RedsysPaymentIntentService` + intenció persistent |
| Transportar dades al callback | GET de MerchantURL | `DS_ORDER` + snapshot de la intenció |
| Validar callback | `RedsysAPI` dins script monolític | `RedsysCallbackService` |
| Facturar | `INSERT factures` al callback | `InvoiceService` |
| Registrar cobrament | camps `PAGAMENT/FRACCIO` | `payment_transaction/payment_allocation` |
| Assignar a inscripció | implícit per `IDPAG` | relació + moviment quantitatiu per inscripció |
| Numeració fiscal | càlcul al canal web | seqüència central SIF |
| Reintents | no acreditats | idempotència per intenció/notificació/factura/pagament |
| Postprocessat acadèmic | barrejat amb callback | sincronització posterior, idempotent i recuperable |

## 4. Estat

**DOCUMENTAT:** ACTUAL i FINAL.  
**IMPLEMENTAT:** nucli FINAL de Redsys/SIF; adaptador ecommerce i atribució quantitativa continuen pendents.  
**VERIFICAT:** lectura estàtica dels fitxers enllaçats.  
**PENDENT:** prova integrada web → intenció → Redsys → callback → worker → factura/cobrament → sincronització acadèmica.
