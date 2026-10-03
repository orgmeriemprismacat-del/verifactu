# UC-022 — Diagrames de classes/components ACTUAL i FINAL

**Data:** 03/10/2026  
**Regla:** ACTUAL = codi executable inspeccionat. FINAL = arquitectura objectiu; cada peça indica si existeix o és pendent.

## 1. ACTUAL — canal intranet llegat

```mermaid
classDiagram
direction LR
class AlumnesPagamentsPage {
  <<PHP legacy>>
  +include comprovarSessio.php
}
class AlumnesPagamentsJS {
  <<JS legacy>>
  +buscar()
  +mostrarModalConfirmacioPagament()
  +aplicarPagament()
  +dataEsValida()
  +suma()
}
class EfectuarPagamentEndpoint {
  <<PHP legacy>>
  +GET id,tipus,pagament,dataPag,banc,obs,numFact,efact
  +session_start()
}
class IntranetLegacy {
  <<PHP legacy>>
  +efectuarPagament(...)
}
AlumnesPagamentsPage --> AlumnesPagamentsJS
AlumnesPagamentsJS --> EfectuarPagamentEndpoint : GET amb efectes
EfectuarPagamentEndpoint --> IntranetLegacy : delega
```

**Riscos verificats:** dades econòmiques enviades pel client, verb GET per mutació, endpoint sense comprovació CSRF específica localitzada i sense adaptador SIF acreditat.

## 2. ACTUAL — nucli SIF disponible però desconnectat del canal

```mermaid
classDiagram
direction LR
class ManualPaymentService {
 +registerByUuid(PDO,uuid,input) array
 +registerByNumVisible(PDO,numVisible,input) array
}
class ManualPaymentInvoiceRepository {
 +findByUuid(PDO,uuid,forUpdate) array?
 +findByNumVisible(PDO,numVisible,forUpdate) array?
}
class ManualPaymentPayloadBuilder {
 +forExistingInvoice(uuid,input) array
}
class PaymentService {
 +registerPayment(payload) array
 -assertSamePayload(payload,existing)
}
class PaymentPayloadValidator {
 +validate(payload) array
}
class PaymentRepository {
 +findByIdempotencyKey(db,key,forUpdate) array?
 +createPayment(db,payload) array
}
class PaymentStatusCalculator {
 +calculate(total,charges,refunds) string
}
ManualPaymentService --> ManualPaymentInvoiceRepository
ManualPaymentService --> ManualPaymentPayloadBuilder
ManualPaymentService --> PaymentService
PaymentService --> PaymentPayloadValidator
PaymentService --> PaymentRepository
PaymentRepository --> PaymentStatusCalculator
```

## 3. FINAL — adaptador autoritzat i identitat bancària

```mermaid
classDiagram
direction LR
class TransferPaymentController {
 <<PENDENT>>
 +postRegister(request,user) JsonResponse
}
class PaymentCommandAuthorization {
 <<PENDENT>>
 +assertAllowed(user,scope)
 +assertCsrf(request)
}
class ExternalBankReceiptResolver {
 <<PENDENT>>
 +resolve(externalEventId,reference,amount,bank,holder) BankReceipt
}
class ManualPaymentService {
 <<IMPLEMENTAT BASE>>
 +registerByUuid()
 +registerByNumVisible()
}
class MultiInvoiceTransferService {
 <<PENDENT UC-105>>
 +registerOrAllocate(receipt,allocations)
}
class PaymentService {
 <<IMPLEMENTAT>>
 +registerPayment()
}
class PaymentActionAudit {
 <<PENDENT INTEGRACIÓ>>
 +requested()
 +completed()
 +failed()
}
class LegacyPaymentSync {
 <<PENDENT>>
 +enqueueAfterCommit(uuidPayment)
}
TransferPaymentController --> PaymentCommandAuthorization
TransferPaymentController --> ExternalBankReceiptResolver
TransferPaymentController --> ManualPaymentService
TransferPaymentController --> MultiInvoiceTransferService
ManualPaymentService --> PaymentService
MultiInvoiceTransferService --> PaymentService
TransferPaymentController --> PaymentActionAudit
TransferPaymentController --> LegacyPaymentSync
```

## 4. Invariants FINAL

- cap CHARGE sense prova d'entrada bancària;
- un fet bancari = una identitat externa estable;
- mateixa clau + payload diferent = CONFLICT;
- una entrada multifactura = un `payment_transaction` + N `payment_allocation`;
- sincronització llegada sempre després del commit SIF;
- el navegador no decideix import fiscal/econòmic autoritatiu;
- auditar actor, request/correlation id, factura/es, import, identitat externa i resultat.
