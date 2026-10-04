# UC-002 · Auditoria detallada i matriu de traçabilitat · 2026-10-04

## 0. Resum executiu

UC-002 **no estava complet** a `main`. El repositori sí contenia el nucli SIF de pagaments, idempotència v2, persistència i càlcul d'estat, però la documentació no reflectia l'estat real del codi llegat i la frontera de pagaments continuava incompleta.

L'auditoria ha recuperat i inspeccionat el `Intranet.php` real com a blob gran, ha separat el cobrament d'una factura existent (`efact=1`) de la facturació llegada durant el cobrament (`efact=0`) i ha corregit defectes de seguretat i consistència que podien afectar el cobrament.

**Conclusió actualitzada:** el nucli SIF és sòlid i el pont Intranet → SIF autoritatiu ja està implementat darrere de `SIF_UC002_AUTHORITATIVE`, amb CSRF, HMAC, request UUID estable i sync llegat post-commit basat en projecció absoluta. UC-002 continua **PARCIAL / NO TANCAT** perquè falta validar-lo E2E/preproducció, connectar l'auditoria funcional genèrica, acreditar l'evidència externa del cobrament i moure notificacions/correus a post-commit.

## 1. Fonts revisades

- fitxa `documentacio/06-fitxes-funcionals/uc-002.md`;
- UML integrat `uc-002-registrar-cobrament-factura.md`;
- codi SIF de service/validator/repository/status/idempotència;
- API `sif/public/api/payments/register.php`;
- config `sif/config/sif.php`;
- `alumnes-pagaments.php` i `alumnes-pagaments.js`;
- endpoints de cerca, modal i mutació;
- `codi-drive/intranet-actual/Intranet.php` recuperat per blob;
- còpia `intranet-nova-canvis-verifactu/Intranet.php`;
- infraestructura `PaymentActionGateway` i `payment_action_event`;
- infraestructura `EnrollmentFundMovementRepository`;
- suite SIF i workflow GitHub Actions;
- branca històrica `audit/uc-002-2026-10-03`, reconciliada contra el `main` actual i **no fusionada a cegues** perquè estava 61 commits per darrere.

## 2. Troballes

### F-002-01 · CRÍTICA · La mutació llegada es feia per GET

**Abans:** `alumnes-pagaments.js` enviava `efectuarPagament.php` amb `method: "GET"`, i l'endpoint llegia `$_GET`.

**Risc:** mutació cacheable/repetible, pitjor frontera CSRF i semàntica HTTP incorrecta.

**Acció:** canvi a POST; endpoint POST-only; no-store; lectura per `$_POST`.

**Estat:** **CORREGIT A BRANCA**.  
**Verificació:** test `Uc002LegacyPaymentBoundaryTest`.

---

### F-002-02 · ALTA · Frontera llegada sense autorització server-side específica

**Abans:** sessió iniciada, però el script de mutació no acreditava permís de pàgina ni origen de la petició.

**Acció:** validar:
- sessió `usuari + intranet`;
- `Sec-Fetch-Site`;
- `Origin/Referer`;
- `X-Requested-With`;
- rols de `/alumnes/pagaments/`;
- import positiu, data, banc i `efact`.

**Estat:** **CORREGIT A BRANCA**.  
**Pendent:** token CSRF dedicat si es vol una defensa sincronitzadora explícita.

---

### F-002-03 · CRÍTICA · API SIF de pagaments sense autenticació pròpia

**Abans:** `/api/payments/register.php` acceptava el JSON i cridava el servei sense `InternalApiAuthenticator` ni allowlist.

**Risc:** qualsevol caller amb accés HTTP a la superfície podia intentar registrar moviments.

**Acció:** POST-only + HMAC + timestamp + request-id/replay + actor/rol signats + `payments.write_roles` fail-closed.

**Estat:** **CORREGIT A BRANCA**.  
**Verificació:** `Uc002PaymentApiBoundaryTest`.

---

### F-002-04 · ALTA · Validador acceptava zero/negatius i desquadraments

**Abans:** `PaymentPayloadValidator` comprovava `is_numeric()`, però no:
- > 0;
- <= 2 decimals;
- suma assignacions = import.

**Acció:** conversió a cèntims i invariants exactes.

**Estat:** **CORREGIT A BRANCA**.  
**Verificació:** nous casos a `PaymentPayloadValidatorTest`.

---

### F-002-05 · CRÍTICA · El cobrament d'una factura existent sobreescrivia `factures.IMPORT`

