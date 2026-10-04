# UC-003 — Auditoria exhaustiva i matriu de traçabilitat

**Data:** 03/10/2026  
**Branca de treball:** `audit/uc-003-2026-10-03`  
**Abast:** fitxa funcional, PHP/JS real, classes, seqüències, activitats per superfície, persistència, proves, runtime i mancances del processament asíncron Redsys.

## 1. Resultat executiu

A l'inici de l'auditoria, UC-003 tenia:
- fitxa funcional genèrica v1.1;
- fitxa/UML integrat;
- codi SIF asíncron real;
- proves reals;
- **però no** tenia fitxers separats de classes ACTUAL/FINAL, seqüències ACTUAL/FINAL, activitats per superfície ni auditoria de traçabilitat pròpia.

Aquesta passada crea el paquet separat, reconcilia la fitxa amb el codi actual i endureix dues condicions de consistència del worker.

### Estat per bloc

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa funcional específica | sí, actualitzada en aquesta branca | n/a | contrastada amb codi | revisió funcional final |
| UML integrat | sí | n/a | contrastat | actualitzar quan canviï runtime |
| Classes ACTUAL/FINAL | sí, nou | n/a | contrast estàtic | render/evidència si es requereix |
| Seqüències ACTUAL/FINAL | sí, nou | n/a | contrast estàtic | runtime real |
| Activitats per superfície | sí, 8 superfícies | n/a | contrast estàtic | JS candidat/runtime |
| Callback SIF | sí | sí | proves existents | preproducció Redsys |
| Dedupe callback/job | sí | sí | proves existents | evidència preprod |
| Worker/cua | sí | sí | **suite `RedsysCallbackWorkerTest` verda al run SIF #1204, incloses les 2 proves noves** | runtime/preproducció |
| Resultat terminal complet | sí | **sí, corregit 03/10** | **`testIncompleteSuccessfulResultBecomesIncident` PASS** | preproducció |
| Fencing `LOCKED_BY` | sí | **sí, corregit 03/10** | **`testPreviousWorkerCannotFinalizeReclaimedJob` PASS** | preproducció |
| Handlers CURS/PACK/GRUP/REGAL/USOC | sí | sí | suites existents per diferents nivells | preprod per canal |
| Fund allocation CURS/PACK | sí | sí | proves prèvies del projecte | evidència Redsys real |
| JS web ACTUAL | sí | sí | lectura estàtica | n/a per callback |
| JS pay candidat | sí com a referència | **no localitzat al snapshot** | no | incorporar/evidenciar |
| Callback llegat | sí | sí | lectura estàtica | retirar després cutover |
| Factura preexistent | sí com a risc | **no acreditat de forma general** | no | P0 abans de casos "factura abans de pagar" |
| Worker operatiu | scripts/preflight sí | codi sí | lectura estàtica | cron/supervisor/config/alertes reals |

## 2. Traçabilitat de la documentació

| Peça | Abans 03/10 | Després 03/10 |
| --- | --- | --- |
| `documentacio/06-fitxes-funcionals/uc-003.md` | genèrica / draft | específica i reconciliada |
| `uc-003-processar-cobrament-redsys-asincron.md` | existent | reconciliat amb troballes actuals |
| `uc-003-classes-actual-final.md` | no existia | creat |
| `uc-003-sequencies-actual-final.md` | no existia | creat |
| `uc-003-activitats-actual-final.md` | no existia | creat |
| `uc-003-inventari-codi-php-js-actual-final-2026-10-03.md` | no existia | creat |
| `uc-003-auditoria-tracabilitat-2026-10-03.md` | no existia | creat |

## 3. Traçabilitat executable

### 3.1 Recepció

| Responsabilitat | Codi | Estat |
| --- | --- | --- |
| endpoint | `sif/public/api/redsys/callback.php` | IMPLEMENTAT |
| HMAC/envelope | `RedsysSignatureValidator` | IMPLEMENTAT |
| correlació intenció | `RedsysCallbackService` + `RedsysPaymentIntentRepository` | IMPLEMENTAT |
| notificació/dedupe | `RedsysNotificationRepository` | IMPLEMENTAT |
| job asíncron | `RedsysCallbackQueueRepository::enqueue` | IMPLEMENTAT |

### 3.2 Processament asíncron

| Responsabilitat | Codi | Estat |
| --- | --- | --- |
| claim | `RedsysCallbackQueueRepository::claimNext` | IMPLEMENTAT |
| stale recovery | `recoverStaleLocks` | IMPLEMENTAT |
| dispatch | `RedsysCallbackDispatcher` | IMPLEMENTAT |
| CURS | `RedsysCourseInvoiceService` | IMPLEMENTAT |
| PACK | `RedsysPackInvoiceService` | IMPLEMENTAT |
| GRUP | `RedsysGroupInvoiceService` | IMPLEMENTAT |
| REGAL | `RedsysGiftInvoiceService` | IMPLEMENTAT |
| USOC alumne | `RedsysUsocInvoiceService` | IMPLEMENTAT |
| sync posterior | `RedsysLegacySyncingProcessor` | IMPLEMENTAT/PARCIAL segons variant |
| retry/incident | `RedsysCallbackWorker` | IMPLEMENTAT |
| worker ownership terminal | repository + worker | **IMPLEMENTAT 03/10** |
| completed result contract | worker | **IMPLEMENTAT 03/10** |

