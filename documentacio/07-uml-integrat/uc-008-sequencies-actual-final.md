# UC-008 — Diagrames de seqüència ACTUAL i FINAL

**Data:** 30/09/2026  
**Revalidació:** 03/10/2026 contra `main` `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`; no s'ha detectat divergència del nucli UC-008. Vegeu [inventari PHP/JS ACTUAL/FINAL](uc-008-inventari-codi-php-js-actual-final-2026-10-03.md) i [revalidació de `main`](uc-008-revalidacio-main-2026-10-03.md).  
**Regla:** ACTUAL descriu backend i UI executable al repositori; FINAL conserva els passos operatius que encara depenen de desplegament/configuració.

Vegeu [classes](uc-008-classes-actual-final.md), [activitats](uc-008-activitats-pagines-incidencies-actual-final.md) i [auditoria vigent](04b-auditoria-detallada-uc-008-2026-09-30.md).

## 1. SEQ-008-ACTUAL-A · Redsys → INCIDENT atòmic

```mermaid
sequenceDiagram
autonumber
participant W as RedsysCallbackWorker
participant Q as RedsysCallbackQueueRepository
participant I as IncidentRepository
participant V as PayloadIdempotencyValidator
participant R as SensitiveDataRedactor
participant DB as MySQL SIF

W->>W: processor->process(job)
alt conflicte funcional o maxAttempts
  W->>R: redact(exception.message)
  R-->>W: safeMessage
  W->>DB: BEGIN
  W->>Q: markIncident(job)
  Q->>DB: UPDATE STATUS=INCIDENT
  W->>I: openDetailed(job estable per UUID_JOB, REDSYS_CALLBACK, key)
  I->>V: calculateHash(payload)
  I->>DB: SELECT IDEMPOTENCY_KEY
  alt ja existeix
    I->>V: assertMatches(payload, storedHash)
    I-->>W: reused + incident_id
  else nou
    I->>DB: INSERT errors_verifactu OPEN
    alt INSERT guanya
      I-->>W: incident_id + uuid_incident
    else duplicate concurrent 1062
      I->>DB: SELECT IDEMPOTENCY_KEY FOR UPDATE
      I->>V: assertMatches(payload, storedHash)
      I-->>W: reused + incident_id
    end
  end
  W->>DB: COMMIT
else error recuperable
  W->>Q: markRetry()
end
```

## 2. SEQ-008-ACTUAL-B · AEAT integritat i dead-letter

```mermaid
sequenceDiagram
autonumber
participant P as FiscalQueueProcessor
participant Q as FiscalQueueRepository
participant I as IncidentRepository
participant DB as MySQL SIF
participant A as Transport AEAT

P->>Q: claimNext()
P->>Q: assertImmutablePayload()
alt integritat incorrecta
  P->>DB: BEGIN
  P->>Q: rejectIntegrity()
  P->>I: openDetailed(FISCAL_PAYLOAD_CONFLICT)
  P->>DB: COMMIT
else integritat correcta
  P->>A: send(payload)
  alt resultat remot ACCEPTED / ACCEPTED_WITH_ERRORS / REJECTED
    P->>Q: complete(response) -> SENT + ESTAT_AEAT remot
  else outcome remot incert
    P->>Q: holdForReview() -> REVIEW
    P->>I: openDetailed(AEAT_DELIVERY_UNCERTAIN)
  else error local/transport i queden intents
    P->>Q: fail() -> RETRY
  else error final
    P->>DB: BEGIN
    P->>Q: fail() -> DEAD_LETTER
    P->>I: openDetailed(AEAT_DEAD_LETTER)
    P->>DB: COMMIT
  end
end
```

## 3. SEQ-008-ACTUAL-C · Acció manual autenticada

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant API as POST /api/incidents/manage.php
participant H as InternalApiAuthenticator
participant S as IncidentLifecycleService
participant I as IncidentRepository
participant A as IncidentActionRepository
participant V as PayloadIdempotencyValidator
participant DB as MySQL SIF

O->>API: body + HMAC + request-id + actor/roles
API->>H: authenticate()
H->>DB: claim anti-replay request
H-->>API: actor resolt
API->>S: assign/evidence/resolve/...
S->>S: autorització per rol
S->>DB: BEGIN
S->>I: findById(FOR UPDATE)
S->>A: append(command)
A->>V: calculateHash(command)
A->>DB: SELECT action by idempotency key
alt reintent equivalent
  A->>V: assertMatches()
  A-->>S: reused=true
else acció nova
  A->>DB: INSERT sif_incident_action
  S->>I: updateLifecycle()
end
S->>DB: COMMIT
S-->>API: resultat tipificat
API-->>O: JSON
```

## 4. SEQ-008-ACTUAL-D · Reintent després d'una transició

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant S as IncidentLifecycleService
participant A as IncidentActionRepository
participant DB as MySQL SIF

O->>S: resolve(K, payload)
S->>DB: BEGIN
S->>A: append(K,payload)
A->>DB: INSERT RESOLVE
S->>DB: UPDATE incident RESOLVED
S->>DB: COMMIT
S-->>O: reused=false

O->>S: resolve(K, mateix payload) de nou
S->>DB: BEGIN
S->>A: append(K,payload)
A->>DB: SELECT K
A-->>S: reused=true
Note over S,A: no depèn de PREVIOUS_STATUS derivat
S->>DB: COMMIT
S-->>O: reused=true, cap segona transició
```

