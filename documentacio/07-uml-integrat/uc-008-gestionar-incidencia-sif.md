# UC-08 · Gestionar una incidència SIF — fitxa i UML integrats

**Àmbit:** detectar, obrir, consultar i gestionar una incidència fiscal, econòmica, documental o de sincronització. **Una incidència no autoritza per si sola a modificar una factura emesa, repetir un cobrament o alterar la cadena fiscal.** La reparació material correspon sempre al cas d'ús específic.

**Estat actual (2026-09-30):** backend, UI, preflight, E2E tècnic read-only, deduplicació, concurrència, deep-links i gate final d'evidències **IMPLEMENTATS I VERIFICATS EN CI**. El run `36664788129` ha passat amb **677/0**; `Intranet AO batch checks` run `36647777483` continua en **success**. Només resten l'execució real de preproducció amb secrets/rols reals i l'alta/configuració del menú a BD si el preflight indica que encara falta.

**Frontera UC-008 / UC-081:** UC-008 és el cas mare i punt d'entrada/consulta/gestió. [UC-081](uc-081-cicle-complet-incidencia.md) detalla el lifecycle intern. Tots dos comparteixen **una sola implementació**: `IncidentLifecycleService` + `IncidentActionRepository`.

## 1. Estat funcional i tècnic

| Capacitat | Documentat | Backend | UI | Prova runtime |
| --- | --- | --- | --- | --- |
| Obrir incidència simple | Sí | Sí | N/A | verificat CI |
| Identitat estable `UUID_INCIDENT` | Sí | Sí a migració nova | N/A | verificat CI |
| Correlació i idempotència | Sí | Sí | N/A | verificat CI |
| Vincular factura/pagament/recurs genèric | Sí | Sí | detall implementat | verificat CI |
| Deduplicar per clau idempotent | Sí | Sí + current read en cursa | N/A | verificat amb concurrència real |
| Llistar / consultar | Sí | API interna + sessió panell | implementat al codi | CI/E2E tècnic verificat; preprod pendent |
| Assignar responsable | Sí | Sí via service/API | implementat al codi | CI + concurrència verificades; preprod pendent |
| Afegir evidència | Sí | Sí via service/API | implementat al codi | verificat CI; preprod pendent |
| Resoldre amb evidència | Sí | Sí via service/API | implementat al codi | verificat CI; preprod pendent |
| `DISMISSED` justificat | Sí | Sí via service/API | implementat al codi | verificat CI; preprod pendent |
| Reobrir | Sí | Sí via service/API | implementat al codi | verificat CI; preprod pendent |
| Historial `sif_incident_action` | Sí | Sí writer PHP append-only | timeline implementat | verificat CI; preprod pendent |
| Incidència Redsys | Sí | atòmica + deduplicada + dades sensibles redaccionades | N/A | verificat CI |
| Incidència AEAT integritat | Sí | Sí | N/A | verificat CI |
| Incidència AEAT retries esgotats | Sí | deduplicada per queue | N/A | verificat CI |
| Reparació automàtica genèrica | No convé | No | No | — |
| Panell oficial | Sí | sessió + lifecycle + CSRF | **implementat al codi** | E2E tècnic verificat; desplegament/E2E real pendent |
| Resum intranet VERI*FACTU | Sí | read-only + fallback darrer resum validat | **implementat al codi** | Intranet AO + CI verificats; desplegament pendent |

## 2. Contracte de persistència

### 2.1. Capçalera d'incidència

`errors_verifactu` continua sent la capçalera canònica. La migració additiva `2026_09_29_000010_add_incident_lifecycle.sql` hi afegeix:

- `UUID_INCIDENT`;
- `UUID_PAYMENT`;
- `RESOURCE_TYPE` / `RESOURCE_ID`;
- `SOURCE_TYPE` / `SOURCE_ID`;
- `SEVERITY`;
- `ASSIGNED_TO`;
- `CORRELATION_ID`;
- `IDEMPOTENCY_KEY`;
- `REASON_CODE`;
- `RESOLVED_AT`;
- `RESOLUTION_NOTES`;
- `CLOSURE_CRITERIA`.