## 4. Traçabilitat PHP/JS

### PHP llegat/candidat

- `codi-drive/web-actual/pagina_efectuar_pagament_automatic.php`
- `codi-drive/web-actual/realitzaPagamentAutomatic.php`
- `codi-drive/web-actual/PagamentCursAutomatic.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysCourseIntentClient.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/doit.php`

### JavaScript

- localitzat: `codi-drive/web-actual/js1619773569/mostrarEfectuarPagamentAutomatic.js`;
- no localitzat al snapshot candidat: `pay.prisma.cat/js/mostrarEfectuarPagament.js`.

**Conclusió:** no hi ha JS necessari al callback SIF. La mancança JS és del pont de frontend candidat, no del processador asíncron servidor.

## 5. Correccions de codi aplicades

### UC03-FIX-01 — no marcar `PROCESSED` un resultat incomplet

**Problema observat:** `RedsysCallbackWorker::runOne()` acceptava qualsevol `array` retornat pel processador i `markProcessed()` permetia persistir `UUID_FACTURA`/`UUID_PAYMENT` nuls.

**Risc:** cua terminal aparentment correcta sense prova de factura+cobrament, fet que trenca la reconstrucció i podria provocar falsos positius de confirmació.

**Correcció:** `assertCompletedResult()` exigeix:
- `ok === true`;
- `uuid_factura` string no buit;
- `uuid_payment` string no buit.

Si falla, el cas es tracta com conflicte funcional i deriva a `INCIDENT`.

### UC03-FIX-02 — fencing del propietari del job

**Problema observat:** `markProcessed`, `markRetry` i `markIncident` comprovaven només `STATUS='PROCESSING'`. Un worker A caducat podia acabar després que B hagués recuperat i reclamat el mateix job.

**Risc:** A podia modificar l'estat d'un job propietat de B.

**Correcció:** les transicions terminals accepten `workerId` i el worker real executa l'UPDATE amb `LOCKED_BY = workerId`. Un conflicte de pèrdua de propietat es propaga sense convertir el job del worker nou a retry/incident.

**Compatibilitat:** el paràmetre del repositori és opcional per no trencar consumidors auxiliars existents; el camí productiu del worker sempre el passa.

### UC03-FIX-03 — factura UC-004 prèvia + cobrament Redsys

**Problema:** una clau idempotent Redsys no reutilitza una factura UC-004 emesa amb una altra clau.  
**Correcció:** nou `RedsysCoveredInvoicePaymentService`, precondició transaccional de `PaymentService`, guard específic de `InvoiceService` i lock compartit per origen a `fact_rels`. El ledger CURS accepta ara pagaments parcials contra una factura completa existent, mantenint una sola factura fiscal. L'estat de factura parcial canònic és `PARTIAL`; `PARTIALLY_PAID` queda restringit a la projecció de sincronització llegada.

## 6. Proves

### Ja existents i revisades

- `RedsysAsyncFlowTest::testAuthorizedCallbackIsProcessedAsynchronouslyFromSnapshot`;
- `RedsysAsyncFlowTest::testTwoConnectionsCannotClaimSameJob`;
- `RedsysCallbackTest`: duplicat, contradicció, amount mismatch, IDPAG extern, payload normalitzat, cèntims i denegació;
- `RedsysCallbackWorkerTest`: processament, retry, incidències, rollback, redacció, stale locks i intents màxims.

### Afegides 03/10

1. `testIncompleteSuccessfulResultBecomesIncident`
   - processor retorna `ok=true` però sense `uuid_payment`;
   - expectativa: `INCIDENT`, sense UUID terminals a la cua.

2. `testPreviousWorkerCannotFinalizeReclaimedJob`
   - worker A reclama;
   - lock caduca;
   - worker B reclama;
   - A intenta `markProcessed`, `markRetry` i `markIncident`;
   - expectativa: 409 en els tres casos i `LOCKED_BY=worker-b`.

**Estat de verificació 03/10:** les dues proves noves i tota la classe `RedsysCallbackWorkerTest` han passat al workflow **SIF checks #1204**. El job global acaba vermell amb **919 passed / 6 failed**, però les sis fallades són de baseline i es reprodueixen idènticament al PR #136, que parteix del mateix SHA base i no incorpora aquests canvis UC-003:
- 5 proves PACK de boundary/privacitat/transports;
- `RedsysSignatureValidatorTest::testValidNotificationDecodesAndNormalizesSignedPayload`, per un hash esperat desactualitzat/diferent.
Per tant, el hardening UC-003 d'aquest PR queda **VERIFICAT EN PROVES ESPECÍFIQUES**; el CI global continua bloquejat per baseline i no es presenta com a verd.

## 7. Mancances per severitat

### P0 — abans de rollout d'escenaris afectats

