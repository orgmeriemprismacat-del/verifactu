# Auditoria detallada UC-008 · Gestionar incidència SIF · 2026-09-30

**Repositori auditat:** `orgmeriemprismacat-del/verifactu`  
**Branca de referència:** `main`  
**Objectiu:** reconciliar fitxa funcional, implementació PHP/JS, UML ACTUAL/FINAL, proves, traçabilitat i pendents reals del UC-008.

## 1. Conclusió executiva

El UC-008 disposa del paquet documental principal i de la implementació específica necessària per al cicle de vida d'incidències. No s'ha detectat cap buit de codi que obligui a afegir una nova peça PHP/JS per completar el contracte documentat.

L'estat correcte queda separat així:

- **DOCUMENTAT:** complet a nivell de fitxa funcional, fitxa integrada, classes, seqüències, activitats, desplegament, menú i proves.
- **IMPLEMENTAT:** backend lifecycle, repositoris, API interna, panell SIF, pont read-only de la intranet, handoff HMAC, preflight, E2E tècnic i validador d'evidències.
- **VERIFICAT:** proves automatitzades de lifecycle, seguretat, idempotència, concurrència, UI contract, frontera intranet read-only, preflight i gate d'evidències. La documentació conserva evidència de CI satisfactòria del bloc UC-008.
- **PENDENT:** verificació sobre l'entorn real de preproducció, rols/secrets reals, descoberta/alta idempotent del menú `/sif-verifactu.php` i conservació de les evidències JSON. Aquest pendent és d'entorn, no de manca de codi.

## 2. Inventari documental

### 2.1. Fitxa funcional

Existeix:

- `documentacio/06-fitxes-funcionals/uc-008.md`

La fitxa cobreix actors, precondicions, entrades, regles, persistència, fiscalitat, idempotència, concurrència, notificacions, proves i decisions pendents.

**Correcció aplicada en aquesta auditoria:** s'ha actualitzat la versió a 1.3 i s'ha substituït l'estat obsolet `IMPLEMENTATION_IN_PROGRESS / E2E_PENDING` per un estat que diferencia codi complet de verificació d'entorn pendent.

### 2.2. Fitxa/UML integrats

Existeix:

- `documentacio/07-uml-integrat/uc-008-gestionar-incidencia-sif.md`

Defineix la frontera UC-008 / UC-081, persistència, lifecycle, API interna, actors, panell oficial, resum intranet i procediment de tancament d'entorn.

### 2.3. Classes ACTUAL/FINAL

Existeix:

- `documentacio/07-uml-integrat/uc-008-classes-actual-final.md`

El model ha de correspondre a les peces reals:

- `IncidentLifecycleService`
- `IncidentRepository`
- `IncidentActionRepository`
- `InternalApiAuthenticator`
- `PanelLaunchAuthenticator`
- `IncidentPanelSession`
- `SifInternalIncidentClient`
- `SifPanelLaunchToken`

No s'ha trobat cap classe essencial del flux implementat que quedi fora del paquet funcional del UC-008.

### 2.4. Seqüències ACTUAL/FINAL

Existeix:

- `documentacio/07-uml-integrat/uc-008-sequencies-actual-final.md`

Les seqüències han de cobrir, com a mínim:

1. consulta read-only des de la intranet;
2. handoff signat intranet → SIF;
3. sessió i CSRF del panell;
4. list/view;
5. assign;
6. evidence;
7. resolve/dismiss;
8. reopen;
9. obertura automàtica des de Redsys/AEAT;
10. reintent idempotent/concurrent.

El codi real revisat és coherent amb aquesta arquitectura.

### 2.5. Activitats ACTUAL/FINAL per pàgina

Existeix:

- `documentacio/07-uml-integrat/uc-008-activitats-pagines-incidencies-actual-final.md`

Les superfícies reals són:

- intranet: `sif-verifactu.php`;
- pont de consulta: `ajax/sif/sifIncidents.php`;
- handoff: `ajax/sif/sifPanelLaunch.php`;
- panell SIF: `sif/public/sif/incidencies/index.php`;
- accions del panell: `sif/public/sif/incidencies/actions.php`;
- client UI: `sif/public/sif/incidencies/app.js`.

## 3. Auditoria del codi PHP/JS real

### 3.1. Intranet read-only

`codi-drive/intranet-actual/ajax/sif/sifIncidents.php`:

- exigeix POST;
- exigeix sessió vàlida;
- valida CSRF;
- només permet `summary`, `list`, `view`;
- rebutja explícitament mutacions;
- deriva identitat i rols de la sessió autenticada;
- signa la petició servidor→SIF mitjançant `SifInternalIncidentClient`.

**Estat:** IMPLEMENTAT i VERIFICAT per tests de frontera.

### 3.2. Handoff intranet → SIF

