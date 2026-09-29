# UC-008 · Auditoria detallada de gestió d'incidències — 2026-09-29

**Estat documental:** AUDITAT EN DETALL / CANVIS DOCUMENTALS APLICATS.  
**Estat backend en aquesta branca:** LIFECYCLE PARCIAL IMPLEMENTAT; API INTERNA + WRITER D'ACCIONS + IDEMPOTÈNCIA + INTEGRACIÓ REDSYS/AEAT.  
**Estat UI:** PENDENT.  
**Estat proves:** SUITE BACKEND CI EXECUTADA · 555 PASSED / 0 FAILED.  
**No acredita:** desplegament productiu, rols reals, dades productives, E2E de navegador, concurrència específica, preproducció ni homologació externa.

## 1. Fonts revisades

- `documentacio/06-fitxes-funcionals/uc-008.md`
- `documentacio/07-uml-integrat/uc-008-gestionar-incidencia-sif.md`
- `documentacio/07-uml-integrat/uc-081-cicle-complet-incidencia.md`
- `documentacio/04-estat-final/18-estat-final-operacio-incidencies.md`
- `documentacio/04-estat-final/25-panell-sif-pay-prisma.md`
- `sif/src/Repository/IncidentRepository.php`
- `sif/src/Service/RedsysCallbackWorker.php`
- `sif/src/Service/FiscalQueueProcessor.php`
- `sif/database/migrations/2026_06_02_000001_create_sif_core.sql`
- `sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql`
- proves d'incidències, Redsys, cua fiscal, idempotència i endpoints.
- còpia actual de la intranet: sidebar, clients interns SIF i JS de consulta SIF.

## 2. Troballes abans de la correcció

| Ref | Troballa | Estat |
| --- | --- | --- |
| UC08-01 | `IncidentRepository::open()` només retornava `ok=true`, sense ID/UUID d'incidència | CORREGIT |
| UC08-02 | `uuidFactura` documentat com a existent però no es validava i no hi havia FK | CORREGIT al contracte ric |
| UC08-03 | només es podia vincular directament factura; pagament/job/inscripció quedaven dins text | CORREGIT parcialment amb recurs genèric |
| UC08-04 | no hi havia correlació ni idempotència pròpia d'incidència | CORREGIT |
| UC08-05 | no hi havia deduplicació segura | CORREGIT per clau idempotent |
| UC08-06 | `sif_incident_action` existia al DDL però sense writer PHP | CORREGIT |
| UC08-07 | no hi havia servei executable d'assignació/evidència/tancament | CORREGIT backend |
| UC08-08 | Redsys marcava job INCIDENT i després obria expedient fora de transacció | CORREGIT |
| UC08-09 | UML deia que `FiscalQueueProcessor` no cridava IncidentRepository | DOCUMENTACIÓ OBSOLETA, CORREGIDA |
| UC08-10 | integritat fiscal obria incidència però DEAD_LETTER per retries esgotats no | CORREGIT |
| UC08-11 | no hi havia API d'incidències autenticada | CORREGIT backend |
| UC08-12 | no existeix UI `pay.prisma.cat/sif/incidencies` acreditada | PENDENT |
| UC08-13 | no existeix resum específic VERI*FACTU a la intranet actual | PENDENT |
| UC08-14 | cap diagrama d'activitats ACTUAL/FINAL específic UC-008 | CORREGIT documentalment |
| UC08-15 | UC-008 i UC-081 duplicaven lifecycle i noms de serveis | FRONTERA UNIFICADA |

## 3. Canvis de codi aplicats

### 3.1. Migració additiva

`2026_09_29_000010_add_incident_lifecycle.sql` amplia la capçalera sense reescriure migrations aplicades.

### 3.2. Repositori

`IncidentRepository::openDetailed()`:

- valida tipus, severitat i resource pair;
- valida existència de factura/pagament si s'aporten;
- genera `UUID_INCIDENT`;
- retorna ID i UUID;
- reutilitza per `IDEMPOTENCY_KEY`;
- rebutja mateixa clau amb payload lògic diferent.

`open()` continua existint per compatibilitat.

### 3.3. Historial

`IncidentActionRepository` implementa append-only sobre `sif_incident_action` amb idempotència d'acció.

### 3.4. Lifecycle

