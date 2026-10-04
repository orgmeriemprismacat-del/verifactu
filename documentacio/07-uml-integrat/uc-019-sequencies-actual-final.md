# UC-019 — Seqüències ACTUAL / FINAL

Data d'auditoria: 2026-10-03.

## ACTUAL — aprovació o denegació USOC

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as alumnes-validar-descomptes.js
participant E as sendMsgValidatCurosDescomptes.php
participant L as LegacyDiscountValidationLookup
participant C as SifInternalUsocClient
participant API as /api/usoc/manage.php
participant S as UsocValidationDecisionService
participant R as UsocValidationDecisionRepository
participant DB as sif.usoc_validation_decision
participant LEG as Intranet::sendMsgValidatCurosDescomptes

G->>UI: SÍ/NO + confirmar
UI->>E: POST idInsc, verificat, CSRF, requestId
E->>E: sessió + CSRF + permís + inputs
E->>L: isUsoc(idInsc)
alt No és USOC
  E->>LEG: aplicar flux llegat
  LEG-->>E: resultat
  E-->>UI: resultat
else És USOC
  E->>C: beginValidationDecision(actor,roles,requestId,idInsc,desired)
  C->>API: POST HMAC signat
  API->>S: begin(...)
  S->>R: begin(...)
  R->>DB: INSERT REQUESTED o reutilitzar
  DB-->>R: decisió
  R-->>S: decisió
  S-->>API: should_apply_legacy?
  API-->>C: decisió
  C-->>E: decisió
  alt REVIEW_REQUIRED
    E--xUI: 409 revisió manual
  else ja COMMITTED / no cal reaplicar
    E-->>UI: decisió ja conciliada
  else REQUESTED i cal mutar
    E->>LEG: aplicar VALID_DESC 1|2
    LEG-->>E: resultat
    E->>C: completeValidationDecision(requestId)
    C->>API: POST HMAC signat
    API->>S: complete(...)
    S->>R: markCommitted o markReviewRequired
    R->>DB: UPDATE estat + hash llegat
    DB-->>R: decisió final
    R-->>S: decisió
    S-->>API: COMMITTED/REVIEW_REQUIRED
    API-->>C: resultat
    C-->>E: resultat
    E-->>UI: èxit només si COMMITTED
  end
end
```

### Propietats verificables al codi

- La mutació llegat queda estrictament entre les fases REQUESTED i COMMITTED.
- `REQUEST_ID` és únic i un reús amb payload divergent produeix conflicte.
- Un estat llegat incompatible deriva a `REVIEW_REQUIRED`.
- El client web no coneix el secret HMAC.
- El backend de la intranet exigeix POST, CSRF, sessió i permís.

## FINAL — amb evidència explícita

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as UI validació
participant A as UsocValidationApplicationService
participant V as EvidenceGateway
participant D as DecisionRepository
participant E as EvidenceRepository
participant L as LegacyAdapter
participant O as OperationalEventRepository

G->>UI: decidir sol·licitud USOC
UI->>A: command(requestId,idInsc,decision,evidenceRef)
A->>V: verify(evidenceRef,date)
V-->>A: VERIFIED / NOT_VERIFIED / UNAVAILABLE
alt UNAVAILABLE o contradicció
  A->>D: REVIEW_REQUIRED
  A->>O: append validation_review_required
  A-->>UI: revisió manual
else decisió acreditada
  A->>E: store metadata/hash/minimum evidence
  A->>D: REQUESTED
  A->>L: apply VALID_DESC
  L-->>A: resulting legacy state
  A->>D: COMMITTED + legacy hash
  A->>O: append validation_committed
  A-->>UI: resultat tipificat
end
```

## Estat

ACTUAL implementat al repositori. FINAL és una evolució per completar la prova d'afiliació i la traça de negoci sense convertir una simple marca `TIPUS_DESC/VALID_DESC` en prova suficient.


## Invariant de concurrència afegit 2026-10-04

La classe de persistència queda reforçada amb una restricció de BD:

```mermaid
classDiagram
    class UsocValidationDecisionRepository {
      +begin(...)
      +findRequestedByInscription(idInsc, forUpdate)
      +markCommitted(...)
      +markReviewRequired(...)
    }

    class usoc_validation_decision {
      ID_INSC
      STATE
      ACTIVE_ID_INSC «generated»
      uq_usoc_validation_active_inscription «unique»
    }

    UsocValidationDecisionRepository --> usoc_validation_decision
```

`ACTIVE_ID_INSC = ID_INSC` únicament per `STATE=REQUESTED`; en estats terminals és `NULL`. Això permet conservar l'històric i, simultàniament, impedir dues decisions actives sobre la mateixa inscripció.


## Denegació USOC — flux ACTUAL reconciliat

```mermaid
sequenceDiagram
    actor G as Gestió
    participant UI as JS intranet
    participant E as Endpoint
    participant S as SIF validation
    participant L as Legacy Intranet
    participant DB as Legacy DB

    G->>UI: Denegar USOC
    UI->>UI: reutilitzar requestId persistent
    UI->>E: POST idInsc, desired=2, requestId
    E->>E: recordar requestId com USOC
    E->>S: begin(requestId, desired=2)
    S-->>E: REQUESTED / should_apply_legacy=true
    E->>L: sendMsgValidatCurosDescomptes()
    L->>DB: TIPUS_DESC=0/1, VALID_DESC=2, A_PAGAR recalculat
    L-->>E: correus enviats / OK
    E->>S: complete(requestId)
    S->>S: accepta reclassificació si desired=2 i VALID_DESC=2
    S-->>E: COMMITTED
    E-->>UI: èxit
    UI->>UI: elimina requestId persistent
```

Una aprovació continua exigint `TIPUS_DESC=4`; només la denegació admet la reclassificació llegada 4 → 0/1.
