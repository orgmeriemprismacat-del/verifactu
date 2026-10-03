# UC-017 · Seqüències ACTUAL / FINAL

## 1. ACTUAL — compra i callback llegat

```mermaid
sequenceDiagram
  actor C as Comprador
  participant W as Web regal
  participant R as Redsys
  participant L as realitzaPagamentRegalAutomatic.php
  participant DB as BD llegada
  participant M as Mail

  C->>W: Selecciona regal + dades
  W->>W: Genera DS_ORDER=time()
  W->>R: Formulari Redsys
  R-->>L: Callback + MerchantParameters
  L->>L: Decodifica resposta
  L->>DB: SELECT regal
  L->>DB: calcula següent factura/ordre
  L->>DB: INSERT factures
  L->>DB: UPDATE regal.FACT_REL
  L->>M: correu comprador/gestió
```

### Riscos de la seqüència ACTUAL
- no queda demostrada la comparació criptogràfica;
- confia en query params;
- numeració fiscal fora del SIF;
- correu dins del callback;
- escriptura fiscal directa al llegat.

## 2. FINAL — cobrament + factura + dret

```mermaid
sequenceDiagram
  actor C as Comprador
  participant W as WebAdapter
  participant I as RedsysPaymentIntentService
  participant R as Redsys
  participant N as NotificationProcessor
  participant G as RedsysGiftInvoiceService
  participant F as InvoiceService
  participant E as GiftEntitlementIssuerService
  participant O as Outbox
  participant S as LegacySync

  C->>W: Confirmar compra
  W->>I: create intent REGAL + snapshot + idempotency
  I-->>W: DS_ORDER / formulari
  W->>R: pagament
  R-->>N: callback signat
  N->>N: validar signatura/import/ordre/moneda
  N-->>G: job VALIDATED
  G->>F: issueInvoice(payload REGAL + CHARGE)
  F-->>G: uuid_factura + uuid_payment
  G->>E: issue GIFT entitlement
  E-->>G: uuid_operation + uuid_entitlement
  G->>O: confirmació/lliurament post-commit
  G->>S: sync no fiscal posterior
```

## 3. FINAL — recuperació si falla el dret després de facturar

```mermaid
sequenceDiagram
  participant G as Gift worker
  participant F as InvoiceService
  participant E as GiftEntitlementIssuerService
  participant Q as Retry/Incident

  G->>F: issueInvoice()
  F-->>G: factura + payment confirmats
  G->>E: issue entitlement
  E--xG: error temporal
  G->>Q: registrar pendent/retry
  Q->>E: reintentar amb mateixa clau
  E-->>Q: mateix entitlement / idempotent
  Note over Q,E: Mai crear un segon CHARGE
```
