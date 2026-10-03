# UC-003 — Inventari de codi PHP/JS ACTUAL, candidat i FINAL

**Data:** 03/10/2026  
**Objectiu:** identificar el codi real que participa o limita el processament asíncron de Redsys i evitar confondre una ruta web amb el callback SIF.

## 1. Resum

| Capa | PHP | JS | Auditoria |
| --- | --- | --- | --- |
| Web ACTUAL | localitzat | localitzat | complet per la superfície principal de curs |
| Pay candidat | localitzat | **referenciat però no present al snapshot** | parcial |
| Callback llegat | localitzat | n/a | localitzat; encara muta llegat si fallback actiu |
| Callback SIF FINAL | localitzat | n/a | complet a nivell estàtic |
| Worker/cua FINAL | localitzat | n/a | complet a nivell estàtic |
| Handlers FINAL | localitzats | n/a | 5 source types localitzats |
| Scripts operatius | localitzats | n/a | preflight + worker CLI localitzats |
| Proves | localitzades | n/a | integració callback/worker/async flow |

## 2. Web ACTUAL — dependència pre-TPV

### PHP

- `codi-drive/web-actual/pagina_efectuar_pagament_automatic.php`
  - gate servidor abans de construir Redsys;
  - prepara `DS_ORDER`, amount, terminal, merchant code i MerchantURL;
  - flags `SIF_REDSYS_COURSE_CUTOVER_ENABLED` / `SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED`;
  - mentre no hi ha cutover final apunta al callback llegat.
- `codi-drive/web-actual/realitzaPagamentAutomatic.php`
  - callback llegat;
  - valida signatura/envelope i dades Redsys;
  - encara conté `INSERT INTO factures` i `UPDATE inscripcions` quan no està retirat.
- `codi-drive/web-actual/PagamentCursAutomatic.php`
  - genera la UI de pagament i el formulari que porta a `pay.prisma.cat/confirmation/`;
  - pertany sobretot al pre-TPV (UC-014/UC-063), però és frontera d'entrada del UC-003.

### JavaScript

- `codi-drive/web-actual/js1619773569/mostrarEfectuarPagamentAutomatic.js`
  - localitzat;
  - `#form_enviar_dades` fa `$('#frm').submit()`;
  - `#form_cancelar_dades` només modifica la UI;
  - no processa signatura, no crea factura ni registra cobrament.
- JS de header/footer/butlletí dins del mateix fitxer: adjacent i **fora del domini econòmic del UC-003**.

**Conclusió JS ACTUAL:** la part econòmica és server-side; el JS és transport/UX i no és font d'autoritat.

## 3. Pay candidat / cutover

### PHP

- `codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php`
  - usa `SifRedsysCourseIntentClient`;
  - obté `DS_ORDER` i import des del SIF;
  - selecciona `doit.php` o `SIF_REDSYS_CALLBACK_URL` segons cutover/drain;
  - bloqueja `cutover=1, drain=0`.
- `codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysCourseIntentClient.php`
  - crida API interna HMAC `/api/redsys/course-intent.php`;
  - exigeix HTTPS i resposta d'intenció completa.
- `codi-drive/pay-prisma-cat-canvis-verifactu/doit.php`
  - fallback de callback;
  - retorna 410 quan cutover+drain ja s'han completat;
  - si segueix actiu, encara executa lògica llegada de factura/pagament.
- `codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentAutomatic.php`
  - altra còpia candidata de callback llegat inventariada prèviament;
  - no s'ha de considerar autoritat FINAL.

### JavaScript

El PHP candidat carrega:

`https://pay.prisma.cat/js/mostrarEfectuarPagament.js?ver=2.0`

Però **no s'ha localitzat** `codi-drive/pay-prisma-cat-canvis-verifactu/js/mostrarEfectuarPagament.js` ni una còpia equivalent dins del snapshot candidat.

**Classificació:** `PENDENT D'EVIDÈNCIA`. No es pot assegurar que el JS desplegat sigui el mateix que el previst pel PHP candidat.

## 4. Endpoint SIF FINAL

- `sif/public/api/redsys/callback.php`
  - endpoint públic de notificació;
  - carrega configuració Redsys;
  - construeix `RedsysSignatureValidator`;
  - lliura el payload verificat a `RedsysCallbackService`;
  - retorna JSON;
  - **sense JavaScript i sense emissió fiscal directa**.

## 5. Validació i recepció FINAL

- `sif/src/Service/RedsysSignatureValidator.php`
  - versions HMAC SHA-512 V2 i SHA-256 V1;
  - validació constant-time;
  - valida format `DS_ORDER`, codi de resposta, transaction type, merchant code, EUR i terminal;
  - normalitza cèntims sense confiar en floats per validar `Ds_Amount`.
- `sif/src/Service/RedsysCallbackService.php`
  - transacció de recepció;
  - busca intenció per `DS_ORDER` amb lock;
  - compara amount/currency/terminal;
  - registra notificació;
  - encua només resposta autoritzada.
- `sif/src/Repository/RedsysPaymentIntentRepository.php`
  - persistència/consulta d'intenció.
- `sif/src/Repository/RedsysNotificationRepository.php`
  - dedupe per ordre;
  - compara callback repetit i detecta contradiccions.
- `sif/src/Repository/RedsysCallbackQueueRepository.php`
  - enqueue, claim, retry, incident, processed, stale recovery;
  - **endurit en aquesta auditoria** perquè les transicions terminals puguin exigir `LOCKED_BY = workerId`.

## 6. Worker i dispatch FINAL

- `sif/src/Service/RedsysCallbackWorker.php`
  - recover + claim + process;
  - backoff 1/5/15/60;
  - incidència en conflictes funcionals o intents exhaurits;
  - **endurit en aquesta auditoria**: no accepta `PROCESSED` si `ok !== true` o falta `uuid_factura` / `uuid_payment`;
  - propaga un conflicte de pèrdua de lock sense intentar sobreescriure el worker nou.