## 5. SEQ-008-ACTUAL-E · Concurrència idempotent real

```mermaid
sequenceDiagram
autonumber
participant A as Procés A
participant B as Procés B
participant IA as IncidentRepository A
participant IB as IncidentRepository B
participant DB as MySQL REPEATABLE READ

par mateixa IDEMPOTENCY_KEY
  A->>IA: openDetailed(payload)
  B->>IB: openDetailed(payload)
end
IA->>DB: SELECT key (snapshot)
IB->>DB: SELECT key (snapshot)
par cursa INSERT
  IA->>DB: INSERT unique key
  IB->>DB: INSERT unique key
end
DB-->>IA: un INSERT guanya
DB-->>IB: 1062 duplicate després del commit guanyador
IB->>DB: SELECT key FOR UPDATE (current read)
alt fila visible en current read
  IB-->>B: reused=true, mateix incident_id
else 1062 encara propaga
  B->>B: TransactionRunner fa ROLLBACK
  B->>IB: reintenta open una sola vegada en transacció nova
  IB->>DB: SELECT key
  DB-->>IB: incidència guanyadora ja confirmada
  IB-->>B: reused=true, mateix incident_id
end
IA-->>A: reused=false, incident_id
Note over A,B: defensa en dues capes; 1 errors_verifactu + 1 acció OPEN

par dues assignacions mateix incident
  A->>DB: SELECT incident FOR UPDATE
  B->>DB: SELECT incident FOR UPDATE (espera)
end
A->>DB: append ASSIGN + UPDATE
A-->>B: allibera lock
B->>DB: llegeix estat actual + append ASSIGN + UPDATE
Note over A,B: 2 accions coherents, 1 estat final IN_PROGRESS
```

**Evidència:** `IncidentConcurrencyTest` amb dos subprocessos PHP i dues connexions PDO independents; run `36661335874`.

## 6. SEQ-008-FINAL-A · Panell llistat i detall

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as app.js / index.php
participant A as actions.php
participant PS as IncidentPanelSession
participant S as IncidentLifecycleService
participant R as IncidentRepository
participant AR as IncidentActionRepository

O->>UI: obrir panell autenticat
UI->>A: POST action=list + CSRF
A->>PS: actor() + assertCsrf()
PS-->>A: actor autenticat
A->>S: list(actor,filters,limit)
S->>R: list(filters,limit)
R-->>S: incidents
S-->>A: JSON
A-->>UI: llistat

O->>UI: obrir expedient
UI->>A: POST action=view + incident_id + CSRF
A->>PS: actor() + assertCsrf()
A->>S: view(actor,id)
S->>R: findById(id)
S->>AR: listForIncident(id)
S-->>A: capçalera + timeline
A-->>UI: detall
```

**Nota:** el panell SIF no torna a passar per l'API interna HMAC. Un cop establerta la sessió SIF mitjançant el handoff signat, `actions.php` treballa same-origin amb sessió + CSRF i invoca directament `IncidentLifecycleService`. L'API interna HMAC és la frontera servidor→servidor utilitzada, entre altres, pel resum read-only de la intranet.

## 7. SEQ-008-FINAL-B · Reparació explícita i tancament

```mermaid
sequenceDiagram
autonumber
actor R as Responsable
participant UI as Panell
participant S as IncidentLifecycleService
participant U as UC corrector específic
participant X as Font externa/BD
participant A as IncidentActionRepository

R->>UI: diagnosticar
UI->>S: addEvidence()
R->>U: executar comanda correctora correlacionada
U->>X: efecte específic idempotent
X-->>U: resultat
U-->>R: evidència
R->>S: resolve(criteria,notes,evidence)
S->>A: append RESOLVE
S-->>UI: RESOLVED
Note over S,U: UC-008 no repeteix CHARGE/factura/registre fiscal per si sol
```

## 8. SEQ-008-FINAL-C · Resum intranet read-only

```mermaid
sequenceDiagram
autonumber
actor U as Usuari intranet
participant IN as Intranet VERI*FACTU
participant C as SifInternalIncidentClient [IMPLEMENTAT CODI]
participant S as SIF incidents

U->>IN: obrir VERI*FACTU
IN->>C: summary()
C->>S: consulta autenticada read-only
alt SIF disponible
  S-->>IN: obertes/crítiques/última actualització
  IN-->>U: indicador + enllaç al panell
else SIF no disponible
  IN-->>U: indisponible + últim resum validat
end
```

## 9. Proves associades

- conflicte funcional Redsys → incidència sense retry;
- cinquè error Redsys → incidència;
- divergència fiscal → no enviar a AEAT + incidència;
- dead-letter final → incidència;
- mateixa key + mateix payload → reuse;
- mateixa key + payload divergent → 409;
- retry assign/resolve després del canvi d'estat → reuse, no duplicat;
- rol read-only → consulta sí, mutació no.
