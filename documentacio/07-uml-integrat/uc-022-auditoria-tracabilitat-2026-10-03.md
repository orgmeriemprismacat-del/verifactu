# UC-022 — Auditoria detallada i matriu de traçabilitat

**Data:** 03/10/2026  
**Cas:** UC-022 · Registrar una transferència rebuda  
**Mètode:** lectura estàtica exhaustiva de documentació, PHP/JS llegat, nucli SIF i tests del repositori.  
**Criteri:** `DOCUMENTAT` descriu contracte; `IMPLEMENTAT` exigeix codi executable; `VERIFICAT CODI` implica contrast directe del codi; `VERIFICAT TEST` només s'usa quan consta execució. En aquesta auditoria no s'ha executat la suite.

## 1. Resum executiu

| Àrea | Estat | Evidència |
| --- | --- | --- |
| Fitxa funcional específica | DOCUMENTAT, REVISAT | `documentacio/06-fitxes-funcionals/uc-022.md` |
| UML integrat | DOCUMENTAT, REVISAT | `uc-022-registrar-transferencia.md` |
| Nucli SIF per factura existent | IMPLEMENTAT / VERIFICAT CODI | `ManualPaymentService`, `ManualPaymentPayloadBuilder`, `PaymentService`, `PaymentRepository` |
| Idempotència per payload V2 | IMPLEMENTAT / VERIFICAT CODI | `PaymentService::assertSamePayload()` |
| Cerca factura per UUID/número visible | IMPLEMENTAT / VERIFICAT CODI | `ManualPaymentInvoiceRepository` |
| Registre CHARGE + allocation + estat cobrament | IMPLEMENTAT / VERIFICAT CODI | `PaymentRepository` |
| Tests del servei manual | IMPLEMENTATS, NO EXECUTATS EN AQUESTA AUDITORIA | `ManualPaymentServiceTest` i `ManualPaymentPayloadBuilderTest` |
| Pantalla intranet «Pagaments» | IMPLEMENTAT LLEGAT / VERIFICAT CODI | `codi-drive/intranet-actual/alumnes-pagaments.php` + JS |
| Endpoint llegat de comanda | IMPLEMENTAT LLEGAT / VERIFICAT CODI | `ajax/alumnes/efectuarPagament.php` |
| Endpoint SIF intern → ManualPaymentService | **IMPLEMENTAT EN AQUESTA BRANCA** | `sif/public/api/payments/manual-transfer.php` + `ManualTransferCommandService` |
| Autorització específica, CSRF i POST | PENDENT | el flux ACTUAL usa GET i sessió serialitzada |
| Identitat bancària externa única | **IMPLEMENTADA AL CONTRACTE SIF** | `external_bank_event_id` → `PROVIDER_REF` + idempotència SHA-256 de l'event bancari |
| Conciliació transversal entre canals | PENDENT | no localitzat resolvedor bancari |
| Una transferència → N factures | PENDENT UC-105 | builder manual crea una sola allocation |
| Dossier classes ACTUAL/FINAL | CREAT EN AQUESTA AUDITORIA | `uc-022-classes-actual-final.md` |
| Dossier seqüències ACTUAL/FINAL | CREAT EN AQUESTA AUDITORIA | `uc-022-sequencies-actual-final.md` |
| Dossier activitats per pàgina/apartat | CREAT EN AQUESTA AUDITORIA | `uc-022-activitats-pagines-actual-final.md` |

## 2. Inventari de superfícies i codi

### P01 — Pàgina intranet `/alumnes/pagaments/`
- `codi-drive/intranet-actual/alumnes-pagaments.php`: inclou `inc/comprovarSessio.php` abans de renderitzar.
- `codi-drive/intranet-actual/js/alumnes-pagaments.js`: cerca per DNI, codi regal o número de factura; només permet un criteri; valida al client import, data i banc.
- El JS previsualitza en determinats casos i finalment crida `aplicarPagament(...)`.

