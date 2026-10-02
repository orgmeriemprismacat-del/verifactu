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
- `sif/tests/Integration/PackEnrollmentIdempotencyBoundaryTest.php`
- `sif/tests/Integration/PackComponentAvailabilityBoundaryTest.php`
- `sif/tests/Integration/LegacyPackCallbackBoundaryTest.php`
- `sif/tests/Integration/RedsysPaymentIntentTest.php`
- `sif/tests/Integration/RedsysPackPreflightScriptTest.php`
- `sif/tests/Integration/RedsysPackPreproductionScriptTest.php`
- `sif/tests/Integration/RedsysPackPreproductionBoundaryTest.php`
- `sif/scripts/test-uc015-local.sh`
- `sif/scripts/test-uc015-local.ps1`

## 4. Matriu per pàgina/bloc

| Bloc | Documentat | Implementat | Verificat | Pendent |
|---|---|---|---|---|
| PK-A01 Llistat packs | sí | filtre d'edició + disponibilitat global de tots els components | boundary test/inspecció | E2E visual |
| PK-A02 Fitxa pack | sí | exigeix totes les edicions obertes | boundary test/inspecció | E2E visual |
| PK-A03 Formulari | sí | **POST-only + same-site/origin + REQUEST_ID + revalidació de totes les edicions** | boundary/idempotency tests | E2E navegador/preproducció |
| PK-A04 Alta N inscripcions | sí | snapshot + transacció + idempotència server-side | proves/inspecció | model comercial explícit/versionat |
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

### F-15 · Èxit de navegador contaminat per errors auxiliars post-commit — corregit

L'endpoint escrivia `$hashIdInserit` després de persistir les inscripcions, però després continuava executant mailing, poblacions, credencials i correus dins el mateix `try`. Si una d'aquestes tasques fallava, el `catch` afegia `missatgeError(...)` a la resposta. El JavaScript detecta qualsevol text amb «error» i mostrava fallada encara que el pack ja estigués commitat, afavorint reintents/duplicats.

**Correcció aplicada:**
- `$packEnrollmentCommitted` passa a `true` només després del commit de les N inscripcions;
- un error anterior al commit continua retornant error al navegador;
- un error posterior al commit es registra amb `error_log` i no contamina el hash d'èxit;
- `PackEnrollmentAtomicityBoundaryTest` comprova l'ordre commit → committed → resposta i la branca post-commit.

Això no converteix els correus legacy en outbox durable; només evita un fals error funcional després d'una alta ja persistent.

**Estat:** implementat i cobert per prova automatitzada; migració dels correus inicials a mecanisme durable continua fora d'aquest fix.

### F-16 · Doble enviament de navegador sense guard explícit — corregit en dues capes

El botó mostrava el modal de càrrega però no existia un estat explícit que impedís executar dues vegades `enviarInscripcio()` davant doble clic ràpid.

**Correcció aplicada:**
- flag `inscripcioPackEnviant`;
- botó desactivat abans de l'AJAX;
- reactivació només si la resposta funcional és error o falla la petició;
- en èxit queda desactivat fins a la redirecció;
- a més, F-18 incorpora idempotència server-side amb `REQUEST_ID`, de manera que un segon HTTP equivalent reutilitza l'alta encara que el guard de client no sigui suficient.

**Estat:** doble clic mitigat al client i deduplicació forta implementada al servidor.

### F-17 · L'alta no comprovava que la suma congelada fos exactament el preu PACK — corregit

El repartiment legacy consumeix el preu del pack sobre els components ordenats amb `min($aux, $preuCursOriginal)`. Abans de la revalidació no existia un guard final que demostrés que s'havia consumit el 100 % del preu del pack. Una configuració incoherent —per exemple, preu PACK superior a la suma dels cursos— podia deixar `A_PAGAR` agregat per sota del preu comercial.

