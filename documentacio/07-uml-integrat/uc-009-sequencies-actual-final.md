# UC-009 · Diagrames de seqüència ACTUAL / FINAL per superfície

**Revalidació:** 2026-10-03  
**Tall auditat:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`

## 1. SQ09-01 · Obrir panell i carregar resum/cua

### ACTUAL

```mermaid
sequenceDiagram
autonumber
actor U as Usuari intranet
participant P as sif-registres-aeat.php
participant JS as sif-registres-aeat.js
participant B as ajax/sif/sifAeat.php
participant C as SifInternalAeatClient
participant API as /api/aeat/operations.php
participant Auth as InternalApiAuthenticator
participant R as AeatOperationsReadRepository
participant DB as BD SIF

U->>P: GET /sif-registres-aeat.php
P->>P: comprovarSessio + crear CSRF si falta
P-->>U: HTML + JS
JS->>B: POST {action: summary}
B->>B: validar sessió
B->>C: request(actor,roles,payload)
C->>C: request id + body hash + HMAC
C->>API: POST HTTPS signat
API->>Auth: authenticate()
Auth->>DB: reclamar request id / anti-replay
API->>R: summary()
R->>DB: mètriques cua + intents + incidències
R-->>API: projecció segura
API-->>C: JSON
C-->>B: JSON
B-->>JS: JSON
par càrrega inicial
  JS->>B: POST {action: list, limit:100}
  B->>C: petició signada
  C->>API: list
  API->>R: listQueue()
  R->>DB: SELECT projecció cua
  DB-->>R: files
  R-->>JS: via API/bridge
end
```

### FINAL

Mateixa seqüència, amb el menú i els rols de preproducció verificats. La pàgina no ha de rebre secrets HMAC, `PAYLOAD_JSON` ni `XML_PAYLOAD`.

## 2. SQ09-02 · Filtrar cua i obrir detall

### ACTUAL / FINAL

```mermaid
sequenceDiagram
autonumber
actor U as Usuari
participant JS as Panell JS
participant API as API AEAT
participant R as AeatOperationsReadRepository
participant DB as BD SIF

U->>JS: seleccionar STATUS
JS->>API: list(status) via bridge HMAC
API->>R: listQueue(status,100)
R->>DB: SELECT cua + factura
DB-->>R: projecció sense payload fiscal
R-->>JS: files
U->>JS: Veure queue_id
JS->>API: detail(queue_id) via bridge HMAC
API->>R: detail(queue_id)
R->>DB: cua + registre + attempts + incidències
DB-->>R: projecció
R-->>JS: detall
JS-->>U: estat cua != estat AEAT + intents + incidències
```

**Invariant:** `fiscal_queue.STATUS=SENT` no s'ha de presentar com `factura_registres.ESTAT_AEAT=ACCEPTED`.

## 3. SQ09-03 · Preflight des del panell

### ACTUAL

```mermaid
sequenceDiagram
autonumber
actor U as Usuari
participant JS as Panell JS
participant API as API AEAT
participant PF as AeatPreflight

U->>JS: Preflight
JS->>API: {action: preflight} via bridge HMAC
API->>PF: check(config aeat)
PF->>PF: extensions + endpoint + schemas + certificat + evidence store
PF-->>API: ready + checks booleans
API-->>JS: JSON sense secrets
JS-->>U: Preparat / No preparat + checks
```

### FINAL

Afegir evidència d'entorn al procés de release; un preflight `ready=true` continua sense equivaldre a una acceptació real AEAT.

## 4. SQ09-04 · Claim i remissió del worker

### ACTUAL

```mermaid
sequenceDiagram
autonumber
participant CLI as run-aeat-worker.php
participant W as SerialWorker
participant Q as FiscalQueueRepository
participant P as FiscalQueueProcessor
participant A as AeatSubmissionAttemptRepository
participant T as FlowControlledTransport/SoapTransport
participant X as AEAT proves
participant DB as BD SIF

CLI->>CLI: exigir CLI + preproduction + --send-test
CLI->>W: runOnce()
W->>DB: GET_LOCK emissor
W->>DB: llegir head de fiscal_queue
W->>P: processNext()
P->>Q: claimNext()
Q->>DB: FOR UPDATE -> PROCESSING + ATTEMPTS + CLAIM_TOKEN
P->>Q: assertImmutablePayload()
Q->>DB: comparar snapshot/registre immutable
P->>A: begin()
A->>DB: INSERT aeat_submission_attempt STARTED
P->>T: send(payload)
T->>X: SOAP/mTLS
X-->>T: resposta
T-->>P: status + response + request_xml
P->>A: complete()
A->>DB: intent terminal
P->>Q: complete()
Q->>DB: SENT + registre/factura ESTAT_AEAT
W->>DB: RELEASE_LOCK
```

### FINAL

La seqüència es manté. Per producció cal una política explícita d'entorn/release; no s'ha d'eliminar el bloqueig actual canviant només la URL.

## 5. SQ09-05 · Error tècnic retryable

### ACTUAL / FINAL

```mermaid
sequenceDiagram
participant P as FiscalQueueProcessor
participant A as AttemptRepository
participant Q as QueueRepository
participant I as IncidentRepository
participant DB as BD

