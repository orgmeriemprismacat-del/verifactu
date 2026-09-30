# UC-008 — Diagrames de classes ACTUAL i FINAL

**Data d'auditoria:** 30/09/2026  
**Estat de referència:** integrat a `main` mitjançant el PR #18 (2026-09-30); aquesta fitxa descriu el backend existent després del merge.  
**Regla:** ACTUAL = codi PHP/SQL/JS real integrat a `main`. FINAL = arquitectura objectiu després de la implementació; els components ja codificats es marquen com a existents i només el desplegament/configuració d'entorn queda pendent.

Vegeu també [fitxa integrada UC-008](uc-008-gestionar-incidencia-sif.md), [seqüències](uc-008-sequencies-actual-final.md), [activitats](uc-008-activitats-pagines-incidencies-actual-final.md) i [UC-081 lifecycle](uc-081-cicle-complet-incidencia.md).

## 1. CL-008-ACTUAL · Backend executable

```mermaid
classDiagram
direction LR

class IncidentRepository {
  <<PHP>>
  +open(db, uuidFactura, type, message) array
  +openDetailed(db, input) array
  +findById(db, incidentId, forUpdate) array?
  +list(db, filters, limit) array
  +updateLifecycle(...) void
}

class IncidentActionRepository {
  <<PHP>>
  +append(db, action) array
  +listForIncident(db, incidentId) array
}

class IncidentLifecycleService {
  <<PHP>>
  +list(actor, filters, limit) array
  +view(actor, incidentId) array
  +open(actor, payload) array
  +assign(actor, incidentId, payload) array
  +addEvidence(actor, incidentId, payload) array
  +resolve(actor, incidentId, payload) array
  +dismiss(actor, incidentId, payload) array
  +reopen(actor, incidentId, payload) array
}

class PayloadIdempotencyValidator {
  <<PHP transversal>>
  +calculateHash(payload) string
  +assertMatches(payload, storedHash) void
}

class SensitiveDataRedactor {
  <<PHP seguretat>>
  +redact(value) string
}

class InternalApiAuthenticator {
  <<PHP>>
  +authenticate(server, rawBody, method, path) array
}

class RedsysCallbackWorker {
  <<PHP>>
  +runOne(db, workerId, now) array?
}

class FiscalQueueProcessor {
  <<PHP>>
  +processNext() array
  +processBatch(limit) array
}

class errors_verifactu {
  <<MySQL capçalera>>
  UUID_INCIDENT
  UUID_FACTURA
  UUID_PAYMENT
  RESOURCE_TYPE
  RESOURCE_ID
  SOURCE_TYPE
  SOURCE_ID
  TIPUS_INCIDENCIA
  SEVERITY
  ASSIGNED_TO
  CORRELATION_ID
  IDEMPOTENCY_KEY
  IDEMPOTENCY_PAYLOAD_HASH
  ESTAT
  DETAILS
  RESOLVED_AT
}

class sif_incident_action {
  <<MySQL timeline>>
  UUID_ACTION
  IDEMPOTENCY_KEY
  PAYLOAD_HASH
  INCIDENT_ID
  ACTION_TYPE
  PREVIOUS_STATUS
  NEW_STATUS
  SEVERITY
  ASSIGNEE_ID
  ACTOR_ID
  REASON_CODE
  EVIDENCE_JSON
  CORRELATION_ID
}

IncidentLifecycleService --> IncidentRepository
IncidentLifecycleService --> IncidentActionRepository
IncidentRepository --> PayloadIdempotencyValidator
IncidentActionRepository --> PayloadIdempotencyValidator
RedsysCallbackWorker --> SensitiveDataRedactor : abans de persistir errors
IncidentRepository --> errors_verifactu
IncidentActionRepository --> sif_incident_action
sif_incident_action --> errors_verifactu : INCIDENT_ID
InternalApiAuthenticator ..> IncidentLifecycleService : manage.php
RedsysCallbackWorker --> IncidentRepository : obrir/reutilitzar
FiscalQueueProcessor --> IncidentRepository : AEAT/integritat
```

## 2. Classes ACTUALS i estat