- `sif/src/Service/RedsysLegacySyncingProcessor.php`
  - embolcall del dispatcher;
  - CURS: projecció de pagament llegada + outbox si està configurat;
  - altres handlers: executa `legacy_sync` quan el resultat l'aporta.
- `sif/src/Service/RedsysCallbackDispatcher.php`
  - valida `SOURCE_TYPE` i snapshot;
  - envia al handler corresponent.

## 7. Handlers per SOURCE_TYPE

| SOURCE_TYPE | Handler | Factura+cobrament | Efectes específics observats |
| --- | --- | --- | --- |
| CURS | `RedsysCourseInvoiceService` | sí | fund allocation per inscripció; promoció JASOM; sync curs |
| PACK | `RedsysPackInvoiceService` | sí | fund allocations per items; outbox pack; legacy sync |
| GRUP | `RedsysGroupInvoiceService` | sí | legacy_sync; no s'ha localitzat allocation service equivalent dins del constructor del worker |
| REGAL | `RedsysGiftInvoiceService` | sí | emet dret/regal; legacy_sync |
| USOC_ALUMNE | `RedsysUsocInvoiceService` | sí | registra cas de finançament i deixa factura d'entitat com a operació explícita posterior |

Serveis d'atribució localitzats:
- `CourseEnrollmentFundAllocationService`;
- `PackEnrollmentFundAllocationService`;
- `EnrollmentFundMovementRepository`.

## 8. Nucli fiscal/econòmic invocat

- `sif/src/Service/RedsysInvoicePayloadBuilder.php`
  - incorpora notificació VALIDATED;
  - crea idempotency keys Redsys per factura i payment;
  - conserva `DS_ORDER`/IDPAG a les relacions.
- `sif/src/Service/InvoiceService.php`
  - emet/reutilitza factura i, amb payment inicial, retorna `uuid_payment`.
- `sif/src/Service/PaymentService.php` / repositoris de pagament
  - nucli de moviments econòmics en fluxos de pagament dedicats.
- persistència afectada: `factura`, `factura_linia`, `factura_registres`, `fiscal_queue`, `payment_transaction`, `payment_allocation`, `fact_rels` i, segons variant, `enrollment_fund_movement`, outbox/drets/casos comercials.

## 9. Operació

- `sif/scripts/preflight-redsys-callback-queue.php`
  - comprova entorn, OpenSSL, PDO MySQL, clau Redsys, DB SIF/llegada, taules i columnes.
- `sif/scripts/process-redsys-callback-queue.php`
  - només CLI;
  - bloqueja producció si `SIF_REDSYS_WORKER_ALLOW_PRODUCTION != 1`;
  - exigeix `--worker-id`;
  - límit 1..100;
  - construeix handlers i executa `runOne()`.

**No acreditat en aquesta lectura estàtica:** cron/supervisor desplegat, periodicitat, alertes, variables reals, secrets, connectivitat i MerchantURL real.

## 10. Proves de codi localitzades

- `sif/tests/Integration/RedsysAsyncFlowTest.php`
  - no crea factura/pagament durant HTTP;
  - worker crea efectes després;
  - dues connexions no reclamen el mateix job.
- `sif/tests/Integration/RedsysCallbackTest.php`
  - duplicat exacte;
  - callback contradictori;
  - mismatch d'import;
  - IDPAG extern ignorat;
  - payload de validador;
  - cèntims exactes;
  - denegació sense efecte fiscal.
- `sif/tests/Integration/RedsysCallbackWorkerTest.php`
  - claim/process;
  - retry;
  - incidència funcional;
  - rollback si falla crear incidència;
  - redacció d'errors;
  - stale locks;
  - intents màxims;
  - **afegit 03/10:** resultat incomplet → incident;
  - **afegit 03/10:** worker antic no pot processed/retry/incident un job reclamat per un altre.

## 11. Factura prèvia UC-004 — implementació CURS a la branca

La ruta CURS ja no depèn exclusivament de la clau d'emissió Redsys. `RedsysCourseInvoiceService` consulta `RedsysCoveredInvoicePaymentService` abans d'emetre. Quan `invoice_before_payment_coverage` resol una única inscripció:
1. valida que la factura sigui `ISSUED`, `EMESA_ABANS_COBRAMENT=1`, amb total contractual i línia d'inscripció coherents;
2. registra/reutilitza el `CHARGE` `PAYMENT|REDSYS|ORDER:<DS_ORDER>` sobre el `UUID_FACTURA` existent;
3. admet cobraments parcials fins al saldo pendent;
4. rebutja sobrepagament o cobertura incompatible amb 409;
5. si no existeix cobertura, `InvoiceService::issueInvoice(payload, true)` bloqueja/reconsulta l'origen abans de crear una nova factura.

El guard és un paràmetre de control fora del payload fiscal, de manera que no altera `IDEMPOTENCY_PAYLOAD_HASH` ni trenca reintents d'ordres Redsys creades abans del canvi.

**Pendent de tancament:** verificació CI/preproducció de CURS, una prova concurrent de dues connexions per la cursa UC-004↔Redsys i decidir si PACK/GRUP/REGAL/USOC necessiten una regla equivalent segons el seu contracte propi.

## 12. Conclusió de l'inventari

**PHP FINAL necessari per UC-003:** localitzat.  
**JS FINAL del callback:** no aplica per disseny.  
**JS del canal ACTUAL:** localitzat.  
**JS del pont candidat:** no present al snapshot.  
**Codi llegat fiscal directe:** encara existeix i només queda neutralitzat quan el cutover/drain s'executa realment.
