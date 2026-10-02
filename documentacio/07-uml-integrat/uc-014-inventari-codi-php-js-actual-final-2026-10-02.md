# UC-014 — Inventari executable PHP/JS ACTUAL, pont candidat i SIF

**Data d'auditoria:** 02/10/2026  
**Base inicial:** `main@68c4534f31a6499a80f928e0e61bb816066b1fbd` · **revalidada després de sincronitzar:** `main@f7fa0822f82be96e842d9f2d031e643ab07f617c`  
**Objectiu:** demostrar quines superfícies, scripts PHP/JS, serveis SIF i proves intervenen realment en «Comprar curs normal per Redsys», separant **ACTUAL**, **PONT CANDIDAT**, **FINAL SIF**, **VERIFICAT** i **PENDENT**.

> Aquest inventari complementa la fitxa funcional i els UML. No acredita desplegament ni una transacció Redsys real de preproducció.

## 1. Llegenda

- **DOCUMENTAT**: hi ha fitxa/diagrama/matriu que descriu la superfície.
- **IMPLEMENTAT**: hi ha codi executable al repositori.
- **VERIFICAT**: hi ha prova automatitzada o evidència CI identificable.
- **PENDENT**: falta desplegament, prova real, decisió o component extern.

## 2. Superfícies web ACTUAL i JavaScript localitzat

| ID | Superfície | PHP real | JS real | Efecte / navegació | Estat |
| --- | --- | --- | --- | --- | --- |
| P-CUR-01 | Confirmació d'inscripció | `pagina_confirmacio_inscripcio_automatic.php`, `ajax/mostrar_confirmacio_inscripcio_automatic.php`, `PagamentCursAutomatic::mostrarPaginaConfirmacio()` | `js1619773569/mostrarConfirmacioInscripcioAutomatic.min.js` | El JS deriva `keyEncr`, carrega l'AJAX, valida camps al client i envia `#frm`; el PHP resol `IDPAG` i renderitza opcions | DOCUMENTAT + IMPLEMENTAT |
| P-CUR-02 | Pàgina de pagament des d'enllaç | `pagina_pagament_automatic.php`, `ajax/mostrar_pagina_pagament_automatic.php`, `PagamentCursAutomatic::mostrar()` | `js1619773569/mostrarPagamentAutomatic.min.js` | Carrega estat per `IDPAG`, mostra pendent/fraccionament i envia formulari | DOCUMENTAT + IMPLEMENTAT |
| P-CUR-03 | Confirmació TPV | `pagina_efectuar_pagament_automatic.php` | `js1619773569/mostrarEfectuarPagamentAutomatic.js` | ACTUAL prepara formulari Redsys; el JS només confirma/cancel·la i envia `#frm` | DOCUMENTAT + IMPLEMENTAT |
| P-CUR-04 | Callback servidor | `realitzaPagamentAutomatic.php` | — | ACTUAL/fallback: valida Redsys abans d'efectes a la branca d'auditoria, però continua sent arquitectura llegada fins al cutover | DOCUMENTAT + IMPLEMENTAT A BRANCA |
| P-CUR-05 | Retorn navegador | `respostaOkPagamentAutomatic.php`, `respostaKoPagamentAutomatic.php` | `mostrarRespostaOkPagamentAutomatic.min.js`, `mostrarRespostaKoPagamentAutomatic.min.js` | El JS és presentacional; en el pont candidat el resultat visible consulta estat SIF autoritatiu | DOCUMENTAT + IMPLEMENTAT |
| P-CUR-06 | Processament asíncron SIF | API + serveis SIF | — | intenció → callback → cua → worker → factura/CHARGE → `EXTERNAL_ALLOCATION` → projecció llegada → outbox | DOCUMENTAT + IMPLEMENTAT + CI PR #79/#95 |

### Conclusió JS

La nota antiga «JS externs no localitzats» queda **invalidada**. Els cinc fitxers JS de P-CUR-01/02/03/05 existeixen dins `codi-drive/web-actual/js1619773569/` i s'han contrastat. No són autoritat econòmica/fiscal: validen UX, carreguen fragments AJAX i envien formularis; les decisions crítiques s'han de tornar a validar al servidor.

