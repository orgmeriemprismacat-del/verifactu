# UC-017 · Auditoria detallada i traçabilitat · 2026-10-03

## 1. Conclusió executiva

UC-017 (**Comprar regal**) existeix documentalment i disposa de core SIF específic, però el flux web actiu continua executant la compra i la facturació principalment al llegat.

**Estat global:** PARCIAL · NO TANCAT.

- **Documentat:** SÍ. Fitxa funcional, UML integrat i regles bàsiques existents.
- **Implementat:** PARCIAL. Core SIF de factura/cobrament i emissió del dret de regal implementats; canal web encara no talla cap al SIF.
- **Verificat:** PARCIAL. Existeixen proves automatitzades del servei SIF; no hi ha evidència d'execució end-to-end sobre preproducció del flux web real.
- **Pendent:** integració web -> intenció Redsys SIF -> callback validat -> worker -> factura/pagament/dret; retirada d'escriptures fiscals llegades; seguretat de callbacks, secrets i URLs; evidència de preproducció.

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

## 6. Matriu documentat / implementat / verificat / pendent

| Peça | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Wizard compra regal | Sí | Sí, llegat | Revisió estàtica | Migrar adaptador |
| Intenció Redsys SIF | Sí | Infraestructura general | No E2E UC-017 | Connectar web |
| Callback validat SIF | Sí | Infraestructura general | Parcial | Retirar callback llegat |
| Factura REGAL SIF | Sí | Sí | Test específic existent | Prova MySQL/pre |
| Pagament Redsys | Sí | Sí al servei | Test específic existent | E2E |
| Dret GIFT | Sí | Sí | Cobert al test del servei | Recovery E2E |
| Idempotència | Sí | Sí al core | Test duplicat del servei | Duplicat real callback/worker |
| Numeració fiscal central | Sí | Sí al core SIF | No al canal web | Tallar numeració llegat |
| PDF/QR segur | Sí | Parcial | No | Evitar exposar codi |
| Correus/outbox | Sí | Llegat directe | No | Implementar outbox |
| Sincronització llegat | Sí | Retornada com a proposta | No | Worker/adaptador |
| Preproducció | Sí | Scripts | No acreditat | Evidència executable |

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