`codi-drive/intranet-actual/ajax/sif/sifPanelLaunch.php`:

- exigeix POST i sessió;
- valida CSRF;
- no accepta identitat ni rols del navegador;
- crea token signat amb dades de sessió.

**Estat:** IMPLEMENTAT i VERIFICAT.

### 3.3. Client HMAC intern

`SifInternalIncidentClient.php`:

- exigeix configuració;
- força HTTPS excepte localhost explícit;
- normalitza rols;
- usa timestamp + request-id UUID + actor + rols + hash del body;
- genera HMAC SHA-256;
- no segueix redirects.

**Estat:** IMPLEMENTAT.

### 3.4. API interna d'incidències

`sif/public/api/incidents/manage.php`:

- autentica via `InternalApiAuthenticator`;
- aplica anti-replay persistent;
- separa lectura i gestió;
- ofereix `summary/list/view/open/assign/evidence/resolve/dismiss/reopen`;
- falla tancat si els rols configurats no autoritzen.

**Estat:** IMPLEMENTAT i VERIFICAT.

### 3.5. Lifecycle

`IncidentLifecycleService.php` implementa:

- `summary`
- `list`
- `view`
- `open`
- `assign`
- `addEvidence`
- `resolve`
- `dismiss`
- `reopen`

Propietats verificades:

- permisos de lectura/gestió;
- transaccions;
- idempotència per acció;
- reintent després de duplicate key concurrent;
- `RESOLVED` exigeix evidència;
- una incidència tancada no es pot mutar com a activa;
- només una incidència tancada es pot reobrir;
- `correlation_id` obligatori a les mutacions.

**Estat:** IMPLEMENTAT i VERIFICAT.

### 3.6. Persistència

`IncidentRepository` + `IncidentActionRepository`:

- capçalera a `errors_verifactu`;
- identitat `UUID_INCIDENT`;
- referència opcional a factura/pagament;
- recurs/origen genèrics;
- severitat, responsable, correlació i idempotència;
- historial append-only a `sif_incident_action`;
- reús idempotent només si el payload lògic és coherent.

**Estat:** IMPLEMENTAT i VERIFICAT.

### 3.7. Panell SIF

`sif/public/sif/incidencies/index.php` + `actions.php` + `app.js`:

- sessió pròpia SIF;
- CSRF;
- UI diferenciada read-only / manager;
- filtres;
- detall;
- timeline;
- assignació;
- evidència;
- resolució/descart;
- reobertura;
- deep-links a factura SIF i registre AEAT;
- no hi ha cap “retry all”.

**Estat:** IMPLEMENTAT i VERIFICAT a nivell de contracte/integració. E2E navegador real pendent.

## 4. Integracions automàtiques

El UC-008 no només és UI.

### Redsys

`RedsysCallbackWorker` obre incidències correlacionades quan correspon i evita separar el canvi d'estat del job de l'obertura de la incidència fora de la transacció prevista.

### AEAT

`FiscalQueueProcessor` obre incidències per conflictes d'integritat o esgotament de retries/dead-letter, mantenint la decisió fiscal fora del UC-008.

**Frontera correcta:** UC-008 governa l'expedient; la reparació real continua delegada al UC específic.

## 5. Proves revisades

Existeixen proves específiques per:

- lifecycle complet;
- idempotència i conflicte de payload;
- factura/pagament desconegut;
- recurs `type/id` incomplet;
- permisos read-only;
- tancament amb evidència;
- dismiss/reopen;
- filtres i resum;
- concurrència real;
- HMAC/anti-replay;
- contracte UI;
- frontera intranet read-only;
- preflight;
- verificació de preproducció;
- E2E tècnic;
- validació final d'evidències.

Fitxers destacats:

- `IncidentLifecycleTest.php`
- `IncidentConcurrencyTest.php`
- `IncidentInternalApiSecurityTest.php`
- `IncidentPanelUiContractTest.php`
- `IncidentPanelIntranetBoundaryTest.php`
- `IncidentPanelPreflightScriptTest.php`
- `IncidentPanelPreproductionVerificationScriptTest.php`
- `IncidentPanelEvidenceValidationScriptTest.php`

**Interpretació correcta de “verificat”:** aquestes proves acrediten el comportament del repositori i la integració automatitzada. No acrediten per si soles la configuració real del servidor, la BD del menú ni una sessió humana de navegador contra preproducció.

## 5 bis. Revalidació contra el `main` posterior als merges del 30/09/2026

Després de la primera reconciliació UC-008, `main` ha continuat avançant. S'ha comparat el tall UC-008 `14fb5175c8bc7bb3c9c27b4eaa0d03835a99e0dd` amb el `main` `e2fd82215dc9dbd1a6938014c19985adceebd3ed`.

Resultat de la comparació:

