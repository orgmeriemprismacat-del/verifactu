# UC-017 · Auditoria detallada i traçabilitat · 2026-10-03

## 1. Conclusió executiva

UC-017 (**Comprar regal**) té dos estats que cal distingir. La **còpia llegada ACTUAL** continua descrivint el circuit històric de pagament/facturació, mentre que la **branca candidata FINAL** ja implementa el tall web/pay-prisma -> intenció SIF -> callback validat -> worker -> factura/pagament -> dret GIFT -> outbox -> estat autoritatiu.

**Estat global:** CANDIDAT IMPLEMENTAT · NO TANCAT FINS A PREPRODUCCIÓ.

- **Documentat:** SÍ. Fitxa funcional, inventari PHP/JS i UML ACTUAL/FINAL complets per l'abast auditat.
- **Implementat:** SÍ EN BRANCA CANDIDATA. El tall Redsys/SIF, idempotència, recovery, outbox, projecció llegada i snapshot AEAT fail-closed estan implementats.
- **Verificat en repositori:** PARCIAL. Hi ha proves específiques, E2E de worker/replay i controls estàtics; els runners del PR encara han de finalitzar.
- **Verificat en entorn:** PENDENT. Falta desplegament controlat, configuració real, compra E2E i evidència conservada.
- **Pendent real:** activar/configurar el candidat en test/preproducció, validar classificació AEAT real, executar preflight/verificador, callback duplicat/retry i confirmar el drenatge del callback llegat.

## 2. Fonts revisades

### Documentació
- `documentacio/06-fitxes-funcionals/uc-017.md`
- `documentacio/07-uml-integrat/uc-017-comprar-regal.md`
- `documentacio/04-estat-final/35-matriu-tracabilitat-diagrames.md`
- UC-018 i UC-119 com a límits funcionals del bescanvi/cicle de vida.

### Codi web actual
- `codi-drive/web-actual/pagina_regal.php`
- `codi-drive/web-actual/js1619773569/mostrarRegal.min.js`
- `codi-drive/web-actual/ajax/efectuarPagamentRegalAutomatic.php`
- `codi-drive/web-actual/pagina_efectuar_pagament_regal_automatic.php`
- `codi-drive/web-actual/realitzaPagamentRegalAutomatic.php`
- `codi-drive/web-actual/respostaPagamentRegal.php`
- `codi-drive/web-actual/respostaOkPagamentRegal.php`

### Core SIF
- `sif/src/Repository/LegacyGiftSnapshotRepository.php`
- `sif/src/Service/LegacyGiftInvoicePayloadBuilder.php`
- `sif/src/Service/RedsysGiftInvoiceService.php`
- `sif/src/Service/GiftEntitlementIssuerService.php`
- `sif/scripts/process-redsys-gift.php`
- `sif/tests/Integration/RedsysGiftInvoiceServiceTest.php`

## 3. Flux ACTUAL comprovat

1. La pàgina `RegalCurs.php/pagina_regal.php` carrega `mostrarRegal.min.js`.
2. El JS construeix un wizard: selecció de curs, destinatari/dedicatòria, previsualització i dades del comprador.
3. El web envia dades cap al flux de pagament de regal.
4. `pagina_efectuar_pagament_regal_automatic.php` construeix directament els paràmetres Redsys.
5. `DS_ORDER` es deriva de `time()`.
6. El callback HTTP apunta a `realitzaPagamentRegalAutomatic.php` amb dades també a query string.
7. El callback decodifica la notificació, però el codi revisat calcula la signatura rebuda/esperada i **no mostra una comparació efectiva abans d'acceptar l'operació**.
8. En resposta autoritzada, el llegat:
   - llegeix `regal`;
   - calcula `factura_relacionada`, any, ordre i número;
   - insereix directament a `factures`;
   - actualitza `regal.FACT_REL`;
   - envia correus directes.
9. La pàgina d'èxit només informa l'usuari.

