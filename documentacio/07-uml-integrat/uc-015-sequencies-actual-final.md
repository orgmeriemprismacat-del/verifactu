# UC-015 · Seqüències ACTUAL / FINAL — Comprar pack

**Data d'auditoria:** 2026-09-29 · **Revalidació main:** 2026-09-30

## 1. ACTUAL — alta del pack al web

```mermaid
sequenceDiagram
autonumber
actor U as Alumne
participant JS as mostrarInscripcioPack.min.js
participant Price as obtenirPreusPack.php
participant Alta as enviarInscripcioPack.php
participant DB as BD legacy
participant Mail as Correu

U->>JS: Obre formulari pack
JS->>Price: GET idPack/idPreu
Price->>DB: consulta info_pack/packs/preu
Price-->>JS: preu original | preu pack
JS-->>U: mostra preu
U->>JS: confirma formulari
JS->>Alta: GET dades del formulari + idPack
Alta->>DB: rellegir preu pack i preus components
Alta->>DB: GET_LOCK allocator IDPAG
Alta->>Alta: reservar MAX(IDPAG)+1 sota lock
loop cada component
 Alta->>DB: INSERT TIPUS_INSC=P + PACK_ORDINAL/base/descompte/total
end
Alta->>DB: RELEASE_LOCK allocator IDPAG
Alta->>Mail: correus alta
Alta-->>JS: hash inscripció
JS-->>U: redirecció confirmació
```

### Riscos ACTUAL residuals

- l'allocator `IDPAG` continua sent MAX+1, tot i estar serialitzat amb lock;
- cal acreditar que `PACK_ORDINAL` representa l'ordre comercial canònic;
- el callback fiscal legacy conserva codi històric però està desactivat per defecte.

## 2. ACTUAL — cobrament pack al callback llegat

```mermaid
sequenceDiagram
autonumber
actor R as Redsys
participant CB as realitzaPagamentPackAutomatic.php
participant DB as BD legacy
participant Mail as Correus

R->>CB: POST Ds_* + URL amb GET idPag/import/order
CB->>CB: comprovar SIF_PACK_LEGACY_CALLBACK_ENABLED
alt desactivat per defecte
 CB-->>R: HTTP 410
else rollback explicit
 CB->>CB: valida signatura + DS_ORDER + import
 CB->>DB: cerca inscripcions IDPAG
CB->>DB: calcula factura_relacionada / ordre fiscal
CB->>DB: INSERT factures
loop per A_PAGAR DESC
 CB->>DB: UPDATE PAGAMENT / FACTURA_RELACIONADA
end
CB->>DB: UPDATE FRACCIO si correspon
CB->>Mail: confirmacions
end
```

**Revalidació 30/09:** el callback legacy queda desactivat per defecte amb HTTP 410 abans de qualsevol escriptura. El codi intern només queda disponible per rollback explícit.

## 3. FINAL — intenció, callback i emissió SIF

```mermaid
sequenceDiagram
autonumber
actor U as Alumne
participant Gate as PackPaymentGate
participant Client as SifPaymentIntentClient
participant Intent as RedsysPaymentIntentService
participant R as Redsys
participant CB as RedsysCallbackService
participant Q as CallbackQueue
participant W as RedsysCallbackWorker
participant D as RedsysCallbackDispatcher
participant P as RedsysPackInvoiceService
participant I as InvoiceService
participant L as EnrollmentFundMovementRepository
participant Sync as AcademicEnrollmentSyncService
participant Outbox as PackPaymentNotificationService

U->>Gate: confirmar pagament pack
Gate->>Gate: rellegir BD i validar composició/preu/receptor/ordinal
Gate-->>Client: snapshot congelat
Client->>Intent: POST HMAC create(PACK, DS_ORDER, total, snapshot)
Intent-->>Client: intenció acceptada
Client->>R: construir TPV amb DS_ORDER/intenció
R->>CB: callback signat
CB->>CB: validar signatura + intent + import + moneda + terminal
CB->>Q: enqueue
W->>Q: claim
W->>D: process(job)
D->>P: issueFromIntentSnapshot()
P->>P: construir N línies
P->>P: validar total factura = import Redsys
P->>I: issueInvoice()
I-->>P: UUID_FACTURA + UUID_PAYMENT
loop cada component
 P->>L: atribució UUID_PAYMENT → ID_INSC
end
P->>Sync: sincronitzar postcommit
P->>Outbox: notificacions postcommit
P-->>W: resultat
W->>Q: PROCESSED
```

## 4. FINAL — callback duplicat

```mermaid
sequenceDiagram
autonumber
participant R as Redsys
participant CB as RedsysCallbackService
participant Q as Queue
participant I as InvoiceService
R->>CB: mateix DS_ORDER
CB->>CB: valida contra mateixa intenció
CB->>Q: registre/encua idempotent
Q->>I: mateix payload/idempotency key
I-->>Q: reutilitza UUID_FACTURA / UUID_PAYMENT
```

## 5. FINAL — mismatch bloquejant

```mermaid
sequenceDiagram
autonumber
participant W as Worker
participant P as RedsysPackInvoiceService
participant B as Pack Payload
participant R as Redsys notification
participant I as InvoiceService
W->>P: processar PACK
P->>B: total línies
P->>R: import validat
alt totals iguals
 P->>I: issueInvoice
else totals diferents
 P-->>W: 409 conflict
 Note over P,I: no factura, no payment
end
```

## 6. Estat

- Seqüència ACTUAL web: documentada.
- Seqüència ACTUAL callback: documentada.
- Seqüència FINAL: **majoritàriament implementada** al flux PACK asíncron.
- Control total factura/import Redsys: implementat.
- Checkout → intenció SIF: implementat.
- Ledger per inscripció: implementat i cablejat al worker.
- Outbox: implementat i cablejat al worker.
- Pendent: eliminar el codi legacy després del rollback, acreditar l'origen comercial de l'ordinal i executar proves d'entorn.