- **57 commits** posteriors al tall UC-008.
- **166 fitxers** modificats en total.
- D'entre les dependències directes o compartides inventariades pel UC-008, només ha canviat `sif/config/sif.php`.
- El canvi de `sif/config/sif.php` és **purament additiu per UC-111**: afegeix `novice_promotion_signed_path` i `novice_promotion.manage_roles`. No modifica rols d'incidències, paths UC-008, HMAC del panell, lifecycle ni persistència d'incidències.
- No han canviat `IncidentLifecycleService`, `IncidentRepository`, `IncidentActionRepository`, `InternalApiAuthenticator`, `PanelLaunchAuthenticator`, `IncidentPanelSession`, `SifInternalIncidentClient`, `sifIncidents.php`, `sifPanelLaunch.php` ni `sif/public/sif/incidencies/*` en aquest interval.

### Regressió CI sobre el `main` actual

El workflow **SIF PHP MySQL tests** de l'últim tall de codi SIF verificat, commit `e2fd82215dc9dbd1a6938014c19985adceebd3ed`, run **36732555122**, ha finalitzat en **success** amb:

- **740 passed**
- **0 failed**

Dins d'aquest mateix run s'han comprovat explícitament **61 proves PASS** relacionades amb incidències/UC-008 i les seves integracions. Inclouen:

- esquema i identitat d'incidències;
- concurrència real d'obertura i assignació;
- lifecycle, idempotència i conflicte per payload divergent;
- rols read/manage;
- HMAC, timestamp i anti-replay;
- contracte de l'API interna;
- sessió del panell i CSRF;
- UI, timeline, tancament amb evidència i absència de bulk retry;
- frontera read-only de la intranet i fallback davant caiguda del SIF;
- handoff autenticat intranet → SIF;
- preflight i verificador agregat de preproducció;
- validador final d'evidències;
- integracions Redsys i AEAT que obren/reutilitzen incidències.

Aquesta regressió posterior confirma que els merges posteriors no han introduït una regressió detectable al codi UC-008.

**Revalidació del capçal final de l'auditoria:** amb activitat paral·lela al repositori, l'últim capçal observat abans de tancar aquesta passada és `1b332f62add5ebe669b1fd3981fec1d6cc3257c4`. La comparació `e2fd8221...1b332f62` conté **17 commits / 11 fitxers** i **cap** afecta les dependències UC-008 inventariades (incident lifecycle/repositories, panell, API interna, bridge intranet, RedsysCallbackWorker, FiscalQueueProcessor o configuració compartida). Per tant, el run 740/0 continua sent l'evidència de regressió aplicable al codi UC-008 fins aquest punt de control.

**Estat després de la revalidació:** `CODE_COMPLETE + DOCUMENTATION_RECONCILED + CURRENT_MAIN_REGRESSION_GREEN`. Continua pendent únicament el tancament d'entorn real.

## 6. Matriu Documentat / Implementat / Verificat / Pendent

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa funcional | Sí | N/A | Revisada | manteniment futur |
| Classes ACTUAL/FINAL | Sí | Sí | coherent amb codi | — |
| Seqüències ACTUAL/FINAL | Sí | Sí | coherent amb flux | E2E visual real |
| Activitats per pàgina | Sí | Sí | coherent amb superfícies | E2E visual real |
| Intranet read-only | Sí | Sí | tests | desplegament real |
| Handoff HMAC | Sí | Sí | tests | secrets reals |
| API interna | Sí | Sí | tests | configuració real |
| Lifecycle | Sí | Sí | tests | — |
| Idempotència | Sí | Sí | tests | — |
| Concurrència | Sí | Sí | tests | càrrega real opcional |
| Panell SIF | Sí | Sí | contract/integration | navegador preprod |
| Deep-links | Sí | Sí | tests | comprovació amb dades reals |
| Redsys → incidència | Sí | Sí | tests | observació preprod |
| AEAT → incidència | Sí | Sí | tests | observació preprod |
| Preflight | Sí | Sí | tests | executar en preprod |
| Menú intranet | Sí | plantilla/preflight | contracte | descoberta + INSERT si falta |
| Gate evidències | Sí | Sí | tests | generar JSON reals |

## 7. Mancances reals

### 6 bis. Comprovació d'evidències de tancament

S'ha comprovat el `main` actual i els artifacts dels workflows verds `36732555122` i `36732555092`.

No consten al repositori ni als artifacts:

- `uc-008-preproduction-evidence.json`;
- `uc-008-menu-evidence.json`;
- `uc-008-closure-validation.json`.

Per tant, el resultat **740/0** acredita regressió de codi i contractes automatitzats, però **no acredita l'execució real de preproducció ni el tancament de l'entorn**. No s'ha fabricat ni inferit cap evidència absent.

