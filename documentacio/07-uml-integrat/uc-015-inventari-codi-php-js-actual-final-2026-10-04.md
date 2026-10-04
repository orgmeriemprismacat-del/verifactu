# UC-015 · Inventari de codi PHP/JS ACTUAL / FINAL — 2026-10-04

**Cas d'ús:** Comprar pack  
**Base auditada:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`  
**Objectiu:** deixar inventariat el codi executable real que implementa PK-A01..PK-A10 i separar-lo del model FINAL, sense inferir classes o callbacks inexistents.

## 1. Resum

La família documental d'UC-015 ja existeix: fitxa funcional, UML integrat, classes ACTUAL/FINAL, seqüències ACTUAL/FINAL, activitats per pàgina/apartat i plantilla d'evidència de preproducció.

Aquesta passada afegeix l'inventari que faltava per poder respondre de manera directa a «quin PHP/JS real implementa cada bloc?».

**Conclusió de codi actualitzada:** la primera passada no va trobar un gap P0/P1 al nucli fiscal/econòmic, però la continuació pàgina per pàgina sí ha detectat **SEC-015-01** a la frontera de confirmació legacy: IV AES-CBC no autenticat, decrypt abans de MAC i token al path. El gap queda corregit al PR #171 amb `PackConfirmationToken` v2; la verificació CI d'aquest nou codi continua pendent.

## 2. PK-A01 · Llistat de packs

### ACTUAL
- `codi-drive/web-actual/inc/buscantPacksDisponibles.php`
- `codi-drive/web-actual/Pack.php`
- consulta de components ordenada i validació de disponibilitat.

### FINAL
El catàleg no produeix efectes fiscals. La disponibilitat es torna a validar abans de l'alta; el llistat no és font d'autoritat per al checkout.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT PER INSPECCIÓ.

## 3. PK-A02 · Fitxa del pack

### ACTUAL
- `codi-drive/web-actual/Pack.php`
- `codi-drive/web-actual/EdicioPack.php`
- `codi-drive/web-actual/pagina_inscripcio_pack.php`

L'ordre operatiu dels components queda estabilitzat per `ORDER BY c.DATAI, p.ID_CURS`.

### FINAL
El mateix ordre es congela a `PACK_ORDINAL`; una futura posició manual/versionada seria una evolució de model, no una mancança actual.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT PER PROVES DE BOUNDARY.

## 4. PK-A03 · Formulari públic

### ACTUAL
- `codi-drive/web-actual/pagina_inscripcio_pack.php`
- `codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js`
- `codi-drive/web-actual/ajax/enviarInscripcioPack.php`
- `codi-drive/web-actual/inc/PublicWebMutationAuthorization.php`

Contracte observat:
- AJAX per POST;
- `REQUEST_ID` UUID v4 persistent al navegador;
- bloqueig de doble enviament al JS;
- PHP POST-only amb 405;
- Origin/Referer segons `WEB_ALLOWED_ORIGINS`;
- `X-Requested-With` exigit pel guard;
- `Sec-Fetch-Site` com a defensa complementària;
- PII fora de query string;
- hash de payload i named lock;
- replay equivalent o 409 si la mateixa clau representa un payload diferent.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT.  
**Nota:** el JS no fixa manualment `X-Requested-With`; en aquest flux jQuery el genera per a XHR same-origin i el backend el valida.

## 5. PK-A04 · Alta N components

### ACTUAL
- `codi-drive/web-actual/ajax/enviarInscripcioPack.php`
- `codi-drive/web-actual/ConnexioBBDD_PreparedStatment.php`

Contracte observat:
- `reserveIdPag()` serialitza l'allocator legacy;
- named lock independent per `REQUEST_ID`;
- N insercions dins una transacció;
- rollback en error i alliberament de locks;
- preu recalculat al servidor;
- no es consumeixen totals del navegador;
- totes les edicions es revaliden;
- `PACK_ORDINAL`, `PACK_BASE`, `PACK_DISCOUNT`, `PACK_DISCOUNT_PCT`, `PACK_TOTAL`, `RID` i `RH1` queden congelats;
- suma exacta de línies = preu PACK abans del commit.

### FINAL
El comportament FINAL ja està implementat. `MAX(IDPAG)+1` sota lock continua sent deute legacy substituïble per una seqüència dedicada, però no és un gap de correcció/concurrència actual.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT PER BOUNDARY TESTS; E2E D'ENTORN PENDENT.

## 5b. PK-A04b · Confirmació d'alta i continuació al pagament

### ACTUAL observat a main abans del fix
- `codi-drive/web-actual/pagina_confirmacio_grup_automatic.php`
- `codi-drive/web-actual/js1619773569/mostrarConfirmacioPagamentGrupAutomatic.min.js`
- `codi-drive/web-actual/ajax/mostrar_pagina_confirmacio_pagament_grup_automatic.php`
- regla `.htaccess` de `/packs/confirmacio/TOKEN`.

Troballa:
- token `IV + HMAC(ciphertext) + ciphertext`;
- HMAC no cobria IV;
- decrypt abans de verificar;
- parsing de `REQUEST_URI` amb longitud màgica;
- token al path;
- pageview analytics potencial sobre una URL amb credencial opaca.

### FINAL implementat al PR #171
- `codi-drive/web-actual/inc/PackConfirmationToken.php`;
- token `v2.` Base64URL;
- AES-256-CBC + clau derivada;
- MAC amb clau separada sobre domini + IV + ciphertext;
- verificació MAC abans de decrypt;
- `ID_INSC|issued_at` i TTL de 24 h;
- fragment `#TOKEN` als clients nous;
- no-store/no-referrer/noindex i `send_page_view=false`;
- endpoint amb `$_GET['keyEncr']` i `encodeURIComponent`;
- fallback temporal només per token **v2** al path.

