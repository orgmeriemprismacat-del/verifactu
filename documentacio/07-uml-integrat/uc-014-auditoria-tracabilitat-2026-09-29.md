# UC-014 — Auditoria detallada i matriu de traçabilitat

**Data:** 29/09/2026  
**Branca d'auditoria:** `audit/uc-014-completa-2026-09-29`  
**Estat global:** **DOC AMPLIADA / IMP SIF AVANÇADA / E2E INTERN + TOOLING PREPRODUCCIÓ VERIFICATS PER CI / E2E REDSYS-PREPRODUCCIÓ I TALL PRODUCTIU PENDENTS**.

## 1. Evidència revisada

### Documentació
- [Fitxa funcional UC-014](../06-fitxes-funcionals/uc-014.md)
- [UML principal](uc-014-comprar-curs-redsys.md)
- [Classes ACTUAL/FINAL](uc-014-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-014-sequencies-actual-final.md)
- [Activitats per pàgina/apartat](uc-014-activitats-pagines-redsys-actual-final.md)

### Codi web actual/llegat
- `codi-drive/web-actual/PagamentCursAutomatic.php`
- `codi-drive/web-actual/pagina_pagament_automatic.php`
- `codi-drive/web-actual/pagina_confirmacio_inscripcio_automatic.php`
- `codi-drive/web-actual/pagina_efectuar_pagament_automatic.php`
- `codi-drive/web-actual/realitzaPagamentAutomatic.php`
- `codi-drive/web-actual/respostaOkPagamentAutomatic.php`
- `codi-drive/web-actual/respostaKoPagamentAutomatic.php`
- còpia candidata `codi-drive/pay-prisma-cat-canvis-verifactu/`

### Codi SIF
- `sif/src/Service/RedsysPaymentIntentService.php`
- `sif/src/Service/RedsysCallbackService.php`
- `sif/src/Service/RedsysCallbackWorker.php`
- `sif/src/Service/RedsysCallbackDispatcher.php`
- `sif/src/Service/RedsysCourseInvoiceService.php`
- `sif/src/Service/LegacyCourseInvoicePayloadBuilder.php`
- `sif/src/Service/RedsysInvoicePayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`
- `sif/src/Service/RedsysCoursePaymentStatusService.php`
- `sif/public/api/redsys/course-status.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysCourseStatusClient.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/CoursePaymentReturnStatus.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/respostaOkPagamentAutomatic.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/respostaKoPagamentAutomatic.php`

### Proves existents
- `RedsysCourseInvoiceServiceTest`
- `RedsysAsyncFlowTest`
- `RedsysPaymentIntentTest`
- `RedsysCourseEndToEndSimulatedTest`
- `RedsysLegacySyncingProcessorCourseTest`
- `RedsysCoursePreproductionBoundaryTest`
- `RedsysCoursePaymentStatusServiceTest`
- `RedsysCourseReturnBoundaryTest`
- `RedsysCourseCutoverBoundaryTest`

### Tooling de preproducció
- `sif/scripts/preflight-redsys-course.php`
- `sif/scripts/preflight-redsys-callback-queue.php`
- `sif/scripts/verify-redsys-course-preproduction.php`
- `sif/scripts/process-redsys-course.php`

## 2. Matriu per acció