## 3. PHP ACTUAL: recorregut i límits

### 3.1 `PagamentCursAutomatic.php`

- El constructor llegeix `inscripcions` per `IDPAG`, incloent `A_PAGAR`, `PAGAMENT`, `FRACCIONAT`, descompte i identitat.
- `mostrar()` i `mostrarPaginaConfirmacio()` decideixen opcions i import visible.
- La branca d'auditoria corregeix dues inicialitzacions `$recentTitulat == 0;` que eren comparacions sense efecte; ara són assignacions.
- La lògica visual JASOM continua sent **presentacional**. L'autorització definitiva de preparar la targeta passa pel gate servidor.

### 3.2 `JasomNovicePaymentGate`

Fitxers:
- `codi-drive/web-actual/inc/JasomNovicePaymentGate.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/inc/JasomNovicePaymentGate.php`

La branca d'auditoria:
- rellegeix `ID`, `CURS`, `A_PAGAR`, `PAGAMENT`, **`FRACCIONAT`** i decisió JASOM des de BD;
- rebutja `IDPAG` no únic/no pagable;
- rebutja pagament superior al pendent o sobre inscripció ja pagada;
- rebutja import parcial quan `FRACCIONAT != 1`;
- no confia en el `codiCurs` aportat pel navegador;
- retorna `payment_amount` i `fractional` autoritatius.

### 3.3 `pagina_efectuar_pagament_automatic.php`

**ACTUAL/fallback**:
- segueix generant l'ordre Redsys fora del SIF i, per tant, no és l'arquitectura final;
- a la branca d'auditoria usa el fraccionament retornat pel gate en lloc del POST;
- saneja les sortides HTML provinents del POST;
- inicialitza `nom-alumne` abans d'usar-lo;
- deixa d'incrustar credencials Redsys i exigeix configuració d'entorn.

**Pont candidat**:
- usa `SifRedsysCourseIntentClient`;
- `RedsysCoursePaymentIntentService` rellegeix saldo i `FRACCIONAT`;
- obté `DS_ORDER`/import de la intenció SIF;
- només usa MerchantURL SIF amb `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1`, `SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=1` i URL HTTPS;
- amb `cutover=1/drain=0` bloqueja nous checkouts i deixa drenar callbacks llegats ja oberts; amb `cutover=0/drain=0` conserva fallback explícit per rollback;
- el gateway Redsys deixa d'estar hardcodejat: `REDSYS_GATEWAY_URL` és obligatòria i ha de ser HTTPS;
- `REDSYS_TERMINAL` és obligatori al pont candidat; el preflight comprova també merchant code/key, que merchant code i clau del pont/SIF coincideixin sense exposar-los, i que `SIF_INTERNAL_API_BASE_URL` sigui HTTPS;
- `SIF_INTERNAL_REDSYS_COURSE_INTENT_SIGNED_PATH` queda declarat explícitament i el preflight comprova que els paths HMAC de course-intent/status coincideixen amb els clients del pont.

## 4. Callback i autoritat fiscal

### ACTUAL/fallback endurit a la branca d'auditoria

`codi-drive/web-actual/realitzaPagamentAutomatic.php`:
- clau Redsys via entorn, sense literal al fitxer;
- verifica versió/signatura amb `hash_equals`;
- recupera `Ds_MerchantData` signat i exigeix el context `UC014I<IDPAG>A<AMOUNT_CENTS>F<FRAC>` creat al checkout;
- valida `Ds_Currency=978`, `Ds_Terminal` i `Ds_MerchantCode` contra la configuració d'entorn;
- exigeix `Ds_Response` numèric abans de classificar `0..99` com a autoritzat, evitant que una resposta textual es converteixi implícitament en `0`;
- deriva `IDPAG`, import i fraccionament exclusivament del context signat; el callback no llegeix `$_GET`;
- usa `Ds_Order` i `Ds_Amount` signats, exigeix exactament una inscripció per `IDPAG` i rellegeix curs/DNI de la BD llegada;
- no envia notificació de depuració abans de validar;
- falla amb HTTP 400 davant callback invàlid;
- usa `DS_ORDER` de 12 dígits en el fallback i MerchantURL sense query funcional.