**Estat:** DOCUMENTAT / IMPLEMENTAT AL PR #171 / VERIFICAT PER INSPECCIÓ / CI I E2E PENDENTS.

## 6. PK-A05 · Intenció i TPV Redsys

### ACTUAL
- `codi-drive/web-actual/inc/PackPaymentGate.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/inc/PackPaymentGate.php`
- `codi-drive/web-actual/pagina_efectuar_pagament_grup_automatic.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_grup_automatic.php`
- `codi-drive/web-actual/inc/SifPaymentIntentClient.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/inc/SifPaymentIntentClient.php`
- `sif/public/api/redsys/intents/create.php`
- `sif/src/Service/RedsysPaymentIntentService.php`

Controls:
- snapshot rellegit des de servidor;
- PACK, ordinals, receptor i imports coherents;
- ecommerce només admet pendent complet;
- `SOURCE_TYPE=PACK`;
- DS_ORDER correlacionat;
- callback = `SIF_REDSYS_CALLBACK_URL`;
- `SIF_REDSYS_PAYMENT_URL` limitada als endpoints Redsys admesos;
- descripció PACK sense DNI;
- titular derivat del nom del receptor;
- retorns PACK sense email.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT PER PROVES; REDSYS REAL PENDENT.

## 7. PK-A06 · Callback, cua i worker

### ACTUAL
- `sif/public/api/redsys/callback.php`;
- serveis `sif/src/Service/RedsysCallbackService.php`, `RedsysCallbackDispatcher.php` i `RedsysCallbackWorker.php`;
- handler `sif/src/Service/RedsysPackInvoiceService.php`;
- `codi-drive/web-actual/realitzaPagamentPackAutomaticProva.php` només com a harness test/preproduction fail-closed.

Els callbacks fiscals productius legacy:
- `codi-drive/web-actual/realitzaPagamentPackAutomatic.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentPackAutomatic.php`

estan retirats físicament del repositori.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT PER BOUNDARY + WORKER E2E SIMULAT; CALLBACK REDSYS REAL PENDENT.

## 8. PK-A07 · Emissió fiscal

### ACTUAL
- `sif/src/Service/RedsysPackInvoiceService.php`
- `sif/src/Repository/LegacyPackSnapshotRepository.php`
- `sif/src/Service/LegacyPackInvoicePayloadBuilder.php`
- `sif/src/Service/RedsysInvoicePayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`

`issueFromIntentSnapshot()` construeix el payload, reconcilia import factura/import Redsys i només després emet factura/payment.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT EN CI.

## 9. PK-A08 · Atribució monetària

### ACTUAL
- `sif/src/Service/PackEnrollmentFundAllocationService.php`
- repositori `EnrollmentFundMovementRepository`
- taula `enrollment_fund_movement`

Un únic `UUID_PAYMENT` s'atribueix a 2..N inscripcions; no es creen N CHARGE bancaris. La suma dels moviments ha de quadrar amb el cobrament.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT EN PROVES; EVIDÈNCIA REAL PENDENT.

## 10. PK-A09 · Notificacions

### ACTUAL
- `sif/src/Service/PackPaymentNotificationService.php`
- `sif/src/Repository/NotificationOutboxRepository.php`
- `sif/src/Service/NotificationOutboxDeliveryService.php`

El PACK crea un `PACK_PAYMENT_CONFIRMED` idempotent a l'outbox. A data 2026-10-04 també existeix el gate genèric de claim/complete del delivery, amb estats PENDING/SENDING/SENT/FAILED i tractament fail-closed dels enviaments amb resultat ambigu.

No s'ha localitzat en aquesta auditoria un worker/script SMTP productiu específic que dreni l'outbox de PACK. Per tant, no s'ha de descriure aquest bloc com «infraestructura de delivery inexistent», sinó com **delivery operatiu no acreditat/activat per al PACK**.

El correu inicial de l'alta web continua sent un flux legacy separat.

