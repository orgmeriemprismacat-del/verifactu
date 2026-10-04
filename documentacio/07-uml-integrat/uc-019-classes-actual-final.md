# UC-019 — Classes ACTUAL / FINAL

Data d'auditoria: 2026-10-03.

## ACTUAL — implementat

```mermaid
classDiagram
direction LR

class AlumnesValidarDescomptesPage {
  <<PHP/HTML>>
  +csrf_validar_descomptes
}
class AlumnesValidarDescomptesJs {
  <<JS>>
  +nouRequestIdValidarDescompte()
  +POST sendMsgValidatCurosDescomptes.php
}
class SendMsgValidatCurosDescomptes {
  <<PHP endpoint>>
  +assert session
  +assert CSRF
  +assert permission
  +beginValidationDecision()
  +legacy mutation
  +completeValidationDecision()
}
class LegacyDiscountValidationLookup {
  <<PHP>>
  +isUsoc(idInsc) bool
}
class SifAuthenticatedActor {
  <<PHP>>
  +fromUser(user) actorId,roles
}
class SifInternalUsocClient {
  <<PHP>>
  +beginValidationDecision()
  +completeValidationDecision()
  +request()
}
class InternalApiAuthenticator {
  <<SIF>>
  +authenticate()
}
class UsocValidationDecisionService {
  <<SIF>>
  +begin()
  +complete()
}
class UsocValidationDecisionRepository {
  <<SIF>>
  +begin()
  +markCommitted()
  +markReviewRequired()
  +findRequested()
}
class UsocValidationDecision {
  <<MySQL table>>
  REQUEST_ID unique
  STATE REQUESTED|COMMITTED|REVIEW_REQUIRED
  ID_INSC
  DESIRED_VALID_DESC
  ACTOR_ID
  LEGACY_BEFORE_VALID_DESC
  LEGACY_AFTER_VALID_DESC
  LEGACY_STATE_HASH
}

AlumnesValidarDescomptesPage --> AlumnesValidarDescomptesJs
AlumnesValidarDescomptesJs --> SendMsgValidatCurosDescomptes
SendMsgValidatCurosDescomptes --> LegacyDiscountValidationLookup
SendMsgValidatCurosDescomptes --> SifAuthenticatedActor
SendMsgValidatCurosDescomptes --> SifInternalUsocClient
SifInternalUsocClient --> InternalApiAuthenticator : HMAC headers
InternalApiAuthenticator --> UsocValidationDecisionService
UsocValidationDecisionService --> UsocValidationDecisionRepository
UsocValidationDecisionRepository --> UsocValidationDecision
```

### Lectura

L'ACTUAL ja no és només disseny. El SIF disposa d'un registre propi de decisió, una frontera de dues fases i idempotència per `REQUEST_ID`. La mutació de `VALID_DESC` continua al llegat, però queda encapsulada entre `beginValidationDecision()` i `completeValidationDecision()`.

## FINAL — objectiu de tancament

```mermaid
classDiagram
direction LR

class UsocValidationApplicationService {
  <<FINAL>>
  +requestDecision(command)
  +confirmExternalEvidence(command)
  +reconcile(requestId)
}
class UsocEligibilityEvidenceGateway {
  <<FINAL boundary>>
  +verify(reference,effectiveDate) result
}
class UsocValidationDecisionRepository {
  <<existent>>
}
class DiscountEvidenceRepository {
  <<FINAL>>
  +storeMetadata()
  +linkDecision()
}
class LegacyUsocValidationAdapter {
  <<FINAL adapter>>
  +applyDecision()
}
class OperationalEventRepository {
  <<FINAL>>
  +append()
}

UsocValidationApplicationService --> UsocEligibilityEvidenceGateway
UsocValidationApplicationService --> UsocValidationDecisionRepository
UsocValidationApplicationService --> DiscountEvidenceRepository
UsocValidationApplicationService --> LegacyUsocValidationAdapter
UsocValidationApplicationService --> OperationalEventRepository
```

El FINAL no exigeix automatitzar necessàriament la consulta amb USOC. Exigeix separar explícitament la **prova d'afiliació** de la **persistència de la decisió**, custodiar només l'evidència necessària, deixar traça append-only i disposar d'un adaptador llegat substituïble.

## Estat

- DOCUMENTAT: sí.
- IMPLEMENTAT: classes ACTUAL indicades.
- VERIFICAT: hi ha tests de servei i de contracte al repositori; no s'ha executat preproducció en aquesta auditoria.
- PENDENT: evidència real de preproducció i definició/custòdia de la font de verificació d'afiliació.


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