## 4. Flux FINAL objectiu

1. Web crea intenció Redsys amb `SOURCE_TYPE=REGAL`, identificador del regal, import server-side i snapshot immutable.
2. Callback Redsys entra al SIF, valida signatura, ordre, import, moneda, terminal i idempotència.
3. La notificació queda registrada i el processament fiscal passa a worker.
4. `RedsysGiftInvoiceService`:
   - exigeix notificació `VALIDATED`;
   - carrega/rep snapshot de regal;
   - compara l'import real Redsys amb el snapshot;
   - construeix payload fiscal;
   - invoca `InvoiceService::issueInvoice()`.
5. `GiftEntitlementIssuerService` crea/reutilitza:
   - `commercial_operation` amb `GIFT_PURCHASE`;
   - `commercial_entitlement` de tipus `GIFT`;
   - event `ISSUE`;
   - estat inicial `UNCLAIMED/ACTIVE`.
6. El llegat només rep sincronització posterior al commit SIF.
7. UC-018 consumeix el dret; no crea un segon cobrament extern.

## 5. Troballes

### F-017-01 · Numeració fiscal al llegat
**Severitat:** BLOQUEJANT.

`realitzaPagamentRegalAutomatic.php` calcula el següent número/ordre mitjançant consulta a `factures`. Això és incompatible amb la numeració fiscal central i transaccional del SIF.

### F-017-02 · Escriptura directa de factura
**Severitat:** BLOQUEJANT.

El callback insereix a `factures` i actualitza `regal.FACT_REL`. El flux final ha de crear factura/pagament/registre fiscal només via SIF.

### F-017-03 · Validació criptogràfica no demostrada
**Severitat:** CRÍTICA.

El callback calcula `$firma` i llegeix `$signatureRecibida`, però en el codi auditat no queda demostrada la comparació abans de considerar `Ds_Response` autoritzada. Cal substituir el callback llegat pel validador SIF o corregir-lo abans de qualsevol ús.

### F-017-04 · Secret Redsys dins del codi
**Severitat:** CRÍTICA.

La configuració Redsys apareix hardcoded al PHP del flux de pagament/callback. Cal externalitzar i rotar el secret. No s'ha de conservar cap secret en repositori.

### F-017-05 · Dades per GET
**Severitat:** ALTA.

El callback es construeix amb `codiCurs`, `order`, `codiRegal`, `dni` i `import` a query string. Són dades manipulables i/o sensibles. El FINAL ha de resoldre context des de la intenció persistent, no confiar en query params.

### F-017-06 · `DS_ORDER=time()`
**Severitat:** ALTA.

La generació basada en segons no és una clau robusta d'idempotència ni de concurrència. El SIF ja disposa de model d'intenció/idempotència.

### F-017-07 · Import client/URL vs import validat
**Severitat:** ALTA.

El llegat propaga `import` entre formularis/URL. El FINAL ha de recalcular/recuperar el preu server-side i contrastar-lo amb Redsys. `RedsysGiftInvoiceService::assertMatchingAmount()` ja cobreix la segona meitat.

### F-017-08 · Core SIF específic ja existeix
**Severitat:** POSITIVA.

`RedsysGiftInvoiceService` i `LegacyGiftInvoicePayloadBuilder` ja creen una factura `REGAL`, relació no visible per alumne i cobrament Redsys idempotent.

### F-017-09 · Dret de regal materialitzat
**Severitat:** POSITIVA.

`GiftEntitlementIssuerService` materialitza `GIFT_PURCHASE` i `GIFT` només després de factura + pagament confirmats, amb codi hash, titular inicial no reclamat i event append-only.

### F-017-10 · Dues transaccions consecutives
**Severitat:** ALTA.

`InvoiceService::issueInvoice()` i `GiftEntitlementIssuerService::issue()` no comparteixen una única transacció externa: la factura/pagament pot quedar confirmada i fallar posteriorment l'emissió del dret. Això és recuperable, però requereix estat/retry/incidència explícits. No s'ha de tornar a cobrar.

