# UC-54 · Operar la cua fiscal i tractar les respostes AEAT

**Frontera.** UC-09 descriu **una remissió** de registre congelat; UC-54 cobreix l'**operació de la cua en conjunt**: seguiment de pendents, preflight, lots, recuperació de locks, `RETRY`/`DEAD_LETTER` i encaminament de respostes que requereixen decisió. **No** crea factures ni decideix automàticament si toca UC-30/31/05.

**Estat verificat:** `FiscalQueueProcessor`, `FiscalQueueRepository`, `FiscalQueueMetricsRepository`, `AeatPreflight`, el script `sif/scripts/preflight-aeat-worker.php` i un transport SOAP **només de proves** existeixen. No s'ha acreditat un panell final amb autorització/assignació ni enviament real de producció.

## 1. Fitxa del cas

| Fase | Dades i comportament revisats |
| --- | --- |
| Actor | Worker programat i responsable tècnica que consulta les mètriques i resol incidències; AEAT només intervé quan UC-09 envia. |
| Preflight | `AeatPreflight::check()` revisa extensions, URLs HTTPS, XSD, certificat llegible, contrasenya i identificadors de l'emissor/SIF; **no** valida confiança/revocació de certificat ni autorització efectiva davant AEAT. |
| Mètriques | `FiscalQueueMetricsRepository::snapshot()` compta `PENDING`, `PROCESSING`, `RETRY`, `SENT`, `DEAD_LETTER`, jobs disponibles `due`, locks obsolets i data de la tasca accionable més antiga. |
| Alertes CLI | `preflight-aeat-worker.php` calcula `DEAD_LETTER_THRESHOLD`, `DUE_QUEUE_THRESHOLD`, `STALE_WORKER_LOCK`; llindars per defecte: 1 `DEAD_LETTER`, 100 `due`, 900 segons de lock. Són paràmetres del script, no garanties de notificació a un operador. |
| Reclamació | `FiscalQueueRepository::claimNext` reclama `PENDING`/`RETRY` degudament disponibles, limita per intents i usa `FOR UPDATE` en transacció. |
| Límits de lot | `FiscalQueueProcessor::processBatch(limit)` admet 1–100, s'atura si no hi ha job o si es produeix una fallada. |
| Transport | `SoapTransport` només permet `TEST_ENDPOINT`, exigeix snapshot `aeat`, conserva evidència d'intent i retorna el resultat de la línia correlacionada; no inferir disponibilitat productiva. |
| Èxit de remissió | La cua es marca `SENT` fins i tot si la resposta de línia és `REJECTED`; `factura_registres.ESTAT_AEAT` i `factura.ESTAT_AEAT` guarden el resultat de línia. |
| Fallada tècnica | `FiscalQueueProcessor::failure()` usa retard exponencial inicial 60 s, màxim 3600 s i 3 intents per defecte; `RETRY` o `DEAD_LETTER` segons límit. |
| Recuperació lock | `recoverStaleLocks(olderThanSeconds)` exigeix mínim 60 s, torna `PROCESSING` antics a `RETRY`; **una resposta externa pot haver arribat abans de perdre el lock**. |

### 1.1. Flux funcional d'operació

1. El worker o la responsable consulta `preflight-aeat-worker.php`/mètriques; si el preflight no és satisfactori, **no** presentar el sistema com a preparat. El script de preflight **no fa cap enviament**.
2. Un procés programat crida `processNext()` o `processBatch()`; la selecció i actualització a `PROCESSING` són transaccionals amb la BD SIF.
3. `AeatTransport::send()` opera fora del commit de reclamació, amb el payload fiscal congelat; `SoapTransport` genera XML i registra evidència privada per intent.
4. Es valida identitat/operació de la resposta AEAT amb `ResponseParser`. `FiscalQueueRepository::complete()` desa XML/JSON i estat del registre individual, i marca la cua `SENT` encara que el resultat sigui `REJECTED` o `ACCEPTED_WITH_ERRORS`.
5. La responsable consulta els jobs per estat. Un `REJECTED`, `ACCEPTED_WITH_ERRORS`, un duplicat detectat o un `DEAD_LETTER` **requereix revisió**; no s'ha acreditat un `IncidentWorkflowService` que assigni o resolgui automàticament tots aquests casos.
6. Davant `RETRY`, abans de reenviar es valora si l'últim intent pot haver-se entregat externament; la recuperació tècnica no és una prova de no-recepció. Davant `DEAD_LETTER`, s'investiga evidència i motiu abans d'una acció controlada posterior.
7. La classificació funcional posterior pot iniciar UC-30, UC-31 o UC-05 quan realment correspongui; **no** reescriu el registre original ni genera un nou pagament.