La migració és **additiva**: no modifica la migració core ja aplicada.

### 2.2. Historial immutable

`sif_incident_action` ja existia al DDL. La branca afegeix `IDEMPOTENCY_KEY` única i implementa `IncidentActionRepository::append()`.

Cada acció conserva:

- incidència;
- tipus d'acció;
- estat anterior/nou;
- severitat;
- responsable;
- actor/rol;
- motiu;
- detalls;
- evidència JSON;
- correlació;
- clau idempotent;
- data.

## 3. Contracte PHP implementat

### 3.1. Compatibilitat

El contracte històric continua disponible:

~~~php
IncidentRepository::open(PDO $db, ?string $uuidFactura, string $type, string $message)
~~~

per no trencar callers existents.

El contracte ric és:

~~~php
IncidentRepository::openDetailed(PDO $db, array $input)
~~~

i retorna:

~~~text
ok
reused
incident_id
uuid_incident
status
~~~

Quan hi ha `uuid_factura` o `uuid_payment`, el repositori comprova que l'objecte existeixi. Una clau idempotent reutilitzada amb un payload lògic diferent produeix conflicte.

### 3.2. Lifecycle

`IncidentLifecycleService` implementa:

~~~text
list
view
open
assign
addEvidence
resolve
dismiss
reopen
~~~

Les operacions d'escriptura passen per `TransactionRunner`; `resolve()` exigeix criteri de tancament, notes i evidència.

### 3.3. API interna

`POST /api/incidents/manage.php`:

- valida HMAC, timestamp i `request_id` amb `InternalApiAuthenticator`;
- aplica anti-replay amb `internal_api_request`;
- separa rols de lectura i gestió;
- no confia en botons/JS per autoritzar;
- ofereix accions `summary/list/view/open/assign/evidence/resolve/dismiss/reopen`.

Els rols es configuren amb:

~~~text
SIF_INCIDENT_READ_ROLES
SIF_INCIDENT_MANAGE_ROLES
~~~

Si no hi ha rols configurats, el servei falla tancat.

## 4. Actors i frontera de responsabilitat

- **Worker SIF / AEAT / Redsys:** pot obrir incidències automàtiques correlacionades.
- **Responsable tècnica / operador autoritzat:** pot consultar i gestionar segons rol servidor.
- **Auditor fiscal/read-only:** consulta, però no assigna, resol, reintenta ni modifica.
- **UC corrector específic:** executa la reparació real. UC-008/081 només governa l'expedient.

No s'ha implementat un `RepairRouter` genèric perquè no és segur convertir “resoldre incidència” en un retry universal. La reparació deriva explícitament a UC-02/28/29a, UC-55/78, UC-74/77, UC-82, UC-124, etc.

## 5. UML de casos d'ús

~~~plantuml
@startuml
left to right direction
actor "Worker SIF" as W
actor "Responsable tècnica" as T
actor "Operador autoritzat" as O
actor "Auditor read-only" as A
rectangle "UC-008 · Gestió d'incidències" {
 usecase "Obrir / reutilitzar
incidència" as Open
 usecase "Consultar expedient" as View
 usecase "UC-081
Gestionar lifecycle" as Life
 usecase "Executar reparació
amb UC específic" as Repair
}
W --> Open
T --> Open
T --> View
O --> View
A --> View
T --> Life
O --> Life
Life ..> View : <<include>>
Life ..> Repair : <<extend>> acció autoritzada
@enduml
~~~

## 6. Diagrama de classes — ACTUAL de la branca

~~~mermaid
classDiagram
direction LR

class IncidentRepository {
  <<PHP EXISTENT>>
  +open(db,uuidFactura,type,message) array
  +openDetailed(db,input) array
  +findById(db,id,forUpdate) array?
  +list(db,filters,limit) array
  +updateLifecycle(...) void
}

class IncidentActionRepository {
  <<PHP EXISTENT>>
  +append(db,action) array
  +listForIncident(db,id) array
}