### F-017-11 · Codi regal al detall de factura
**Severitat:** MITJANA/ALTA.

`LegacyGiftInvoicePayloadBuilder` posa `Codi regal <code>` al detall de línia. Si el PDF hereta aquest detall, pot exposar una credencial bescanviable. Cal decidir si el document fiscal ha de contenir el codi complet, parcial o cap codi.

### F-017-12 · Correus directes fora d'outbox
**Severitat:** ALTA.

El llegat envia confirmacions directament des del callback. El FINAL necessita outbox/retry i separació entre factura del comprador i lliurament del codi.

### F-017-13 · Snapshot fiscal AEAT específic del regal
**Severitat original:** BLOQUEJANT PER PREPRODUCCIÓ.

El builder de regal no podia inferir jurídicament impost, règim o causa d'exempció a partir d'un simple estat intern. La candidata incorpora `GiftAeatInvoicePayloadEnricher`, exigeix configuració explícita en PREPROD/PRODUCTION i falla tancada si falta. **Queda pendent validar i configurar els valors fiscals reals; no queda pendent programació coneguda.**

## 6. Matriu documentat / implementat / verificat / pendent

| Peça | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Wizard compra regal | Sí | Sí; reserva/preview server-bound en candidata | Proves de frontera | Evidència navegador/pre |
| Intenció Redsys SIF | Sí | Sí: servei + endpoint + client signat | Tests servei/boundary | E2E desplegat |
| Callback validat SIF | Sí | Sí: callback/queue/worker comú | Tests async/replay | Callback real/sandbox |
| Factura REGAL SIF | Sí | Sí | Tests MySQL de servei/E2E | Evidència preproducció |
| Pagament Redsys | Sí | Sí | Tests callback/worker | E2E entorn |
| Dret GIFT | Sí | Sí | Test entitlement + replay | Evidència MySQL/pre |
| Idempotència | Sí | Sí | Callback duplicat + worker replay | Repetició controlada en pre |
| Numeració fiscal central | Sí | Sí via InvoiceService | Verificació repositori | Confirmar cutover/drain |
| PDF/QR segur | Sí | Codi retirat del detall fiscal; AEAT snapshot fail-closed | Tests payload | Verificar PDF/QR generat |
| Correus/outbox | Sí | Sí al camí SIF | Test idempotència/sense codi | Provar consumidor/plantilla |
| Sincronització llegat | Sí | Sí, posterior a SIF | Tests projecció | Evidència preproducció |
| Preproducció | Sí | Preflight + go/no-go + verificador + plantilla evidència | Estructura testada | Executar i conservar evidència |

## 7. Proves mínimes per tancament

- RG17-01 compra nominal amb comprador != destinatari.
- RG17-02 callback duplicat.
- RG17-03 worker repetit.
- RG17-04 import diferent del snapshot.
- RG17-05 `DS_ORDER` desconegut.
- RG17-06 notificació no validada.
- RG17-07 fallada després de factura però abans de crear entitlement.
- RG17-08 reintent de creació del dret sense segon cobrament.
- RG17-09 accés a PDF sense revelar el codi a actor no autoritzat.
- RG17-10 comprovació que el callback web ja no escriu `factures`.
- RG17-11 comprovació que cap secret Redsys existeix al codi desplegable.
- RG17-12 traça completa: intent -> notification -> payment -> invoice -> fiscal record -> entitlement -> mail outbox.

## 8. Criteri de tancament

UC-017 només podrà passar a **VERIFICAT/TANCAT** quan:
1. el web no generi ni facturi directament;
2. el callback productiu validi signatura i resolgui el context des del SIF;
3. factura, cobrament i dret siguin idempotents;
4. no hi hagi secret Redsys en fitxers;
5. el codi de regal no quedi exposat inadequadament;
6. hi hagi prova E2E en preproducció amb evidència conservada;
7. el callback duplicat i el retry entre factura i entitlement no dupliquin diners ni drets.