| ID | Pàgina/apartat/acció | PHP/JS/servei | BD/efecte ACTUAL | FINAL | Estat |
| --- | --- | --- | --- | --- | --- |
| A14-01 | Mostrar confirmació | `PagamentCursAutomatic::mostrarPaginaConfirmacio` + JS extern | llegeix `inscripcions/curs` | vista basada en estat autoritatiu | ACTUAL contrastat / FINAL pendent integració |
| A14-02 | Mostrar pagament | `PagamentCursAutomatic::mostrar` | calcula pendent amb camps llegats | ledger + regles servidor | ACTUAL contrastat |
| A14-03 | Preparar targeta | `pagina_efectuar_pagament_automatic.php` | usa POST del navegador a l'ACTUAL | crear intent persistent | IMPLEMENTAT en la còpia candidata; desplegament no acreditat |
| A14-04 | Crear DS_ORDER | `time()` | ACTUAL llegat | `RedsysPaymentIntentService` + generador servidor | IMPLEMENTAT i cobert per tests |
| A14-05 | Enviar import TPV | `importPagare * 100` | ACTUAL llegat | `EXPECTED_AMOUNT` recomputat pel SIF | IMPLEMENTAT en el pont candidat; producció no acreditada |
| A14-06 | Callback | `doit.php` / `realitzaPagamentAutomatic.php` + callback SIF | GET + POST Redsys | `RedsysCallbackService` | CUTOVER EXPLÍCIT IMPLEMENTAT EN CÒPIA CANDIDATA; legacy 410 quan `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1`; desplegament no acreditat |
| A14-07 | Signatura | `RedsysAPI` | comparació no localitzada a l'ACTUAL original | `RedsysSignatureValidator` + reforç callback candidat | IMPLEMENTAT; desplegament real pendent d'acreditar |
| A14-08 | Comparar ordre/import | script llegat | no acreditat a l'ACTUAL original | intenció vs callback | IMPLEMENTAT i provat al circuit SIF/candidat |
| A14-09 | Facturar | INSERT directe a `factures` | factura llegada | `InvoiceService` | FINAL implementat |
| A14-10 | Numeració | MAX/últim + 1 | canal web | seqüència fiscal central | GAP P0 |
| A14-11 | Registrar cobrament | UPDATE `inscripcions.PAGAMENT` | acumulatiu | `payment_transaction/allocation` + projecció llegada | IMPLEMENTAT; wiring CURS verificat per CI |
| A14-12 | Fraccionament | `FRACCIO` + suma | mutació camp | moviments immutables + suma ledger | E2E intern parcial→complet verificat per CI |
| A14-13 | Callback duplicat | no acreditat a l'ACTUAL original | risc de segon efecte | idempotència | E2E intern duplicat verificat per CI |
| A14-14 | Correu | callback | enviament immediat | outbox/postcommit | pendent |
| A14-15 | Retorn OK/KO | `CoursePaymentReturnStatus` + `SifRedsysCourseStatusClient` + endpoint `course-status.php` | l'ACTUAL assumeix resultat del navegador | consulta read-only de la intenció/notificació/cua SIF | IMPLEMENTAT EN CÒPIA CANDIDATA + VERIFICAT CI; DESPLEGAMENT NO ACREDITAT |
| A14-16 | Sync acadèmica | barrejat/parcial | efectes postpagament | procés recuperable separat | pendent |
| A14-17 | Atribució per inscripció | implícita per IDPAG | sense moviment quantitatiu explícit | moviment EXTERNAL→INSCRIPCIÓ | DISSENY/PENDENT |

## 3. Mancances prioritzades

### P0 — abans de considerar el flux apte
1. Integrar ecommerce amb `RedsysPaymentIntentService`.
2. No confiar en import/curs/order funcionals transportats des del client o MerchantURL.
3. Verificar criptogràficament la notificació Redsys abans de qualsevol efecte.
4. Comparar DS_ORDER/import/moneda/terminal amb la intenció persistida.
5. Retirar la generació directa de factura i numeració del callback web.
6. Garantir idempotència de callback/job/factura/pagament.
7. Externalitzar secrets Redsys del codi.
8. Executar i conservar evidència de proves d'integració.

### P1
1. Completar atribució monetària explícita per inscripció.
2. Migrar fraccionament a moviments immutables.
3. Separar sincronització acadèmica de l'emissió fiscal.
4. Incorporar o materialitzar el JS necessari per completar traçabilitat.

## 4. Estat independent

