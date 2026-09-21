# 34 - Diagrames de dades i estats del SIF

## 1. Objectiu i abast

Completar les vistes de classes, seqüències i casos d'ús amb el model persistent que governa idempotència, numeració, cadena fiscal, cobraments, cues, documents i incidències.

Estat de les fonts:

- les taules del nucli i `redsys_payment_intent` són al checkout base;
- `redsys_callback_queue` i l'enduriment de `redsys_notifications` són a `feature/redsys-async-queue`;
- estats i fluxos AEAT finals continuen parcialment en disseny.

## 2. Model entitat-relació SIF

```mermaid
erDiagram
  factura {
    bigint ID PK
    char UUID_FACTURA UK
    varchar IDEMPOTENCY_KEY UK
    varchar NUM_VISIBLE UK
    varchar ESTAT_FACTURA
    varchar ESTAT_COBRAMENT
    varchar ESTAT_AEAT
    decimal TOTAL
  }
  factura_linia {
    bigint ID PK
    char UUID_FACTURA FK
    int ORDRE
    varchar CONCEPTE
    varchar DESC_ORIGEN
    decimal TOTAL
  }
  factura_registres {
    bigint ID PK
    char UUID_FACTURA FK
    bigint FISCAL_ORDER UK
    varchar TIPUS_REGISTRE
    char HASH_FACT
    char HASH_FACT_ANT
  }
  factura_rectificacio {
    bigint ID PK
    char UUID_FACTURA_RECTIFICATIVA FK
    char UUID_FACTURA_RECTIFICADA FK
    varchar MOTIU
    varchar MODE_RECTIFICACIO
  }
  fiscal_sequence {
    bigint ID PK
    char TIPUS_SERIE
    smallint ANY_FACT
    int LAST_NUM
  }
  fiscal_chain_state {
    tinyint ID PK
    bigint LAST_FISCAL_ORDER
    char LAST_HASH
  }
  fiscal_queue {
    bigint ID PK
    char UUID_FACTURA FK
    varchar IDEMPOTENCY_KEY UK
    varchar STATUS
    int ATTEMPTS
  }
  payment_transaction {
    bigint ID PK
    char UUID_PAYMENT UK
    varchar IDEMPOTENCY_KEY UK
    varchar TIPUS_MOVIMENT
    decimal IMPORT
    varchar DS_ORDER
  }
  payment_allocation {
    bigint ID PK
    char UUID_PAYMENT FK
    char UUID_FACTURA FK
    decimal IMPORT_ASSIGNAT
    varchar TIPUS_ASSIGNACIO
  }
  fact_rels {
    bigint ID PK
    char UUID_FACTURA FK
    bigint ID_FACTURA_LINIA FK
    varchar SOURCE_TYPE
    bigint SOURCE_ID
    int IDPAG
    varchar DS_ORDER
  }
  factura_documents {
    bigint ID PK
    char UUID_FACTURA FK
    varchar TIPUS
    varchar PATH_FITXER
    char HASH_FITXER
  }
  redsys_payment_intent {
    char UUID_INTENT PK
    varchar DS_ORDER UK
    varchar SOURCE_TYPE
    varchar SOURCE_ID
    decimal EXPECTED_AMOUNT
    json SNAPSHOT_JSON
  }
  redsys_notifications {
    bigint ID PK
    varchar DS_ORDER UK
    decimal IMPORT
    varchar RESPONSE_CODE
    varchar STATUS
  }
  redsys_callback_queue {
    bigint ID PK
    char UUID_JOB UK
    bigint NOTIFICATION_ID FK
    char UUID_INTENT FK
    varchar STATUS
    int ATTEMPTS
  }
  credit_balance {
    bigint ID PK
    char UUID_CREDIT UK
    decimal IMPORT_ORIGINAL
    decimal IMPORT_DISPONIBLE
    varchar ESTAT
  }
  errors_verifactu {
    bigint ID PK
    char UUID_FACTURA
    varchar TIPUS_INCIDENCIA
    varchar ESTAT
  }

  factura ||--|{ factura_linia : conté
  factura ||--|{ factura_registres : genera
  factura ||--o{ fiscal_queue : envia
  factura ||--o{ factura_documents : conserva
  factura ||--o{ fact_rels : relaciona
  factura_linia o|--o{ fact_rels : concreta
  payment_transaction ||--|{ payment_allocation : distribueix
  factura ||--o{ payment_allocation : rep
  factura ||--o{ factura_rectificacio : rectificativa
  factura ||--o{ factura_rectificacio : rectificada
  redsys_payment_intent ||--o{ redsys_callback_queue : origina
  redsys_notifications ||--o| redsys_callback_queue : encola
```

