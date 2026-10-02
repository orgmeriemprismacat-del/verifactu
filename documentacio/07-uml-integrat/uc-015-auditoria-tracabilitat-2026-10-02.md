# UC-015 · Auditoria exhaustiva de traçabilitat · 2026-10-02

## 1. Abast i punt de tall

Auditoria executada contra `main` a:

- commit: `f7fa0822f82be96e842d9f2d031e643ab07f617c`;
- cas: **UC-015 · Comprar pack**;
- objectiu: contrastar fitxa funcional, PHP/JS real, UML de classes, seqüències i activitats ACTUAL/FINAL, proves i traçabilitat;
- criteri d'estat: separar **documentat**, **implementat**, **verificat** i **pendent**.

No es considera “verificat” allò que només apareix al diagrama FINAL o a la fitxa sense correspondència executable/prova.

## 2. Inventari documental

| Lliurable | Existeix | Estat 02/10 |
|---|---:|---|
| Fitxa funcional | sí | revisada; requeria matisos d'estat actual |
| UML integrat | sí | útil i coherent amb el nucli SIF |
| Classes ACTUAL/FINAL | sí | revisat i actualitzat |
| Seqüències ACTUAL/FINAL | sí | **corregit**: eliminat servei inexistent |
| Activitats ACTUAL/FINAL per pàgina/bloc | sí | revisat i actualitzat |
| Auditoria/traçabilitat històrica | sí | 29/09–30/09 |
| Auditoria/traçabilitat vigent | sí | **aquest document** |

Fitxers:

- `documentacio/06-fitxes-funcionals/uc-015.md`
- `documentacio/07-uml-integrat/uc-015-comprar-pack.md`
- `documentacio/07-uml-integrat/uc-015-classes-actual-final.md`
- `documentacio/07-uml-integrat/uc-015-sequencies-actual-final.md`
- `documentacio/07-uml-integrat/uc-015-activitats-pagines-pack-actual-final.md`
- `documentacio/07-uml-integrat/uc-015-auditoria-tracabilitat-2026-09-29.md`

**Conclusió d'inventari:** no falta cap dels quatre lliurables documentals principals demanats (fitxa, classes, seqüències, activitats). El problema detectat no era d'absència sinó de **desalineació puntual entre FINAL i codi real**.

## 3. Inventari de codi real

### 3.1. Web / alta legacy

- `codi-drive/web-actual/Pack.php`
- `codi-drive/web-actual/InfoPack.php`
- `codi-drive/web-actual/EdicioPack.php`
- `codi-drive/web-actual/InscripcioPack.php`
- `codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js`
- `codi-drive/web-actual/ajax/mostrar_inscripcio_packs.php`
- `codi-drive/web-actual/ajax/enviarInscripcioPack.php`

### 3.2. Checkout PACK → SIF

- `codi-drive/web-actual/inc/PackPaymentGate.php`
- `codi-drive/web-actual/inc/SifPaymentIntentClient.php`
- `codi-drive/web-actual/pagina_efectuar_pagament_grup_automatic.php`
- còpies operatives equivalents sota `codi-drive/pay-prisma-cat-canvis-verifactu/`

### 3.3. SIF / fiscal / econòmic

- `sif/public/api/redsys/intents/create.php`
- `sif/src/Service/RedsysPaymentIntentService.php`
- `sif/src/Service/RedsysPackInvoiceService.php`
- `sif/src/Repository/LegacyPackSnapshotRepository.php`
- `sif/src/Service/LegacyPackInvoicePayloadBuilder.php`
- `sif/src/Service/RedsysInvoicePayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`
- `sif/src/Service/PackEnrollmentFundAllocationService.php`
- `sif/src/Service/PackPaymentNotificationService.php`
- `sif/src/Service/RedsysLegacySyncingProcessor.php`
- `sif/src/Service/LegacySyncService.php`
- `sif/scripts/process-redsys-callback-queue.php`

