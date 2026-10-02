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
JS->>JS: generar/reutilitzar REQUEST_ID UUID v4 a sessionStorage
JS->>Alta: POST dades + idPack + REQUEST_ID
Alta->>DB: GET_LOCK prisma_pack_req_<hash>
Alta->>DB: buscar RID + RH1 a inscripcions
alt mateix REQUEST_ID + mateix payload hash
 Alta-->>JS: hash de confirmació d'una inscripció existent
 Alta->>DB: RELEASE_LOCK request
else mateix REQUEST_ID + payload diferent/inconsistent
 Alta-->>JS: HTTP 409 sense mutació
else request nou
 Alta->>Alta: validar/normalitzar formulari
 Alta->>DB: carregar N components + regles dies-inscriu-cursos
 Alta->>Alta: exigir totes les edicions obertes
 Alta->>DB: rellegir preu pack i preus components
 Alta->>DB: GET_LOCK allocator IDPAG
 Alta->>Alta: reservar MAX(IDPAG)+1 sota lock
 Alta->>DB: BEGIN transaction
 loop cada component
  Alta->>DB: INSERT TIPUS_INSC=P + snapshot + RID/RH1
 end
 Alta->>DB: validar suma línies = preu PACK
 Alta->>DB: COMMIT transaction
 Alta->>Alta: marca enrollment committed
 Alta->>DB: RELEASE_LOCK allocator IDPAG
 Alta->>DB: RELEASE_LOCK request
 Alta-->>JS: hash inscripció
 Alta->>Mail: correus/auxiliars postcommit
 Note over Alta,Mail: una fallada auxiliar es loga i no converteix l'alta commitada en error
end
JS->>JS: netejar REQUEST_ID només en èxit determinista
JS-->>U: redirecció confirmació
```

### Riscos ACTUAL residuals

- les N inscripcions ja es persisteixen atòmicament; en excepció es fa rollback i el lock `IDPAG` s'allibera per `finally`;
- l'alta pública és POST-only amb comprovació same-site/origin i idempotència server-side `REQUEST_ID`+payload hash; una alta nova exigeix totes les edicions obertes i un reintent equivalent reutilitza l'alta abans de rellegir disponibilitat/pack actual; resta E2E navegador/preproducció i valorar controls anti-abús addicionals;
- l'allocator `IDPAG` continua sent MAX+1, tot i estar serialitzat amb lock;
- `PACK_ORDINAL` queda determinat pel mateix ordre estable de presentació `DATAI, ID_CURS`; resta decidir si negoci requereix una posició explícita separada;
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
participant SyncProc as RedsysLegacySyncingProcessor
participant D as RedsysCallbackDispatcher
participant P as RedsysPackInvoiceService
participant I as InvoiceService
participant L as PackEnrollmentFundAllocationService
participant Outbox as PackPaymentNotificationService
participant Legacy as LegacySyncService

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
W->>SyncProc: process(job)
SyncProc->>D: process(job)
D->>P: issueFromIntentSnapshot()
P->>P: construir N línies i validar total = import Redsys
P->>I: issueInvoice()
I-->>P: UUID_FACTURA + UUID_PAYMENT
P->>L: allocate(UUID_PAYMENT, N ID_INSC)
L-->>P: N atribucions idempotents
P->>Outbox: enqueue(PACK_PAYMENT_CONFIRMED)
Outbox-->>P: event idempotent
P-->>D: resultat + legacy_sync=PACK_FULL_PAYMENT
D-->>SyncProc: resultat
SyncProc->>Legacy: syncAfterSifSuccess()
SyncProc->>Legacy: syncPackFullPayment()
SyncProc-->>W: resultat + legacy_sync_executed
W->>Q: PROCESSED
```

**Revalidació 02/10:** el wrapper real del worker és `RedsysLegacySyncingProcessor`; no existeix cap `AcademicEnrollmentSyncService` en aquest flux. La sincronització legacy s'executa només després que el handler PACK hagi retornat una emissió SIF correcta.

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
- Pendent: eliminar el codi legacy després del rollback, decidir si cal una posició comercial explícita independent de l'ordre cronològic estable i executar proves d'entorn, incloent la nova frontera POST de l'alta pública.
