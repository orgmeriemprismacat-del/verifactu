# UC-003 — Diagrames de seqüència ACTUAL i FINAL

**Data d'auditoria:** 03/10/2026  
**Objectiu:** separar el callback llegat síncron del circuit SIF asíncron i deixar explícits duplicats, retries, incidències i ownership del worker.

## 1. ACTUAL — web/callback llegat

```mermaid
sequenceDiagram
autonumber
actor U as Alumne/pagador
participant JS as mostrarEfectuarPagamentAutomatic.js
participant P as pagina_efectuar_pagament_automatic.php
participant R as Redsys
participant C as realitzaPagamentAutomatic.php
participant DB as BD llegada
participant M as Mail

U->>JS: confirma pagament
JS->>P: submit formulari preparat
P->>R: Ds_Order + amount + MerchantURL
R->>C: callback POST signat
C->>C: valida signatura, order, import, moneda, terminal, comerç
C->>DB: SELECT inscripció/curs
alt autoritzat
  C->>M: comunicacions operatives
  C->>DB: calcula numeració llegada
  C->>DB: INSERT factures
  C->>DB: UPDATE PAGAMENT/FACTURA_RELACIONADA/DATA PAG/FRACCIO
else denegat/error
  C->>DB: registra observació/estat llegat segons circuit
end
```

**Risc estructural ACTUAL:** la recepció HTTP, la facturació, el cobrament, la projecció acadèmica i les notificacions conviuen al mateix script. Encara que el fallback s'ha endurit criptogràficament en auditories anteriors, no és el patró FINAL.

## 2. CANDIDAT — intenció SIF amb fallback de callback

```mermaid
sequenceDiagram
autonumber
actor U as Alumne/pagador
participant P as pay pagina_efectuar_pagament_automatic.php
participant IC as SifRedsysCourseIntentClient
participant SI as API intenció SIF
participant R as Redsys
participant L as doit.php
participant S as callback SIF

U->>P: prepara pagament
P->>IC: create(IDPAG, import, terminal)
IC->>SI: POST HMAC
SI-->>IC: DS_ORDER + import autoritatiu
IC-->>P: intenció creada/reutilitzada
alt cutover=0
  P->>R: MerchantURL = doit.php
  R->>L: callback llegat
else cutover=1 i drain=1
  P->>R: MerchantURL = SIF_REDSYS_CALLBACK_URL
  R->>S: callback SIF
else cutover=1 i drain=0
  P-->>U: fail-closed; no nova sessió
end
```

## 3. FINAL — recepció HTTP i encuat

```mermaid
sequenceDiagram
autonumber
participant R as Redsys
participant E as /api/redsys/callback.php
participant V as RedsysSignatureValidator
participant C as RedsysCallbackService
participant I as RedsysPaymentIntentRepository
participant N as RedsysNotificationRepository
participant Q as RedsysCallbackQueueRepository
participant DB as SIF DB

R->>E: POST Ds_MerchantParameters + Signature
E->>V: decodeAndVerify($_POST)
V->>V: valida versió, HMAC, merchant, transaction type, order, amount, EUR, terminal
V-->>E: payload normalitzat + payload_hash
E->>C: receiveCallback(DB,payload,true)
C->>DB: BEGIN
C->>I: findByDsOrder(..., FOR UPDATE)
I-->>C: intenció
C->>C: compara amount/currency/terminal
C->>N: recordReceived()
alt callback autoritzat 0000..0099
  C->>Q: enqueue(notification)
  Q-->>C: job creat/reutilitzat
else denegat
  C->>C: notification STATUS=ERROR
end
C->>DB: COMMIT
C-->>E: status/duplicate/queue_status/uuid_job
E-->>R: resposta HTTP curta
Note over E,Q: no s'emet factura ni cobrament dins la petició HTTP
```

## 4. FINAL — worker, factura i cobrament

```mermaid
sequenceDiagram
autonumber
participant W as RedsysCallbackWorker
participant Q as RedsysCallbackQueueRepository
participant P as RedsysLegacySyncingProcessor
participant D as RedsysCallbackDispatcher
participant H as Handler segons SOURCE_TYPE
participant I as InvoiceService
participant A as Allocation/Sync/Outbox
participant Inc as IncidentRepository

W->>Q: recoverStaleLocks(now)
W->>Q: claimNext(workerId,now)
Q-->>W: job PROCESSING + LOCKED_BY
W->>P: process(job)
P->>D: process(job)
D->>H: issueFromIntentSnapshot(order,snapshot)
H->>I: issueInvoice(payload + CHARGE)
I-->>H: ok + UUID_FACTURA + UUID_PAYMENT
H->>A: efectes post-SIF específics
A-->>H: resultat addicional
H-->>D: resultat
D-->>P: resultat
P-->>W: resultat
W->>W: exigeix ok=true + UUID_FACTURA + UUID_PAYMENT
W->>Q: markProcessed(..., workerId)
Note over W,Q: només el propietari actual del lock pot fer transició terminal
```

## 5. FINAL — duplicat i contradicció

