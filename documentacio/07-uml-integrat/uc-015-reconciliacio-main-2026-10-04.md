# UC-015 · Reconciliació amb main — 2026-10-04

**Base revisada:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`  
**PRs rellevants:** #102 (tancament integral), #149 (alineació de boundaries PACK/Redsys)  
**Abast:** fitxa funcional, PHP/JS real, UML de classes, seqüències i activitats ACTUAL/FINAL, traçabilitat, proves i governança.

## 1. Veredicte

L'UC-015 disposa de tots els lliurables principals que es demanaven:
- fitxa funcional;
- UML integrat;
- classes ACTUAL/FINAL;
- seqüències ACTUAL/FINAL;
- activitats ACTUAL/FINAL per pàgina i apartat PK-A01..PK-A10;
- auditoria/traçabilitat;
- plantilla/verificador d'evidència de preproducció.

No faltava un diagrama principal. Faltaven dues peces de governança per deixar el cas inequívocament reconciliat amb el repositori actual:
1. inventari PHP/JS per pàgina/bloc amb estat executable actual;
2. gate selectiu UC-015 independent de la suite global.

Amb aquesta passada s'afegeixen tots dos.

## 2. DOCUMENTAT

### Fitxa
`documentacio/06-fitxes-funcionals/uc-015.md` cobreix actor, precondicions, dades, regles, fiscalitat, AEAT, concurrència/idempotència, notificacions, proves, gaps i decisions.

### UML
- `uc-015-comprar-pack.md`
- `uc-015-classes-actual-final.md`
- `uc-015-sequencies-actual-final.md`
- `uc-015-activitats-pagines-pack-actual-final.md`

### Evidència
- `uc-015-plantilla-evidencia-preproduccio.md`
- `uc-015-auditoria-tracabilitat-2026-10-02.md`
- `uc-015-inventari-codi-php-js-actual-final-2026-10-04.md` (nou)

**Estat documental:** COMPLET, amb textos d'estat antics que es corregeixen en aquesta reconciliació.

## 3. IMPLEMENTAT

### Alta pública
POST-only, same-origin configurable, REQUEST_ID, fingerprint, named lock, replay segur, 409 en conflicte, N insercions atòmiques i totals server-authoritative.

### Ordre comercial
Contracte v1 `c.DATAI, p.ID_CURS` compartit per presentació/alta i congelat a `PACK_ORDINAL`.

### Checkout
`PackPaymentGate` + intenció SIF autenticada, snapshot servidor, pagament complet, DS_ORDER, callback SIF i proteccions de privacitat.

### SIF
Callback/cua/worker → `RedsysPackInvoiceService` → factura/payment → `PackEnrollmentFundAllocationService` → `PackPaymentNotificationService` → sync legacy post-SIF.

### Doble autoritat fiscal
Els callbacks productius PACK legacy estan retirats físicament.

**Estat d'implementació:** CODE COMPLETE per l'abast UC-015 actual.

## 4. VERIFICAT

### Evidència anterior
La branca del tancament #102 va concentrar el codi i la documentació, però el seu HEAD sincronitzat `151d46e...` va tenir runs vermells després de la resincronització. Per tant, no s'utilitza aquell HEAD com a prova final verda.

### Evidència vigent posterior
El PR #149 va corregir cinc boundaries desfasats sense modificar codi productiu:
- `PackEnrollmentIdempotencyBoundaryTest`;
- `PackEnrollmentTransportBoundaryTest`;
- `PackPaymentPrivacyBoundaryTest`;
- `PackPublicEnrollmentBoundaryTest`;
- `RedsysSignatureValidatorTest`.

HEAD #149: `8871e15ab7017124e4beca5d6290dc00d0bd135a`.

GitHub Actions:
- `SIF checks`: success;
- `SIF PHP MySQL tests`: success;
- suite SIF: **971 passed / 0 failed**.

Aquesta evidència acredita que els boundaries PACK/Redsys que havien quedat desfasats estan alineats amb el codi actual.

### Gate específic nou
S'afegeixen:
- `sif/tests/run-uc015-tests.php`;
- `.github/workflows/uc015-sif-checks.yml`.

A partir d'ara, el tancament UC-015 disposa d'una porta selectiva pròpia.

## 5. PENDENT

La continuació pàgina per pàgina ha detectat un gap de seguretat addicional, **SEC-015-01**, a la confirmació PACK. El gap està corregit al PR #171; per tant no queda obert com a codi pendent, però la nova implementació encara requereix CI i acceptació runtime.

Queda pendent d'**acceptació operativa**:
1. pagament PACK real en preproducció;
2. DS_ORDER real i evidència de callback/cua/worker;
3. comprovació de factura/payment + N moviments de ledger + outbox + sync legacy;
4. PK-01..PK-11 navegador;
5. variables/secrets/callback HTTPS definitius;
6. transport real de notificacions PACK.

Sobre notificacions: `NotificationOutboxDeliveryService` ja existeix i implementa claim/complete fail-closed, però aquesta auditoria no ha localitzat un worker/script SMTP productiu que dreni específicament l'outbox PACK. El pendent és, doncs, **operatiu/cutover**, no l'absència total d'infraestructura de delivery.

## 6. Desfasaments corregits en aquesta passada

### R-01 · Estat de merge/CI antic
Diversos documents encara deien «CI del HEAD final abans del merge». El #102 ja està fusionat i #149 aporta evidència posterior verda. Calia reescriure l'estat com a historial + acceptació actual.

### R-02 · Matriu global obsoleta
`00-matriu-cobertura-cataleg.md` encara classificava UC-15 com `[BASE/ASYNC/PARCIAL]`, incompatible amb la fitxa específica i l'estat real.

### R-03 · Noms de tests antics
Els runners locals UC-015 encara esperaven `testPaymentResponsePagesTreatEmailAsOptionalEscapedHint`; #149 el va substituir per `testPaymentResponsePagesDoNotExposeEmailInReturnUrlsOrViews`.

### R-04 · Gate UC-015 no selectiu
Hi havia suite global i runners locals, però no un `run-uc015-tests.php` + workflow propi. S'afegeixen.

### R-05 · Delivery descrit massa genèricament com pendent
Ja existeix `NotificationOutboxDeliveryService`. La documentació passa a distingir:
- enqueue PACK: implementat;
- gate genèric claim/complete: implementat;
- transport/worker SMTP operatiu PACK: pendent d'acreditació.

## 7. Matriu resum

| Bloc | Documentat | Implementat | Verificat | Pendent |
|---|---|---|---|---|
| PK-A01 llistat | sí | sí | inspecció | cap gap intern |
| PK-A02 fitxa/ordre | sí | sí | boundary | cap gap intern |
| PK-A03 formulari | sí | sí | boundary | E2E navegador |
| PK-A04 alta N | sí | sí | atomicitat/idempotència | E2E fallada real |\n| PK-A04b confirmació | sí | token v2 implementat PR171 | inspecció + tests escrits | CI PR171 + E2E navegador |
| PK-A05 intenció | sí | sí | CI/tests | Redsys real |
| PK-A06 callback | sí | sí | worker/boundary | callback preprod |
| PK-A07 factura | sí | sí | CI | evidència real |
| PK-A08 ledger | sí | sí | CI/E2E simulat | evidència real |
| PK-A09 notificació | sí | enqueue + delivery gate | proves | transport/cutover |
| PK-A10 fraccionament | sí | sí | tests | només excepció intranet |
| sync legacy | sí | sí | proves/inspecció | evidència runtime |

## 8. Criteri de tancament

**Auditoria tècnica/documental:** TANCADA quant a inventari i correccions, subjecta al gate selectiu d'aquest PR.  
**Implementació UC-015:** COMPLETA al PR #171 per l'abast definit, inclosa la nova frontera segura de confirmació; **CI del nou codi pendent**.  
**Acceptació de producció:** NO ACREDITADA encara; requereix preproducció real i evidència operativa.


## 9. Revalidació addicional de desplegament — 2026-10-04

### R-06 · Paritat de desplegament web/pay

S'ha comparat el circuit PACK a les dues còpies:
- `PackPaymentGate.php`: idèntic;
- `SifPaymentIntentClient.php`: idèntic;
- `apiRedsys.php`: idèntic;
- checkout: mateix contracte de negoci, amb diferències només de presentació/assets;
- connexió BD: la web conté transaccions/locks/allocator perquè crea altes; pay no els necessita;
- OK/KO de pay pertanyen al retorn autoritatiu UC-014, mentre PACK continua retornant a `www.prisma.cat`.

S'ha creat `PackDeploymentParityBoundaryTest` amb tres garanties:
1. adaptadors crítics web/pay byte-a-byte idèntics;
2. contracte PACK del checkout present a les dues còpies;
3. retorn PACK mantingut sobre la frontera pública.

### R-07 · Runbook de preproducció

La plantilla d'evidència s'ha actualitzat perquè `verify-redsys-pack-preproduction.php` sigui l'orquestrador canònic:
- dry-run;
- execute;
- execute + `--sync-legacy`;
- verificació persistent posterior amb `verify-redsys-pack-evidence.php`.

Això elimina l'ambigüitat entre scripts individuals i el flux d'acceptació recomanat.


### R-08 · SEC-015-01 — token de confirmació PACK

La revisió de la ruta `/packs/confirmacio` ha descobert una frontera que no estava separada a l'inventari original.

**ACTUAL observat a main:**
- `base64(IV + HMAC(ciphertext) + ciphertext)`;
- IV no autenticat;
- `openssl_decrypt` abans de `hash_equals`;
- HMAC només del ciphertext;
- token al path/access logs;
- parsing fràgil de `REQUEST_URI`;
- pageview analytics potencial amb la URL sensible.

**FINAL implementat al PR #171:**
- helper `PackConfirmationToken` v2;
- AES-256-CBC amb clau derivada + HMAC amb clau separada;
- MAC sobre domini + IV + ciphertext abans de decrypt;
- Base64URL;
- TTL 24 h + clock skew 5 min;
- format legacy fail-closed;
- fragment URL `#TOKEN`;
- `$_GET['keyEncr']` + `encodeURIComponent`;
- no-store / no-referrer / noindex;
- pageview automàtic desactivat;
- proves unitàries i boundary específiques;
- PK-A04b afegit als diagrames d'activitats.

**Evidència:** el baseline anterior segueix sent PR #149 = 971/0. Aquesta evidència **no s'atribueix** al nou fix; el PR #171 necessita el seu gate verd abans del merge.