class IncidentLifecycleService {
  <<PHP EXISTENT>>
  +list(actor,filters,limit) array
  +view(actor,id) array
  +open(actor,payload) array
  +assign(actor,id,payload) array
  +addEvidence(actor,id,payload) array
  +resolve(actor,id,payload) array
  +dismiss(actor,id,payload) array
  +reopen(actor,id,payload) array
}

class InternalApiAuthenticator {
  <<PHP EXISTENT>>
  +authenticate(server,rawBody,method,path) array
}

class RedsysCallbackWorker {
  <<PHP EXISTENT>>
  +runOne(db,workerId,now) array?
}

class FiscalQueueProcessor {
  <<PHP EXISTENT>>
  +processNext() array
  +processBatch(limit) array
}

class IncidentPanel {
  <<UI IMPLEMENTADA AL CODI · DESPLEGAMENT PENDENT>>
  +list()
  +detail()
  +assign()
  +evidence()
  +close()
}

class RepairRouter {
  <<NO IMPLEMENTAR COM RETRY GENÈRIC>>
  +deriveToSpecificUseCase()
}

IncidentLifecycleService --> IncidentRepository
IncidentLifecycleService --> IncidentActionRepository
InternalApiAuthenticator ..> IncidentLifecycleService : API manage.php
RedsysCallbackWorker --> IncidentRepository
FiscalQueueProcessor --> IncidentRepository
IncidentPanel ..> IncidentLifecycleService : API interna
IncidentLifecycleService ..> RepairRouter : frontera funcional
~~~

**Correcció respecte de la documentació anterior:** `FiscalQueueProcessor → IncidentRepository` és una dependència real. L'anterior UML la descrivia com a no acreditada i estava desactualitzat.

## 7. Seqüència ACTUAL A — Redsys → incidència

~~~mermaid
sequenceDiagram
autonumber
participant W as RedsysCallbackWorker
participant Q as RedsysCallbackQueueRepository
participant I as IncidentRepository
participant DB as BD SIF

W->>W: processor->process(job)
alt conflicte funcional o intents esgotats
  W->>DB: BEGIN
  W->>Q: markIncident(job)
  Q->>DB: UPDATE queue STATUS=INCIDENT
  W->>I: openDetailed(REDSYS_CALLBACK, job UUID)
  I->>DB: validar factura si existeix
  I->>DB: buscar IDEMPOTENCY_KEY
  alt ja existeix
    I-->>W: reused + incident_id
  else nova
    I->>DB: INSERT errors_verifactu OPEN
    I-->>W: incident_id + uuid_incident
  end
  W->>DB: COMMIT
  W-->>W: status=INCIDENT
else error recuperable
  W->>Q: markRetry()
end
~~~

**Invariant:** no pot quedar el job en `INCIDENT` perquè ha fet commit i fallar després la creació de l'expedient dins de la mateixa transacció.

## 8. Seqüència ACTUAL B — AEAT integritat / dead-letter

~~~mermaid
sequenceDiagram
autonumber
participant P as FiscalQueueProcessor
participant Q as FiscalQueueRepository
participant I as IncidentRepository
participant DB as BD SIF
participant AEAT as Transport AEAT

P->>Q: claimNext()
alt payload/hash inconsistent
  P->>DB: BEGIN
  P->>Q: rejectIntegrity()
  P->>I: openDetailed(FISCAL_PAYLOAD_CONFLICT)
  I->>DB: INSERT/reuse incident
  P->>DB: COMMIT
  P-->>P: DEAD_LETTER + incident_id
else payload íntegre
  P->>AEAT: send()
  alt error transport i queden intents
    P->>Q: fail() -> RETRY
  else error transport i intents esgotats
    P->>DB: BEGIN
    P->>Q: fail() -> DEAD_LETTER
    P->>I: openDetailed(AEAT_DEAD_LETTER)
    I->>DB: INSERT/reuse incident
    P->>DB: COMMIT
  end
end
~~~

Un `DEAD_LETTER` local **no prova un rebuig remot**. La incidència ha de conservar aquesta distinció i UC-77 decideix el tractament fiscal.

## 9. Seqüència ACTUAL C — gestió manual backend

