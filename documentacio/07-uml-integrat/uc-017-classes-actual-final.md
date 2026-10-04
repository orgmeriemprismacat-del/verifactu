# UC-017 · Classes i components ACTUAL / FINAL

## ACTUAL

```mermaid
flowchart LR
  UI[pagina_regal.php] --> JS[mostrarRegal.min.js]
  JS --> PAYPAGE[pagina_efectuar_pagament_regal_automatic.php]
  PAYPAGE --> REDSYS[Redsys]
  REDSYS --> CALLBACK[realitzaPagamentRegalAutomatic.php]
  CALLBACK --> LEGACY[(BD llegada: regal/factures/params)]
  CALLBACK --> MAIL[MailSMTPComvive]
```

### Responsabilitats observades
- UI/JS: selecció, destinatari, dedicatòria, dades comprador, previsualització.
- PHP pagament: crea `DS_ORDER`, import i paràmetres Redsys.
- Callback llegat: interpreta resposta, factura, actualitza regal i envia correu.
- BD llegada: continua sent font de numeració i escriptura fiscal en aquest circuit.

## FINAL

```mermaid
classDiagram
  class GiftPurchaseWebAdapter
  class RedsysPaymentIntentService
  class RedsysNotificationProcessor
  class RedsysGiftInvoiceService
  class LegacyGiftSnapshotRepository
  class LegacyGiftInvoicePayloadBuilder
  class RedsysInvoicePayloadBuilder
  class InvoiceService
  class GiftEntitlementIssuerService
  class CommercialEntitlementRepository
  class NotificationOutbox
  class LegacyGiftSync

  GiftPurchaseWebAdapter --> RedsysPaymentIntentService
  RedsysNotificationProcessor --> RedsysGiftInvoiceService
  RedsysGiftInvoiceService --> LegacyGiftSnapshotRepository
  RedsysGiftInvoiceService --> LegacyGiftInvoicePayloadBuilder
  RedsysGiftInvoiceService --> RedsysInvoicePayloadBuilder
  RedsysGiftInvoiceService --> InvoiceService
  RedsysGiftInvoiceService --> GiftEntitlementIssuerService
  GiftEntitlementIssuerService --> CommercialEntitlementRepository
  RedsysGiftInvoiceService --> LegacyGiftSync
  RedsysGiftInvoiceService --> NotificationOutbox
```

## Estat per component

| Component | Estat |
| --- | --- |
| Wizard web regal | ACTUAL llegat + hardening candidat de reserva/preview |
| Adapter web/pay-prisma -> SIF | IMPLEMENTAT EN CANDIDATA |
| Intenció Redsys REGAL | IMPLEMENTADA EN CANDIDATA |
| `RedsysGiftInvoiceService` | IMPLEMENTAT |
| `LegacyGiftSnapshotRepository` | IMPLEMENTAT |
| `LegacyGiftInvoicePayloadBuilder` | IMPLEMENTAT + AEAT fail-closed |
| `GiftEntitlementIssuerService` | IMPLEMENTAT |
| Outbox compra regal | IMPLEMENTAT EN CAMÍ SIF |
| Sync posterior llegat | IMPLEMENTAT POST-SIF |
| Callback llegat | ACTIU NOMÉS A CÒPIA/ROLLBACK; 410 DESPRÉS CUTOVER+DRAIN |
| Preproducció | PENDENT D'EXECUCIÓ/EVIDÈNCIA |


## Implementació FINAL reconciliada — 2026-10-03

El diagrama FINAL deixa de ser exclusivament objectiu per als components següents,
que ja existeixen a la branca candidata:

```mermaid
classDiagram
  class SifRedsysGiftIntentClient
  class RedsysGiftPaymentIntentService
  class RedsysPaymentIntentService
  class RedsysCallbackService
  class RedsysCallbackWorker
  class RedsysGiftInvoiceService
  class InvoiceService
  class GiftEntitlementIssuerService
  class GiftPaymentNotificationService
  class RedsysGiftPaymentStatusService
  class SifRedsysGiftStatusClient
  class GiftPaymentReturnStatus

  SifRedsysGiftIntentClient --> RedsysGiftPaymentIntentService
  RedsysGiftPaymentIntentService --> RedsysPaymentIntentService
  RedsysCallbackService --> RedsysCallbackWorker
  RedsysCallbackWorker --> RedsysGiftInvoiceService
  RedsysGiftInvoiceService --> InvoiceService
  RedsysGiftInvoiceService --> GiftEntitlementIssuerService
  RedsysGiftInvoiceService --> GiftPaymentNotificationService
  SifRedsysGiftStatusClient --> RedsysGiftPaymentStatusService
  GiftPaymentReturnStatus --> SifRedsysGiftStatusClient
```

La seva existència al repositori no prova desplegament productiu.