### P02 — Comanda llegat
- `aplicarPagament()` envia **GET** a `ajax/alumnes/efectuarPagament.php`.
- Envia `id`, `numFact`, `tipus`, `pagament`, `dataPag`, `banc`, `obs` i `efact`.
- L'endpoint fa `session_start()`, deserialitza `usuari`/`intranet` i delega a `Intranet::efectuarPagament(...)`.
- En el fitxer endpoint inspeccionat no hi ha `comprovarSessio.php`, token CSRF ni verb POST.

### P03 — Nucli SIF manual
- `ManualPaymentService::registerByUuid()` i `registerByNumVisible()` localitzen la factura i deleguen al builder.
- `ManualPaymentPayloadBuilder` exigeix import positiu i data; per defecte usa `TRANSFERENCIA`, `CHARGE`, `INTRANET` i una sola allocation.
- Amb `reference`, la clau és `TRANSFERENCIA|REF:<reference>`; sense referència, combina factura, data, import i banc.
- `PaymentService` valida, bloqueja per clau, compara payload hash V1/V2 i reutilitza només si el payload és equivalent.
- `PaymentRepository` persisteix `payment_transaction`, `payment_allocation` i recalcula `factura.ESTAT_COBRAMENT`.

### P04 — API genèrica SIF
- `sif/public/api/payments/register.php` exposa el `PaymentService` genèric.
- No instancia `ManualPaymentService` ni acredita la vinculació directa de la pantalla d'intranet al servei manual.

## 3. Troballes detallades

| ID | Troballa | Estat |
| --- | --- | --- |
| UC022-01 | La fitxa funcional existia però continuava com `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW`. | DOCUMENTAT |
| UC022-02 | L'UML integrat ja descrivia el servei manual i riscos, però no separava dossiers complets ACTUAL/FINAL per classes, seqüències i activitats. | DOCUMENTAT |
| UC022-03 | El circuit real d'intranet usa JS llegat + GET amb efectes persistents. | VERIFICAT CODI |
| UC022-04 | La pàgina principal sí inclou `comprovarSessio.php`; l'endpoint de comanda inspeccionat no. | VERIFICAT CODI |
| UC022-05 | No s'ha localitzat CSRF específic a l'endpoint `efectuarPagament.php`. | VERIFICAT REPO |
| UC022-06 | El navegador envia import, data, banc i observacions; el control visible és client-side. | VERIFICAT CODI |
| UC022-07 | El nucli SIF manual no està connectat documentalment ni per codi a aquest endpoint llegat. | VERIFICAT REPO |
| UC022-08 | `ManualPaymentService` rebutja factura inexistent abans de registrar moviment. | VERIFICAT CODI |
| UC022-09 | `ManualPaymentPayloadBuilder` força CHARGE/INTRANET i una allocation a una factura. | VERIFICAT CODI |
| UC022-10 | El builder valida import > 0, data no buida i mètode admès. | VERIFICAT CODI |
| UC022-11 | Amb referència, la idempotency key no inclou factura/import; la seguretat davant payload contradictori depèn del hash V2 de `PaymentService`. | VERIFICAT CODI |
| UC022-12 | `PaymentService` compara el payload existent i llança conflicte si mateixa clau té payload diferent. | VERIFICAT CODI |
| UC022-13 | `PaymentRepository` actualitza l'estat `PARTIAL/PAID/OVERPAID` a partir del ledger. | VERIFICAT CODI |
| UC022-14 | La persistència no acredita identitat bancària externa immutable; `REFERENCIA_BANCARIA` és text. | VERIFICAT CODI / GAP |
| UC022-15 | Dues transferències reals amb la mateixa referència textual poden col·lidir amb la política actual de clau. | RISC DEDUÏT |
| UC022-16 | Sense referència, factura+dia+import+banc és heurística de reintent, no identificador bancari extern. | VERIFICAT CODI / GAP |
| UC022-17 | La conciliació del titular/pagador i l'existència real de l'abonament queden fora del servei revisat. | VERIFICAT LÍMIT |
| UC022-18 | Una transferència repartida entre diverses factures no és suportada pel builder manual. | VERIFICAT CODI |
| UC022-19 | El repositori genèric suporta múltiples allocations, però cal contracte UC-105 i control del saldo de l'entrada. | IMPLEMENTAT BASE / PENDENT CAS |
| UC022-20 | Els tests existents cobreixen alta per UUID, número visible, reintent equivalent, parcial i factura absent. | VERIFICAT CODI |
| UC022-21 | Els tests existents no acrediten, per si sols, execució en MySQL/preproducció. | PENDENT VERIFICACIÓ |
| UC022-22 | No hi ha prova E2E intranet → SIF → ledger → sincronització llegat. | PENDENT |
| UC022-23 | No hi ha evidència específica de permisos de comanda, CSRF, idempotency key del canal i resposta JSON tipificada. | PENDENT |
| UC022-24 | L'API genèrica de pagaments no substitueix un adaptador manual autoritzat perquè accepta payload complet del client. | VERIFICAT ARQUITECTURA |
| UC022-25 | El flux FINAL ha de separar «identificar entrada bancària» de «assignar-la a factura/es». | DECISIÓ ARQUITECTÒNICA DOCUMENTADA |