`fiscal_sequence`, `fiscal_chain_state`, `credit_balance` i `errors_verifactu` no tenen totes les relacions expressades com a foreign keys. `fact_rels` és el pont lògic amb les BDs antigues; no es creen foreign keys entre bases de dades.

## 3. Separació entre bases de dades

```mermaid
flowchart LR
  Web[(BD web / ecommerce)]
  Intranet[(BD intranet)]
  SIF[(BD fiscal SIF)]
  Files[(Documents fora del webroot)]
  AEAT[AEAT]

  Web -- snapshot de només lectura --> SIF
  Intranet -- operació autoritzada --> SIF
  SIF -- resum post-SIF opcional --> Web
  SIF -- factures, pagaments i registres --> SIF
  SIF -- hash i metadades --> Files
  SIF -. fiscal_queue pendent d'integració .-> AEAT
```

La font fiscal és el SIF. Les taules web/intranet conserven operativa i compatibilitat, però no decideixen numeració, hash, registre ni estat AEAT.

## 4. Numeració i cadena fiscal

```mermaid
flowchart LR
  Input[Payload validat]
  Idem{IDEMPOTENCY_KEY existeix?}
  Existing[Retornar factura existent]
  Series[Blocatge fiscal_sequence per sèrie i any]
  Chain[Blocatge fiscal_chain_state global]
  Number[Assignar NUM_VISIBLE]
  Hash[Calcular HASH_FACT amb HASH_FACT_ANT]
  Record[Inserir factura_registres amb FISCAL_ORDER]
  Queue[Inserir fiscal_queue]
  Commit[COMMIT]

  Input --> Idem
  Idem -- sí --> Existing
  Idem -- no --> Series
  Series --> Chain
  Chain --> Number
  Number --> Hash
  Hash --> Record
  Record --> Queue
  Queue --> Commit
```

La numeració humana segueix `TIPUS_SERIE + ANY_FACT + NUM_SEQ`; la cadena segueix l'ordre temporal global `FISCAL_ORDER`. No s'utilitza `SELECT MAX()+1`.

## 5. Estat de factura

```mermaid
stateDiagram-v2
  [*] --> ISSUED: issueInvoice
  ISSUED --> RECTIFIED: rectificativa vinculada
  ISSUED --> CANCELLED: cancel·lació fiscal futura
  RECTIFIED --> RECTIFIED: rectificacions addicionals auditades
  [*] --> HISTORICAL: importació NO_VERIFACTU

  note right of CANCELLED
    Estat previst; RegistroAnulacion
    encara no està implementat.
  end note
```

Una factura emesa no torna a esborrany ni s'edita. `HISTORICAL` és el valor utilitzat pel circuit d'importació, encara que el diccionari documental original necessiti actualitzar-se perquè el reculli explícitament.

## 6. Estat de cobrament

```mermaid
stateDiagram-v2
  [*] --> PENDING: factura sense cobrament
  PENDING --> PARTIAL: cobrament inferior al total
  PENDING --> PAID: cobrament total
  PARTIAL --> PARTIAL: nova fracció insuficient
  PARTIAL --> PAID: suma igual al total
  PAID --> OVERPAID: cobrament superior
  PAID --> PARTIALLY_REFUNDED: devolució parcial
  OVERPAID --> PARTIALLY_REFUNDED: retorn parcial
  PARTIALLY_REFUNDED --> REFUNDED: devolució total
  PAID --> REFUNDED: devolució total
```

`PaymentStatusCalculator` deriva l'estat dels càrrecs i devolucions assignats. Una compensació entra com a moviment econòmic i també participa en el saldo aplicat a la factura.