### 3.4. Proves específiques/relacionades

- `sif/tests/Unit/PackPaymentGateTest.php`
- `sif/tests/Unit/LegacyPackSnapshotRepositoryTest.php`
- `sif/tests/Integration/LegacyPackInvoicePayloadBuilderTest.php`
- `sif/tests/Integration/RedsysPackInvoiceServiceTest.php`
- `sif/tests/Integration/PackCommercialOrderBoundaryTest.php`
- `sif/tests/Integration/PackPublicEnrollmentBoundaryTest.php`
- `sif/tests/Integration/PackMultiCourseCommunicationBoundaryTest.php`
- `sif/tests/Integration/PackEnrollmentAtomicityBoundaryTest.php`
- `sif/tests/Integration/LegacyPackCallbackBoundaryTest.php`
- `sif/tests/Integration/RedsysPaymentIntentTest.php`
- `sif/tests/Integration/RedsysPackPreflightScriptTest.php`
- `sif/tests/Integration/RedsysPackPreproductionScriptTest.php`
- `sif/scripts/test-uc015-local.sh`
- `sif/scripts/test-uc015-local.ps1`

## 4. Matriu per pàgina/bloc

| Bloc | Documentat | Implementat | Verificat | Pendent |
|---|---|---|---|---|
| PK-A01 Llistat packs | sí | sí, legacy | inspecció | E2E visual |
| PK-A02 Fitxa pack | sí | sí, legacy | inspecció | E2E visual |
| PK-A03 Formulari | sí | **POST-only + same-site/origin implementat** | inspecció + boundary test | E2E navegador/preproducció |
| PK-A04 Alta N inscripcions | sí | sí, snapshot legacy | proves/inspecció | model comercial explícit/versionat |
| PK-A05 Intenció/URL pagament | sí | sí | proves + CI històrica | prova d'entorn real |
| PK-A06 Callback | sí | sí, SIF autoritatiu | proves | Redsys preproducció |
| PK-A07 Factura | sí | sí | proves + CI | E2E real |
| PK-A08 Distribució monetària | sí | sí | proves idempotència/suma | E2E real |
| PK-A09 Correus/notificació | sí | enqueue sí | prova d'outbox | lliurament UC-58 |
| PK-A10 Fraccionament | sí | ecommerce bloquejat | proves | només circuit excepcional intranet |

## 5. Troballes verificades

### F-01 · Alta pública POST i frontera same-site — corregit

L'auditoria va detectar que `mostrarInscripcioPack.min.js` enviava dades personals per GET a `enviarInscripcioPack.php`. Aquesta troballa s'ha corregit a la mateixa branca:

- el JS usa `method: "POST"`;
- el PHP accepta només POST i respon 405 a altres mètodes;
- les dades s'obtenen exclusivament de `$_POST`;
- `Cache-Control: no-store` evita cachejar la resposta;
- `Sec-Fetch-Site`, `Origin` i `Referer` bloquegen orígens cross-site quan aquests headers estan presents;
- la PII deixa de formar part de la query string;
- `PackPublicEnrollmentBoundaryTest` blinda el contracte.

El formulari continua sent públic/anònim i no s'ha introduït una sessió artificial només per afegir un token CSRF. Resta validar en E2E navegador/preproducció i valorar controls anti-abús addicionals si la política operativa els exigeix.

**Estat:** implementat en codi; prova automatitzada escrita; E2E pendent.

### F-02 · Snapshot comercial servidor

L'alta crea N files `TIPUS_INSC='P'` amb un `IDPAG` compartit i congela a `OBSERVACIONS`:

- `PACK`
- `PACK_ORDINAL`
- `PACK_BASE`
- `PACK_DISCOUNT`
- `PACK_DISCOUNT_PCT`
- `PACK_TOTAL`

`PackPaymentGate` torna a validar coherència de PACK, ordinals, receptor fiscal, imports i pagament complet abans de crear la intenció.