### 1.2. Matriu de decisió i riscos

| Estat observat | Significat i següent pas |
| --- | --- |
| `PENDING` | Registre fiscal generat i en cua; encara no demostra enviament. |
| `PROCESSING` | Worker ha reclamat feina; no pressuposa enviament extern complet. |
| `RETRY` | Error tècnic o lock recuperat, disponibilitat programada; risc de resposta externa incerta. |
| `SENT` + `ACCEPTED` | Registre individual acceptat segons resposta persistida. |
| `SENT` + `ACCEPTED_WITH_ERRORS` | Ha arribat resposta d'acceptació amb errors; revisar contingut de línia. |
| `SENT` + `REJECTED` | Ha arribat resposta de rebuig; **no** és un error de xarxa que s'hagi de reintentar a cegues. |
| `DEAD_LETTER` | S'ha esgotat la política de reintents; factura/registres passen a `ERROR` segons el repositori. |
| `SENT` sense evidència esperada | Revisar coherència de fitxer privat, XML i resposta; el nombre de files de `fiscal_queue` no és una prova d'acceptació AEAT. |

**Observació de codi:** la recuperació de `PROCESSING` obsolet a `RETRY` no executa, per si sola, una consulta d'estat a l'AEAT ni una conciliació del fitxer d'evidència. La política d'autoreintent en resultat incert i la traça operativa fins a resolució continuen pendents de validació.

**Proves existents però no executades:** `FiscalQueueProcessorTest` i `FiscalQueueMetricsRepositoryTest` amb BD de proves/transport simulat; `AeatWorkerPreflightScriptTest` comprova el script. No equivalen a proves d'enviament real ni del panell d'operació.

### 1.3. Panell «Registres AEAT» i separació de cues

**Operació prevista per PrisMa.** La documentació funcional situa els registres a `pay.prisma.cat/sif/registres-aeat`, amb filtres de **període, estat AEAT, número de factura i UUID**. La pantalla consulta `factura_registres`, `fiscal_queue` i `factura`; només pot modificar l'estat d'una tasca de `fiscal_queue` en un **reintent autoritzat**. La fitxa especifica aquest **contracte de pantalla pendent**, no afirma que el panell ni els seus permisos estiguin connectats al processador PHP.

**Matriu de resultats visibles.** A més del número, la taula ha de mostrar `UUID_FACTURA`, `FISCAL_ORDER`, tipus de registre, `fiscal_queue.STATUS`, `ATTEMPTS/NEXT_RETRY_AT`, `factura_registres.ESTAT_AEAT` i evidència/causa consultable segons rol. `PENDING` no és error remot; `RETRY` pot correspondre a una fallada tècnica **després** d'un enviament real; `SENT+REJECTED` no és un job que encara esperi transport. Un operador sense autorització de reintent té **consulta**, no un botó funcional per reobrir o crear registres.

**Recuperació controlada.** Abans de reactivar un `DEAD_LETTER` o un lock obsolet, comprovar `UUID_FACTURA` + `FISCAL_ORDER`, payload immutable, historial d'intents i si existeix resposta remota ja rebuda o incerta. Un clic de reintent **no** torna a executar UC-01/05, no renumera factures i no crea un registre nou per simplificar el reprocessament. Les decisions de subsanació/registre d'anul·lació es tramiten amb el cas corresponent **després de classificar la causa**, no com a efecte automàtic d'un retry.

**Alertes amb valor probatori limitat.** `FiscalQueueMetricsRepository` i el preflight CLI exposen nombres de pendents, due, locks i dead-letter. Les alertes per llindar són observabilitat; **no** acrediten que el registre estigui acceptat o que s'hagi tramitat la incidència. Si el panell mostra «enviat», presentar el resultat real de la **línia AEAT** al costat, especialment per `ACCEPTED_WITH_ERRORS` i `REJECTED`.