### 6 ter. Enduriment del gate de tancament — 02/10/2026

En una revisió específica dels scripts de tancament s'ha detectat que el validador final podia acceptar una evidència amb `ok=true` generada sota `SIF_ENV=test`. Això era incompatible amb el criteri de tancament, que exigeix evidència de **preproducció real**.

Canvis aplicats:

- `validate-uc008-evidence.php` exigeix ara `environment=preproduction`;
- exigeix `checks.preflight_ok=true`;
- exigeix `checks.e2e_ok=true`;
- exigeix `menu.ok=true`;
- valida explícitament `scope=uc-008-intranet-menu-discovery`;
- manté l'exigència d'una única entrada `/sif-verifactu.php` amb `status=ALREADY_PRESENT`;
- s'han afegit proves negatives per impedir que `test`, un scope de menú incorrecte o un `ok=true` superior sense checks interns tanquin el gate;
- la sanitització del verificador de preproducció s'ha alineat amb el validador final per eliminar també claus que continguin `signature`, `merchant_key` o `certificate_password`.

També s'ha unificat el nom de l'evidència del menú a `uc-008-menu-evidence.json`.

Aquest enduriment no canvia el lifecycle ni la UI del UC-008; reforça exclusivament la qualitat i traçabilitat de l'evidència necessària per declarar l'entorn tancat.

A més, el resultat `uc-008-closure-validation.json` incorpora ara `validated_at` i els **SHA-256** dels dos fitxers d'entrada (`preproduction_sha256` i `menu_sha256`). El gate exigeix hashes vàlids de 64 caràcters, de manera que el resultat final queda lligat als JSON exactes que s'han validat sense exposar-ne el path ni el contingut.

### 7.1. Pendents obligatoris d'entorn

1. Executar:
   `php sif/scripts/verify-incidents-panel-preproduction.php`
2. Conservar:
   `uc-008-preproduction-evidence.json`
3. Executar a la intranet:
   `php preflight-sif-verifactu-menu.php`
4. Si `/sif-verifactu.php` no existeix a `apartats`, confirmar pare, nivell, rols, ordre i icona abans de fer l'alta.
5. Tornar a executar el preflight del menú fins obtenir `ALREADY_PRESENT`.
6. Validar el parell:
   `php sif/scripts/validate-uc008-evidence.php uc-008-preproduction-evidence.json uc-008-menu-evidence.json`
7. Conservar `uc-008-closure-validation.json` amb `ok=true`.
8. Fer una passada de navegador amb:
   - usuari read-only;
   - gestor;
   - deep-link factura;
   - deep-link AEAT;
   - resolució amb evidència;
   - logout.

### 7.2. No s'ha de considerar manca de UC-008

No cal afegir un `RepairRouter` genèric ni una reparació automàtica massiva. Això trencaria la frontera funcional: la incidència governa i deriva, però la mutació fiscal/econòmica correspon al UC corrector.

## 8. Traçabilitat

### Persistència

- `errors_verifactu` → capçalera de l'expedient.
- `sif_incident_action` → historial immutable.
- `internal_api_request` → anti-replay.

### Entrada intranet

- `sif-verifactu.php`
- `js/sif-verifactu.js`
- `ajax/sif/sifIncidents.php`
- `ajax/sif/sifPanelLaunch.php`

### Entrada SIF

- `public/api/incidents/manage.php`
- `public/sif/incidencies/index.php`
- `public/sif/incidencies/actions.php`
- `public/sif/incidencies/app.js`

### Domini

- `IncidentLifecycleService`
- `IncidentRepository`
- `IncidentActionRepository`

### Seguretat

- `InternalApiAuthenticator`
- `PanelLaunchAuthenticator`
- `IncidentPanelSession`

### Readiness

- `preflight-incidents-panel.php`
- `e2e-incidents-panel.php`
- `verify-incidents-panel-preproduction.php`
- `validate-uc008-evidence.php`

## 9. Canvis aplicats en aquesta auditoria

1. Actualitzada `documentacio/06-fitxes-funcionals/uc-008.md` a versió 1.3.
2. Reconciliat l'estat del cas: codi complet/verificat al repositori, entorn pendent.
3. Creat aquest document com a auditoria vigent del 2026-09-30.
4. No s'ha modificat PHP/JS perquè l'auditoria no ha detectat un buit de codi justificat que calgui corregir ara.

## 10. Criteri de tancament

El UC-008 es pot considerar **CODE-COMPLETE**.

No s'ha de considerar **ENVIRONMENT-CLOSED / PRODUCTION-VERIFIED** fins que:

- preproduction verifier → `ok=true`;
- menu preflight → `ALREADY_PRESENT`;
- evidence validator → `ok=true`;
- passada funcional real amb rols autoritzats/read-only → satisfactòria.