**Estat:** documentat + implementat + cobert per proves.

### F-03 · Fraccionament ecommerce

L'alta força `FRACCIONAT=0`. `PackPaymentGate` rebutja pagaments previs/parcials i exigeix l'import pendent complet.

**Estat:** documentat + implementat + provat.

### F-04 · Intenció SIF abans de Redsys

`pagina_efectuar_pagament_grup_automatic.php` construeix el snapshot autoritatiu, genera `DS_ORDER`, i `SifPaymentIntentClient` crea la intenció per API interna HMAC. `redsys/intents/create.php` és POST-only, autentica actor/signatura i aplica allowlist de rols.

`RedsysPaymentIntentService` valida per PACK:

- IDPAG;
- PACK/source;
- mínim dos ítems;
- ordinals positius, únics i contigus;
- identitat de les inscripcions;
- suma de línies = import esperat.

**Estat:** documentat + implementat + provat.

### F-05 · Emissió fiscal PACK

`RedsysPackInvoiceService::issueFromIntentSnapshot()`:

1. crea payload fiscal des del snapshot;
2. afegeix el cobrament Redsys validat;
3. exigeix total factura = import cobrament;
4. emet via `InvoiceService`;
5. reparteix fons per inscripció;
6. encola notificació;
7. retorna instrucció de sincronització legacy post-SIF.

**Estat:** documentat + implementat + provat.

### F-06 · Ledger per inscripció

`PackEnrollmentFundAllocationService` comprova:

- CHARGE confirmat;
- import del payment;
- total factura;
- línia fiscal corresponent;
- suma exacta d'atribucions;
- ordinal contigu;
- idempotència per `DS_ORDER + ID_INSC`.

**Estat:** documentat + implementat + provat.

### F-07 · Outbox

`PackPaymentNotificationService` crea `PACK_PAYMENT_CONFIRMED` amb clau idempotent per `DS_ORDER`.

**Estat:** enqueue implementat i provat; **lliurament efectiu** continua fora d'aquest UC i pendent d'UC-58/runtime.

### F-08 · Sincronització legacy real

El worker injecta el dispatcher dins `RedsysLegacySyncingProcessor`. Després de l'èxit SIF:

- `LegacySyncService::syncAfterSifSuccess()`;
- per `PACK_FULL_PAYMENT`, `LegacySyncService::syncPackFullPayment()`.

La seqüència documental anterior dibuixava `AcademicEnrollmentSyncService`, que **no existeix** en aquest flux. S'ha corregit.

**Estat:** implementat; documentació corregida.

### F-09 · Callback fiscal legacy

El codi històric de `realitzaPagamentPackAutomatic.php` encara existeix, però queda curt-circuitat per defecte amb HTTP 410 si no s'habilita explícitament el flag de rollback.

**Estat:** desactivat operativament; **retirada física pendent**.

### F-10 · Motiu intern de descompte massa específic

`LegacyPackInvoicePayloadBuilder` etiquetava qualsevol descompte com «aplicat a la línia del segon curs». El model actual admet N components i l'ordinal és dada comercial; el text era fals per a altres distribucions.

**Correcció aplicada en aquesta auditoria:** motiu neutral basat en el snapshot comercial + regressió en `LegacyPackInvoicePayloadBuilderTest`.

### F-11 · `pagFrac` era una entrada client inexistent — corregit

El PHP llegia `pagFrac` del request encara que el JS del pack no l'enviava. A més, la regla vigent de UC-015 estableix que l'ecommerce no permet fraccionament.

**Correcció aplicada:**
- `pagFrac` ja no forma part del contracte d'entrada;
- el servidor fixa `new Text('No')`;
- el contracte JS/PHP queda alineat: tots els camps consumits pel PHP són realment enviats pel JS;
- el boundary test comprova que `pagFrac` no torna a ser controlable pel client.

**Estat:** implementat i cobert per prova.

### F-12 · Correu d'alta limitat a dos cursos — corregit per PACK N