## 7. Cua fiscal AEAT `[BASE/DISSENY]`

```mermaid
stateDiagram-v2
  [*] --> PENDING: emissió crea entrada
  PENDING --> PROCESSING: worker reclama
  PROCESSING --> SENT: enviament correcte
  PROCESSING --> RETRY: error temporal
  RETRY --> PROCESSING: AVAILABLE_AT
  PROCESSING --> FAILED: error definitiu
  RETRY --> FAILED: intents exhaurits
```

La taula i l'entrada `PENDING` existeixen. El worker/client AEAT, el certificat, l'XML/XSD i les transicions efectives encara no estan implementats al codi revisat.

## 8. Intenció, notificació i cua Redsys `[ASYNC]`

```mermaid
stateDiagram-v2
  state Intent {
    [*] --> PENDING
    PENDING --> PENDING: espera callback
  }

  state Notification {
    [*] --> RECEIVED
    RECEIVED --> VALIDATED: autoritzat i coherent
    RECEIVED --> ERROR: denegat o invàlid
    VALIDATED --> DUPLICATE: repetició equivalent
  }

  state CallbackJob {
    [*] --> QUEUED: callback autoritzat
    QUEUED --> PROCESSING: claimNext
    PROCESSING --> PROCESSED: factura/pagament resolts
    PROCESSING --> RETRY: error tècnic
    RETRY --> PROCESSING: nou intent
    PROCESSING --> INCIDENT: error funcional
    RETRY --> INCIDENT: cinquè intent fallit
    PROCESSING --> RETRY: lock caducat
  }
```

Un callback denegat conserva notificació però no crea job. Un duplicat contradictori obre incidència. El resultat `PROCESSED` conserva els UUIDs de factura i pagament.

## 9. Moviments i assignacions

```mermaid
flowchart LR
  Charge[CHARGE]
  Refund[REFUND]
  Compensation[COMPENSATION]
  Tx[payment_transaction]
  Allocation[payment_allocation]
  Invoice[factura]

  Charge --> Tx
  Refund --> Tx
  Compensation --> Tx
  Tx --> Allocation
  Allocation -- INVOICE_PAYMENT --> Invoice
  Allocation -- PARTIAL_PAYMENT --> Invoice
  Allocation -- CLAIM_PAYMENT --> Invoice
  Allocation -- REFUND --> Invoice
  Allocation -- CREDIT_COMPENSATION --> Invoice
```

Els valors `CLAIM_PAYMENT` i `CREDIT_COMPENSATION` apareixen als circuits implementats i han de mantenir-se sincronitzats amb el diccionari de camps, que encara mostra una llista inicial més curta.

## 10. Claus d'idempotència

| Àmbit | Clau única o patró | Efecte evitat |
| --- | --- | --- |
| Factura | `factura.IDEMPOTENCY_KEY` | Factura o número duplicats. |
| Pagament | `payment_transaction.IDEMPOTENCY_KEY` | Moviment i assignació duplicats. |
| Cua AEAT | `fiscal_queue.IDEMPOTENCY_KEY` | Doble remissió planificada. |
| Notificació Redsys | `redsys_notifications.DS_ORDER` | Reprocessament del callback. |
| Intenció Redsys | `redsys_payment_intent.DS_ORDER` | Context previ ambigu. |
| Job Redsys | `NOTIFICATION_ID UNIQUE` | Dos jobs per notificació. |
| Registre fiscal | `factura_registres.FISCAL_ORDER` | Fork de la cadena global. |
| Rectificació | parella rectificativa/original única | Vincle duplicat. |

## 11. Cobertura de les setze taules

| Grup | Taules | Estat |
| --- | --- | --- |
| Facturació | `factura`, `factura_linia`, `factura_registres`, `factura_rectificacio` | Base implementada; anul·lació/subsanació pendents. |
| Ordre fiscal | `fiscal_sequence`, `fiscal_chain_state`, `fiscal_queue` | Escriptura base implementada; consumidor AEAT pendent. |
| Economia | `payment_transaction`, `payment_allocation`, `credit_balance` | Serveis base implementats. |
| Relacions/documents/errors | `fact_rels`, `factura_documents`, `errors_verifactu` | Repositoris base; panell/workers pendents. |
| Redsys | `redsys_notifications`, `redsys_payment_intent`, `redsys_callback_queue` | Circuit complet a la branca asíncrona. |