## 4. ACTUAL vs FINAL

| Capacitat | ACTUAL | FINAL |
| --- | --- | --- |
| Cerca | DNI/codi regal/factura des del JS | cerca server-side amb permisos i objecte factura SIF |
| Comanda | GET llegat | POST autenticat + CSRF + permís específic |
| Import | enviat pel navegador | validat contra entrada bancària i saldo disponible |
| Identitat transferència | referència/banc/data/import | `external_bank_event_id` o equivalent immutable |
| Idempotència | no acreditada al canal llegat | clau de canal + identitat externa + payload hash |
| Persistència | llegat `Intranet::efectuarPagament` | `payment_transaction` + allocations SIF |
| Multifactura | flux llegat no reconciliat amb SIF | un CHARGE + N allocations, UC-105 |
| Sincronització llegat | escriptura directa llegat | només després de commit SIF, reintent idempotent |
| Errors | text HTML / cerca de paraula `error` | JSON tipificat: CREATED/REUSED/CONFLICT/VALIDATION/ERROR |
| Auditoria | no acreditada end-to-end | payment_action_event/operational_event + correlation |

## 5. Verificat vs no verificat

### VERIFICAT CODI
- existència i forma del flux JS/GET;
- validacions del builder;
- cerca de factura;
- persistència del ledger;
- càlcul de l'estat de cobrament;
- idempotència V1/V2 de `PaymentService`;
- tests existents al repositori.

### NO VERIFICAT EN EXECUCIÓ
- execució de la suite;
- MySQL `sif_test*`;
- preproducció;
- permisos reals del canal;
- protecció CSRF;
- prova d'una transferència real;
- sincronització posterior al commit;
- E2E amb factura prèvia, parcial, complet, conflicte i multifactura.

## 6. Mancances prioritzades

### P0 — abans d'activar el canal
1. Connectar l'adaptador intranet server-side amb l'endpoint signat `POST /api/payments/manual-transfer.php` ja implementat.
2. Substituir GET per POST i validar sessió, rol, permís específic i CSRF.
3. No acceptar com autoritatius import/factura/estat només perquè els envia el client.
4. Fer que la intranet subministri l'`external_bank_event_id` immutable exigit pel nou contracte; definir la font exacta d'aquest identificador en la conciliació bancària.
5. Definir resposta JSON tipificada i gestió explícita de `CONFLICT`.
6. Garantir que cap fallback escriu directament al llegat si falla el SIF.

### P1
7. Implementar/ratificar UC-105 per multifactura.
8. Registrar auditoria funcional/correlació de la comanda.
9. Sincronització llegat post-commit amb cua/reintent idempotent.
10. Proves d'integració de canal i evidència MySQL/preproducció.

## 7. Criteris de tancament UC-022

UC-022 no s'ha de marcar `COMPLETE` fins que:
- la pantalla real cridi el contracte SIF;
- la comanda sigui POST autoritzada i protegida;
- la intranet aporti i conservi l'`external_bank_event_id` estable que el SIF ja exigeix;
- els reintents equivalents reutilitzin i els contradictoris retornin conflicte;
- parcial/complet/sobrepagament quedin demostrats;
- la sincronització llegat no pugui duplicar el CHARGE;
- existeixi evidència d'execució de tests i, com a mínim, una prova de preproducció.