El codi real `efectuarPagamentFacturaGenerada()` usava:

`UPDATE factures SET data_pagament=?, IMPORT=?, FORMA_PAGAMENT=? WHERE NUM=?`.

Per una factura emesa abans del cobrament, això podia substituir el total de factura per l'import d'una fracció.

**Acció:** `updFactGenerada` conserva `IMPORT` i actualitza només dades de resum de cobrament.

**Estat:** **CORREGIT A BRANCA**.  
**Verificació:** `Uc002LegacyExistingInvoiceTest`.

---

### F-002-06 · CRÍTICA · Un segon pagament parcial podia perdre l'acumulat anterior

En la branca parcial de repartiment:

- abans: `PAGAMENT = auxPagat`;
- correcte: `PAGAMENT = PAGAMENT_anterior + auxPagat`.

**Risc:** saldo acadèmic incorrecte, cobrament aparent inferior al real i possibilitat de reclamar import ja cobrat.

**Acció:** acumulació explícita.

**Estat:** **CORREGIT A BRANCA**.  
**Verificació:** `Uc002LegacyExistingInvoiceTest`.

---

### F-002-07 · MITJANA · Debug echoes dins el camí econòmic

`efectuarPagamentFacturaGenerada()` imprimia dades i SQL/valors de depuració.

**Acció:** eliminats els `echo` executables del mètode.

**Estat:** **CORREGIT A BRANCA**.

---

### F-002-08 · ALTA · Documentació anterior afirmava que `Intranet.php` era buit

La lectura convencional del connector retornava contingut buit perquè el fitxer és molt gran, però l'arbre Git mostrava ~1,48 MB i `fetch_blob` va recuperar el codi complet.

**Acció:** corregida la documentació; ara el mètode real i les consultes queden inventariats.

**Estat:** **CORREGIT DOCUMENTALMENT**.

---

### F-002-09 · ALTA · `efact=0` i `efact=1` estaven conceptualment barrejats

`efact=0` pot generar factura durant el cobrament; `efact=1` cobra factura ja existent.

**Acció:** els diagrames ACTUAL/FINAL separen les dues superfícies. UC-002 FINAL només considera autoritatiu el cobrament sobre document ja emès.

**Estat:** **CORREGIT DOCUMENTALMENT**.  
**Pendent:** migrar/retirar el flux `efact=0` segons els UC d'emissió.

---

### F-002-10 · ALTA · Pont Intranet → SIF autoritatiu

**Situació inicial:** l'endpoint llegat cridava `Intranet::efectuarPagament()` sense registrar primer el moviment al SIF.

**Acció implementada:**
- `SifInternalApiClient::registerExistingInvoicePayment()`;
- proxy `sifPagamentFactura.php` amb HMAC/CSRF/actor/rol;
- feature flag `SIF_UC002_AUTHORITATIVE`;
- bloqueig fail-closed del vell `efectuarPagament.php` per `efact=1` quan el mode està actiu;
- command `register_existing_invoice` a `/api/payments/register.php`.

**Estat:** **IMPLEMENTAT DARRERE FLAG / PENDENT E2E I ACTIVACIÓ**.

---

### F-002-11 · ALTA · Idempotència end-to-end tècnica

A `main`, `PaymentService` ja comparava payload per hash v1/v2. La branca completa la traça tècnica:
- el JS conserva un UUID v4 a `sessionStorage` per la mateixa intenció;
- el proxy valida l'UUID i construeix `INTRANET|UC002|REQ:<uuid>`;
- `ManualPaymentPayloadBuilder` accepta una idempotency key explícita;
- un retry equivalent reutilitza el mateix `UUID_PAYMENT`;
- si el SIF ja ha fet commit però el sync falla, es respon `202 PENDING_RETRY`.

**Estat:** **IMPLEMENTAT / PENDENT PROVA E2E DE PÈRDUA DE RESPOSTA**.

**Límit:** aquesta idempotència evita duplicats tècnics, però no substitueix la prova bancària/TPV del fet extern.

---

### F-002-12 · ALTA · `payment_action_event` no està connectat al registre genèric

Existeixen:
- `PaymentActionGateway`;
- `PaymentActionEventWriter`;
- `PaymentActionEventRepository`.

Però `register.php -> PaymentService` no usa el gateway.