## 12. Dades llegades descobertes a les còpies actuals

Aquest model és inferit de consultes i escriptures PHP, no d'un `SHOW CREATE TABLE`. Només mostra els camps necessaris per entendre la migració de pagament i factura.

```mermaid
erDiagram
  INSCRIPCIONS {
    int ID
    string IDPAG
    decimal A_PAGAR
    decimal PAGAMENT
    string TIPUS_INSC
    string FACTURA_RELACIONADA
    string ENTITAT
    string TIPUS_DESC
    string VALID_DESC
  }

  FACTURES {
    string factura_relacionada
    int ANY
    int ORDRE
    string NUM
    date DATA
    date data_pagament
    string RAO
    string CIF
    decimal IMPORT
    string FORMA_PAGAMENT
    string E_FACT
  }

  CURS {
    string ID_CURS
    int ANY
    string MES
    string CURS
    string NOM_CURS
    decimal HORES
  }

  REGAL {
    string CODI_REGAL
  }

  DESCOMPTES {
    string TIPUS_DESC
  }

  INSCRIPCIONS }o--o{ FACTURES : referencia_legacy
  CURS ||--o{ INSCRIPCIONS : origina
  REGAL ||--o{ INSCRIPCIONS : pot_originar
  DESCOMPTES ||--o{ INSCRIPCIONS : condiciona
```

La relació `INSCRIPCIONS`–`FACTURES` no s'ha de traslladar com una clau fiscal fiable sense conciliació. `IDPAG`, `FACTURA_RELACIONADA`, `ANY`, `ORDRE` i `NUM` són identificadors o resums llegats; el SIF ha de generar i conservar els seus UUID, idempotency keys, número visible i cadena fiscal.

### 12.1. Traducció cap al SIF

| Dada llegada | Destí o tractament SIF | Regla |
| --- | --- | --- |
| `inscripcions.IDPAG` | `fact_rels` / `ORIGIN_REF` / context de la intenció | Correlació, mai única autoritat fiscal. |
| `A_PAGAR`, `PAGAMENT` | Snapshot comercial i conciliació | El saldo SIF es deriva dels moviments persistits. |
| `FACTURA_RELACIONADA` | Relació llegada auditada | No substitueix l'UUID de factura. |
| `RAO`, `CIF`, `ADRECA`, `CP`, `POBLACIO` | Snapshot del receptor | Es congela en emetre; no es rellegeix per reescriure una factura emesa. |
| `CONCEPTE1`, `CONCEPTE2`, `IMPORT`, `CURS`, `HORES` | `factura_linia` | Construcció específica per curs, pack, grup, regal o USOC. |
| `data_pagament`, `FORMA_PAGAMENT` | `payment_transaction` i `payment_allocation` | Moviment separat de la factura. |
| `ANY`, `ORDRE`, `NUM` | Migració/relació o número visible validat | La nova numeració es reserva només al SIF. |
| `E_FACT` | Preferència/estat d'e-factura | Canvi auditat; no altera l'empremta d'una factura emesa. |

### 12.2. Dades de col·laboradors separades

```mermaid
erDiagram
  PERSONAL ||--o{ COBRAMENTS : presenta
  CURSOS ||--o{ COBRAMENTS : correspon
  DATES_COBRAMENTS ||--o{ COBRAMENTS : tarifa
  BESTRETES ||--o{ COBRAMENTS : compensa

  COBRAMENTS {
    string DNI_TUTOR
    string ROL
    int ANY
    string CURS
    string MES
    decimal IMPORT
    decimal IRPF
    decimal APAGAR
    string GESTIONAT
  }
```

Aquest segon grup representa honoraris i factures/rebuts rebuts de tutors. No s'ha de barrejar amb `payment_transaction` de cobraments a clients ni amb les factures emeses pel SIF.

## 13. Model registral ampliat que falta `[DISSENY]`

