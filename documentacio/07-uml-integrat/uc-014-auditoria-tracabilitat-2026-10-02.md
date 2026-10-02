# UC-014 — Auditoria exhaustiva i matriu de traçabilitat

**Data:** 02/10/2026  
**Base inicial:** `main@68c4534f31a6499a80f928e0e61bb816066b1fbd` · **revalidada després de sincronitzar:** `main@5cc0410018929bed53d0e2e2078f4b4c4f2bf6f7`  
**Auditoria anterior:** [29/09/2026](uc-014-auditoria-tracabilitat-2026-09-29.md)  
**Inventari executable actualitzat:** [PHP/JS ACTUAL, pont candidat i SIF](uc-014-inventari-codi-php-js-actual-final-2026-10-02.md)

## 1. Resultat executiu

El UC-014 **sí disposa** de fitxa funcional, UML integrat, classes ACTUAL/FINAL, seqüències ACTUAL/FINAL i activitats per pàgina. També existeix el PHP/JS real de les superfícies web i el circuit SIF final. El problema principal de l'auditoria anterior ja no és absència d'artefactes, sinó **desalineació** entre documents antics i el codi fusionat després.

Estat 02/10/2026:

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa + UML | Sí | n/a | contrast de codi | validació operativa final |
| PHP/JS ACTUAL | Sí | Sí | boundary/unit en aquesta branca | CI de la branca + desplegament |
| Intenció SIF | Sí | Sí | CI PR #79 i proves dedicades | preproducció real |
| Callback/cua/worker | Sí | Sí | CI intern | Redsys real |
| Factura/cobrament SIF | Sí | Sí | E2E intern | evidència preprod |
| Atribució quantitativa per inscripció | Sí | Sí: `EXTERNAL_ALLOCATION` | PR #95: 841/0 + 4 workflows verds | evidència Redsys real |
| Sync llegat | Sí | Sí | E2E intern | evidència preprod |
| Retorn navegador autoritatiu | Sí | Sí al pont candidat | proves boundary/status | desplegament |
| Outbox CURS | Sí | productor sí | E2E intern | delivery UC-58 |
| Cutover | Sí | flag/guards sí | boundary test | execució real i retirada definitiva del llegat |

## 2. Evidència de codi

### 2.1 Web ACTUAL

- `PagamentCursAutomatic.php`
- `pagina_confirmacio_inscripcio_automatic.php`
- `pagina_pagament_automatic.php`
- `pagina_efectuar_pagament_automatic.php`
- `realitzaPagamentAutomatic.php`
- `respostaOkPagamentAutomatic.php`
- `respostaKoPagamentAutomatic.php`
- `ajax/mostrar_confirmacio_inscripcio_automatic.php`
- `ajax/mostrar_pagina_pagament_automatic.php`
- `inc/JasomNovicePaymentGate.php`

JS localitzat:
- `mostrarConfirmacioInscripcioAutomatic.min.js`
- `mostrarPagamentAutomatic.min.js`
- `mostrarEfectuarPagamentAutomatic.js`
- `mostrarRespostaOkPagamentAutomatic.min.js`
- `mostrarRespostaKoPagamentAutomatic.min.js`

### 2.2 Pont candidat

- `SifRedsysCourseIntentClient.php`
- `SifRedsysCourseStatusClient.php`
- `CoursePaymentReturnStatus.php`
- `pagina_efectuar_pagament_automatic.php`
- `doit.php`
- `realitzaPagamentAutomatic.php`
- retorns OK/KO

### 2.3 SIF

- `RedsysCoursePaymentIntentService`
- `RedsysPaymentIntentService`
- `RedsysSignatureValidator`
- `RedsysCallbackService`
- `RedsysCallbackQueueRepository`
- `RedsysCallbackWorker`
- `RedsysCallbackDispatcher`
- `RedsysCourseInvoiceService`
- `LegacyCourseInvoicePayloadBuilder`
- `RedsysInvoicePayloadBuilder`
- `InvoiceService`
- `PaymentRepository`
- `RedsysLegacySyncingProcessor`
- `CourseLegacyPaymentSyncService`
- `CoursePaymentNotificationService`
- `NotificationOutboxRepository`
- `RedsysCoursePaymentStatusService`

## 3. Matriu exhaustiva per acció