**Correcció aplicada:**
- conversió del preu PACK i suma de cursos a cèntims;
- bloqueig si `preuPack > sumaCursos` o hi ha imports negatius;
- acumulació explícita de `totalPackLinesCents`;
- abans del commit s'exigeix `aux == 0` cèntims i `totalPackLinesCents == preuPackCents`;
- qualsevol divergència llança error dins la transacció i provoca rollback;
- `PackEnrollmentAtomicityBoundaryTest` blinda que el guard s'executi abans del commit.

**Estat:** implementat i cobert per prova automatitzada; el checkout/SIF conserva a més els seus guards independents de reconciliació.

### F-18 · Idempotència server-side de l'alta pública — implementada

L'alta pública ja no depèn només del guard de doble clic. El navegador genera un UUID v4 i el conserva a `sessionStorage` mentre el resultat és incert. El servidor calcula un fingerprint SHA-256 sobre els 19 camps funcionals del formulari i serialitza l'operació per `REQUEST_ID`.

**Contracte implementat:**
- el JS envia `requestId` a cada intent;
- en error de xarxa/5xx es conserva el mateix identificador per poder recuperar un commit amb resposta perduda;
- en èxit es neteja el `REQUEST_ID`;
- el PHP valida UUID v4 abans de qualsevol mutació;
- un named lock `prisma_pack_req_<hash>` serialitza dos intents del mateix request;
- el lookup de reintents s'executa **abans** dels validators legacy i abans de rellegir l'estat comercial actual del pack;
- cada línia persisteix `RID|<uuid>` i `RH1|<sha256>` junt amb el snapshot comercial;
- mateix RID + mateix hash + ordinals coherents → retorna una nova confirmació del mateix `IDPAG` sense reservar un altre IDPAG, inserir files ni reenviar correus;
- mateix RID + payload diferent → HTTP 409;
- fingerprints/IDPAG/ordinals interns inconsistents → HTTP 409 fail-closed;
- el lock de request s'allibera immediatament després del commit, abans de tasques SMTP;
- `mostrarInscripcioPack.min.js` puja a `ver=7.5` per evitar clients cachejats amb el contracte GET antic;
- `LegacyPackSnapshotRepositoryTest` acredita que els marcadors `RID/RH1` són ignorats pel parser fiscal;
- `PackEnrollmentIdempotencyBoundaryTest` blinda persistència, ordre del replay, conflicte i persistència del request al navegador.

El fingerprint és deliberadament conservador: diferències literals del formulari es consideren payload diferent, de la mateixa manera que el contracte transversal SIF no reutilitza una clau amb entrada contradictòria.

**Estat:** implementat i cobert per proves automatitzades; resta E2E de navegador/preproducció amb resposta perduda/reintent concurrent.

### F-19 · Disponibilitat del pack validada només parcialment — corregit

La revalidació ha detectat tres desalineacions relacionades:

1. `EdicioPack::inscripcioOberta()` calculava `DateTime::diff()->days`, un valor absolut; per tant no distingia correctament si la data límit era passada o futura. Tampoc suportava correctament configuracions amb dies negatius.
2. `Pack.php` comprovava només `$this->edicions[0]` i, a més, comparava el retorn `0/1` amb `< 0`, de manera que el bloqueig no podia actuar com estava documentat.
3. `enviarInscripcioPack.php` confiava en `PUBLIC/ESTAT` i en la UI, però no revalidava la finestra d'inscripció de cadascun dels N components abans de crear les files.

**Correcció aplicada:**
- `EdicioPack::inscripcioOberta()` calcula `data_inici + dies_configurats` amb signe i exigeix `dataLimit > avui`, igual que la regla utilitzada al llistat;
- `Pack.php` carrega les regles `dies-inscriu-cursos` per hores i exigeix que **cada edició** tingui almenys una regla encara oberta;
- el POST d'alta repeteix aquesta validació server-side per tots els components abans de calcular preus, reservar `IDPAG` o iniciar la transacció;
- un replay idempotent ja commitat es resol abans d'aquesta revalidació, de manera que un canvi posterior de disponibilitat no impedeix recuperar una resposta perduda;
- `buscantPacksDisponibles.php` separa el filtre «conté l'edició seleccionada» de la disponibilitat global i només llista packs amb almenys dos components i **tots oberts**;
- `PackComponentAvailabilityBoundaryTest` blinda dates amb signe, validació de tots els components, ordre abans de preu/IDPAG i independència del replay.

