# UC-014 — Diagrames de seqüència ACTUAL i FINAL

**Data:** 29/09/2026.  
**Objectiu:** separar el recorregut llegat que factura dins del callback de l'arquitectura final asíncrona del SIF.

## 1. ACTUAL — compra i callback llegat

```mermaid
sequenceDiagram
autonumber
actor A as Alumne/pagador
participant P as PagamentCursAutomatic
participant E as pagina_efectuar_pagament_automatic.php
participant R as Redsys
participant C as realitzaPagamentAutomatic.php
participant DB as BD llegada
participant M as Mail
participant OK as respostaOk/Ko

A->>P: Obre pagament / confirma inscripció
P->>DB: SELECT inscripcions + curs
DB-->>P: preu, pagat, curs, IDPAG, descompte
P-->>A: targeta/transferència segons estat
A->>E: POST dades i import a pagar
E->>E: DS_ORDER = time()
E->>R: formulari TPV amb amount/order/MerchantURL
R->>C: POST notificació + MerchantURL amb GET funcional
C->>C: decodifica Ds_MerchantParameters
C->>DB: SELECT inscripció per IDPAG
C->>DB: SELECT curs
alt Ds_Response autoritzat
  C->>DB: calcula factura_relacionada i ordre
  C->>DB: INSERT factures
  C->>DB: UPDATE inscripcions PAGAMENT/FACTURA_RELACIONADA/DATA PAG/FRACCIO
  C->>M: correus de gestió/suport/alumne
else denegat/error
  C->>M: notificació/error segons branca
end
R-->>OK: retorn navegador OK/KO
OK-->>A: missatge visual
```

### Punts que el diagrama ACTUAL no dona per resolts

1. No s'ha localitzat a la còpia revisada la comparació efectiva entre la signatura recalculada i la rebuda.
2. No s'ha acreditat la igualtat entre `Ds_Order` del payload i `order` de la MerchantURL.
3. No s'ha acreditat la igualtat entre `Ds_Amount` i l'import funcional utilitzat posteriorment.
4. El retorn OK/KO del navegador no és una prova suficient de persistència fiscal/econòmica.
5. El callback duplicat no té una protecció idempotent equivalent a la del SIF nou en el fragment auditat.

## 2. FINAL — intenció, callback, cua i emissió SIF

```mermaid
sequenceDiagram
autonumber
actor A as Alumne/pagador
participant Web as EcommerceCoursePaymentAdapter [PENDENT]
participant Intent as RedsysPaymentIntentService
participant R as Redsys
participant C as RedsysCallbackService
participant Q as RedsysCallbackQueue
participant W as RedsysCallbackWorker
participant D as RedsysCallbackDispatcher
participant H as RedsysCourseInvoiceService
participant LB as LegacyCourseInvoicePayloadBuilder
participant RB as RedsysInvoicePayloadBuilder
participant I as InvoiceService
participant Sync as Sincronització llegada [PENDENT]

A->>Web: Confirma curs, edició i forma de pagament
Web->>Web: revalida inscripció, places, receptor, preu i descompte
Web->>Intent: create(CURS, source_id, IDPAG, expected_amount, currency, terminal, snapshot)
Intent-->>Web: intent creat o reutilitzat
Web->>R: redirecció amb DS_ORDER de la intenció
R->>C: callback signat
C->>C: valida signatura + DS_ORDER + import + moneda + terminal
C->>Q: persisteix notificació i encola
C-->>R: HTTP sense factura
W->>Q: claimNext()
Q-->>W: job únic
W->>D: process(job)
D->>H: issueFromIntentSnapshot()
H->>LB: build(snapshot)
LB-->>H: factura/línia/relació INSCRIPCIO
H->>RB: buildFromValidatedNotification()
RB-->>H: payload + CHARGE + idempotency keys
H->>I: issueInvoice(payload)
I-->>H: UUID_FACTURA + UUID_PAYMENT + reused?
H-->>W: resultat
W->>Q: markProcessed(result)
opt sincronització operativa
  W->>Sync: aplicar estat acadèmic/llegat de forma idempotent
end
```

## 3. FINAL — callback duplicat

```mermaid
sequenceDiagram
autonumber
participant R as Redsys
participant C as RedsysCallbackService
participant Q as Queue/Notification repos
participant W as Worker
participant I as InvoiceService

R->>C: callback DS_ORDER X
C->>Q: insert/reuse notificació compatible
C->>Q: enqueue/reuse job
R->>C: callback duplicat DS_ORDER X
C->>Q: reuse o CONFLICT si payload incompatible
W->>Q: claim job únic
W->>I: issueInvoice(idempotency_key estable)
I-->>W: factura/pagament creats o reutilitzats
Note over C,I: cap segon ingrés, factura o assignació monetària
```

## 4. FINAL — import o payload incompatible

```mermaid
sequenceDiagram
autonumber
participant R as Redsys
participant C as RedsysCallbackService
participant Intent as redsys_payment_intent
participant Inc as Incidència

R->>C: callback
C->>Intent: carregar DS_ORDER esperat
alt signatura/import/moneda/terminal no coincideixen
  C->>Inc: registrar incidència correlacionada
  C-->>R: resposta tècnica sense facturar
else coincideixen
  C->>C: continuar cua asíncrona
end
```

## 5. Estat

**DOCUMENTAT:** seqüència ACTUAL, FINAL nominal, duplicat i conflicte.  
**IMPLEMENTAT:** serveis SIF centrals; no l'adaptador ecommerce final.  
**VERIFICAT:** estàticament.  
**PENDENT:** execució end-to-end i reconciliació amb la web desplegada.
