# UC-017 · Revalidació exhaustiva després de reconciliació · 2026-10-04

## 1. Abast i font de veritat

Aquesta revalidació es fa sobre la branca `audit/uc-017-reconciliada-2026-10-04`
(PR #161), ja reconciliada amb `main` en el moment de la revisió.

No duplica la fitxa funcional ni els diagrames existents. Revalida:
- fitxa `documentacio/06-fitxes-funcionals/uc-017.md`;
- inventari PHP/JS;
- classes ACTUAL/FINAL;
- seqüències ACTUAL/FINAL;
- activitats per pàgina;
- matriu global de traçabilitat;
- codi real `web-actual`, overlay candidat `pay-prisma-cat-canvis-verifactu` i core SIF.

## 2. Conclusió executiva

**Documentat:** SÍ, amb els artefactes demanats existents i aquesta revalidació com a addenda.

**Implementat en candidata:** SÍ per al flux SIF, amb correccions addicionals de checkout i
idempotència aplicades en aquesta revalidació.

**Verificat en repositori:** PARCIAL. Hi ha proves de regressió i controls estructurals,
però els workflows associats a la branca encara no constitueixen evidència verda fins que
finalitzin correctament.

**Verificat en test/preproducció:** NO.

**Tancat:** NO. UC-017 queda en **NO-GO de producció** fins a demostrar el mapping
`www -> pay -> SIF`, configurar secrets/AEAT, executar la compra E2E, callback duplicat,
reintent i conservar l'evidència.

## 3. Artefactes existents

| Artefacte | Existeix | Estat després de revalidació |
| --- | --- | --- |
| Fitxa funcional | Sí | actualitzada per addenda |
| Inventari PHP/JS | Sí | actualitzat |
| Classes ACTUAL/FINAL | Sí | actualitzat amb token/fencing |
| Seqüències ACTUAL/FINAL | Sí | actualitzat amb idempotència cross-order |
| Activitats per pàgina | Sí | actualitzat amb superfície de desplegament |
| Auditoria/traçabilitat UC | Sí | mantinguda + aquesta revalidació |
| Plantilla evidència preproducció | Sí | vigent |
| Matriu global | Sí | afegit override UC-017 |
| Codi candidat | Sí | corregit |
| Evidència E2E desplegada | No | pendent |

## 4. Superfície real localitzada

### 4.1 Web ACTUAL (`www.prisma.cat`)

La pàgina `pagina_pagament_regal_automatic.php` carrega
`js1619773569/mostrarPagamentRegal.min.js`, que demana per AJAX
`ajax/mostrar_pagina_pagament_regal_automatic.php`. Aquest endpoint inclou
`web-actual/PagamentRegalAutomatic.php`.

La còpia ACTUAL continua sent llegat i, com a evidència:
- transporta context de regal/import al formulari;
- apunta a la ruta de pagament de `www`;
- el callback ACTUAL factura al llegat.

Aquesta còpia no s'ha de confondre amb la implementació FINAL candidata.

### 4.2 Overlay candidat de `pay.prisma.cat`

El candidat és a `codi-drive/pay-prisma-cat-canvis-verifactu`:
- `PagamentRegalAutomatic.php`;
- `GiftCheckoutToken.php`;
- `pagina_efectuar_pagament_regal_automatic.php`;
- `SifRedsysGiftIntentClient.php`;
- `realitzaPagamentRegalAutomatic.php`;
- pàgines de retorn/status.

### 4.3 Core SIF

El core FINAL usa:
- `RedsysGiftPaymentIntentService`;
- `RedsysPaymentIntentRepository`;
- callback/cua/worker Redsys comuns;
- `RedsysGiftInvoiceService`;
- `LegacyGiftInvoicePayloadBuilder`;
- `GiftAeatInvoicePayloadEnricher`;
- `InvoiceService`;
- `GiftEntitlementIssuerService`;
- outbox de reserva i postpagament;
- projecció llegada posterior.

## 5. Noves troballes de revalidació

### F-017-19 · Handoff web -> pay trencat i context mutable
**Severitat original:** BLOQUEJANT.  
**Estat:** **CORREGIDA EN CANDIDATA**.

La candidata havia introduït `$giftId` però el formulari no l'enviava. Alhora encara
renderitzava hidden fields amb `$codiRegal` i `$correu` ja no inicialitzats dins del
mètode. La pàgina següent exigia `giftId`, de manera que el flux podia fallar abans de
crear la intenció SIF.

La correcció no es limita a afegir un hidden mutable:
- `GiftCheckoutToken::issue()` crea un token HMAC de curta durada;
- el formulari envia només `giftToken` com a identitat del regal;
- `GiftCheckoutToken::verify()` obté l'ID autoritzat abans de contactar el SIF;
- secret dedicat: `UC017_GIFT_CHECKOUT_HMAC_SECRET`, mínim 32 bytes;
- els hidden llegats `giftId/codiRegal/email/import` no són autoritat del checkout.

### F-017-20 · Dos DS_ORDER diferents per al mateix regal
**Severitat original:** BLOQUEJANT.  
**Estat:** **CORREGIDA EN CANDIDATA; PENDENT EXECUCIÓ CI/E2E**.

La idempotència anterior només cobria el mateix `DS_ORDER`. Dos reintents del mateix
regal podien crear dues intencions diferents. A més, `RedsysInvoicePayloadBuilder`
substituïa la clau estable `LEGACY|REGAL|ID:<giftId>` per una clau dependent de
`DS_ORDER`. Això permetia arribar a una segona factura/CHARGE SIF abans que
l'entitlement, que sí és únic per regal, detectés el conflicte.

Correccions:
1. `GET_LOCK('uc017_gift_intent_<id>')` serialitza la creació d'intents per regal.
2. Un intent sense notificació es reutilitza.
3. Si ja existeix notificació `VALIDATED`, no es crea un nou intent.
4. Si existeixen múltiples intents pendents heretats, es falla tancat i es demana
   reconciliació.
5. La factura REGAL conserva la clau estable del regal; un segon `DS_ORDER` no pot
   crear una segona factura ni un segon `payment_transaction`.

### F-017-21 · Doble superfície web/pay sense mapping de desplegament acreditat
**Severitat:** BLOQUEJANT PER CUTOVER.  
**Estat:** **PENDENT D'ENTORN/DESPLEGAMENT**.

El repositori conté el circuit ACTUAL sota `web-actual` i l'overlay FINAL sota
`pay-prisma-cat-canvis-verifactu`. La ruta ACTUAL de `www` continua demostrant
l'execució del llegat. Per tant, no es pot afirmar que el hardening candidat sigui el
circuit efectiu fins que el desplegament acrediti:
- quin DocumentRoot serveix `pay[-dev|-test|-pre].prisma.cat`;
- que el formulari de `www` deriva al checkout candidat de `pay`;
- que `DS_MERCHANT_MERCHANTURL` apunta al callback SIF en cutover;
- que el callback llegat queda drenat i retorna 410 quan correspongui;
- que cap escriptura a `factures` llegada és executable en el camí final.

### F-017-22 · Enllaç llegat AES-CBC autentica ciphertext però no IV
**Severitat:** MITJANA/ALTA.  
**Estat:** **PENDENT DE HARDENING/RETIRADA DEL MECANISME LLEGAT**.

`ajax/mostrar_pagina_pagament_regal_automatic.php` i els generadors llegats usen
AES-128-CBC i HMAC del ciphertext, però l'IV queda fora del MAC. No és el mecanisme
recomanat per al FINAL. El nou `giftToken` protegeix el pas checkout -> pay, però no
converteix aquest enllaç llegat en un token autenticat modern.

Criteri: no bloqueja les proves unitàries del SIF, però sí s'ha de resoldre o retirar
aquesta entrada abans de considerar la superfície web definitivament sanejada.

### F-017-23 · Proves desalineades amb el codi
**Severitat:** ALTA PER QUALITAT D'EVIDÈNCIA.  
**Estat:** **CORREGIDA EN CANDIDATA**.

S'han corregit dues desalineacions:
- el test de cutover buscava `$url` quan el codi real usa `$urlPag`;
- el test d'intenció enviava `gift_code` quan el servei real exigeix `gift_id`.

Per tant, la simple existència d'aquells tests no es considera verificació històrica.

## 6. Proves afegides/corregides

- `GiftCheckoutTokenTest`: round-trip, manipulació i fail-closed sense secret.
- `RedsysGiftCutoverBoundaryTest`: token de checkout i asserts de `$urlPag`.
- `RedsysGiftPaymentIntentServiceTest`:
  - reutilitza un únic intent pendent;
  - bloqueja un nou intent després d'una notificació validada.
- `LegacyGiftInvoicePayloadBuilderTest`:
  - una segona ordre Redsys diferent del mateix regal no pot crear una segona
    factura/CHARGE SIF.

Aquestes proves són **implementades**, no **verificades**, fins que el runner les executi.

## 7. Matriu actual documentat / implementat / verificat / pendent

| Bloc | Documentat | Implementat candidata | Verificat repositori | Verificat entorn | Pendent |
| --- | --- | --- | --- | --- | --- |
| Wizard + reserva | Sí | Sí | Parcial | No | navegador/pre |
| Preview POST+CSRF | Sí | Sí | Parcial | No | navegador/pre |
| Checkout identity | Sí | Sí, HMAC | Prova creada | No | secret + E2E |
| Intenció REGAL | Sí | Sí | Proves ampliades | No | CI + MySQL/pre |
| Idempotència mateix DS_ORDER | Sí | Sí | Proves existents | No | pre |
| Idempotència entre DS_ORDER | Sí | Sí, nova | Prova creada | No | CI + E2E |
| Callback SIF | Sí | Sí | Parcial | No | sandbox/pre |
| Factura + CHARGE | Sí | Sí | Proves | No | E2E |
| Entitlement GIFT | Sí | Sí | Proves | No | E2E |
| AEAT snapshot | Sí | Sí fail-closed | Parcial | No | valors fiscals reals |
| Outbox | Sí | Sí | Proves | No | transport real |
| Retorn navegador | Sí | Sí via status SIF | Parcial | No | navegador/pre |
| Mapping www/pay | Sí en aquesta addenda | Codi separat | No acreditat | No | **bloquejant** |
| Enllaç CBC llegat | Sí en aquesta addenda | Llegat | No sanejat | No | retirar/harden |
| Evidència preprod | Sí, plantilla/verificador | Sí eines | No executat | No | **bloquejant** |

## 8. Criteri de tancament revisat

UC-017 només pot passar a **VERIFICAT/TANCAT** quan tots aquests punts siguin certs:
1. CI del head reconciliat finalitza verd.
2. `UC017_GIFT_CHECKOUT_HMAC_SECRET` està configurat fora del repositori.
3. mapping `www -> pay -> SIF` comprovat sobre els dominis de test/preproducció.
4. una compra controlada crea una sola intenció activa, una factura, un CHARGE i un GIFT.
5. doble clic/reload reutilitza l'intent pendent.
6. callback duplicat del mateix ordre és idempotent.
7. una segona ordre diferent del mateix regal no crea segon registre econòmic SIF.
8. retry entre factura i entitlement no duplica cobrament ni dret.
9. callback llegat drenat/desactivat en cutover.
10. valors AEAT reals configurats i verificats.
11. PDF/QR final verificat sense codi bescanviable.
12. outbox/SMTP verificat amb evidència.
13. mecanisme d'enllaç llegat CBC retirat o hardenitzat.

Fins llavors: **CANDIDATE_IMPLEMENTED / VERIFICATION_PENDING / PRODUCTION_NO_GO**.