**Nota tècnica:** no s'ha connectat mecànicament en aquesta auditoria perquè `PaymentActionGateway` i `PaymentService` utilitzen `TransactionRunner` i el runner actual sempre fa `beginTransaction()`; embolcallar un servei dins l'altre sobre la mateixa PDO implicaria transacció imbricada no suportada.

**Estat:** **PENDENT / BLOQUEJANT**.  
**Solució FINAL:** un únic owner transaccional o un service intern que accepti transacció existent.

---

### F-002-13 · MITJANA/ALTA · Projecció per inscripció implementada; ledger econòmic genèric parcial

`ExistingInvoiceLegacyProjectionService` resol les línies `SOURCE_TYPE=INSCRIPCIO`, suma el net confirmat del ledger de pagaments, el limita al total fiscal i projecta de forma determinista l'import absolut per `ID_INSC`. La projecció detecta divergències de `IDPAG`, `FACTURA_RELACIONADA` i cobertura incompleta.

Això permet sincronitzar el resum acadèmic/llegat sense sumar dues vegades, però **no substitueix** `enrollment_fund_movement` com a ledger econòmic explícit per inscripció.

**Estat:** **PROJECCIÓ IMPLEMENTADA / LEDGER GENÈRIC PARCIAL**.

---

### F-002-14 · ALTA · Sync llegat post-commit

**Situació inicial:** `efectuarPagamentFacturaGenerada()` feia escriptures incrementals i correus dins un procediment no idempotent.

**Acció implementada al mode autoritatiu:**
1. el SIF fa commit del `CHARGE`;
2. `ExistingInvoiceLegacyProjectionService` calcula una projecció absoluta;
3. `Uc002LegacyPaymentProjectionApplier` bloqueja files amb `FOR UPDATE`, valida imports/identitats i escriu `PAGAMENT = valor_projectat` dins una transacció;
4. un error de sync retorna `202 PENDING_RETRY`, no crea un nou cobrament.

**Estat sync:** **IMPLEMENTAT DARRERE FLAG / PENDENT E2E**.  
**Encara pendent:** correus/notificacions post-commit; el vell mètode continua enviant correus quan el flag és desactivat.

---

### F-002-15 · ALTA · Evidència del fet extern no és regla genèrica del servei

La idempotency key no demostra per si sola que una transferència/TPV existeixi ni evita dues claus per un únic ingrés.

**Estat:** **PENDENT**.  
**FINAL:** `ExternalReceiptReconciler` o adaptadors de canal que resolguin referència externa inequívoca abans del `CHARGE`.

---

### F-002-16 · CI del main no es pot donar per verd

Al tall:
- `main@2bd2a751...`: SIF PHP MySQL tests en cua;
- commit anterior `04a484...`: run SIF fallit;
- hi havia alta càrrega de runs en cua.

**Estat:** **NO VERIFICAT** fins al run de la PR UC-002.

## 3. Traçabilitat requisit → codi → prova

| Req | Requisit | Codi | Prova | Estat |
|---|---|---|---|---|
| R-01 | només POST per mutació | JS + endpoint legacy + API SIF | boundary tests | IMPLEMENTAT branca |
| R-02 | actor autenticat | `InternalApiAuthenticator` | API boundary | IMPLEMENTAT branca |
| R-03 | rol explícit | `payments.write_roles` + rol pàgina | boundary tests | IMPLEMENTAT branca |
| R-04 | request replay-safe | `InternalApiRequestRepository` | auth tests existents | IMPLEMENTAT SIF |
| R-05 | import > 0 | validator | unit | IMPLEMENTAT branca |
| R-06 | 2 decimals | validator | unit | IMPLEMENTAT branca |
| R-07 | allocations=sum movement | validator | unit | IMPLEMENTAT branca |
| R-08 | mateixa clau + mateix payload = reús | PaymentService | integration/idempotency | IMPLEMENTAT |
| R-09 | mateixa clau + payload diferent = conflicte | PaymentService/hash | idempotency flow | IMPLEMENTAT |
| R-10 | no reemetre factura | PaymentRepository | RegisterPaymentTest | IMPLEMENTAT |
| R-11 | no alterar import fiscal per cobrament legacy | Intranet query | LegacyExistingInvoiceTest | IMPLEMENTAT branca |
| R-12 | fraccions acumulatives | Intranet | LegacyExistingInvoiceTest | IMPLEMENTAT branca |
| R-13 | recalcular estat cobrament | repository + calculator | integration/unit | IMPLEMENTAT |
| R-14 | audit event d'alta | PaymentActionGateway | — | PENDENT |
| R-15 | imputació per ID_INSC | fund movement infra | fluxos específics | PARCIAL |
| R-16 | evidència externa | — genèric | — | PENDENT |
| R-17 | intranet -> SIF | client HMAC + proxy + command | bridge boundary + command tests | IMPLEMENTAT FLAGGED |
| R-18 | sync legacy post-commit | projection service + applier absolut | projection + boundary tests | IMPLEMENTAT FLAGGED / E2E PENDENT |
| R-19 | mail post-commit | — | — | PENDENT |
| R-20 | E2E preproducció | entorn | evidència | PENDENT |

