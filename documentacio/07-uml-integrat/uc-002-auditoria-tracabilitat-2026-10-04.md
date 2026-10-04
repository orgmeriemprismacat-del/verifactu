# UC-002 · Auditoria detallada i matriu de traçabilitat · 2026-10-04

## 0. Resum executiu

UC-002 **no estava complet** a `main`. El repositori sí contenia el nucli SIF de pagaments, idempotència v2, persistència i càlcul d'estat, però la documentació no reflectia l'estat real del codi llegat i la frontera de pagaments continuava incompleta.

L'auditoria ha recuperat i inspeccionat el `Intranet.php` real com a blob gran, ha separat el cobrament d'una factura existent (`efact=1`) de la facturació llegada durant el cobrament (`efact=0`) i ha corregit defectes de seguretat i consistència que podien afectar el cobrament.

**Conclusió:** el nucli SIF és sòlid i ha quedat més protegit; la UI llegada encara no està connectada al SIF de manera autoritativa. UC-002 queda **PARCIAL / NO TANCAT** fins completar adaptador, auditoria, sync post-commit i E2E.

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

### F-002-10 · ALTA · La intranet encara no delega al SIF

Tot i les correccions de frontera, l'endpoint llegat continua cridant `Intranet::efectuarPagament()`, no `PaymentService`.

**Risc:** doble font de veritat, absència d'UUID econòmic SIF al flux de pantalla i manca d'idempotència end-to-end.

**Estat:** **PENDENT / BLOQUEJANT TANCAMENT**.

---

### F-002-11 · ALTA · Idempotència SIF sí implementada, però no end-to-end

A `main`, `PaymentService` ja compara payload per hash v1/v2. `PayloadIdempotencyValidator` v2 elimina metadades de traça abans del fingerprint.

**Estat nucli:** **IMPLEMENTAT I DOCUMENTAT**.

**Pendent:** la UI llegada ha de crear/reutilitzar una clau durable i transportar-la fins al SIF.

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

### F-002-13 · MITJANA/ALTA · Ledger per inscripció no integrat genèricament

`EnrollmentFundMovementRepository` i serveis d'assignació existeixen, però el cobrament genèric només imputa a factura.

**Estat:** **PARCIAL**.  
**Pendent:** resoldre línies d'inscripció i reconciliar suma factura/pagament/ID_INSC.

---

### F-002-14 · ALTA · Llegat no transaccional end-to-end

`efectuarPagamentFacturaGenerada()` usa connexions diferents, actualitza factura i inscripcions i envia correu dins el mateix procediment.

**Risc:** fallada intermèdia amb estat parcial i reexecució manual potencialment duplicada.

**Estat:** **PENDENT / BLOQUEJANT**.  
**FINAL:** SIF commit únic; sync legacy + correus post-commit, idempotents i retryables.

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
| R-17 | intranet -> SIF | — | — | PENDENT |
| R-18 | sync legacy post-commit | — | — | PENDENT |
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

- adaptador UI → SIF;
- idempotència durable end-to-end;
- PaymentActionGateway al flux genèric;
- ledger genèric ID_INSC;
- reconciliació d'evidència externa;
- sync legacy post-commit/retry;
- outbox notificacions;
- resposta JSON tipificada;
- proves E2E i evidència.

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

1. crear `SifInternalPaymentClient` / adaptador intranet;
2. definir mapping factura llegada → `UUID_FACTURA`;
3. construir idempotency key estable + request/correlation IDs;
4. registrar SIF primer;
5. fer sync llegat amb mateix `UUID_PAYMENT`;
6. redissenyar owner transaccional per connectar `PaymentActionGateway`;
7. afegir ledger `ID_INSC` quan la factura tingui diverses inscripcions;
8. moure correus a post-commit/outbox;
9. executar E2E: parcial, complet, retry, conflicte i pèrdua de resposta.
