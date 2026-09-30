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