## 8. Relacions

- [Fitxa funcional](../06-fitxes-funcionals/uc-022.md)
- [UML integrat](uc-022-registrar-transferencia.md)
- [Classes ACTUAL/FINAL](uc-022-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-022-sequencies-actual-final.md)
- [Activitats per pàgina/apartat](uc-022-activitats-pagines-actual-final.md)
- UC-002: registre de cobrament genèric.
- UC-104: excés/saldo.
- UC-105: una transferència repartida entre diverses factures.


## 9. Implementació posterior dins la branca d'auditoria

### 9.1. Endpoint intern signat

S'ha incorporat `sif/public/api/payments/manual-transfer.php`, exclusivament **POST**, autenticat amb el mecanisme existent `InternalApiAuthenticator`. Això aporta:

- HMAC SHA-256 sobre mètode, path, timestamp, request UUID, actor, rols i hash del body;
- finestra temporal configurable;
- anti-replay persistent amb `internal_api_request`;
- identitat d'actor i rols procedents de la intranet/server caller;
- resposta JSON SIF homogènia.

### 9.2. Autorització de la comanda

`ManualTransferCommandService` exigeix:
- actor autenticat;
- intersecció amb `SIF_MANUAL_TRANSFER_ROLES`;
- exactament un selector de factura (`uuid_factura` o `num_visible`);
- `external_bank_event_id` no buit;
- mètode forçat a `TRANSFERENCIA`.

### 9.3. Identitat de l'entrada bancària

`ManualPaymentPayloadBuilder` ara prioritza:

`TRANSFERENCIA|BANK_EVENT_SHA256:<sha256(BANC + "\\n" + external_bank_event_id)>`

i persisteix aquest identificador a `payment_transaction.PROVIDER_REF`.

La `reference` lliure continua a `REFERENCIA_BANCARIA`, però ja no és la identitat principal quan existeix l'event immutable.

### 9.4. Tests nous

- `ManualTransferCommandServiceTest`: rol autoritzat, rol denegat, event bancari obligatori i conflicte del mateix event sobre factura diferent.
- `ManualPaymentPayloadBuilderTest`: prioritat de `external_bank_event_id` respecte de la referència lliure.
- `ManualPaymentServiceTest`: mateix `reference` amb payload/factura diferent retorna conflicte.

Aquests tests continuen com **CREATS / PENDENTS DE RESULTAT** fins que finalitzi el workflow MySQL del PR.


## 10. Preparació del caller d'intranet

S'han afegit dos components a `codi-drive/intranet-nova-canvis-verifactu/`:

- `SifInternalApiClient.php`: client genèric server-to-server que genera request UUID, normalitza rols, calcula el body hash, construeix la cadena canònica i signa amb HMAC SHA-256 exactament com espera `InternalApiAuthenticator`.
- `SifManualTransferGateway.php`: adaptador UC-022 que envia `num_visible`, import, data, `external_bank_event_id`, referència, banc i notes a `POST /api/payments/manual-transfer.php`.

Aquests fitxers **encara no estan connectats al JavaScript/endpoint llegat**. La raó és deliberada: el flux actual no disposa d'una font fiable d'`external_bank_event_id` ni d'un contracte CSRF local documentat. Connectar-lo inventant un identificador derivat de data/import/banc degradaria la garantia que acabem d'implementar.

### 10.1. Configuració runtime requerida a la intranet

- `SIF_INTERNAL_API_BASE_URL`
- `SIF_INTERNAL_API_KEY_ID`
- `SIF_INTERNAL_API_SECRET`
- opcional `SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH`

El secret no s'ha d'exposar mai al navegador ni persistir al repositori.


## 11. Continuació executable del canal intranet

### 11.1. Frontera funcional descoberta al llegat

El mètode `Intranet::efectuarPagament(...)` barreja dos casos diferents:

- `efact = 0`: calcula numeració, crea una factura nova al llegat i després aplica el cobrament. **No és UC-022** i no s'ha migrat dins aquest tall.
- `efact = 1`: parteix d'una factura ja generada, actualitza el cobrament i les inscripcions relacionades. **Aquest és el tall UC-022**.

El JavaScript nou només envia `efact=1` al canal SIF. El camí `efact=0` queda explícitament temporal i pendent del cas d'ús d'emissió corresponent.

### 11.2. Defecte funcional del projector llegat

En `efectuarPagamentFacturaGenerada()`, la consulta `updPayInscr` és:

`UPDATE inscripcions SET PAGAMENT=?, FACTURA_RELACIONADA=? WHERE ID=?`

i en el cas parcial el valor calculat pot ser només l'import del moviment actual. Per tant, un cobrament successiu pot sobreescriure el valor acumulat en lloc de projectar el total confirmat.

Aquesta lògica **no es reutilitza** després del commit SIF.

### 11.3. Projecció idempotent SIF → llegat

S'ha afegit `GeneratedInvoiceLegacyPaymentSyncService`:

1. calcula el total confirmat de la factura des de `payment_transaction + payment_allocation`;
2. localitza `factura_relacionada` pel `NUM_VISIBLE`;
3. bloqueja factura i inscripcions llegades;
4. reparteix de manera determinista el total acumulat entre les inscripcions;
5. escriu valors absoluts derivats del ledger SIF, no increments;
6. limita la projecció al total contractual llegat;
7. **no actualitza `factures`**: la factura fiscal llegada queda fora de la projecció;
8. és segura davant reintents perquè deriva valors absoluts del ledger SIF.

Si el cobrament SIF queda confirmat però la projecció falla, l'endpoint respon `202 PENDING_RETRY`. El mateix `external_bank_event_id` es pot reenviar: el cobrament queda `REUSED` i només es reintenta la projecció.

### 11.4. Seguretat navegador → intranet → SIF

Implementat a `codi-drive/intranet-nova-canvis-verifactu`:

- `SifPaymentSessionGuard.php`: sessió obligatòria, refresc dels rols vigents des de BD i CSRF amb comparació constant.
- `ajax/alumnes/obtenirTokenPagamentSif.php`: obtenció del token CSRF, només lectura i `no-store`.
- `ajax/alumnes/registrarTransferenciaSif.php`: només POST JSON; no accepta mutació GET; obté actor/rol de sessió i no del navegador.
- `js/alumnes-pagaments.js`: per `efact=1` exigeix l'ID real del moviment bancari i usa el nou POST.
- `SifInternalApiClient.php`: signatura HMAC server-to-server; el secret no passa al navegador.

### 11.5. Separació transferència / TPV

El canal UC-022 rebutja `TPV` i `REDSYS` tant a la intranet com al servei SIF. Aquests cobraments han d'entrar pel cicle Redsys corresponent.

### 11.6. Factura històrica no present al SIF

No s'autoemet cap factura substitutiva. El preparador existent de factura-abans-de-pagar construeix una nova factura fiscal des d'inscripcions i **no és un importador d'una factura històrica ja numerada**.

Per tant:

- factura present al SIF → UC-022 pot registrar el cobrament;
- factura només al llegat → bloqueig i enviament a migració/reconciliació prèvia;
- queda prohibit crear silenciosament una nova factura amb numeració diferent per poder cobrar.

### 11.7. Estat després d'aquesta continuació

| Bloc | Estat |
|---|---|
| Ledger SIF + idempotència | IMPLEMENTAT |
| HMAC + anti-replay intranet→SIF | IMPLEMENTAT EN BRANCA |
| Rol vigent + CSRF navegador→intranet | IMPLEMENTAT EN BRANCA |
| external_bank_event_id | IMPLEMENTAT EN CONTRACTE I UI |
| Exclusió TPV/Redsys | IMPLEMENTAT |
| Auditoria REQUESTED/terminal del cobrament | IMPLEMENTAT EN BRANCA |
| Projecció acumulada SIF→llegat | IMPLEMENTAT EN BRANCA |
| PENDING_RETRY / reintent idempotent | IMPLEMENTAT EN BRANCA |
| Correu/notificació equivalent al llegat | PENDENT DE DESACOBLAR / OUTBOX |
| Migració de factures històriques absents al SIF | PENDENT FORA DEL FLUX UC-022 |
| Execució MySQL / preproducció | PENDENT D'EVIDÈNCIA |
| Desplegament intranet nova | NO VERIFICAT |


