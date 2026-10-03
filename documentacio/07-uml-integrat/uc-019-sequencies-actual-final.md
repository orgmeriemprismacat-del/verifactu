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