| Dimensió | Estat |
| --- | --- |
| Documentació funcional | AMPLIADA; pendent validació de negoci |
| Classes UML | ACTUAL/FINAL documentades |
| Seqüències UML | ACTUAL/FINAL documentades |
| Activitats RM-037 | Documentades per 6 superfícies + variants |
| Codi llegat | Contrastat estàticament |
| Codi SIF Redsys | Implementació real localitzada |
| Adaptador ecommerce | IMPLEMENTAT EN CÒPIA CANDIDATA; DESPLEGAMENT NO ACREDITAT |
| Ledger per inscripció | IMPLEMENTAT PER `payment_transaction/allocation`; projecció llegada connectada |
| Tests | EXECUTATS EN CI; E2E INTERN, BOUNDARIES DE PREPRODUCCIÓ I RETORN AUTORITATIU VERDS ALS 3 WORKFLOWS |
| Preproducció | TOOLING PREPARAT I FAIL-CLOSED VERIFICAT; EXECUCIÓ REDSYS REAL NO ACREDITADA |
| Producció | NO ACREDITADA |

## 5. Criteri de tancament del UC-014

No marcar **TANCAT AMB EVIDÈNCIA** fins que:
- la web crea una intenció SIF abans de Redsys;
- callback i worker final s'usen en l'entorn objectiu;
- callback duplicat i payload incompatible s'han provat;
- pagament parcial/complet i reintent són reproduïbles;
- factura, registre fiscal, CHARGE, assignació i sync acadèmica tenen traça;
- les pantalles de retorn reflecteixen l'estat real;
- s'adjunta evidència de prova amb commit, BD, entorn, data i resultat.

## 6. Nota de seguretat

Durant l'auditoria s'han observat secrets Redsys literals en còpies de codi del repositori. Aquest document no els reprodueix. Cal rotació/externalització segons la política de secrets i verificar quina configuració està activa abans de desplegar.


## 7. Evidència CI i pla de tall final

El wiring de sincronització de curs al worker Redsys ha estat integrat a `main` i verificat per CI. El PR #55 afegeix també el retorn navegador basat en estat autoritatiu: `RedsysCoursePaymentStatusService` deriva `PENDING/PROCESSING/CONFIRMED/REJECTED/REVIEW` des de la intenció, notificació i cua; `CONFIRMED` exigeix job `PROCESSED` amb `UUID_FACTURA` i `UUID_PAYMENT`; si la consulta falla o la MerchantURL SIF no està activada, el retorn queda `UNVERIFIED/PENDING` i mai converteix l'URL OK del navegador en prova de cobrament. `SIF_REDSYS_CALLBACK_URL` defineix la destinació SIF, però **no activa per si sola el tall**. El tall exigeix `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1`; en aquest estat la URL SIF és obligatòria i HTTPS i els callbacks llegats candidats responen 410 abans de qualsevol efecte. Amb el flag a `0`, la còpia candidata manté el callback llegat com a via de transició/rollback.

`RedsysCourseEndToEndSimulatedTest` cobreix de forma integrada: intenció → callback validat → cua → worker → factura → `payment_transaction`/`payment_allocation` → projecció llegada; inclou callback duplicat i parcial→complet. A més, `RedsysCourseCutoverBoundaryTest` blinda l'activació explícita de MerchantURL, el 410 pre-efecte dels callbacks llegats i el rollback per flag mentre el llegat encara existeix. `RedsysCoursePreproductionBoundaryTest` verifica que el verificador falla tancat fora de `test/preproduction`, que la mutació queda darrere de `--execute`, que `--sync-legacy` exigeix evidència de projecció econòmica i que la sortida sanititza secrets/signatures/raw payloads. Els workflows `SIF PHP MySQL tests`, `SIF checks` i `UC-111 integration verification` han acabat en verd tant per als boundaries de preproducció com per als tests del retorn autoritatiu (`RedsysCoursePaymentStatusServiceTest` i `RedsysCourseReturnBoundaryTest`). Això acredita l'E2E **intern simulat**, el **tooling de preproducció** i el **retorn autoritatiu al repositori**, però **no** una execució contra Redsys/preproducció real ni el desplegament del pont candidat.

El procediment de tall operatiu queda definit a [UC-014 — Pla de tall final Redsys cap al SIF](uc-014-pla-tall-final-redsys-sif.md).
