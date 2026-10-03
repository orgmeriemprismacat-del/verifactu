# UC-012 — Seqüències i activitats ACTUAL / FINAL per pàgina

## 1. P-MOR-02 · Recordatori final

### ACTUAL
```mermaid
sequenceDiagram
actor O as Operador
participant JS as facturacio-recordatori-pagament-final.js
participant A as updDadesRecordatoriPagament.php
participant I as Intranet.php
participant DB as web.inscripcions
participant SMTP as MailSMTPComvive
O->>JS: marca registres
JS->>A: POST idInsc
A->>I: updateSendMsg_Facturacio_Recordatori_Pagament(id)
I->>DB: rellegir dades i curs
I->>DB: UPDATE reclamació/observacions
I->>SMTP: enviar recordatori
A-->>JS: HTML/OK
```

### FINAL
```mermaid
sequenceDiagram
actor O as Operador
participant UI as Intranet
participant C as API interna HMAC
participant D as DebtClaimCoordinator
participant R as DebtSnapshotRepository
participant CR as DebtClaimCaseRepository
participant OX as NotificationOutbox
O->>UI: confirmar P-MOR-02
UI->>C: command + idempotency + csrf
C->>D: execute
D->>R: saldo/titular fiscals actuals
R-->>D: snapshot
D->>CR: append NOTICE_FINAL
D->>OX: enqueueIdempotent
D-->>C: result
C-->>UI: CREATED/REUSED/CONFLICT
```

## 2. P-MOR-03 · Primera reclamació

### ACTUAL
```mermaid
flowchart TD
A[Obrir primera reclamació] --> B[Consultar registres legacy]
B --> C[Operador marca]
C --> D[POST idInsc]
D --> E[Rellegir inscripció/curs]
E --> F[Generar URL amb IDPAG legacy]
F --> G[UPDATE reclamat + data_reclamacio]
G --> H[SMTP alumne + còpia]
H --> I[Reload]
```

### FINAL
```mermaid
flowchart TD
A[Preview] --> B[Saldo SIF + receptor fiscal]
B --> C{Deute exigible?}
C -- No --> D[NO_CHANGE / cancel·lar avís]
C -- Sí --> E[Confirmar]
E --> F[Autorització + idempotència + lock]
F --> G[Append FIRST_CLAIM]
G --> H[Outbox versionada]
H --> I[Projectar al llegat després del commit si cal]
```

## 3. P-MOR-04 · Reclamació final / eventual baixa

### ACTUAL
```mermaid
sequenceDiagram
actor O as Operador
participant JS as facturacio-reclamacio-final.js
participant A as updLastClaimPay.php
participant I as Intranet.php
participant DB as legacy
O->>JS: marca reclamació/baixa
JS->>A: POST idInsc
A->>I: updateSendMsg_LastClaimPay
I->>DB: consultar i mutar estat
I-->>A: resultat
A-->>JS: resultat
```

### FINAL
```mermaid
sequenceDiagram
actor O as Operador
participant C as DebtClaimCoordinator
participant S as DebtSnapshotRepository
participant CR as DebtClaimCaseRepository
participant X as UC-72/95/96
participant OX as Outbox
O->>C: FINAL_CLAIM
C->>S: revalidar saldo i receptor fiscal
alt només reclamació
 C->>CR: append FINAL_CLAIM
 C->>OX: enqueue notice
else decisió acadèmica
 C->>X: derivar baixa/pròrroga
 X-->>C: decisió separada
 C->>CR: correlacionar decisió
end
Note over C,X: mai anul·lar factura automàticament per impagament
```

## 4. P-MOR-05 · Regularització després de reclamació

```mermaid
sequenceDiagram
actor O as Operador
participant C as ClaimPaymentService
participant R as ManualPaymentInvoiceRepository
participant B as ClaimPaymentPayloadBuilder
participant P as PaymentService
participant CR as DebtClaimCaseRepository
O->>C: ingrés real: UUID_FACTURA/import/data/ref
C->>R: find invoice
C->>B: build CLAIM_PAYMENT
C->>P: registerPayment
P-->>C: UUID_PAYMENT / reused
C-->>O: resultat
O->>CR: no accés directe
C-->>O: UUID_PAYMENT
O->>C: reconcileAfterPayment via DebtClaimCoordinator
```

## 5. Activitats per superfície

### `facturacio-recordatori-pagament-final.php`
**ACTUAL:** llistar → marcar → POST → UPDATE + SMTP → recarregar.  
**FINAL (nucli implementat):** preview SIF → confirmar via bridge segur → append event → outbox. Projecció legacy només si es decideix al cutover.

### `facturacio-primera-reclamacio-pagament.php`
**ACTUAL:** llistar → generar URL legacy → marcar reclamació → SMTP.  
**FINAL (nucli implementat):** receptor fiscal de factura → FIRST_CLAIM idempotent → outbox. URL de pagament final encara no forma part del cutover.

### `facturacio-reclamacio-final.php`
**ACTUAL:** llistar casos antics → reclamar/baixar en flux acoblat.  
**FINAL (nucli implementat):** FINAL_CLAIM al SIF; qualsevol baixa/pròrroga continua com a ordre separada d’UC-72/95/96.

### `facturacio-control-morosos.php`
**ACTUAL:** quatre conjunts de deute segons entitat/certificat/estat legacy.  
**FINAL:** saldo i receptor provenen del SIF; la UI de llista de casos oberts encara no ha substituït la taula legacy.

## 6. Controls transversals FINAL

Cada mutació ha d'acreditar:
- autenticació + rol + abast;
- CSRF o autenticació de servei;
- `REQUEST_ID` / `CORRELATION_ID`;
- clau idempotent i comparació de payload;
- lock/versió de l'expedient;
- recalcul de saldo abans del commit;
- outbox després del commit;
- cap moviment fiscal/monetari si només s'envia una reclamació.


## 7. Estat d'implementació de les seqüències

- P-MOR-02/03/04: **coordinador/event/outbox implementats a la branca**.
- P-MOR-05: **cobrament existent + reconciliació/tancament implementats**.
- Bridge intranet: **implementat i desactivat per feature flag**.
- Venciment/pròrroga: **no forma part encara del snapshot perquè UC-096 continua en DISSENY**.
- Proves: **escrites; Actions continua en cua**.
- Cutover de les accions dels JS legacy: **pendent**.