Les setze taules inventariades cobreixen el nucli fiscal/econòmic i la branca Redsys, però no totes les evidències necessàries per gestionar el sistema complet. Aquest diagrama és un model conceptual; els noms finals s'han de concretar en una migració revisada.

```mermaid
erDiagram
  OPERATIONAL_EVENT ||--o| COURSE_CHANGE_EVENT : especialitza
  OPERATIONAL_EVENT ||--o| ENROLLMENT_CANCELLATION_EVENT : especialitza
  OPERATIONAL_EVENT ||--o{ SIF_AUDIT_EVENT : deixa_traca
  BILLING_PROFILE_HISTORY ||--o{ OPERATIONAL_EVENT : contextualitza

  FACTURA ||--o{ FACTURA_REGISTRES : te
  FACTURA_REGISTRES ||--o{ AEAT_SUBMISSION_ATTEMPT : genera
  PAYMENT_TRANSACTION ||--o{ PAYMENT_ACTION_EVENT : deixa_traca
  PAYMENT_ALLOCATION ||--o{ PAYMENT_ACTION_EVENT : pot_afectar
  FACTURA ||--o{ DOCUMENT_JOB : necessita
  DOCUMENT_JOB ||--o| FACTURA_DOCUMENTS : produeix
  FACTURA_DOCUMENTS ||--o{ FISCAL_DOCUMENT_ACCESS : rep

  OPERATIONAL_EVENT ||--o{ NOTIFICATION_OUTBOX : comunica
  NOTIFICATION_OUTBOX ||--o{ NOTIFICATION_DELIVERY_ATTEMPT : intenta

  ERRORS_VERIFACTU ||--o{ SIF_INCIDENT_ACTION : historial
  RECONCILIATION_RUN ||--o{ RECONCILIATION_ITEM : detecta
  RECONCILIATION_ITEM }o--o| ERRORS_VERIFACTU : pot_obrir

  SIF_VERSION ||--o{ SIF_DECLARATION : documenta
  SIF_VERSION ||--o{ BACKUP_RESTORE_EVIDENCE : valida
  SIF_VERSION ||--o{ FISCAL_EXPORT : produeix

  OPERATIONAL_EVENT {
    string UUID_EVENT
    string TYPE
    string SOURCE_TYPE
    string SOURCE_ID
    string ACTOR
    string REASON_CODE
    json BEFORE_JSON
    json AFTER_JSON
    string STATUS
  }
  BILLING_PROFILE_HISTORY {
    string SUBJECT_TYPE
    string SUBJECT_ID
    json PROFILE_JSON
    datetime VALID_FROM
    string CHANGED_BY
  }
  AEAT_SUBMISSION_ATTEMPT {
    string UUID_ATTEMPT
    bigint FISCAL_RECORD_ID
    string REQUEST_HASH
    string RESULT
    string AEAT_CSV
    string ERROR_CODE
    datetime SENT_AT
  }
  PAYMENT_ACTION_EVENT {
    string UUID_EVENT
    string UUID_PAYMENT
    string PAYMENT_IDEMPOTENCY_KEY
    string CORRELATION_ID
    string CAUSATION_ID
    string ACTION
    string RESULT
    string SOURCE_ENVIRONMENT
    string SOURCE_CHANNEL
    string ACTOR_TYPE
    string ACTOR_ID
    string ACTOR_ROLE
    string REQUEST_ID
    string REASON_CODE
    string BEFORE_HASH
    string AFTER_HASH
    json CHANGESET_JSON
    string ERROR_CODE
    datetime OCCURRED_AT
    datetime RECORDED_AT
  }
  DOCUMENT_JOB {
    string UUID_JOB
    string UUID_FACTURA
    string DOCUMENT_TYPE
    string GENERATOR_VERSION
    string STATUS
    int ATTEMPTS
  }
  NOTIFICATION_OUTBOX {
    string UUID_MESSAGE
    string EVENT_ID
    string TEMPLATE_CODE
    string TEMPLATE_VERSION
    string RECIPIENT
    string STATUS
    string IDEMPOTENCY_KEY
  }
  FISCAL_DOCUMENT_ACCESS {
    string UUID_ACCESS
    bigint DOCUMENT_ID
    string ACTOR_OR_TOKEN
    string ACTION
    string RESULT
    datetime ACCESSED_AT
  }
  SIF_INCIDENT_ACTION {
    string UUID_ACTION
    bigint INCIDENT_ID
    string ACTOR
    string ACTION
    string OLD_STATUS
    string NEW_STATUS
    datetime CREATED_AT
  }
  SIF_VERSION {
    string VERSION_CODE
    string STATUS
    string ARTIFACT_HASH
    datetime ACTIVATED_AT
  }
  FISCAL_EXPORT {
    string UUID_EXPORT
    string REQUESTED_BY
    json CRITERIA_JSON
    string FILE_HASH
    string STATUS
  }
  BACKUP_RESTORE_EVIDENCE {
    string UUID_EVIDENCE
    string TYPE
    string SCOPE
    string ARTIFACT_HASH
    string RESULT
    datetime EXECUTED_AT
  }
```

