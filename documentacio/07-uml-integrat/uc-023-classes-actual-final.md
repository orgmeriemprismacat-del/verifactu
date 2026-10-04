# UC-023 — Classes ACTUAL / FINAL

**Data:** 03/10/2026  
**Objectiu:** separar el model de classes que existeix avui del model necessari per tancar UC-023.

## 1. ACTUAL — nucli SIF implementat

```mermaid
classDiagram
direction LR

class ManualInstallmentPaymentService {
  +registerByUuid(PDO, uuidFactura, input) array
  +registerByNumVisible(PDO, numVisible, input) array
  -registerForInvoice(invoice, input) array
}

class ManualInstallmentPaymentPayloadBuilder {
  +forExistingInvoice(uuidFactura, input) array
  -idempotencyKey(idInsc, amount, movementDate, user, operationId?) string
  -providerRef(idInsc, user, operationId?) string
}

class ManualPaymentInvoiceRepository {
  +findByUuid(PDO, uuidFactura, forUpdate=false) array?
  +findByNumVisible(PDO, numVisible, forUpdate=false) array?
}

class PaymentService {
  +registerPayment(payload) array
  -createOrReusePayment(payload) array
  -assertSamePayload(payload, existing) void
}

class PaymentPayloadValidator {
  +validate(payload) array
}

class PaymentRepository {
  +findByIdempotencyKey(PDO, key, forUpdate=false) array?
  +createPayment(PDO, payload) array
  -createAllocation(PDO, uuidPayment, allocation) void
  -refreshInvoicePaymentStatus(PDO, uuidFactura) void
}

class PaymentStatusCalculator {
  +calculate(invoiceTotal, charges, refunds) string
}

ManualInstallmentPaymentService --> ManualPaymentInvoiceRepository : localitza factura
ManualInstallmentPaymentService --> ManualInstallmentPaymentPayloadBuilder : construeix CHARGE
ManualInstallmentPaymentService --> PaymentService : registra/reutilitza
PaymentService --> PaymentPayloadValidator : valida contracte
PaymentService --> PaymentRepository : ledger
PaymentRepository --> PaymentStatusCalculator : recalcula estat
```

### 1.1. Responsabilitats reals

| Classe | Implementat | No implementat aquí |
| --- | --- | --- |
| ManualInstallmentPaymentService | factura existent + delegació a PaymentService | autorització del canal, vincle ID_INSC↔factura, conciliació bancària |
| ManualInstallmentPaymentPayloadBuilder | import/data/inscripció/usuari, CHARGE manual, assignació | prova de cobrament real, saldo pendent |
| PaymentService | transacció, idempotència i conflicte de payload | semàntica específica de quota/fracció |
| PaymentRepository | moviment, assignació i estat de factura | atribució pròpia per ID_INSC |
| PaymentStatusCalculator | PENDING/PARTIAL/PAID/OVERPAID/refunds | decisió de si OVERPAID és admissible |

## 2. ACTUAL — canal intranet llegat

```mermaid
classDiagram
direction LR
class alumnes_pagaments_js {
  +buscarPagament()
  +suma()
  +mostrarModalConfirmacioPagament()
  +aplicarPagament()
}
class buscarInfomacioPagament_php
class mostrarModalConfPag_php
class efectuarPagament_php
class SessionIntranetObject {
  +mostrarPagaments(...)
  +mostrarModalConfPag(...)
  +efectuarPagament(...)
}

alumnes_pagaments_js --> buscarInfomacioPagament_php : GET consulta
alumnes_pagaments_js --> mostrarModalConfPag_php : GET preview
alumnes_pagaments_js --> efectuarPagament_php : GET mutació
buscarInfomacioPagament_php --> SessionIntranetObject
mostrarModalConfPag_php --> SessionIntranetObject
efectuarPagament_php --> SessionIntranetObject
```

**Límit d'auditoria:** el repositori inspeccionat no acredita un enllaç explícit d'aquest endpoint amb `ManualInstallmentPaymentService`. Per tant no s'ha de dibuixar com si el canal ja utilitzés el nucli SIF.

## 3. FINAL — classes necessàries

```mermaid
classDiagram
direction LR

class InstallmentCommandController {
  +preview(command, actor) InstallmentPreview
  +register(command, actor) InstallmentResult
}

class InstallmentAuthorizationPolicy {
  +assertAllowed(actor, invoice, inscription) void
}

class InstallmentDestinationGuard {
  +verify(invoiceId, inscriptionId, amount, externalEventId) VerifiedInstallment
}

class ExternalReceiptReconciler {
  +resolve(externalEventId, channel, reference) ReceiptResolution
}

class ManualInstallmentPaymentService {
  +registerByUuid(PDO, uuidFactura, input) array
}

class PaymentService {
  +registerPayment(payload) array
}

class InstallmentAttributionRepository {
  +findByExternalEvent(PDO, eventId) array?
  +createAttribution(PDO, paymentUuid, inscriptionId, amount, metadata) void
}

class PaymentActionEventRepository {
  +requested(...)
  +completed(...)
  +rejected(...)
  +conflict(...)
}

class OperationalEventRepository {
  +append(...)
}

InstallmentCommandController --> InstallmentAuthorizationPolicy
InstallmentCommandController --> InstallmentDestinationGuard
InstallmentDestinationGuard --> ExternalReceiptReconciler
InstallmentCommandController --> ManualInstallmentPaymentService
ManualInstallmentPaymentService --> PaymentService
InstallmentCommandController --> InstallmentAttributionRepository
InstallmentCommandController --> PaymentActionEventRepository
InstallmentCommandController --> OperationalEventRepository
```

## 4. Invariants FINAL

1. **Cap CHARGE sense fet econòmic identificable.**
2. **Mateix event extern + mateix payload = reús.**
3. **Mateix event extern + payload contradictori = conflicte.**
4. **Events externs diferents poden tenir mateix import/data/usuari sense col·lisionar.**
5. **ID_INSC ha d'estar cobert per la factura o relació fiscal corresponent.**
6. **La regla d'import pendent és server-side.**
7. **Un ingrés ja registrat com Redsys/transferència no es torna a crear com MANUAL.**
8. **Autorització, idempotència i ledger s'avaluen al servidor.**
9. **La UI no decideix l'èxit per text HTML sinó per resposta tipificada.**
10. **L'auditoria d'intent i resultat és correlacionable.**

## 5. Estat

| Peça FINAL | Estat |
| --- | --- |
| Identificador immutable opcional de fracció al builder | IMPLEMENTAT EN BRANCA |
| Controller POST autenticat | PENDENT |
| Authorization policy específica | PENDENT |
| Destination guard ID_INSC↔factura | PENDENT |
| Reconciliador cross-channel | PENDENT |
| Atribució monetària per inscripció | PENDENT |
| Events d'auditoria específics UC-023 | PENDENT / TRANSVERSAL |