Això redueix risc mentre existeixi fallback, però **no converteix el callback llegat en autoritat fiscal final**. La numeració/facturació directa s'ha de retirar del runtime quan el cutover SIF quedi acreditat.

### FINAL SIF

- `RedsysSignatureValidator`: valida `HMAC_SHA256_V1`, signatura, merchant code esperat, moneda EUR i terminal; el callback SIF falla tancat si no té merchant code configurat.
- `RedsysCallbackService`: exigeix signatura verificada, correlaciona amb `redsys_payment_intent` i compara import/divisa/terminal.
- `RedsysCallbackQueueRepository` + `RedsysCallbackWorker`: cua idempotent i incidència recuperable.
- `RedsysCourseInvoiceService`: handler `CURS` sobre snapshot/intenció validada.
- `RedsysInvoicePayloadBuilder`: injecta `CHARGE`, `DS_ORDER`, `IDPAG` i idempotència.
- `InvoiceService`: seqüència fiscal central, factura, línies, registre/cadena, cobrament inicial i `payment_allocation`.
- `CourseLegacyPaymentSyncService`: projecta el total confirmat per `IDPAG` a `PAGAMENT`, `DATA PAG` i `M→1`.
- `CoursePaymentNotificationService`: crea `COURSE_PAYMENT_CONFIRMED` a `notification_outbox` després del sync.

## 5. Atribució econòmica a la inscripció

El `main` sincronitzat incorpora el **PR #95**, que tanca l'antic A14-17 amb una atribució quantitativa explícita:

- `CourseEnrollmentFundAllocationService`;
- `EnrollmentFundMovementRepository`;
- taula `enrollment_fund_movement`;
- moviment `EXTERNAL_ALLOCATION`;
- clau idempotent `FUND|CURS|ORDER:<DS_ORDER>|INSC:<ID_INSC>`;
- enllaços a `UUID_PAYMENT`, `UUID_FACTURA` i `ID_INSC_DESTI`.

Ordre executable FINAL:
1. `InvoiceService` crea/reutilitza factura, `CHARGE` i `payment_allocation`.
2. `CourseEnrollmentFundAllocationService` bloqueja/valida el `CHARGE` confirmat.
3. Verifica import, factura i línia `INSCRIPCIO`.
4. Crea/reutilitza exactament un `EXTERNAL_ALLOCATION` per `DS_ORDER + ID_INSC`.
5. Només després continua la projecció llegada i l'outbox.

La fase falla tancada davant mismatch i és idempotent davant replay. `CourseEnrollmentFundAllocationServiceTest` cobreix alta/reús, trams parcials i mismatch; l'E2E CURS comprova complet, duplicat i parcial→complet.

**Evidència PR #95:** `SIF PHP MySQL tests` **841 passed / 0 failed**; `SIF checks`, `UC-111 integration verification` i `UC-004 SIF secure flow checks` verds.

## 6. Retorn navegador

Pont candidat:
- `CoursePaymentReturnStatus`
- `SifRedsysCourseStatusClient`
- `POST /api/redsys/course-status.php`
- `RedsysCoursePaymentStatusService`

`CONFIRMED` només es retorna quan existeixen intenció CURS correcta, notificació vàlida i job `PROCESSED` amb `UUID_FACTURA` i `UUID_PAYMENT`. Un URL OK de Redsys no és suficient.

## 7. Proves localitzades

### Intenció, callback i emissió
- `RedsysCoursePaymentIntentServiceTest`
- `RedsysPaymentIntentTest`
- `RedsysAsyncFlowTest`
- `RedsysCourseInvoiceServiceTest`
- `RedsysCourseEndToEndSimulatedTest`