| ID | Acció | ACTUAL | FINAL/SIF | Estat 02/10 |
| --- | --- | --- | --- | --- |
| A14-01 | Mostrar confirmació | PHP + AJAX + JS localitzats | vista continua sent llegat, autoritat al servidor | DOCUMENTAT/IMPLEMENTAT |
| A14-02 | Mostrar saldo/opcions | `PagamentCursAutomatic` usa `A_PAGAR/PAGAMENT/FRACCIONAT` | intent rellegeix saldo i fraccionament | IMPLEMENTAT |
| A14-03 | Preparar pagament | gate servidor abans del formulari; fallback encara no és intent SIF | `RedsysCoursePaymentIntentService` | IMPLEMENTAT; deploy candidat pendent |
| A14-04 | Generar ordre | fallback usa ordre llegada | `RedsysDsOrderGenerator` + intent persistent | IMPLEMENTAT al candidat/SIF |
| A14-05 | Fixar import | gate ACTUAL valida pendent/fraccionament a BD en aquesta branca | `EXPECTED_AMOUNT` recomputat | IMPLEMENTAT + proves de política |
| A14-06 | Callback | fallback endurit en aquesta branca | `RedsysSignatureValidator` + `RedsysCallbackService` | IMPLEMENTAT |
| A14-07 | Signatura | ara validada abans d'efectes al fallback de branca | validació criptogràfica SIF | IMPLEMENTAT; CI branca pendent |
| A14-08 | Order/import | ara comparats al fallback de branca | intenció vs callback, inclou divisa/terminal | IMPLEMENTAT |
| A14-09 | Autorització TPV | resposta Redsys | només autorització positiva arriba a handler | IMPLEMENTAT |
| A14-10 | Numeració fiscal | llegat conserva numeració pròpia mentre hi hagi fallback | `FiscalSequenceRepository::next()` via `InvoiceService` | **FINAL IMPLEMENTAT**; retirada llegat pendent |
| A14-11 | Registrar cobrament | muta `PAGAMENT` en callback llegat | `payment_transaction` + `payment_allocation`, després projecció | IMPLEMENTAT/VERIFICAT intern |
| A14-12 | Fraccionament | camp llegat + gate servidor | intents per tram + suma moviments confirmats | IMPLEMENTAT; parcial→complet E2E intern |
| A14-13 | Duplicat | fallback no és l'autoritat final | notification/job/invoice/payment idempotents | VERIFICAT intern |
| A14-14 | Notificació | correu immediat en fallback | `notification_outbox` post-sync | productor IMPLEMENTAT; delivery UC-58 PENDENT |
| A14-15 | Retorn OK/KO | actual llegat no és prova fiscal | status SIF read-only | IMPLEMENTAT/VERIFICAT al candidat |
| A14-16 | Sync inscripció | barrejat al callback | `CourseLegacyPaymentSyncService` post-SIF | IMPLEMENTAT/VERIFICAT intern |
| A14-17 | Atribució inscripció | `IDPAG` + fila llegada | `CourseEnrollmentFundAllocationService` → `enrollment_fund_movement.EXTERNAL_ALLOCATION` per `DS_ORDER + ID_INSC`, vinculat a `UUID_PAYMENT`/`UUID_FACTURA` | **IMPLEMENTAT I VERIFICAT CI al PR #95** |
| A14-18 | Outbox | no existeix al llegat | `CoursePaymentNotificationService` | IMPLEMENTAT; transport pendent |
| A14-19 | Cutover | no aplicable a l'ACTUAL | flag explícit + callback llegat 410 al candidat | IMPLEMENTAT/VERIFICAT boundary |
| A14-20 | Secrets | literals històrics trobats | candidat usa entorn; fallback s'externalitza en aquesta branca | CODI CORREGIT; **rotació P0 pendent** |

## 4. Correccions aplicades en aquesta auditoria

1. `JasomNovicePaymentGate` llegeix `FRACCIONAT` de BD i rebutja imports parcials quan no pertoquen.
2. Les dues còpies del gate (ACTUAL/candidat) mantenen la mateixa política.
3. `JasomNovicePaymentGateTest` incorpora casos explícits fraccionat/no fraccionat.
4. `PagamentCursAutomatic.php` inicialitza correctament `$recentTitulat`.
5. Checkout ACTUAL/candidat:
   - fraccionament autoritatiu;
   - sortida HTML sanejada;
   - variable `nomAlumnePag` inicialitzada.
6. Checkout ACTUAL deixa de tenir credencial Redsys literal i exigeix configuració d'entorn.
7. Callback ACTUAL:
   - secret per entorn;
   - signatura validada amb comparació constant-time;
   - `DS_ORDER` i import contrastats;
   - cap correu de depuració pre-validació;
   - callback invàlid falla tancat.