### 13.1. Ampliacions imprescindibles de taules existents

Abans d'aquestes ampliacions cal afegir `payment_action_event` com a ledger append-only. Ha de permetre correlacionar intents previs a la creació, consultes, canvis, denegacions, errors i resultats de tots els entorns. Les mutacions confirmades han de conservar l'event terminal en la mateixa transacció que el moviment o assignació.

| Taula actual | Mancança detectada | Ampliació mínima a validar |
| --- | --- | --- |
| `factura` | L'esquema base no conserva tots els camps tipificats al diccionari normatiu amb la mateixa granularitat. | Emissor, descripció d'operació, règims/causes, SIF/productor, timestamps i versionat necessaris per reconstruir el registre. |
| `factura_registres` | Està orientada a `ALTA`; no hi ha circuit executable d'anul·lació/subsanació. | Tipus i indicadors registrals, referència al registre anterior/corregit, dades `RechazoPrevio`/`SinRegistroPrevio`, XML i estat per registre. |
| `fiscal_queue` | Conserva payload i últim error, però no tots els intents/respostes. | Proper retry, primer/últim enviament, lock owner, request hash, dead-letter i relació amb `aeat_submission_attempt`. |
| `factura_documents` | Registre base curt. | Job, versió del generador, storage ref no públic, estat controlat, QR data/hash, metadades d'enviament i accessos. |
| `errors_verifactu` | Només conté factura, tipus, estat i details. | Prioritat, origen, objecte genèric, error, responsable, resolució i historial d'accions. |
| `fact_rels` | Aporta origen i visibilitat bàsica. | Política/subjecte de visibilitat, relació amb event operatiu i justificació d'excepcions. |
| `redsys_notifications` | Conserva recepció normalitzada. | Relació explícita amb intenció/job/resultat quan s'integri la branca asíncrona. |

La migració 000004 materialitza les ampliacions de `factura`,
`factura_linia`, `factura_registres`, `fiscal_queue` i `factura_documents`.
Continuaran sent pendents fins que s'apliquin, els writers les omplin i es
validin amb dades de preproducció. `errors_verifactu`, `fact_rels` i la relació
completa de notificacions Redsys mantenen les ampliacions en taules de control
o en disseny.

### 13.2. Invariants del ledger de pagaments

- no hi ha petició externa sobre pagament sense `CORRELATION_ID` i `payment_action_event`;
- `UUID_PAYMENT` pot ser nul en `REQUESTED`, `REJECTED` o `FAILED` previs a la creació, però la clau idempotent ha de permetre correlació;
- tota mutació confirmada té un event terminal `SUCCEEDED`, `REUSED` o `NO_CHANGE` dins el mateix commit;
- una consulta o exportació només retorna dades després de persistir l'event d'accés;
- un `REQUESTED` sense event terminal dins el temps previst genera incidència;
- no hi ha `UPDATE` o `DELETE` funcional sobre `payment_action_event`;
- no es guarden secrets ni dades completes de targeta dins `CHANGESET_JSON`;
- correccions d'assignació generen nous registres i events, no reescriptura silenciosa.

## 14. Estats de correcció i incidència que també formen part del model