`IncidentLifecycleService` implementa llistat, vista, obertura, assignació, evidències, resolució, dismissal i reobertura amb autorització per rols.

### 3.5. API

`/api/incidents/manage.php` utilitza `InternalApiAuthenticator`, HMAC, timestamp, request-id i anti-replay.

### 3.6. Workers

- Redsys: `markIncident + openDetailed` dins una sola transacció.
- AEAT integritat: reject + incident dins una sola transacció.
- AEAT retry esgotat: `DEAD_LETTER + AEAT_DEAD_LETTER` dins una sola transacció.

## 4. Invariants consolidats

1. Obrir/tancar una incidència no mou diners.
2. Obrir/tancar una incidència no modifica una factura emesa.
3. Cap retry d'incidència crea un segon `CHARGE`, factura o registre fiscal per defecte.
4. La mateixa causa/job pot reutilitzar expedient per clau idempotent.
5. Una clau reutilitzada amb un recurs/tipus diferent és conflicte.
6. `RESOLVED` exigeix evidència.
7. `DISMISSED` exigeix justificació i criteri de tancament.
8. Auditor read-only no muta.
9. Estat de cua local no equival automàticament a resposta remota.
10. La reparació real pertany al UC específic.

## 5. Frontera UC-008 / UC-081

Decisió aplicada:

- **UC-008:** cas mare, obertura/consulta/gestió i integracions.
- **UC-081:** especificació detallada del lifecycle intern.
- **Implementació compartida:** `IncidentLifecycleService` + `IncidentActionRepository`.
- No es crearà un segon `IncidentWorkflowService`.

## 6. Cobertura ACTUAL/FINAL

La fitxa UML UC-008 conté ara activitats ACTUAL/FINAL per:

1. obertura automàtica;
2. llistat;
3. detall;
4. triage/assignació;
5. investigació/evidències;
6. acció correctora;
7. resolució;
8. dismissal/reobertura;
9. resum intranet VERI*FACTU.

La UI ACTUAL es documenta com a absent quan no hi ha codi real; no s'ha inventat una pantalla existent.

## 7. Proves escrites

- `IncidentLifecycleTest`
- `IncidentLifecycleSchemaTest`
- ampliació `FiscalQueueProcessorTest`
- ampliació `HttpEndpointsTest`

A més continuen sent rellevants:

- `DocumentsAndIncidentsTest`
- `RedsysCallbackWorkerTest`
- `PayloadIdempotencyFlowTest`

## 8. Pendents bloquejants per considerar UC-008 complet

- [x] Executar migracions en `sif_test*` via CI.
- [x] Executar `php sif/tests/run-tests.php`: **555 passed, 0 failed**.
- [ ] Corregir qualsevol regressió detectada.
- [ ] Implementar UI del panell d'incidències.
- [ ] Configurar i provar rols reals.
- [ ] Implementar resum/enllaç read-only des de la intranet.
- [ ] Provar concurrència/idempotència amb dues peticions simultànies.
- [ ] Provar rollback Redsys si falla la inserció d'incidència.
- [ ] Validar dades sensibles/retenció d'evidències.
- [ ] Proves preproducció i captura d'evidències.

## 9. Estat

```text
DOCUMENTAT      = AUDITAT I ACTUALITZAT
IMPLEMENTAT     = PARCIAL BACKEND
VERIFICAT       = ESTÀTICAMENT + CI PHP/MYSQL
PROVAT          = SUITE BACKEND CI · 555 PASSED / 0 FAILED
UI              = PENDENT
UML CLASSES     = ACTUALITZAT
UML SEQÜÈNCIA   = ACTUALITZAT
UML ACTIVITATS  = ACTUAL/FINAL CREAT
TRAÇABILITAT    = ACTUALITZADA
TANCAMENT UC    = NO
```


## 10. Evidència d'execució

| Evidència | Resultat |
| --- | --- |
| PR post-merge | #21 |
| Run 36638546735 · UC-111 integration verification | **555 passed, 0 failed** |
| Run 36638546786 · SIF PHP and MySQL checks | **555 passed, 0 failed** |
| Lint PHP | **PASS** |
| PHP / MySQL del segon run | PHP 8.4 / MySQL 8.4 |
| BD legacy de test | creada i usada pel workflow |
| E2E navegador / preproducció | **PENDENT** |