### 1.4. Proves de panell i recuperació (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| CQF-01 | Filtrar per número, UUID i període | Retornar registre i job correlacionats, no barrejar ordres fiscals de la mateixa factura. |
| CQF-02 | SENT amb ACCEPTED_WITH_ERRORS | Veure estat doble i advertiment/revisió per la resposta. |
| CQF-03 | Rol només consulta demana RETRY | Denegació al servidor, cap canvi de cua. |
| CQF-04 | DEAD_LETTER amb possible enviament anterior | Reconciliar evidència/estat remot abans de reactivar. |
| CQF-05 | Panell mostra mètrica de jobs due a zero però hi ha REJECTED | El rebuig segueix visible per revisió; no donar el sistema per «sense incidències». |

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Worker fiscal" as W
actor "Responsable tècnica" as T
actor "AEAT de proves" as A
rectangle "SIF · operació fiscal" {
 usecase "UC-54\nOperar cua fiscal" as Main
 usecase "Preflight i mètriques" as Health
 usecase "UC-09\nRemetre registre individual" as Send
 usecase "Recuperar locks i reintents" as Recover
 usecase "Classificar resposta de línia" as Class
 usecase "UC-08\nGestionar incidència" as Incident
}
W --> Main
T --> Health
T --> Main
T --> Incident
A --> Send
Main ..> Health : <<include>>
Main ..> Send : <<include>> (si hi ha job)
Main ..> Recover : <<include>> (quan cal)
Main ..> Class : <<include>> (si hi ha resposta)
@enduml
```

## 3. UML de classes operatives reals

```mermaid
classDiagram
direction LR
class FiscalQueueProcessor {
 +processNext() array
 +processBatch(limit) array
 +recoverStaleLocks(seconds,now) int
}
class FiscalQueueRepository {
 +claimNext(db,maxAttempts) array
 +complete(db,item,status,response,requestXml) void
 +fail(db,item,error,maxAttempts,nextRetryAt) string
 +recoverStaleLocks(db,before) int
}
class FiscalQueueMetricsRepository {
 +snapshot(db,staleLockSeconds) array
}
class AeatPreflight {
 +check(config) array
}
class TransactionRunner {
 +run(callback) mixed
}
class AeatTransport {
 <<interface>>
 +send(payload) array
}
class SoapTransport {
 +send(payload) array
}
class ResponseParser {
 +parse(xml,snapshot) array
}
FiscalQueueProcessor --> TransactionRunner : claims/resultats separats
FiscalQueueProcessor --> FiscalQueueRepository : estats cua
FiscalQueueProcessor --> AeatTransport : remissió individual
SoapTransport ..|> AeatTransport
SoapTransport --> ResponseParser : estat del registre
```

`FiscalQueueMetricsRepository` i `AeatPreflight` són usats per **script d'operació/preflight**, no s'han dibuixat com a dependències inexistents del processador fiscal.

## 4. Seqüència — lot i resultat del registre

```mermaid
sequenceDiagram
autonumber
actor Op as Worker o cron
participant Pre as Script preflight/metrics
participant P as FiscalQueueProcessor
participant Q as FiscalQueueRepository
participant T as SoapTransport [només proves]
participant AEAT as AEAT de proves
participant DB as BD SIF
Op->>Pre: Comprovar preflight i snapshot de cua
Pre-->>Op: Ready, counts, due, stale_locks, alerts
opt Preflight satisfactori i procés autoritzat
 Op->>P: processBatch(limit <=100)
 loop Fins a limit o primer error/no job
  P->>Q: claimNext() dins transacció
  Q->>DB: PENDING/RETRY → PROCESSING, ATTEMPTS+1
  alt Sense job
   Q-->>P: null
  else Job reclamat
   Q-->>P: payload congelat
   P->>T: send(payload) fora del commit de claim
   T->>AEAT: SOAP/mTLS amb evidència d'intent
   alt Resposta individual correlacionada
    AEAT-->>T: Correcto/AceptadoConErrores/Incorrecto
    T-->>P: ACCEPTED/ACCEPTED_WITH_ERRORS/REJECTED
    P->>Q: complete() en transacció nova
    Q->>DB: SENT + XML, JSON i ESTAT_AEAT
   else Error tècnic o resultat extern incert
    T--xP: Excepció
    P->>Q: fail() en transacció nova
    Q->>DB: RETRY o DEAD_LETTER
   end
  end
 end
end
Note over P,DB: SENT no equival a ACCEPTED; retry no prova absència de resposta externa
```

## 5. Evidència i traçabilitat

[UC-54 original](../06-fitxes-funcionals/uc-054.md) · [UC-09 individual](uc-009-remetre-registre-aeat.md) · [UC-08 incidència](uc-008-gestionar-incidencia-sif.md) · [FiscalQueueProcessor](../../sif/src/Service/FiscalQueueProcessor.php) · [FiscalQueueRepository](../../sif/src/Repository/FiscalQueueRepository.php) · [FiscalQueueMetricsRepository](../../sif/src/Repository/FiscalQueueMetricsRepository.php) · [Preflight script](../../sif/scripts/preflight-aeat-worker.php) · [SoapTransport](../../sif/src/Aeat/SoapTransport.php) · [FiscalQueueProcessorTest](../../sif/tests/Integration/FiscalQueueProcessorTest.php).