El nucli de pack admet N components, però el correu d'alta utilitzava `$titols[0]`, `$titols[1]`, `[TITOL1]` i `[TITOL2]`. Un pack amb més de dos components s'inscrivia/facturava amb N línies però la comunicació només descrivia els dos primers.

**Correcció aplicada:**
- el correu usa ara `[CURSOS_PACK]`;
- reutilitza `$datesRealitzacioCursos`, generat en bucle per tots els components;
- s'elimina la dependència de `$edicions[1]` i `$titols[0/1]` del correu;
- `PackMultiCourseCommunicationBoundaryTest` blinda que la plantilla no torni a dos cursos fixos.

**Estat:** implementat i cobert per prova; E2E de correu pendent.

### F-13 · Alta N no atòmica i lock només alliberat en èxit — corregit

L'alta legacy reservava un `IDPAG` amb `GET_LOCK` i executava N `INSERT INTO inscripcions` sense transacció. Si una inserció intermèdia fallava, les anteriors podien quedar persistides i `releaseIdPag()` només apareixia al camí d'èxit.

**Correcció aplicada:**
- `ConnexioBBDDSTMT` incorpora `beginTransaction()`, `commitTransaction()` i `rollbackTransaction()`;
- després de reservar `IDPAG`, totes les insercions del pack es fan dins una única transacció;
- cada consulta de preu i cada insert es valida;
- `lastInsertId()` es captura abans del commit;
- el commit precedeix `releaseIdPag()`;
- qualsevol `Exception` fa rollback;
- `finally` garanteix rollback/alliberament si el flux surt abans de completar-se;
- `PackEnrollmentAtomicityBoundaryTest` blinda l'ordre transaccional i la via d'error.

**Estat:** implementat i cobert per prova automatitzada; resta E2E amb fallada injectada en preproducció si es vol evidència runtime.

### F-14 · CI no cobria tot el canal web PACK — corregit

La CI específica de UC-015 lintava `PackPaymentGate.php` i la pàgina de pagament, però no tenia cobertura completa del formulari d'alta, la plantilla ni el JavaScript. `sif-checks.yml` sí incloïa part del PHP legacy, però els canvis només de JS/plantilla podien no disparar el workflow.

**Correcció aplicada:**
- `sif-tests.yml` dispara ara per canvis a la connexió legacy, endpoint d'alta, JS i plantilla PACK;
- el pas de lint valida PHP de connexió/endpoint/plantilla i `node --check` del JS;
- `sif-checks.yml` inclou JS/plantilla als paths i un pas sintàctic dedicat UC-015 PACK.

**Estat:** implementat; el HEAD final del PR ha de demostrar la nova porta verda.

## 6. UML i traçabilitat

### Classes

El diagrama existeix i diferencia web públic, checkout i SIF. `EnviarInscripcioPack` queda actualitzat com a POST-only amb frontera same-site/origin.

### Seqüències

La seqüència FINAL s'ha alineat amb la cadena executable:

`CallbackWorker → RedsysLegacySyncingProcessor → Dispatcher → RedsysPackInvoiceService → InvoiceService / Ledger / Outbox → LegacySyncService`.

### Activitats

Els 10 blocs PK-A01..PK-A10 existeixen. PK-A03 queda ara classificat correctament com:

- ACTUAL: POST-only + same-site/origin + preu servidor;
- FINAL residual: acreditar navegador/preproducció i controls anti-abús si pertoquen.

## 7. Verificació i CI

### Evidència específica UC-015

El paquet final específic del UC-015 es va fusionar a:

- `41d696824ce42b92c8a322094027337af7825987`
- PR #72
- workflow `SIF PHP MySQL tests`: **success**
- run: https://github.com/orgmeriemprismacat-del/verifactu/actions/runs/36741186555

Això és evidència de CI del bloc UC-015 després dels enduriments de 30/09.

### HEAD auditat