~~~mermaid
sequenceDiagram
autonumber
actor O as Operador
participant API as /api/incidents/manage.php
participant H as InternalApiAuthenticator
participant S as IncidentLifecycleService
participant I as IncidentRepository
participant A as IncidentActionRepository
participant DB as BD SIF

O->>API: POST signat action=assign/evidence/resolve
API->>H: HMAC + timestamp + request_id + rols
H->>DB: claim internal_api_request
H-->>API: actor autenticat
API->>S: acció(actor,payload)
S->>S: comprovar rol servidor
S->>DB: BEGIN
S->>I: findById(... FOR UPDATE)
S->>A: append(idempotency_key, actor, estat, evidència)
A->>DB: INSERT sif_incident_action
S->>I: updateLifecycle()
I->>DB: UPDATE errors_verifactu
S->>DB: COMMIT
S-->>API: resultat tipificat
API-->>O: JSON
~~~

## 10. Activitats ACTUAL/FINAL per pàgina i apartat

### 10.1. Obertura automàtica

#### ACTUAL

~~~mermaid
flowchart TD
A[Worker detecta error] --> B{Redsys o AEAT cobert?}
B -->|Redsys funcional/max retries| C[Transacció]
C --> D[Job -> INCIDENT]
D --> E[openDetailed amb job/correlació/idempotència]
E --> F[COMMIT]
B -->|AEAT integritat| G[rejectIntegrity]
G --> H[openDetailed FISCAL_PAYLOAD_CONFLICT]
B -->|AEAT retries esgotats| I[fail -> DEAD_LETTER]
I --> J[openDetailed AEAT_DEAD_LETTER]
B -->|altres orígens| K[Encara requereixen integració específica]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[Qualsevol detector SIF] --> B[Classificar recurs + causa]
B --> C[Construir correlation/idempotency]
C --> D[Obrir o reutilitzar expedient]
D --> E[Conservar evidència mínima segura]
E --> F[Notificar/resumir segons SLA]
F --> G[Sense repetir efecte fiscal/econòmic]
~~~

### 10.2. Panell · llistat d'incidències

#### ACTUAL

~~~mermaid
flowchart TD
A[UI SIF implementada al codi] --> B[actions.php action=list]
B --> C[Autoritzar rol read/manage]
C --> D[Filtrar estat/severitat/tipus/responsable]
D --> E[Retornar JSON]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[pay.prisma.cat/sif/incidencies] --> B[Autenticar sessió]
B --> C[API list]
C --> D[Taula per prioritat/estat/origen]
D --> E[Filtres]
E --> F[Obrir detall]
~~~

### 10.3. Panell · detall

#### ACTUAL

~~~mermaid
flowchart TD
A[UI SIF implementada al codi] --> B[actions.php action=view]
B --> C[IncidentRepository findById]
C --> D[IncidentActionRepository listForIncident]
D --> E[JSON capçalera + timeline]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[Obrir expedient] --> B[Capçalera]
B --> C[Recurs/origen/correlació]
C --> D[Timeline immutable]
D --> E[Evidències]
E --> F[Enllaços als UCs correctors]
~~~

### 10.4. Triage i assignació

#### ACTUAL

~~~mermaid
flowchart TD
A[POST action=assign] --> B[Rol manage?]
B -->|no| C[403]
B -->|sí| D[SELECT FOR UPDATE]
D --> E{RESOLVED/DISMISSED?}
E -->|sí| F[409]
E -->|no| G[append ASSIGN idempotent]
G --> H[ESTAT=IN_PROGRESS + responsable]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[Operador obre detall] --> B[Selecciona severitat/responsable]
B --> C[Motiu obligatori]
C --> D[Confirmar]
D --> E[API assign]
E --> F[Timeline + responsable visibles]
~~~

### 10.5. Investigació i evidències

#### ACTUAL

~~~mermaid
flowchart TD
A[POST action=evidence] --> B[Validar role + incident actiu]
B --> C[EVIDENCE_JSON no buit]
C --> D[append ADD_EVIDENCE]
D --> E[Estat principal no canvia]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[Afegir evidència] --> B[Seleccionar tipus/referència]
B --> C[No incloure secrets/PAN/tokens]
C --> D[Guardar evidència o referència privada]
D --> E[Timeline auditable]
~~~