8. Callbacks candidats eliminen notificació de depuració abans de validar Redsys i fallen tancat en error.
9. Nova prova `RedsysCourseLegacyFallbackBoundaryTest`.

## 5. Reclassificació de buits antics

### Ja no són P0 d'implementació

- crear intenció SIF;
- validar criptografia al circuit SIF/candidat;
- comparar order/import/divisa/terminal;
- idempotència del circuit SIF;
- atribució quantitativa `EXTERNAL_ALLOCATION` per inscripció;
- seqüència fiscal central;
- sync CURS post-SIF;
- retorn navegador autoritatiu;
- productor durable d'outbox;
- localització del JS.

Aquests punts existeixen al repositori i tenen proves. El que falta és principalment **desplegament/evidència real**.

### P0 actuals

1. Rotar qualsevol secret Redsys històric que pogués haver quedat exposat al repositori/historial.
2. Configurar secrets/URLs/rol intern en preproducció sense fallback insegur.
3. Executar una transacció Redsys real controlada i conservar evidència.
4. Activar cutover només després de preflights verds; comprovar 410/retirada del callback fiscal llegat.
5. No declarar producció fins que factura/cobrament/sync/retorn siguin verificats al runtime desplegat.

### P1

1. Completar delivery UC-58 si el flux productiu ha de conservar email postpagament.
2. Revalidar variants comercials fora del curs ordinari.
3. Verificar qualsevol efecte acadèmic addicional fora de `PAGAMENT / DATA PAG / M→1`, si aplica al curs concret. L'atribució quantitativa del cobrament ja no és P1: queda implementada pel PR #95.

## 6. Proves i evidència

El cap del PR #79 (`3569fffcf6a7579bec8452390f8d998eb1a09c9c`) té:
- `SIF PHP MySQL tests`: success;
- `SIF checks`: success;
- `UC-111 integration verification`: success;
- `UC-004 SIF secure flow checks`: success.

Això verifica el circuit intern fusionat fins a outbox, no el runtime de preproducció Redsys.

### Evidència addicional PR #95

El PR #95 (`feat/uc-014-enrollment-fund-allocation-2026-10-02`) integra `CourseEnrollmentFundAllocationService` i `EnrollmentFundMovementRepository`. El seu head reconciliat (`3fa6377e…`) va completar amb èxit:
- `SIF PHP MySQL tests`: **841 passed / 0 failed**;
- `SIF checks`;
- `UC-111 integration verification`;
- `UC-004 SIF secure flow checks`.

`CourseEnrollmentFundAllocationServiceTest` cobreix alta/reús, parcial per trams i mismatch fail-closed; `RedsysCourseEndToEndSimulatedTest` exigeix moviment únic davant duplicat i suma correcta al parcial→complet.

En aquesta branca s'han afegit/modificat proves de hardening ACTUAL. **Fins que GitHub Actions no les executi, s'han de marcar IMPLEMENTADES però NO REVALIDADES PER CI.**

## 7. Criteri de tancament

UC-014 pot passar a **TANCAT AMB EVIDÈNCIA** només quan, sobre un commit identificat i un entorn identificat:

- el checkout desplegat crea intenció SIF abans de Redsys;
- el callback desplegat entra al SIF i no factura al llegat;
- signatura/order/import/divisa/terminal queden acreditats;
- callback duplicat no duplica factura ni cobrament;
- pagament parcial i complet acumulen correctament;
- `UUID_FACTURA`, `UUID_PAYMENT`, `IDPAG`, relació `INSCRIPCIO`, job i notificació són reconstruïbles;
- el retorn navegador no afirma èxit abans del job `PROCESSED`;
- la projecció llegada és coherent;
- s'ha rotat/configurat qualsevol secret històric afectat;
- si l'email forma part del criteri operatiu de tall, UC-58 acredita el lliurament.

## 8. Estat final de l'auditoria documental

**DOCUMENTAT:** complet per UC-014 ordinari, inclòs PHP/JS i sis superfícies P-CUR.  
**IMPLEMENTAT:** nucli SIF, pont candidat, `EXTERNAL_ALLOCATION` per inscripció, outbox, retorn autoritatiu i hardening del fallback a la branca.  
**VERIFICAT:** circuit intern anterior per CI PR #79 i fund allocation per CI PR #95 (841/0 + quatre workflows verds); proves noves de hardening de la branca pendents de CI en el moment de redactar aquesta versió.  
**PENDENT:** Redsys real de preproducció, desplegament/cutover, rotació/configuració de secrets i delivery UC-58.