## 9. Reconciliació d'implementació posterior a l'auditoria

Després de la primera passada d'auditoria s'han implementat a la mateixa branca
candidata els canvis descrits a
`uc-017-implementacio-hardening-2026-10-03.md`.

### 9.1. Estat de les troballes

| Troballa | Estat després del hardening |
| --- | --- |
| F-017-01 numeració fiscal al llegat | **RESOLTA EN CAMÍ FINAL**: el callback SIF/worker emet via `InvoiceService`; el fallback llegat només existeix mentre `CUTOVER=0` |
| F-017-02 escriptura directa de factura | **RESOLTA EN CAMÍ FINAL**; fallback transitori bloquejat amb `410` quan cutover+drain |
| F-017-03 validació criptogràfica no demostrada | **CORREGIDA EN CANDIDAT**: `hash_equals` + validació context/import/terminal/merchant |
| F-017-04 secret Redsys al codi | **CORREGIDA EN CANDIDAT**: variables d'entorn |
| F-017-05 dades per GET | **CORREGIDA EN CANDIDAT**: context signat `MerchantData` i intenció SIF |
| F-017-06 `DS_ORDER=time()` | **CORREGIDA EN CANDIDAT**: `RedsysDsOrderGenerator` |
| F-017-07 import client/URL | **CORREGIDA EN CANDIDAT**: import autoritatiu rellegit pel SIF |
| F-017-08 core SIF específic | IMPLEMENTAT |
| F-017-09 dret de regal | IMPLEMENTAT |
| F-017-10 dues transaccions consecutives | **MITIGADA**: retry del worker + idempotència de factura/entitlement/outbox; prova de frontera creada |
| F-017-11 codi al detall de factura | **CORREGIDA EN CANDIDAT**: la línia fiscal usa `Val regal`; el codi queda fora del detall de factura |
| F-017-12 correus directes | **RESOLT EN CAMÍ FINAL** amb `GiftPaymentNotificationService`; transport final pendent de prova |

### 9.2. Nous components

- `RedsysGiftPaymentIntentService`
- `/api/redsys/gift-intent.php`
- `SifRedsysGiftIntentClient`
- `RedsysGiftPaymentStatusService`
- `/api/redsys/gift-status.php`
- `SifRedsysGiftStatusClient`
- `GiftPaymentReturnStatus`
- `GiftPaymentNotificationService`

### 9.3. Nou estat global

**Documentat:** SÍ, paquet específic complet.

**Implementat:** SÍ per al camí candidat SIF de compra/cobrament/factura/dret/outbox/estat;
el fallback llegat es conserva només per rollback abans del tall.

**Verificat:** PARCIAL. Hi ha proves automatitzades i workflows CI en execució/cua,
però encara no hi ha evidència de preproducció amb Redsys real/sandbox i MySQL de
l'entorn desplegat.

**Pendent:** desplegar candidat, configurar secrets, executar preflight,
prova E2E, callback duplicat, retry entre factura i entitlement/outbox, verificar
transport d'outbox i aprovar retirada definitiva del fallback.

Per tant, UC-017 **encara no és CLOSED**, però ja no és correcte descriure el seu
camí FINAL com a “pendent de programar”.


## 10. Estat de resolució de troballes — branca candidata 2026-10-04

Aquesta secció és l'estat vigent de les troballes F-017-01..12 després del hardening. La severitat original es conserva com a risc del sistema ACTUAL/llegat; la columna **candidata** indica si el risc queda resolt en el codi proposat.