Entre `41d6968...` i `f7fa082...` hi ha 261 commits. No s'han modificat els fitxers web/fitxes/core PACK; les dues dependències compartides rellevants han canviat així:

- `RedsysPaymentIntentService`: s'ha afegit validació específica de `CURS`; la branca `PACK` continua cridant la mateixa `validatePackSnapshot()`;
- `process-redsys-callback-queue.php`: s'ha afegit notificació de curs; la injecció del handler PACK continua intacta.

La revisió de codi del PR a `0b32fa270325501e648fe31dbf51768f048fe0d7` va completar correctament els quatre workflows:

- `SIF checks`: success · run `36943484797`;
- `SIF PHP MySQL tests`: success · run `36943484891`;
- `UC-004 SIF secure flow checks`: success · run `36943484800`;
- `UC-111 integration verification`: success · run `36943484841`.

Després s'ha resincronitzat la branca amb `main`; per criteri de merge, el HEAD final ha de tornar a mantenir aquests checks verds.

Per tant:

- **verificació de codi UC-015:** sí;
- **CI de la revisió de codi del PR:** sí;
- **CI del HEAD final després de qualsevol resincronització:** obligatòria abans del merge;
- **E2E navegador + Redsys preproducció:** pendent.

## 8. Estat final per categoria

### Documentat

**SÍ**, amb els lliurables requerits presents. En aquesta auditoria s'han corregit dues desalineacions: servei de sincronització inexistent al diagrama de seqüència i classificació massa optimista del transport d'alta.

### Implementat

**SÍ, per al nucli fiscal/econòmic:** snapshot, gate de pagament, intenció SIF, callback/worker, factura, payment, ledger per inscripció, enqueue outbox i sync legacy.

**SÍ, per al transport del canal web inicial:** POST-only, sense PII a query string i amb frontera same-site/origin. Continua sent un formulari anònim i resta E2E.

### Verificat

- inspecció directa contra `main@f7fa082...`: sí;
- proves unitàries/integració del paquet UC-015: sí, CI verda a `41d6968...`;
- regressió nova del motiu de descompte: escrita i coberta per la suite verda del PR a `0b32fa2...`;
- qualsevol HEAD posterior per resincronització amb `main` requereix nova CI verda abans del merge;
- E2E real: pendent.

### Pendent

1. Executar PK-01..PK-11 en preproducció amb DS_ORDER real, incloent alta POST i rebuig GET/cross-site.
2. Tancar decisió de negoci sobre ordre comercial explícit vs `DATAI, ID_CURS`.
3. Eliminar físicament callback fiscal PACK legacy després de la finestra de rollback.
4. Validar lliurament real de notificació (UC-58), no només enqueue.
5. Validar CI del HEAD final del PR.
6. Si es vol tancament formal, registrar evidències de variables d'entorn, worker i callback HTTPS de preproducció.

## 9. Canvis aplicats per aquesta auditoria

- correcció del diagrama de seqüència FINAL;
- detecció i correcció del transport GET: POST-only + frontera same-site/origin + boundary test;
- correcció del text fiscal intern de descompte perquè no pressuposi «segon curs»;
- eliminació de `pagFrac` com a entrada client i fixació server-side de no fraccionament;
- correu d'alta generalitzat de 2 cursos fixos a PACK N;
- alta N convertida en transacció atòmica amb rollback i lock `IDPAG` segur;
- CI ampliada perquè endpoint, connexió, plantilla i JS PACK activin i passin lint.
- prova de regressió associada;
- actualització de la fitxa funcional i UML integrat;
- creació d'aquest registre de revalidació 02/10.

## 10. Criteri de tancament

UC-015 no s'ha de marcar com a completament tancat mentre quedin oberts l'E2E/preproducció, la decisió d'ordre comercial i els pendents operatius indicats. El **nucli SIF PACK** sí pot considerar-se implementat, amb evidència automatitzada prèvia, subjecta a CI verda del commit final d'aquesta auditoria.