### 11.8. Auditoria de la projecció llegada

La projecció post-commit ja no queda sense traça. `ManualTransferLegacyProjectionService` registra a `payment_action_event`:

- `SYNC_LEGACY / REQUESTED` abans d'intentar la projecció;
- `SYNC_LEGACY / SUCCEEDED` amb imports confirmat/projectat quan acaba;
- `SYNC_LEGACY / FAILED` quan falla la projecció o la seva traça terminal.

El resultat continua sent `PENDING_RETRY` si la projecció o la seva evidència obligatòria no queda completada. Això separa clarament el commit econòmic SIF de la projecció operativa llegada sense perdre correlació.


**Nota d'esquema UC-022:** `external_bank_event_id` es limita a **80 caràcters**, coherent amb `payment_transaction.PROVIDER_REF VARCHAR(80)`. `PROVIDER_REF` conserva l'identificador original; la clau idempotent utilitza el SHA-256 del banc normalitzat + salt de línia + identificador per evitar col·lisions per normalització i mantenir una longitud estable dins `IDEMPOTENCY_KEY VARCHAR(120)`.


**Namespace de la identitat bancària:** el banc és obligatori com a namespace del moviment. La unicitat efectiva del cobrament manual és `SHA-256(UPPER(TRIM(banc)) + "\n" + external_bank_event_id)`; així un identificador localment únic de BBVA no col·lideix amb el mateix text emès per un altre banc.


### 11.9. Triple nivell d'auditoria

La matriu funcional exigeix tres responsabilitats diferents i el canal UC-022 ja les materialitza en el camí d'èxit/reutilització:

- `payment_action_event`: intent `REQUESTED` + resultat terminal `SUCCEEDED|REUSED`;
- `operational_event`: `REGISTER_MANUAL_TRANSFER`, impacte fiscal `NONE`, impacte econòmic `PAYMENT`;
- `sif_audit_event`: auditoria comuna de l'acció sensible sobre el recurs `PAYMENT`.

`operational_event` i `sif_audit_event` s'escriuen dins la mateixa transacció que `payment_transaction/payment_allocation`; el terminal de `payment_action_event` també forma part d'aquell commit. La projecció posterior al legacy té els seus propis events `SYNC_LEGACY`.

Les denegacions i validacions prèvies generen `payment_action_event` amb `ACCESS_DENIED|VALIDATION_REJECTED` i resultat `REJECTED`, sense cap mutació econòmica.

### 11.10. Notificació

S'ha implementat `ManualTransferNotificationService` sobre `notification_outbox`, separat de Redsys. El bundle és idempotent per `uuid_payment` i missatge, encola una notificació interna i, quan es pot resoldre el responsable de l'entitat a la BD d'intranet, una notificació al responsable. Si la BD d'intranet no està configurada o falla la preparació obligatòria del bundle, el canal queda en `PENDING_RETRY` sense duplicar el cobrament.

**Encara pendent de verificació real:** execució del worker/delivery de correu a test/preproducció i conservació de l'evidència.


## Via alternativa d'evidència UC-022

Davant la cua persistent de GitHub Actions, s'ha afegit `sif/scripts/test-uc022-local.sh`.

Garanties del runner:

- només admet una BD amb nom `sif_test*`;
- només admet MySQL a `127.0.0.1` o `localhost`;
- fixa `SIF_ENV=test`;
- executa `php -l` sobre els PHP que formen la superfície UC-022;
- executa `sif/tests/run-uc022-tests.php` per defecte; la suite global només s'executa amb `RUN_FULL_SIF_SUITE=1`;
- registra data, versió PHP, commit i branca quan s'executa des d'un checkout Git;
- conserva l'evidència a `sif/test-results/uc022-<timestamp>.log`.