| Troballa | Candidata | Evidència principal | Pendent real |
| --- | --- | --- | --- |
| F-017-01 Numeració fiscal al llegat | **RESOLTA EN CANDIDATA** | checkout crea intenció SIF; callback final és `/api/redsys/callback.php`; factura via `InvoiceService` | activar cutover i demostrar que el callback llegat queda drenat |
| F-017-02 Escriptura directa de factura | **RESOLTA EN CANDIDATA** | guard `SIF_REDSYS_GIFT_CUTOVER_ENABLED + SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED` retorna 410 abans de carregar el callback llegat | desplegament i retirada física posterior del fallback |
| F-017-03 Validació criptogràfica | **RESOLTA EN CANDIDATA** | callback SIF amb `RedsysSignatureValidator`; fallback també usa `hash_equals` i context signat | prova preproducció amb notificació Redsys real/simulada controlada |
| F-017-04 Secret Redsys hardcoded | **RESOLTA EN CANDIDATA** | bridge usa `REDSYS_MERCHANT_KEY`, `REDSYS_MERCHANT_CODE`, `REDSYS_TERMINAL`, `REDSYS_GATEWAY_URL` | configurar secret store i rotar qualsevol clau històricament exposada |
| F-017-05 Dades per GET | **RESOLTA EN CANDIDATA** | MerchantURL no porta DNI, import, codi o curs; context mínim via `DS_MERCHANT_MERCHANTDATA` signat | validar URL desplegada |
| F-017-06 `DS_ORDER=time()` | **RESOLTA EN CANDIDATA** | `RedsysGiftPaymentIntentService + RedsysDsOrderGenerator` | prova de concurrència/col·lisions a entorn |
| F-017-07 Import de client | **RESOLTA EN CANDIDATA** | intent rellegeix `regal.IMPORT`; checkout substitueix import POST pel retorn SIF; callback compara import | prova E2E |
| F-017-08 Core SIF específic | **IMPLEMENTAT** | invoice service + intent/status + worker REGAL | desplegament |
| F-017-09 Dret de regal | **IMPLEMENTAT** | `GiftEntitlementIssuerService`, `GIFT_PURCHASE`, `GIFT`, event `ISSUE` | evidència MySQL/preproducció |
| F-017-10 Dues transaccions consecutives | **MITIGADA/RECUPERABLE** | worker no marca PROCESSING com PROCESSED fins acabar; error tècnic entra a RETRY; factura/payment/entitlement són idempotents; test de replay | executar recovery E2E real i conservar evidència |
| F-017-11 Codi al detall fiscal | **RESOLTA EN CANDIDATA** | línia factura usa `Val regal`; test rebutja codi bescanviable al detall | verificar PDF/QR generat |
| F-017-12 Correus directes | **RESOLTA EN CAMÍ SIF** | `GiftPaymentNotificationService` + `notification_outbox` idempotent; payload sense codi cru | activar consumidor/outbox en preproducció i comprovar destinatari/plantilla |
| F-017-13 Snapshot oficial AEAT absent al builder de regal | **RESOLTA EN CANDIDATA** | `GiftAeatInvoicePayloadEnricher`; builder fail-closed en PREPROD/PRODUCTION; configuració fiscal explícita; prova XSD | validar valors fiscals reals, configurar-los i conservar evidència d'alta oficial en preproducció |

### 10.1 Estat de tancament resultant

- **Documentat:** COMPLET per a l'abast auditat d'UC-017.
- **Implementat:** CANDIDAT COMPLET per al tall Redsys/SIF, reserva web, factura/cobrament, entitlement, estat, outbox, projecció llegada, retry i evidència.
- **Verificat en repositori:** PARCIAL; hi ha proves automàtiques i controls estàtics, però els runners del PR encara no constitueixen evidència fins finalitzar.
- **Verificat en entorn:** PENDENT.
- **Producció:** NO TANCADA.

L'únic bloqueig de tancament funcional que queda és d'**entorn i desplegament controlat**, no una absència coneguda de la implementació candidata: executar CI, desplegar a test/preproducció, executar `preflight-redsys-gift.php`, fer una compra controlada, executar `verify-redsys-gift-preproduction.php DS_ORDER`, provar callback duplicat/retry i conservar l'evidència.