P->>A: attempt STARTED
P-xP: error sense resultat remot acreditat
P->>A: FAILED
P->>Q: fail()
alt ATTEMPTS < max
 Q->>DB: RETRY + NEXT_RETRY_AT
else intents esgotats
 Q->>DB: DEAD_LETTER + ESTAT_AEAT=ERROR
 P->>I: AEAT_DEAD_LETTER
end
```

## 6. SQ09-06 · Resultat remot incert

### ACTUAL / FINAL

```mermaid
sequenceDiagram
participant P as FiscalQueueProcessor
participant A as AttemptRepository
participant Q as QueueRepository
participant I as IncidentRepository
participant DB as BD

P-xP: AeatDeliveryUncertainException / resultat no consolidable
P->>A: UNCERTAIN si correspon
P->>Q: holdForReview()
Q->>DB: STATUS=REVIEW, NEXT_RETRY_AT=NULL
P->>I: incidència AEAT específica
Note over P,DB: no hi ha retry automàtic
```

## 7. SQ09-07 · Conciliar REVIEW des del panell

### ACTUAL

```mermaid
sequenceDiagram
autonumber
actor U as Responsable autoritzat
participant JS as Panell JS
participant B as Bridge intranet
participant API as API AEAT
participant S as AeatReviewReconciliationService
participant Q as FiscalQueueRepository
participant DB as BD SIF

U->>JS: Conciliar sense reenviar
JS->>U: confirmació explícita
U->>JS: confirmar
JS->>B: POST reconcile + queue_id + attempt_uuid + CSRF
B->>B: hash_equals(CSRF)
B->>API: HMAC server-to-server
API->>API: rol de reconcile
API->>S: reconcile(queueId,attemptUuid,actor)
S->>Q: reviewForUpdate()
Q->>DB: SELECT REVIEW FOR UPDATE
S->>DB: carregar attempt del mateix queue
S->>S: latest attempt + estat terminal + REQUEST_HASH
S->>S: regenerar XML des snapshot immutable
S->>Q: reconcileReview()
Q->>DB: REVIEW -> SENT + resultat original
S->>DB: resoldre incidència + operational_event
S-->>JS: reconciled_without_resend=true
JS-->>U: resultat actualitzat
```

### FINAL

Mateixa seqüència. Si l'intent és `UNCERTAIN`, antic o amb hash incompatible, es manté `REVIEW`; la UI no ha de disposar d'una acció que faci un segon SOAP.

## 8. SQ09-08 · Deep-link des d'incidències

### ACTUAL / FINAL

```mermaid
sequenceDiagram
actor U as Gestor
participant Inc as Panell incidències
participant P as sif-registres-aeat.php
participant JS as sif-registres-aeat.js

U->>Inc: obrir reparació FISCAL_QUEUE
Inc-->>P: /sif-registres-aeat.php?queue_id=N
P-->>JS: shell autoritzat
JS->>JS: llegir queue_id numèric
JS->>JS: loadDetail(N)
Note over JS: no executa reconcile automàticament
```

## 9. Matriu de verificació

| Seqüència | Codi localitzat | Prova existent abans | Cobertura afegida 03/10 |
| --- | --- | --- | --- |
| SQ09-01 | sí | repositori/API/auth genèrics | contracte UI intranet |
| SQ09-02 | sí | `AeatOperationsReadRepositoryTest` | contracte UI |
| SQ09-03 | sí | `AeatPreflightTest`, worker preflight | contracte UI |
| SQ09-04 | sí | `AeatWorkflowTest`, `FiscalQueueProcessorTest` | CI path/lint panell |
| SQ09-05 | sí | sí | — |
| SQ09-06 | sí | sí | — |
| SQ09-07 | sí | `AeatReviewReconciliationServiceTest` | UUID estricte + contracte UI |
| SQ09-08 | sí | `IncidentPanelUiContractTest` | contracte UI UC-009 |

## 10. Estat

- **Documentat:** sí, seqüències ACTUAL/FINAL separades.
- **Implementat:** totes les seqüències ACTUAL, excepte desplegament/menú real d'entorn.
- **Verificat:** proves UC-009 passen al run CI del 02/10; nova cobertura queda pendent del CI de la branca.
- **Pendent:** prova preproducció real, certificat/configuració, rols i menú desplegats, enviament AEAT de proves acreditat.