```mermaid
stateDiagram-v2
  [*] --> PROPOSED
  PROPOSED --> CLASSIFIED
  CLASSIFIED --> RECTIFICATION: canvi econòmic o fiscal
  CLASSIFIED --> COMPLEMENTARY: increment facturable
  CLASSIFIED --> CANCELLATION_RECORD: registre improcedent
  CLASSIFIED --> CORRECTION_RECORD: error registral subsanable
  CLASSIFIED --> NO_FISCAL_ACTION: només event operatiu
  CLASSIFIED --> BLOCKED: ambigüitat o permís insuficient
  RECTIFICATION --> QUEUED
  COMPLEMENTARY --> QUEUED
  CANCELLATION_RECORD --> QUEUED
  CORRECTION_RECORD --> QUEUED
  NO_FISCAL_ACTION --> AUDITED
  QUEUED --> AUDITED
  BLOCKED --> [*]
  AUDITED --> [*]
```

```mermaid
stateDiagram-v2
  [*] --> OPEN
  OPEN --> ACKNOWLEDGED
  ACKNOWLEDGED --> IN_PROGRESS
  IN_PROGRESS --> RESOLVED
  IN_PROGRESS --> OPEN: reoberta
  OPEN --> DISMISSED: fals positiu justificat
  ACKNOWLEDGED --> DISMISSED: no aplicable justificat
  RESOLVED --> [*]
  DISMISSED --> [*]
```

La definició funcional, l'origen i el criteri de completitud d'aquests registres és el document 38.

## 15. Esquema físic incorporat per la migració 000003

El diagrama següent mostra les relacions principals de la migració additiva. Les
taules de log poden existir abans que l'objecte de negoci; per això algunes
relacions amb pagament o document són opcionals.

```mermaid
erDiagram
  PAYMENT_TRANSACTION ||--o{ PAYMENT_ACTION_EVENT : "pot relacionar"
  FACTURA ||--o{ OPERATIONAL_EVENT : "context fiscal"
  PAYMENT_TRANSACTION ||--o{ OPERATIONAL_EVENT : "context economic"
  OPERATIONAL_EVENT ||--o| COURSE_CHANGE_EVENT : "especialitza"
  OPERATIONAL_EVENT ||--o| ENROLLMENT_CANCELLATION_EVENT : "especialitza"
  FACTURA_REGISTRES ||--|| FACTURA_REGISTRE_CONTROL : "amplia"
  FACTURA_REGISTRES ||--o{ AEAT_SUBMISSION_ATTEMPT : "genera intents"
  FISCAL_QUEUE ||--o{ AEAT_SUBMISSION_ATTEMPT : "programa"
  FACTURA ||--o{ DOCUMENT_JOB : "demana"
  FACTURA_DOCUMENTS ||--o| DOCUMENT_JOB : "resultat"
  FACTURA_DOCUMENTS ||--o{ FISCAL_DOCUMENT_ACCESS : "acces"
  FACTURA ||--o{ NOTIFICATION_OUTBOX : "pot notificar"
  PAYMENT_TRANSACTION ||--o{ NOTIFICATION_OUTBOX : "pot notificar"
  NOTIFICATION_OUTBOX ||--o{ NOTIFICATION_DELIVERY_ATTEMPT : "intenta"
  ERRORS_VERIFACTU ||--o{ SIF_INCIDENT_ACTION : "historial"
  SIF_VERSION ||--o{ SIF_DECLARATION : "documenta"
  FISCAL_EXPORT ||--o{ FISCAL_EXPORT_ACCESS : "registra acces"
  RECONCILIATION_RUN ||--o{ RECONCILIATION_ITEM : "conte"
  FACT_RELS ||--|| FACT_RELS_CONTEXT : "justifica"
  OPERATIONAL_EVENT ||--o{ FACT_RELS_CONTEXT : "origina"
```

### 15.1. Diferència entre disseny conceptual i disponibilitat