**GAP-003-P0-01 · factura fiscal preexistent — IMPLEMENTAT A BRANCA / CI PENDENT.**  
Per CURS amb cobertura UC-004, `RedsysCoveredInvoicePaymentService`:
- resol `invoice_before_payment_coverage` per `INSCRIPCIO`;
- valida factura `ISSUED`, `EMESA_ABANS_COBRAMENT=1`, total contractual i línia única;
- registra/reutilitza `PAYMENT|REDSYS|ORDER:<DS_ORDER>` sobre el `UUID_FACTURA` existent;
- comprova saldo pendent sota lock i rebutja sobrepagament;
- suporta 50+70 sobre una factura de 120 sense nova emissió;
- deixa el guard de cobertura fora del payload/hash fiscal per compatibilitat amb reintents antics;
- serialitza UC-004 i Redsys sobre l'origen indexat de `fact_rels`; UC-004 fa rollback si ja hi ha factura Redsys `ISSUED`.
La generalització a altres variants continua sent una decisió específica de cada UC.

### P0 restant

**GAP-003-P0-02 · cutover real.**  
Mentre `doit.php`/`realitzaPagamentAutomatic.php` estiguin actius, el llegat pot continuar escrivint factures i inscripcions. Cal executar i evidenciar pausa → drain → MerchantURL SIF → 410/retirada.

### P1

**GAP-003-P1-01 · JS candidat absent del snapshot.** Incorporar la versió desplegable de `pay.prisma.cat/js/mostrarEfectuarPagament.js` o una evidència immutable equivalent.

**GAP-003-P1-02 · worker runtime.** Acreditar cron/supervisor, worker IDs, freqüència, límits, restart, alertes i observabilitat.

**GAP-003-P1-03 · variants i atribució.** CURS/PACK tenen allocation quantitativa. GRUP/REGAL/USOC tenen models específics; verificar per cada variant que la reconstrucció participant/beneficiari/pagador és suficient i no assumir equivalència.

**GAP-003-P1-04 · preproducció.** Callback real amb Redsys, duplicat, retry, lock stale, parcial/complet i error posterior al commit.

### P2

- mètriques de latència de cua i antiguitat del job;
- runbook de replay/reconciliació;
- evidència exportable que uneixi intenció → notificació → job → factura → payment → assignacions → sync.

## 8. Casos de prova operatius obligatoris

| ID | Escenari | Resultat esperat |
| --- | --- | --- |
| RC-03-01 | callback autoritzat sense factura prèvia | una factura + un payment; job PROCESSED |
| RC-03-02 | factura prèvia compatible | cap segona factura; payment aplicat a l'existent |
| RC-03-03 | callback duplicat exacte | un sol efecte econòmic/fiscal |
| RC-03-04 | callback contradictori | 409 + incidència; cap efecte nou |
| RC-03-05 | error tècnic handler | RETRY amb backoff |
| RC-03-06 | 409/422 handler | INCIDENT |
| RC-03-07 | resultat sense uuid_payment | INCIDENT |
| RC-03-08 | A stale / B reclaims / A finishes | A no pot tocar job de B |
| RC-03-09 | pagament parcial → següent pagament | totals acumulats sense duplicats |
| RC-03-10 | commit SIF correcte + sync llegada falla | no repetir CHARGE/factura; retry/reconciliació idempotent |
| RC-03-11 | cutover drain | sessions antigues drenen, noves van només al SIF |
| RC-03-12 | variant CURS/PACK/GRUP/REGAL/USOC | traça completa segons contracte propi |

## 9. Criteri de tancament

### Auditoria tècnica/documental
Es pot considerar tancada quan:
- fitxa, classes, seqüències, activitats i inventari estan reconciliats;
- CI del head passa;
- no queda cap troballa de codi sense classificar.

### Implementació funcional general
No es considera tancada per al conjunt de casos Redsys mentre:
- la ruta de factura preexistent no tingui CI/preproducció/evidència;
- el JS candidat no estigui traçat si forma part del desplegament;
- no hi hagi evidència dels variants requerits.

### Acceptació operativa
Requereix:
- preflight verd a l'entorn objectiu;
- callback Redsys real;
- worker/supervisor real;
- cutover/drain;
- duplicat/retry/incidència;
- reconstrucció completa per `DS_ORDER`;
- evidències conservades.

## 10. Estat al final d'aquesta passada

**DOCUMENTAT:** paquet UC-003 completat en aquesta branca.  
**IMPLEMENTAT:** nucli asíncron + fencing/resultat complet + ruta CURS de cobrament Redsys sobre factura UC-004 existent, inclosos parcials i serialització d'origen.  
**VERIFICAT:** les correccions 03/10 passen la suite específica del worker al runner GitHub; el CI global manté 6 fallades de baseline reproduïdes fora d'aquest PR.  
**PENDENT:** CI/preproducció de la nova ruta de factura preexistent, JS candidat, runtime/cutover, evidència final i sanejament del baseline CI compartit.

**Classificació temporal:** `AUDIT_PACKAGE_COMPLETE / UC003_TESTS_PASS / BASELINE_CI_RED / OPERATIONAL_PENDING`.