La mera existència del runner **no compta com a verificació**: cal conservar un log `RESULT=PASS` generat sobre l'entorn de test/preproducció.


## 11. Verificació CI i separació de regressions alienes

Els workflows generals del PR han finalitzat amb resultat global vermell, però les proves específiques creades per UC-022 han passat:

- `ManualPaymentServiceTest::testRejectsSameTransferReferenceForDifferentInvoicePayload` — PASS.
- 4/4 proves de `ManualTransferCommandServiceTest` — PASS.
- `ManualPaymentPayloadBuilderTest::testPrioritizesImmutableBankEventIdOverFreeTextReference` — PASS.

Aquests PASS apareixen tant al job general `SIF checks` com al job `SIF PHP MySQL tests`.

Les 6 fallades observades al conjunt complet no corresponen al UC-022:
- 5 proves de límits/privacitat del flux de packs;
- 1 prova de `RedsysSignatureValidatorTest`.

Per evitar que aquestes regressions alienes ocultin l'evidència del UC-022, s'ha afegit:
- `sif/tests/run-uc022-tests.php`;
- workflow `.github/workflows/uc-022-manual-transfer.yml`.

## 12. Reconciliació final de la sincronització llegada

La inspecció de `Intranet::efectuarPagamentFacturaGenerada()` confirma que **no es pot reutilitzar després del SIF** perquè fa una actualització directa de la factura llegada:

`UPDATE factures SET data_pagament=?, IMPORT=?, FORMA_PAGAMENT=? WHERE NUM=?`

A més, actualitza inscripcions, fraccionament i envia notificacions.

Durant l'auditoria es va detectar temporalment una segona projecció a la intranet. S'ha eliminat per evitar doble aplicació. L'arquitectura definitiva és:

`intranet caller -> endpoint SIF -> payment ledger -> ManualTransferLegacyProjectionService -> GeneratedInvoiceLegacyPaymentSyncService`

Propietats definitives:

- **un únic propietari de la projecció: el SIF**;
- la intranet no escriu el legacy després de la resposta SIF;
- `GeneratedInvoiceLegacyPaymentSyncService` actualitza només `inscripcions`;
- no executa cap `UPDATE factures`;
- deriva la projecció del total confirmat del ledger SIF;
- el reintent torna a calcular valors absoluts, de manera que és idempotent;
- `SYNC_LEGACY REQUESTED/SUCCEEDED/FAILED` deixa traça al SIF;
- `PENDING_RETRY` conserva el cobrament confirmat i permet repetir la mateixa comanda.

No cal cap taula auxiliar de projecció a la BD legacy: l'idempotència autoritativa és la del payment SIF + la projecció derivada i auditada.

### 12.1. Preparació de preproducció

S'han afegit:

- `sif/scripts/preflight-uc022-manual-transfer.php`;
- `sif/scripts/verify-uc022-preproduction.php` — només lectura;
- `documentacio/09-proves-qa/uc-022-runbook-preproduccio.md`;
- `documentacio/09-evidencies/UC-022/plantilla-evidencia-preproduccio.md`.

El verificador no publica l'identificador bancari ni el número visible en clar: en conserva hashes SHA-256.

### 12.2. Estat actual del tancament

**Verificat en CI**
- registre manual SIF;
- idempotència i conflicte;
- autorització;
- identitat bancària amb namespace del banc;
- auditoria del cobrament;
- contracte HTTP i intranet;
- projecció acumulada;
- contracte de notificació/outbox.

**Implementat però encara no acreditat a preproducció real**
- POST navegador → intranet amb CSRF;
- HMAC intranet → SIF;
- projecció SIF → legacy;
- notificació/outbox;
- recuperació `PENDING_RETRY`.

**Encara pendent**
- executar el preflight amb la configuració real;
- executar i conservar T01–T06 del runbook;
- font de l'`external_bank_event_id` decidida: identificador únic del banc a l'extracte/detall/export; resta automatitzar-ne la importació.
- conservar evidència del worker de notificacions.
