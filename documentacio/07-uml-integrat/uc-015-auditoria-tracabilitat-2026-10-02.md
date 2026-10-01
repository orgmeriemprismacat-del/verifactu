# UC-015 · Auditoria exhaustiva de traçabilitat · 2026-10-02

## 1. Abast i punt de tall

Auditoria executada contra `main` a:

- commit: `47f8f834ab3ee44db64d8e8987f8b371766329f9`;
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
| PK-A03 Formulari | sí | **parcial FINAL** | inspecció directa JS/PHP | **GET → POST/proteccions de canal** |
| PK-A04 Alta N inscripcions | sí | sí, snapshot legacy | proves/inspecció | model comercial explícit/versionat |
| PK-A05 Intenció/URL pagament | sí | sí | proves + CI històrica | prova d'entorn real |
| PK-A06 Callback | sí | sí, SIF autoritatiu | proves | Redsys preproducció |
| PK-A07 Factura | sí | sí | proves + CI | E2E real |
| PK-A08 Distribució monetària | sí | sí | proves idempotència/suma | E2E real |
| PK-A09 Correus/notificació | sí | enqueue sí | prova d'outbox | lliurament UC-58 |
| PK-A10 Fraccionament | sí | ecommerce bloquejat | proves | només circuit excepcional intranet |

## 5. Troballes verificades

### F-01 · L'alta pública continua sent GET

`mostrarInscripcioPack.min.js` envia `enviarInscripcioPack.php` amb `method: "GET"`. El PHP llegeix `$_GET` per nom, cognoms, DNI, telèfon, correu, adreça i altres camps.

**Estat:** implementat legacy, **no** implementat FINAL.

El preu sí que ha estat endurit: `enviarInscripcioPack.php` ignora imports del client i els recalcula des de BD. Per tant cal separar dues afirmacions:

- **preu backend-authoritative:** implementat;
- **transport segur/definitiu de l'alta:** pendent.

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

## 6. UML i traçabilitat

### Classes

El diagrama existeix i diferencia legacy, checkout i SIF. S'ha afegit explícitament que `EnviarInscripcioPack` rep avui l'alta per GET i que aquest canal no representa l'estat FINAL.

### Seqüències

La seqüència FINAL s'ha alineat amb la cadena executable:

`CallbackWorker → RedsysLegacySyncingProcessor → Dispatcher → RedsysPackInvoiceService → InvoiceService / Ledger / Outbox → LegacySyncService`.

### Activitats

Els 10 blocs PK-A01..PK-A10 existeixen. PK-A03 queda ara classificat correctament com:

- ACTUAL: GET legacy + preu servidor;
- FINAL: POST/proteccions de canal + snapshot/operació.

## 7. Verificació i CI

### Evidència específica UC-015

El paquet final específic del UC-015 es va fusionar a:

- `41d696824ce42b92c8a322094027337af7825987`
- PR #72
- workflow `SIF PHP MySQL tests`: **success**
- run: https://github.com/orgmeriemprismacat-del/verifactu/actions/runs/36741186555

Això és evidència de CI del bloc UC-015 després dels enduriments de 30/09.

### HEAD auditat

Entre `41d6968...` i `47f8f83...` hi ha 222 commits. No s'han modificat els fitxers web/fitxes/core PACK; les dues dependències compartides rellevants han canviat així:

- `RedsysPaymentIntentService`: s'ha afegit validació específica de `CURS`; la branca `PACK` continua cridant la mateixa `validatePackSnapshot()`;
- `process-redsys-callback-queue.php`: s'ha afegit notificació de curs; la injecció del handler PACK continua intacta.

A l'hora de l'auditoria, el workflow complet del HEAD `47f8f83...` es troba encara **queued**:

- run: https://github.com/orgmeriemprismacat-del/verifactu/actions/runs/36943292835

Per tant:

- **verificació de codi al HEAD:** sí;
- **CI específica del darrer paquet UC-015:** sí;
- **CI completa del HEAD actual:** pendent de finalització;
- **E2E navegador + Redsys preproducció:** pendent.

## 8. Estat final per categoria

### Documentat

**SÍ**, amb els lliurables requerits presents. En aquesta auditoria s'han corregit dues desalineacions: servei de sincronització inexistent al diagrama de seqüència i classificació massa optimista del transport d'alta.

### Implementat

**SÍ, per al nucli fiscal/econòmic:** snapshot, gate de pagament, intenció SIF, callback/worker, factura, payment, ledger per inscripció, enqueue outbox i sync legacy.

**PARCIAL, per al canal web inicial:** l'alta segueix sent GET legacy.

### Verificat

- inspecció directa contra `main@9da7549...`: sí;
- proves unitàries/integració del paquet UC-015: sí, CI verda a `41d6968...`;
- regressió nova del motiu de descompte: escrita en aquesta branca;
- CI de la branca/HEAD després d'aquesta auditoria: pendent;
- E2E real: pendent.

### Pendent

1. Migrar `enviarInscripcioPack.php` + JS d'alta a POST i definir/provar les proteccions definitives del canal.
2. Executar PK-01..PK-11 en preproducció amb DS_ORDER real i conservar evidència.
3. Tancar decisió de negoci sobre ordre comercial explícit vs `DATAI, ID_CURS`.
4. Eliminar físicament callback fiscal PACK legacy després de la finestra de rollback.
5. Validar lliurament real de notificació (UC-58), no només enqueue.
6. Esperar/validar CI del HEAD/PR actual.
7. Si es vol tancament formal, registrar evidències de variables d'entorn, worker i callback HTTPS de preproducció.

## 9. Canvis aplicats per aquesta auditoria

- correcció del diagrama de seqüència FINAL;
- anotació explícita del gap GET a classes i activitats;
- correcció del text fiscal intern de descompte perquè no pressuposi «segon curs»;
- prova de regressió associada;
- actualització de la fitxa funcional i UML integrat;
- creació d'aquest registre de revalidació 02/10.

## 10. Criteri de tancament

UC-015 no s'ha de marcar com a completament tancat mentre quedin oberts el transport GET de l'alta i l'E2E/preproducció. El **nucli SIF PACK** sí pot considerar-se implementat, amb evidència automatitzada prèvia, subjecta a CI verda del commit final d'aquesta auditoria.