```mermaid
sequenceDiagram
autonumber
participant R as Redsys
participant C as RedsysCallbackService
participant N as RedsysNotificationRepository
participant Q as RedsysCallbackQueueRepository

R->>C: callback order X / payload A
C->>N: insert notification
C->>Q: enqueue
R->>C: callback order X / payload A
C->>N: duplicate compatible
C->>Q: reutilitza job
Note over C,Q: no segona factura/cobrament
R->>C: callback order X / payload B contradictori
C->>N: detecta conflicte
C->>C: rollback + incidència REDSYS_CALLBACK
C-->>R: 409
```

## 6. FINAL — error tècnic, error funcional i resultat incomplet

```mermaid
sequenceDiagram
autonumber
participant W as Worker
participant Q as Queue
participant P as Processor
participant Inc as Incident

W->>Q: claimNext()
W->>P: process(job)
alt error tècnic i intents disponibles
  P-->>W: exception
  W->>Q: markRetry(next, error, workerId)
else 409/422 o intents exhaurits
  P-->>W: exception
  W->>Q: markIncident(error, workerId)
  W->>Inc: open incident
else result ok però falta factura o cobrament
  P-->>W: {ok:true, uuid_factura, uuid_payment absent}
  W->>W: assertCompletedResult() -> 409
  W->>Q: markIncident(..., workerId)
  W->>Inc: open incident
end
```

## 7. FINAL — fencing de worker caducat

```mermaid
sequenceDiagram
autonumber
participant A as Worker A
participant B as Worker B
participant Q as Queue

A->>Q: claimNext(worker-a)
Note over A,Q: A triga > 15 min
B->>Q: recoverStaleLocks()
B->>Q: claimNext(worker-b)
Q-->>B: mateix job, LOCKED_BY=worker-b
A->>Q: markProcessed(..., worker-a)
Q-->>A: 409 lost ownership
A-->>A: no transforma el job a incident/retry
B->>Q: continua com a propietari vàlid
```

## 8. FINAL implementat a la branca — factura UC-004 ja emesa abans del TPV

```mermaid
sequenceDiagram
autonumber
participant W as Worker
participant H as Handler CURS/variant
participant F as RedsysCoveredInvoicePaymentService
participant Cov as InvoiceBeforePaymentCoverageRepository
participant Guard as invoice_origin_guard
participant Pay as PaymentService
participant Inv as InvoiceService
participant Inc as Reconciliació

W->>H: job autoritzat
H->>F: registerIfCovered(order,snapshot,payload)
F->>Cov: findClaims(INSCRIPCIO)
alt factura UC-004 existent i compatible
  Cov-->>F: UUID_FACTURA
  F->>Pay: registerPaymentWithPrecondition(CHARGE → UUID_FACTURA)
  Pay->>Cov: findClaims(..., FOR UPDATE)
  Cov-->>Pay: cobertura única/estable
  Pay->>F: assertReceivable(): lock factura + línia + saldo pendent
  Pay-->>F: UUID_PAYMENT creat/reutilitzat
  F-->>H: UUID_FACTURA existent + UUID_PAYMENT
else no hi ha cobertura UC-004
  Cov-->>F: none
  H->>Inv: issueInvoice(payload, respectBeforePaymentCoverage=true)
  Inv->>Cov: lockOriginInvoiceRelations()
  Cov->>Guard: UPSERT/lock (INSCRIPCIO,SOURCE_ID)
  Inv->>Cov: recheck coverage/fact_rels abans d'emetre
else cobertura/invoice/import incompatible
  F-->>H: 409
  H->>Inc: worker deriva a incidència
end
```

**Estat de branca:** implementat per **CURS + cobertura UC-004**. La ruta valida una factura única, total contractual, línia d'inscripció i saldo pendent; admet parcials sense nova factura. `InvoiceService` rep el guard com a paràmetre fora del payload idempotent. Les **rutes competidores d'emissió** UC-004 i Redsys bloquegen el mateix mutex persistent `invoice_origin_guard` abans de seqüència/cadena fiscal; els IDs es deduplicen i s'ordenen numèricament per reduir deadlocks. Quan la cobertura UC-004 ja existeix, el cobrament no necessita tornar a adquirir el mutex d'emissió: bloqueja la fila UNIQUE d'`invoice_before_payment_coverage` amb `FOR UPDATE` i després factura/línia abans de calcular el saldo. La prova de contenció usa dues connexions MySQL amb `READ COMMITTED`, de manera que la garantia d'emissió no depèn d'un gap lock implícit.

## 9. Estat

**DOCUMENTAT:** recorregut ACTUAL, candidat, recepció FINAL, worker, duplicat, errors, fencing i cas de factura prèvia.  
**IMPLEMENTAT:** seqüències 3–8 al codi SIF per CURS/UC-004, inclosos hardening del worker, cobrament sobre factura prèvia, parcials, binding estricte d'identitat Redsys i mutex durable d'origen.  
**VERIFICAT:** el hardening inicial passa SIF #1204; en la branca rebased, els heads `39799a1`, `8dc12f2`, `144aff7` i `23f8e07` han aportat runs verds de SIF/UC-004/UC-111, inclosa la prova MySQL de dues connexions a `READ COMMITTED`. El head actual encara espera cua d'Actions.  
**PENDENT:** verificar la seqüència 8 al CI i a preproducció; generalitzar només si el contracte de PACK/GRUP/REGAL/USOC ho requereix; cutover, cron/monitoratge i evidència operativa.