### Sync, retorn i cutover
- `RedsysLegacySyncingProcessorCourseTest`
- `RedsysCoursePaymentStatusServiceTest`
- `RedsysCourseReturnBoundaryTest`
- `RedsysCourseCutoverBoundaryTest`
- `RedsysCoursePreproductionBoundaryTest`
- `RedsysCoursePreproductionScriptTest`

### Hardening ACTUAL afegit en aquesta auditoria
- `JasomNovicePaymentGateTest`: inclou ara no-fraccionat parcial rebutjat i fraccionat parcial admès.
- `RedsysCourseLegacyFallbackBoundaryTest`: comprova gate, escaping, configuració externa, signatura/order/import, `MerchantData`, moneda/terminal/merchant code i `Ds_Response` estricte abans d'efectes, a més de la inicialització JASOM.

**Evidència anterior:** el cap del PR #79 (`3569fffc…`) va completar amb èxit `SIF PHP MySQL tests`, `SIF checks`, `UC-111 integration verification` i `UC-004 SIF secure flow checks`. El PR #95 (`3fa6377e…`) va tornar a deixar els quatre workflows verds i les suites SIF en **841 passed / 0 failed**, incorporant `EXTERNAL_ALLOCATION`.  
**Evidència d'aquesta branca:** el hardening ACTUAL ha quedat revalidat al PR #105 sobre el head de codi `56d32d600d26d39d94b8a7227e4d732f07d35ce5`: `SIF PHP MySQL tests`, `SIF checks` i `UC-111 integration verification` han acabat en success.

## 8. Pendent real després d'aquesta auditoria

1. Desplegar el pont candidat a preproducció.
2. Configurar secrets Redsys només al secret store/entorn i **rotar qualsevol credencial històrica exposada**, ja que eliminar-la del HEAD no l'elimina de l'historial Git.
3. Executar Redsys real de proves i conservar evidència de `DS_ORDER`, callback, job, factura, `UUID_PAYMENT`, sync i retorn navegador.
4. Activar el cutover explícit només amb preflight verd i comprovar que els callbacks llegats queden retirats/410.
5. Completar UC-58 per claim/locks/retries/transport i evidència de lliurament de `notification_outbox`.
6. Revalidar variants comercials que no siguin el curs ordinari (taller/jornada/packs/JASOM específic) en els UC corresponents.

## 9. Estat consolidat

| Dimensió | Estat 02/10/2026 |
| --- | --- |
| Fitxa funcional | DOCUMENTADA; actualització 02/10 en aquesta branca |
| PHP ACTUAL | INVENTARIAT; fallback endurit en aquesta branca |
| JS ACTUAL | LOCALITZAT I TRAÇAT |
| Classes ACTUAL/FINAL | EXISTEIXEN; reconciliades en aquesta auditoria |
| Seqüències ACTUAL/FINAL | EXISTEIXEN; reconciliades en aquesta auditoria |
| Activitats per pàgina | EXISTEIXEN per P-CUR-01..06; JS incorporat |
| SIF final | IMPLEMENTAT al repositori |
| Pont candidat | IMPLEMENTAT al repositori |
| Atribució quantitativa per inscripció | IMPLEMENTADA + VERIFICADA al PR #95 (`EXTERNAL_ALLOCATION`) |
| E2E intern | VERIFICAT al PR #79 i ampliat/verificat al PR #95 amb fund allocation; hardening ACTUAL també VERIFICAT per CI al PR #105 |
| Redsys preproducció real | PENDENT |
| Cutover productiu | PENDENT |
| Lliurament email UC-58 | PENDENT |


## 10. Minimització de PII al TPV i callback

La branca d'auditoria redueix dades no necessàries:
- product description Redsys: curs/codi, sense DNI;
- titular Redsys: nom del titular, no document identificatiu;
- MerchantData: només context tècnic mínim signat;
- MerchantURL: sense dades funcionals;
- retorns navegador: sense email;
- callback servidor-a-servidor: sense renderitzar blocs de dades de negoci al cos HTTP.