## 4. Matriu documentat / implementat / verificat / pendent

### DOCUMENTAT

- definició UC-002;
- separació `efact=1` / `efact=0`;
- classes ACTUAL/FINAL;
- seqüències ACTUAL/FINAL;
- activitats per pàgina/apartat;
- inventari PHP/JS;
- traçabilitat i riscos;
- criteris de tancament.

### IMPLEMENTAT

- `PaymentService`;
- idempotència v1/v2;
- `PaymentRepository`;
- `PaymentStatusCalculator`;
- invariants monetaris reforçats a branca;
- API signada i autoritzada a branca;
- frontera llegada POST/rol/origen a branca;
- preservació d'import fiscal llegat;
- acumulació correcta de fraccions.

### VERIFICAT

**Per inspecció de codi:** tot l'anterior.  
**Per tests definits:** cobertura unitària/integració/boundary present.  
**Per execució CI actual:** **encara no** en el moment d'escriure aquest document.  
**Per E2E/preproducció:** **no**.

### PENDENT

- activar i validar `SIF_UC002_AUTHORITATIVE=1` a test/preproducció;
- PaymentActionGateway/`payment_action_event` al flux genèric;
- ledger econòmic genèric `enrollment_fund_movement` per ID_INSC quan sigui exigible;
- reconciliació d'evidència externa bancària/TPV;
- outbox/notificacions post-commit;
- E2E de parcial, complet, retry, pèrdua de resposta, conflicte i sobrepagament;
- conservar evidència d'execució.

## 5. Fitxers canviats per l'auditoria

### Codi

- `sif/src/Service/PaymentPayloadValidator.php`
- `sif/public/api/payments/register.php`
- `sif/config/sif.php`
- `codi-drive/intranet-actual/js/alumnes-pagaments.js`
- `codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php`
- `codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/efectuarPagament.php`
- `codi-drive/intranet-actual/Intranet.php`
- `codi-drive/intranet-nova-canvis-verifactu/Intranet.php`
- `.github/workflows/sif-tests.yml`

### Proves

- `sif/tests/Unit/PaymentPayloadValidatorTest.php`
- `sif/tests/Integration/Uc002PaymentApiBoundaryTest.php`
- `sif/tests/Integration/Uc002LegacyPaymentBoundaryTest.php`
- `sif/tests/Integration/Uc002LegacyExistingInvoiceTest.php`

### Documentació

- `documentacio/06-fitxes-funcionals/uc-002.md`
- `uc-002-classes-actual-final.md`
- `uc-002-sequencies-actual-final.md`
- `uc-002-activitats-pagines-actual-final.md`
- `uc-002-inventari-codi-php-js-actual-final-2026-10-04.md`
- `uc-002-auditoria-tracabilitat-2026-10-04.md`

## 6. Decisió de tancament

**UC-002 NO es declara tancat.**

Es pot declarar **“nucli SIF implementat + fronteres corregides + documentació reconciliada”**, però el tancament funcional requereix que la pantalla real faci el cobrament contra SIF i que el resultat es sincronitzi al llegat sense duplicar diners.

## 7. Ordre recomanat de continuació

1. desplegar la branca a `sif_test` / preproducció amb les variables internes de pagament;
2. activar `SIF_UC002_AUTHORITATIVE=1` només en aquell entorn;
3. executar E2E: parcial, complet, retry, pèrdua de resposta, conflicte de payload i sobrepagament;
4. verificar una factura multiinscripció i la projecció absoluta al llegat;
5. conservar UUID/request-id i evidència SQL abans/després;
6. redissenyar l'owner transaccional per connectar `PaymentActionGateway`;
7. decidir/afegir `enrollment_fund_movement` genèric;
8. moure correus a post-commit/outbox;
9. només després valorar activació en producció.