| Component | Estat | Observació |
| --- | --- | --- |
| `IncidentRepository` | IMPLEMENTAT + CONCURRÈNCIA VERIFICADA | `open()` + `openDetailed()`; duplicate concurrent recuperat amb current read `FOR UPDATE` sota MySQL REPEATABLE READ |
| `IncidentActionRepository` | IMPLEMENTAT | timeline append-only i idempotent |
| `IncidentLifecycleService` | IMPLEMENTAT | lifecycle backend, sense reparació genèrica |
| `PayloadIdempotencyValidator` | REUTILITZAT | hash canònic del payload, conflicte si divergeix |
| `InternalApiAuthenticator` | IMPLEMENTAT PREVI | HMAC, timestamp, request-id i anti-replay |
| `SensitiveDataRedactor` | IMPLEMENTAT | redacció de PAN Luhn, CVV, signatures, secrets/password/merchant key |
| `RedsysCallbackWorker` | INTEGRAT | job INCIDENT + expedient atòmic; payload estable per `UUID_JOB`; redacció sensible abans de `LAST_ERROR/DETAILS` |
| `FiscalQueueProcessor` | INTEGRAT | incidència per integritat/dead-letter, deduplicada per `fiscal_queue.ID`; REVIEW per outcome incert |
| `errors_verifactu` | AMPLIAT | capçalera/lifecycle |
| `sif_incident_action` | AMPLIAT | idempotency key + payload hash; accions concurrents serialitzades per lock de capçalera |
| UI de panell | IMPLEMENTADA AL CODI | handoff HMAC, sessió SIF, CSRF, vista, accions i resum intranet; desplegament pendent |

## 3. CL-008-FINAL · Arquitectura objectiu reconciliada

```mermaid
classDiagram
direction LR

class IncidentPanelPage {
  <<IMPLEMENTAT>>
  sif/public/sif/incidencies/index.php
}

class IncidentPanelActions {
  <<IMPLEMENTAT>>
  sif/public/sif/incidencies/actions.php
  +summary()
  +list()
  +view()
  +assign()
  +evidence()
  +resolve()
  +dismiss()
  +reopen()
  +logout()
}

class IncidentPanelClient {
  <<IMPLEMENTAT JS>>
  sif/public/sif/incidencies/app.js
}

class IncidentPanelSession {
  <<IMPLEMENTAT>>
  +start()
  +establish(actor)
  +actor()
  +csrfToken()
  +assertCsrf()
  +destroy()
}

class PanelLaunchAuthenticator {
  <<IMPLEMENTAT>>
  +authenticate(input,path) array
}

class SifInternalIncidentClient {
  <<IMPLEMENTAT INTRANET>>
  +request(actorId,roles,payload) array
}

class SifPanelLaunchToken {
  <<IMPLEMENTAT INTRANET>>
  +create(actorId,roles) array
}

class IncidentLifecycleService {
  <<IMPLEMENTAT>>
}

class IncidentNotifier {
  <<OPCIONAL / DECISIÓ PENDENT>>
  +notifyAssignment()
  +notifyCritical()
}

class RepairUseCase {
  <<UC ESPECÍFIC>>
  +executeCorrelatedCommand()
}

IncidentPanelPage --> IncidentPanelClient
IncidentPanelClient --> IncidentPanelActions
IncidentPanelActions --> IncidentPanelSession
IncidentPanelActions --> IncidentLifecycleService
PanelLaunchAuthenticator --> IncidentPanelSession : estableix actor
SifPanelLaunchToken --> PanelLaunchAuthenticator : handoff signat
SifInternalIncidentClient --> IncidentLifecycleService : via API interna read-only
IncidentLifecycleService ..> IncidentNotifier : només si s'aprova política
IncidentLifecycleService ..> RepairUseCase : derivació explícita
```

### Diferència ACTUAL / FINAL

A nivell de codi, **ACTUAL i FINAL ja coincideixen en el nucli funcional**. El FINAL no requereix crear un segon controlador, una segona vista ni un segon client d'incidències. El que queda fora del repositori és:

- desplegament/configuració real;
- rols i secrets reals;
- alta/verificació del menú de la intranet;
- E2E de navegador/preproducció;
- SLA/notificacions només si s'aproven funcionalment.

## 4. Regla de frontera

No es crearà un segon `IncidentWorkflowService`. **UC-008 i UC-081 comparteixen `IncidentLifecycleService`**. La reparació d'una factura, pagament, document, cua AEAT o matrícula no es converteix en un mètode genèric del lifecycle: es deriva al cas d'ús responsable amb la mateixa correlació.

## 5. Pendent per tancar entorn

- desplegament/configuració productiva del panell;
- alta del menú VERI*FACTU a la BD de menú de la intranet;
- política real de rols, severitats i SLA;
- notificacions si s'aproven;
- evidència E2E de preproducció/producció.

**UI existent al repositori:** `PanelLaunchAuthenticator`, `IncidentPanelSession`, `sif/public/sif/incidencies/*`, `SifInternalIncidentClient`, `SifPanelLaunchToken` i `sif-verifactu.php`.
