# UC-008 — Diagrames de seqüència ACTUAL i FINAL

**Data:** 29/09/2026  
**Regla:** ACTUAL descriu el backend executable integrat a `main`; FINAL incorpora les superfícies UI encara pendents.

Vegeu [classes](uc-008-classes-actual-final.md), [activitats](uc-008-activitats-pagines-incidencies-actual-final.md) i [auditoria](04-auditoria-detallada-uc-008-gestionar-incidencia-2026-09-29.md).

## 1. SEQ-008-ACTUAL-A · Redsys → INCIDENT atòmic

```mermaid
sequenceDiagram
autonumber
participant W as RedsysCallbackWorker
participant Q as RedsysCallbackQueueRepository
participant I as IncidentRepository
participant V as PayloadIdempotencyValidator
participant DB as MySQL SIF

W->>W: processor->process(job)
alt conflicte funcional o maxAttempts
  W->>DB: BEGIN
  W->>Q: markIncident(job)
  Q->>DB: UPDATE STATUS=INCIDENT
  W->>I: openDetailed(job, REDSYS_CALLBACK, key)
  I->>V: calculateHash(payload)
  I->>DB: SELECT IDEMPOTENCY_KEY
  alt ja existeix
    I->>V: assertMatches(payload, storedHash)
    I-->>W: reused + incident_id
  else nou
    I->>DB: INSERT errors_verifactu OPEN
    I-->>W: incident_id + uuid_incident
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
  alt transport correcte
    P->>Q: complete(response)
  else error i queden intents
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

## 5. SEQ-008-FINAL-A · Panell llistat i detall

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as pay.prisma.cat/sif/incidencies
participant C as IncidentInternalApiClient [PENDENT]
participant API as API incidents
participant S as IncidentLifecycleService

O->>UI: obrir incidències
UI->>C: list(filters)
C->>API: POST signat action=list
API->>S: list(actor,filters)
S-->>API: incidents
API-->>C: JSON
C-->>UI: files
O->>UI: obrir expedient
UI->>C: view(id)
C->>API: POST signat action=view
API->>S: view(actor,id)
S-->>UI: capçalera + timeline
```

## 6. SEQ-008-FINAL-B · Reparació explícita i tancament

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

## 7. SEQ-008-FINAL-C · Resum intranet read-only

```mermaid
sequenceDiagram
autonumber
actor U as Usuari intranet
participant IN as Intranet VERI*FACTU
participant C as SummaryClient [PENDENT]
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

## 8. Proves associades

- conflicte funcional Redsys → incidència sense retry;
- cinquè error Redsys → incidència;
- divergència fiscal → no enviar a AEAT + incidència;
- dead-letter final → incidència;
- mateixa key + mateix payload → reuse;
- mateixa key + payload divergent → 409;
- retry assign/resolve després del canvi d'estat → reuse, no duplicat;
- rol read-only → consulta sí, mutació no.