**Estat:** implementat i cobert per prova automatitzada; resta E2E de dates límit/preproducció.

### F-20 · Faltava un verificador canònic E2E de preproducció per PACK — corregit

CURS disposava de `verify-redsys-course-preproduction.php`, però PACK només tenia peces separades de `preflight`, `preview` i `process`. Això obligava a executar-les manualment i feia més fàcil conservar evidència incompleta o saltar-se una porta de seguretat.

**Correcció aplicada:**
- nou `sif/scripts/verify-redsys-pack-preproduction.php`;
- només accepta `SIF_ENV=test|preproduction`;
- executa `preflight-redsys-pack.php` i `preflight-redsys-callback-queue.php`;
- exigeix un `DS_ORDER`;
- sempre executa el preview read-only;
- no muta res tret que s'indiqui explícitament `--execute`;
- `--sync-legacy` només s'aplica dins el bloc d'execució;
- en execució exigeix identitat de factura i payment, almenys dues atribucions de fons, identitat de tots els moviments, suma del ledger igual al total del preview i una fila d'outbox amb identitat;
- si se sol·licita sync legacy, exigeix `legacy_sync_executed=true`;
- la sortida d'evidència **no copia el payload fiscal complet del preview**: només conserva DS_ORDER, IDPAG i totals;
- `preflight-redsys-pack.php` comprova ara callback, endpoint d'intenció, worker, preview, processor, preflight de cua i el mateix verificador;
- `RedsysPackPreproductionBoundaryTest` blinda fail-closed, `--execute` explícit, evidència econòmica/outbox i sanitització.

**Estat:** eina i proves implementades. **Pendent:** executar-la contra un `DS_ORDER` Redsys real de preproducció i conservar el JSON d'evidència.

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

**SÍ, per al canal web inicial:** POST-only, sense PII a query string, frontera same-site/origin i idempotència server-side amb `REQUEST_ID`+payload hash. Continua sent un formulari anònim i resta E2E.

### Verificat

- inspecció directa contra `main@f7fa082...`: sí;
- proves unitàries/integració del paquet UC-015: sí, CI verda a `41d6968...`;
- regressió nova del motiu de descompte: escrita i coberta per la suite verda del PR a `0b32fa2...`;
- qualsevol HEAD posterior per resincronització amb `main` requereix nova CI verda abans del merge;
- E2E real: pendent.

### Pendent

1. Executar `verify-redsys-pack-preproduction.php <DS_ORDER>` amb un DS_ORDER real de preproducció; després completar PK-01..PK-11 de navegador, incloent alta POST, rebuig GET/cross-site, doble clic, replay del mateix `REQUEST_ID` i un pack amb un component fora de finestra.
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
- CI ampliada perquè endpoint, connexió, plantilla i JS PACK activin i passin lint;
- resposta post-commit desacoblada de fallades auxiliars per evitar falsos errors i reintents;
- suma comercial de components validada en cèntims contra el preu PACK abans del commit;
- idempotència server-side de l'alta amb `REQUEST_ID`, `RID/RH1`, named lock i replay/conflicte;
- cache-bust del bundle d'inscripció a `ver=7.5`;
- disponibilitat corregida: dates amb signe i exigència de tots els components oberts al llistat, fitxa i POST;
- verificador de preproducció PACK creat amb preflight, preview, execució opt-in, ledger/outbox i evidència sense payload fiscal complet.
- prova de regressió associada;
- actualització de la fitxa funcional i UML integrat;
- creació d'aquest registre de revalidació 02/10.

## 10. Criteri de tancament

UC-015 no s'ha de marcar com a completament tancat mentre quedin oberts l'E2E/preproducció, la decisió d'ordre comercial i els pendents operatius indicats. El **nucli SIF PACK** sí pot considerar-se implementat, amb evidència automatitzada prèvia, subjecta a CI verda del commit final d'aquesta auditoria.
