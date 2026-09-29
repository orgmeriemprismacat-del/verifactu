# UC-015 · Seqüències ACTUAL / FINAL — Comprar pack

**Data d'auditoria:** 2026-09-29

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
JS->>Alta: GET dades + preuCursos + preuPack + idPack
Alta->>DB: SELECT últim IDPAG
Alta->>Alta: IDPAG = últim + 1
loop cada component
 Alta->>DB: INSERT inscripcions TIPUS_INSC=P
end
Alta->>Mail: correus alta
Alta-->>JS: hash inscripció
JS-->>U: redirecció confirmació
```

### Riscos ACTUAL

- import del pack provinent del client;
- generació concurrent de `IDPAG`;
- no hi ha snapshot comercial versionat;
- no es conserva ordinal comercial explícit.

## 2. ACTUAL — cobrament pack al callback llegat

```mermaid
sequenceDiagram
autonumber
actor R as Redsys
participant CB as realitzaPagamentPackAutomatic.php
participant DB as BD legacy
participant Mail as Correus

R->>CB: POST Ds_* + URL amb GET idPag/import/order
CB->>CB: calcula signatura
CB->>DB: cerca inscripcions IDPAG
CB->>DB: calcula factura_relacionada / ordre fiscal
CB->>DB: INSERT factures
loop per A_PAGAR DESC
 CB->>DB: UPDATE PAGAMENT / FACTURA_RELACIONADA
end
CB->>DB: UPDATE FRACCIO si correspon
CB->>Mail: confirmacions
```

**Observació d'auditoria:** el fitxer llegit calcula la signatura però no s'ha acreditat una comparació bloquejant amb `Ds_Signature` abans de les escriptures.

## 3. FINAL — intenció, callback i emissió SIF

```mermaid
sequenceDiagram
autonumber
actor U as Alumne
participant Web as PackCheckoutAdapter
participant V as PackCommercialSnapshotValidator
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
participant Outbox as NotificationOutbox

U->>Web: confirmar compra
Web->>V: validar composició/preu/receptor/ordinal
V-->>Web: snapshot congelat
Web->>Intent: create(PACK, DS_ORDER, total, snapshot)
Intent-->>Web: UUID_INTENT
Web->>R: inicia TPV
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
- Seqüència FINAL: documentada.
- Control total factura/import Redsys: implementat el 2026-09-29.
- Ledger per inscripció: pendent.