| Capa | Estat després d'aquesta revisió |
| --- | --- |
| Disseny de responsabilitats | Documentat per a les 21 taules de la migració. |
| SQL i índexs | Implementats en una migració forward-only/idempotent per `CREATE TABLE IF NOT EXISTS`. |
| Repositoris append-only bàsics | Implementats per `payment_action_event` i `operational_event`. |
| Integració amb tots els casos/canals | Pendent i bloquejant. |
| Permisos reals de preproducció/producció | Plantilla disponible; execució i evidència pendents. |
| Certificació de compliment | No assolida; requereix implementació, proves i aprovació. |

## 16. Esquema físic d'operació comercial incorporat per la migració 000004

```mermaid
erDiagram
  COMMERCIAL_OPERATION ||--|{ COMMERCIAL_OPERATION_PARTY : "te parts"
  COMMERCIAL_OPERATION ||--o{ DISCOUNT_VALIDATION : "valida descomptes"
  COMMERCIAL_OPERATION ||--o{ PAYMENT_LINK : "ofereix enllacos"
  COMMERCIAL_OPERATION o|--o| REDSYS_PAYMENT_INTENT : "pot crear intencio"
  COMMERCIAL_OPERATION o|--o| FACTURA : "pot originar factura"
  COMMERCIAL_OPERATION o|--o| PAYMENT_TRANSACTION : "pot relacionar cobrament"
  PAYMENT_LINK o|--o| PAYMENT_LINK : "substitueix"

  COMMERCIAL_OPERATION {
    string UUID_OPERATION PK
    string IDEMPOTENCY_KEY UK
    string OPERATION_TYPE
    string CLASSIFICATION
    string CLASSIFICATION_REASON
    string STATUS
    decimal GROSS_AMOUNT
    decimal DISCOUNT_AMOUNT
    decimal NET_AMOUNT
    json PRICE_SNAPSHOT_JSON
    json TAX_SNAPSHOT_JSON
    datetime EXPIRES_AT
  }
  COMMERCIAL_OPERATION_PARTY {
    bigint ID PK
    string UUID_OPERATION FK
    string PARTY_KEY
    string PARTY_ROLE
    string NIF_CIF
    string PRODUCT_CODE
    string PRODUCT_EDITION
    decimal LINE_AMOUNT
    json SNAPSHOT_JSON
  }
  DISCOUNT_VALIDATION {
    string UUID_VALIDATION PK
    string UUID_OPERATION FK
    string DISCOUNT_TYPE
    string STATUS
    string RULE_VERSION
    string EVIDENCE_HASH
    decimal RESULT_DISCOUNT_AMOUNT
    string FUTURE_ENTITLEMENT_REF
  }
  PAYMENT_LINK {
    string UUID_PAYMENT_LINK PK
    string UUID_OPERATION FK
    string TOKEN_HASH UK
    string STATUS
    decimal EXPECTED_AMOUNT
    datetime EXPIRES_AT
    string REPLACED_BY_UUID FK
  }
```

```mermaid
stateDiagram-v2
  [*] --> DRAFT
  DRAFT --> RESERVED: reserva valida
  RESERVED --> PENDING_VALIDATION: dades o evidencia pendent
  PENDING_VALIDATION --> RESERVED: validacio correcta
  RESERVED --> READY_FOR_PAYMENT: BILLABLE i snapshot congelat
  RESERVED --> COMPLETED: FREE_SAMPLE o NON_BILLABLE aprovat
  RESERVED --> INCIDENT: classificacio ambigua
  READY_FOR_PAYMENT --> PAYMENT_PENDING: enllac o intencio creada
  PAYMENT_PENDING --> PAID: cobrament confirmat
  PAID --> INVOICED: factura emesa o reutilitzada
  INVOICED --> COMPLETED
  DRAFT --> EXPIRED
  RESERVED --> EXPIRED
  DRAFT --> CANCELLED
  RESERVED --> CANCELLED
  INCIDENT --> PENDING_VALIDATION: revisio
  COMPLETED --> [*]
  EXPIRED --> [*]
  CANCELLED --> [*]
```

`FREE_SAMPLE` i `SUBSIDISED_PENDING_DECISION` són classificacions, no estats
econòmics. La primera pot acabar en `COMPLETED` sense factura ni pagament; la
segona no pot avançar a `READY_FOR_PAYMENT` fins que negoci/fiscalitat decideixi
finançador, receptor i document aplicable.