### 10.6. Acció correctora

#### ACTUAL

~~~mermaid
flowchart TD
A[Diagnosi] --> B[UC-008 no modifica factura/diners]
B --> C[Operador deriva manualment al UC específic]
C --> D[Executar UC-02/55/74/77/82/etc.]
D --> E[Tornar a UC-008 amb evidència]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[Diagnosi] --> B[Seleccionar reparació per tipus]
B --> C{Acció segura i suportada?}
C -->|no| D[Escalar/revisió humana]
C -->|sí| E[Derivar explícitament al servei del UC específic]
E --> F[Conservar correlació]
F --> G[Verificar resultat abans de tancar]
~~~

### 10.7. Resolució

#### ACTUAL

~~~mermaid
flowchart TD
A[POST action=resolve] --> B[Rol manage]
B --> C[closure_criteria obligatori]
C --> D[resolution_notes obligatori]
D --> E[evidence obligatòria]
E --> F[SELECT FOR UPDATE]
F --> G[append RESOLVE]
G --> H[ESTAT=RESOLVED + RESOLVED_AT]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[Operador demana tancament] --> B[Repetir prova/conciliar font]
B --> C{Resultat acreditat?}
C -->|no| D[Mantenir obert + evidència FAIL/BLOCKED]
C -->|sí| E[Registrar prova, criteri i notes]
E --> F[RESOLVED]
~~~

### 10.8. Dismissal i reobertura

#### ACTUAL

~~~mermaid
flowchart TD
A[DISMISS] --> B[Motiu + criteri + notes]
B --> C[append DISMISS]
C --> D[ESTAT=DISMISSED]
D --> E{Cal reobrir?}
E -->|sí| F[action=reopen]
F --> G[append REOPEN]
G --> H[ESTAT=OPEN i netejar camps tancament]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[DISMISSED] --> B[Justificació material]
B --> C[Comprovar que no hi ha impacte fiscal/econòmic pendent]
C --> D[Guardar decisió]
D --> E[Reobertura només amb nova causa/evidència]
~~~

### 10.9. Intranet principal · VERI*FACTU

#### ACTUAL