**Estat:** ENQUEUE DOCUMENTAT/IMPLEMENTAT/VERIFICAT; GATE DELIVERY IMPLEMENTAT; TRANSPORT OPERATIU PACK PENDENT D'ACREDITACIÓ (UC-58/cutover).

## 11. PK-A10 · Fraccionament

### ACTUAL
- alta ecommerce: `FRACCIONAT=0`;
- `PackPaymentGate` rebutja cobrament parcial o previ;
- el checkout automàtic exigeix el pendent complet.

### FINAL
Qualsevol excepció de fraccionament ha d'entrar per un circuit intern explícit, no manipulant el checkout públic.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT.

## 12. Sincronització legacy post-SIF

### ACTUAL
- `sif/src/Service/RedsysLegacySyncingProcessor.php`
- `sif/src/Service/LegacySyncService.php`

Després de l'èxit SIF:
1. `syncAfterSifSuccess()`;
2. si `mode=PACK_FULL_PAYMENT`, `syncPackFullPayment()`.

`AcademicEnrollmentSyncService` no és part d'aquest UC i no s'ha d'usar als UML.

**Estat:** DOCUMENTAT / IMPLEMENTAT / VERIFICAT PER INSPECCIÓ I PROVES.

## 13. Proves canòniques UC-015

La suite selectiva creada el 2026-10-04 és `sif/tests/run-uc015-tests.php` i queda protegida per `.github/workflows/uc015-sif-checks.yml`.

Inclou boundaries d'alta pública, transport, idempotència, atomicitat, disponibilitat, ordre comercial, privacitat Redsys, checkout, callback legacy retirat, snapshot/builder, factura PACK, worker E2E, evidència/preproducció, intenció i guards compartits rellevants.

El PR #149 va alinear cinc proves desfasades amb el codi actual. El seu HEAD `8871e15...` va executar la suite global amb **971 passed / 0 failed**.

## 14. Pendents reals

1. Executar un PACK real en preproducció i conservar evidència de DS_ORDER, callback, cua, factura, payment, ledger, outbox i sync legacy.
2. Executar PK-01..PK-11 de navegador amb configuració real d'entorn.
3. Acreditar el drenatge/transport real de l'outbox PACK i el seu cutover operatiu.
4. Validar en navegador/preproducció la confirmació v2: token correcte, IV/ciphertext manipulat, caducat, fragment URL i fallback v2 de desplegament.\n5. Mantenir el gate selectiu UC-015 verd en qualsevol canvi que afecti el circuit.


## 15. Paritat de desplegament web / pay

La passada del 04/10 compara explícitament les còpies desplegables:

### Idèntics byte-a-byte
- `codi-drive/web-actual/inc/PackPaymentGate.php` = `codi-drive/pay-prisma-cat-canvis-verifactu/inc/PackPaymentGate.php`;
- `codi-drive/web-actual/inc/SifPaymentIntentClient.php` = `codi-drive/pay-prisma-cat-canvis-verifactu/inc/SifPaymentIntentClient.php`;
- `codi-drive/web-actual/inc/apiRedsys.php` = `codi-drive/pay-prisma-cat-canvis-verifactu/inc/apiRedsys.php`.

S'ha afegit `PackDeploymentParityBoundaryTest` perquè els dos primers adaptadors crítics no puguin divergir silenciosament.

### Checkout
Les dues còpies de `pagina_efectuar_pagament_grup_automatic.php` **no són byte-a-byte idèntiques** perquè carreguen assets/estils diferents de `www.prisma.cat` i `pay.prisma.cat`. La lògica PACK crítica sí és equivalent i queda blindada per:
- `PackCheckoutBoundaryTest`;
- `PackPaymentPrivacyBoundaryTest`;
- `PackDeploymentParityBoundaryTest`.

Totes dues mantenen `PackPaymentGate`, `SifPaymentIntentClient`, callback SIF, allowlist de `SIF_REDSYS_PAYMENT_URL`, producte PACK sense DNI i retorns OK/KO sobre el domini públic.

### Connexió BD
`ConnexioBBDD_PreparedStatment.php` és intencionadament asimètrica:
- la còpia web incorpora transaccions, named locks i reserva/alliberament d'`IDPAG` perquè executa PK-A04;
- la còpia pay no crea altes PACK i no necessita aquests mètodes.

Aquesta diferència **no és drift de negoci** mentre el checkout pay continuï limitat a lectura/validació.

### Respostes OK/KO
Les còpies `pay-prisma-cat` utilitzen `CoursePaymentReturnStatus.php` per al retorn autoritatiu d'UC-014. El checkout PACK, tant des de web com des de pay, fixa:
- `https://www.prisma.cat/respostaOkPagamentAutomatic.php`;
- `https://www.prisma.cat/respostaKoPagamentAutomatic.php`.

Per tant, la diferència de les pàgines OK/KO de pay no constitueix una segona ruta activa del PACK. El boundary nou ho blinda.
