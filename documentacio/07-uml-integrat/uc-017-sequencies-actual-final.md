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


## 4. Estat d'implementació de la seqüència FINAL

A la branca candidata ja hi ha implementació directa dels passos:

```text
checkout
-> gift-intent signat
-> redsys_payment_intent
-> callback SIF validat
-> redsys_callback_queue
-> worker REGAL
-> factura/payment
-> entitlement
-> notification_outbox
-> gift-status
-> retorn navegador autoritatiu
```

El punt encara no acreditat és el **desplegament i execució E2E en preproducció**,
no l'absència dels components.

## 5. FINAL revalidat — token + fencing d'intents

```mermaid
sequenceDiagram
  actor C as Comprador
  participant W as Web regal
  participant T as GiftCheckoutToken
  participant P as Pay
  participant I as GiftIntentService
  participant DB as SIF
  participant R as Redsys

  C->>W: obre pagament del regal autoritzat
  W->>T: issue(giftId, TTL)
  T-->>W: giftToken HMAC
  W->>P: POST giftToken + dades titular
  P->>T: verify(giftToken)
  T-->>P: giftId
  P->>I: create(giftId)
  I->>DB: GET_LOCK per giftId
  I->>DB: buscar intent sense notificació
  alt intent pendent existent
    DB-->>I: mateix DS_ORDER
  else cap intent
    I->>DB: crear un únic intent
  end
  I-->>P: DS_ORDER + import autoritatiu
  P->>R: formulari signat Redsys
```

La factura REGAL conserva `LEGACY|REGAL|ID:<giftId>`; per tant una notificació
validada amb un segon `DS_ORDER` no pot materialitzar una segona factura/CHARGE SIF.