~~~mermaid
flowchart TD
A[Sidebar actual] --> B[No s'ha acreditat apartat UC-008]
B --> C[Sense resum d'incidències]
~~~

#### FINAL

~~~mermaid
flowchart TD
A[Intranet VERI*FACTU] --> B[Consultar resum SIF read-only]
B --> C[Indicador obertes/crítiques]
C --> D[Enllaç a pay.prisma.cat/sif/incidencies]
D --> E[Resolució només al SIF]
~~~

## 11. Proves

### Escrites i executades

- `IncidentLifecycleTest`: identitat, referències factura/pagament/recurs, lifecycle, permisos, idempotència, filtres i summary.
- `IncidentConcurrencyTest`: dues obertures simultànies amb la mateixa key i dues assignacions simultànies amb connexions/processos independents.
- `IncidentInternalApiSecurityTest`: HMAC, timestamp, anti-replay, rols fail-closed, 404, journal d'actor/rol i límit de llistat.
- `IncidentPanelLaunchAuthenticatorTest`: handoff signat i anti-replay del panell.
- `IncidentPanelIntranetBoundaryTest`: intranet read-only, identitat des de sessió i fallback sense fals zero.
- `IncidentPanelPreflightScriptTest`: GO/NO-GO de rols, secrets, paths i superfície.
- `IncidentPanelE2eScriptTest`: E2E tècnic read-only preparat i bloqueig de production.
- `IncidentPanelUiContractTest`: filtres, detall, timeline append-only, RESOLVED/DISMISSED i absència de retry massiu.
- `RedsysCallbackWorkerTest`: rollback, deduplicació per job i redacció de dades sensibles.
- `SensitiveDataRedactorTest`: PAN Luhn, CVV, signatures i secrets.
- `FiscalQueueProcessorTest`: integritat, retries/dead-letter, deduplicació per queue i separació REJECTED remot/error local.
- `AeatWorkflowTest`: resultat incert → REVIEW sense retransmissió cega.
- `HttpEndpointsTest` i `IncidentLifecycleSchemaTest`: wiring HTTP i esquema.

### Existents i relacionades

- `DocumentsAndIncidentsTest::testOpenIncidentStoresOpenFiscalIssue`.
- `RedsysCallbackWorkerTest::testFunctionalConflictBecomesIncidentWithoutRetry`.
- `RedsysCallbackWorkerTest::testFifthTechnicalFailureBecomesIncident`.
- `PayloadIdempotencyFlowTest` per `FISCAL_PAYLOAD_CONFLICT`.

**Verificació CI actual:** run **36664788129**, amb **677 passed / 0 failed** sobre PHP 8.4 + MySQL 8.4. Inclou concurrència real, deduplicació Redsys/AEAT, redacció sensible, API/UI, preflight, E2E tècnic read-only, deep-links i validador final d'evidències. L'E2E contra preproducció real i la configuració productiva continuen pendents.

## 12. Gaps pendents

1. **Desplegament real:** carregar secrets/rols de preproducció i executar `preflight-incidents-panel.php`.
2. **E2E real:** executar `e2e-incidents-panel.php` contra la URL HTTPS de preproducció i conservar la sortida JSON.
3. **Menú intranet:** consultar la BD real `apartats` i donar d'alta `/sif-verifactu.php` amb pare/ordre/rols reals.
4. **Superfícies de reparació/navegació:** el panell deriva a superfícies reals existents de la intranet: `alumnes-factura.php?uuid_factura=...` per factura SIF i `sif-registres-aeat.php?queue_id=...` per cua AEAT. Són deep-links de navegació; UC-008 no executa reparació automàtica.
5. **Governança operativa:** decidir SLA/prioritats i notificacions automàtiques si s'aproven.
6. **Cobertura de detectors:** afegir integracions d'obertura per documents, conciliació, legacy i altres workers només quan el cas funcional corresponent ho requereixi.

**Ja tancat al codi/CI:** UI, resum intranet, concurrència real, rollback Redsys, deduplicació Redsys/AEAT, redacció sensible, preflight, E2E tècnic read-only i deep-links a factura/AEAT.

## 13. Traçabilitat

- [Fitxa funcional UC-008](../06-fitxes-funcionals/uc-008.md)
- [UC-081 · lifecycle detallat](uc-081-cicle-complet-incidencia.md)
- [Auditoria detallada UC-008](04-auditoria-detallada-uc-008-gestionar-incidencia-2026-09-29.md)
- [Proves pendents UC-008](05-proves-pendents-uc-008-implementacio.md)
- [IncidentRepository](../../sif/src/Repository/IncidentRepository.php)
- [IncidentActionRepository](../../sif/src/Repository/IncidentActionRepository.php)
- [IncidentLifecycleService](../../sif/src/Service/IncidentLifecycleService.php)
- [API incidències](../../sif/public/api/incidents/manage.php)
- [RedsysCallbackWorker](../../sif/src/Service/RedsysCallbackWorker.php)
- [FiscalQueueProcessor](../../sif/src/Service/FiscalQueueProcessor.php)
- [Migració lifecycle](../../sif/database/migrations/2026_09_29_000010_add_incident_lifecycle.sql)
- [IncidentLifecycleTest](../../sif/tests/Integration/IncidentLifecycleTest.php)
- [Estat final operació/incidències](../04-estat-final/18-estat-final-operacio-incidencies.md)
- [Panell SIF](../04-estat-final/25-panell-sif-pay-prisma.md)

**Estat de tancament documental:** classes, seqüències i activitats ACTUAL/FINAL actualitzades.  
**Estat de tancament tècnic:** backend + UI + seguretat + idempotència + concurrència + preflight + E2E tècnic + deep-links + gate d'evidències implementats i verificats en CI (**677/0**). Pendents només configuració/desplegament de preproducció, E2E real i alta/configuració del menú de BD si encara no existeix.
