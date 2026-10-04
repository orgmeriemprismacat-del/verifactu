# UC-006 · Auditoria detallada i traçabilitat — 2026-10-03

## 1. Objecte de l’auditoria

Auditoria estàtica exhaustiva de **UC-006 — Registrar devolució, saldo o compensació** sobre `main` a partir del commit base `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`.

S’han revisat:
- fitxa funcional;
- fitxa/UML integrada;
- PHP/JS real de les superfícies llegades;
- serveis, builders i repositoris SIF;
- proves d’integració existents;
- scripts de preview/process;
- traçabilitat amb UC-05, UC-28, UC-29 i UC-29a;
- mancances de classes, seqüències i activitats ACTUAL/FINAL.

## 2. Conclusió executiva

UC-006 **no estava complet documentalment** segons el patró “fitxa → classes A/F → seqüències A/F → activitats per pàgina → auditoria”. Abans d’aquesta branca existien:
1. la fitxa funcional genèrica `documentacio/06-fitxes-funcionals/uc-006.md`;
2. la fitxa/UML integrada `uc-006-devolucio-saldo-compensacio.md`.

No existien peces dedicades UC-006 de classes ACTUAL/FINAL, seqüències ACTUAL/FINAL ni activitats per pàgina/apartat, i la fitxa funcional atribuïa com a “CONFIRMAT” garanties transversals que el codi concret no demostra.

### Estat real

- **DOCUMENTAT:** ara queda cobert el conjunt documental principal.
- **IMPLEMENTAT:** existeixen els serveis base UC-28/29/29a i, en aquesta branca, primitives transaccionals de ledger per CREDIT_CREATE, REFUND_EXIT i COMPENSATION_ALLOCATION.
- **VERIFICAT ESTÀTICAMENT:** contractes, wiring absent i proves existents han estat contrastats al repositori.
- **PENDENT:** UC-006 com a orquestració de negoci i integració real.
- **PENDENT BLOQUEJANT:** titularitat, evidència externa genèrica del REFUND, orquestrador/autorització, derivació obligatòria de la clau de dret a les superfícies productives, auditoria transversal i E2E. El consum quantitatiu del dret per inscripció ja té primitives executables.

## 3. Inventari documental abans/després

| Artefacte | Abans | Branca d’auditoria |
| --- | --- | --- |
| Fitxa funcional | Sí, v1.1 genèrica | **Actualitzada a v2.0 reconciliada** |
| Fitxa/UML integrada | Sí | Sí; s’enllaça amb auditoria |
| Classes ACTUAL/FINAL | **No** | **Creat** |
| Seqüències ACTUAL/FINAL | **No** | **Creat** |
| Activitats ACTUAL/FINAL per pàgina | **No** | **Creat** |
| Auditoria/traçabilitat dedicada | **No** | **Creat** |
| Proves de servei refund | Sí | Identificades |
| Proves de servei saldo/compensació | Sí | Identificades |
| Prova E2E UC-006 | No localitzada | Pendent |

## 4. Inventari de codi PHP/JS

### 4.1 Superfície alumne

| Fitxer | Funció UC-006 | Estat |
| --- | --- | --- |
| `alumnes-mostrar-alumne.php` | shell de pàgina, CSRF lifecycle, carrega JS runtime | IMPLEMENTAT |
| `js/alumnes-mostrar-alumne.min.js` | baixa i canvi de curs reals | IMPLEMENTAT |
| `mostraModalDonarBaixa.php` | consulta modal baixa | IMPLEMENTAT |
| `confirmacioBaixa_DonarBaixa.php` | mutació baixa amb POST/CSRF/permís | IMPLEMENTAT |
| `realitzarCanviCurs_CanviCurs.php` | mutació canvi; preview SIF opcional | IMPLEMENTAT PARCIAL |
| Adaptador devolució/saldo després de baixa/canvi | execució UC-006 | **NO LOCALITZAT** |

**Nota important:** la pàgina executa `alumnes-mostrar-alumne.min.js?ver=1.6`; el fitxer minificat és evidència de runtime i no s’ha substituït per inferències del source no minificat.

### 4.2 Superfície factura

| Fitxer | Funció | Estat |
| --- | --- | --- |
| `alumnes-factura.php` | shell Consulta/Anul·la factura | IMPLEMENTAT |
| `js/alumnes-factura.js` | modal i POST d’anul·lació | IMPLEMENTAT |
| `mostrarModalAnulaFactura_Factures.php` | modal llegat, guard SIF | IMPLEMENTAT |
| `anularFactura_Factures.php` | POST autoritzat cap a `Intranet::anularFactura` | IMPLEMENTAT |
| Wiring a `ManualRefundService` | convertir retorn confirmat en REFUND | **NO LOCALITZAT** |

### 4.3 SIF devolució

| Component | Observació | Estat |
| --- | --- | --- |
| `ManualRefundService` | cerca factura i registra payload via PaymentService | IMPLEMENTAT |
| `ManualRefundPayloadBuilder` | import/data/mètode + idempotency key | IMPLEMENTAT |
| `PaymentService` | clau + hash, reús i recovery duplicate key | IMPLEMENTAT |
| `PaymentRepository` | transaction/allocation + recàlcul cobrament | IMPLEMENTAT |
| `ManualRefundServiceTest` | parcial, total, missing invoice + REFUND_EXIT/rollback afegits | PROVES AMPLIADES · CI PENDENT |
| evidència externa retorn | no forma part del servei | PENDENT |
| límit retornable per inscripció | `availableAmountForInscription()` + rollback transaccional amb `source_enrollment_id` | IMPLEMENTAT A LA BRANCA |

### 4.4 SIF saldo i compensació

| Component | Observació | Estat |
| --- | --- | --- |
| `CreditBalanceService::createCredit` | crea saldo ACTIVE | IMPLEMENTAT |
| `CreditBalanceService::applyCredit*` | locks, límit saldo/deute, consumeix | IMPLEMENTAT |
| `CreditBalancePayloadBuilder` | payload de saldo i compensació | IMPLEMENTAT |
| `CreditBalanceRepository` | persistència + outstanding invoice | IMPLEMENTAT |
| `CreditBalanceServiceTest` | creació, idempotència, consum de dret, compensació destí i rollback | PROVES AMPLIADES · CI PENDENT |
| idempotència creació saldo | clau/hash + reús/conflicte; `source_enrollment_id` exigeix clau | IMPLEMENTAT PARCIAL |
| titularitat saldo vs factura | no acreditada | PENDENT BLOQUEJANT |

## 4.5. Troballa addicional — ledger executable per inscripció

L'auditoria ampliada ha localitzat una base que redueix el gap de traçabilitat:

- `sif/database/migrations/2026_09_30_000030_add_enrollment_fund_movement.sql`;
- `sif/src/Repository/EnrollmentFundMovementRepository.php`;
- `sif/src/Service/CourseEnrollmentFundAllocationService.php`;
- `sif/src/Service/PackEnrollmentFundAllocationService.php`;
- `sif/tests/Integration/CourseEnrollmentFundAllocationServiceTest.php`.

### Què està implementat

- moviment immutable identificat per UUID i `IDEMPOTENCY_KEY` UNIQUE;
- `EXTERNAL_ALLOCATION` d'un `CHARGE` confirmat cap a `ID_INSC_DESTI`;
- variant `COMPENSATION_ALLOCATION` al repositori;
- `INTERNAL_TRANSFER` i `REVERSAL` previstos pel CHECK de la migració;
- correlació amb factura/línia/pagament/operació;
- reús idempotent amb verificació de payload;
- allocadors reals per curs i pack.

### Ampliació implementada a la branca UC-006

- migració `2026_10_04_000034_extend_enrollment_fund_exits.sql`;
- `UUID_CREDIT` al ledger;
- `availableAmountForInscription()` amb lock opcional i bloqueig de saldo negatiu;
- `CREDIT_CREATE`: inscripció origen → `credit_balance`, idempotent i dins la mateixa transacció de l'alta del saldo;
- `REFUND_EXIT`: inscripció origen → exterior, vinculat al `REFUND CONFIRMED`, amb límit tant pel dret disponible com pel mateix `UUID_PAYMENT`;
- `COMPENSATION_ALLOCATION`: `UUID_CREDIT` + `COMPENSATION` → factura/línia/inscripció destí;
- els reintents poden canviar `correlation_id` sense alterar la identitat econòmica, alineat amb `PayloadIdempotencyValidator` de `main`;
- els scripts preview/process exposen `source_enrollment_id`, `target_enrollment_id`, correlació i `uuid_operation`.

### Què continua faltant

- política de titularitat/pagador/receptor;
- `INTERNAL_TRANSFER` de canvi de curs com a operació orquestrada;
- obligar els identificadors d'inscripció i la clau de dret des de UI/endpoint UC-006;
- evidència externa genèrica abans de registrar un REFUND;
- audit gateway i E2E.

**Conclusió 04/10:** el model quantitatiu per inscripció ja cobreix les primitives principals de sortida/entrada d'UC-006; el buit passa a ser sobretot **orquestració, autorització, titularitat i verificació operativa**.

## 5. Contrast de la fitxa funcional antiga amb el codi

### 5.1 REQUEST_ID / CORRELATION_ID

La fitxa antiga deia que existien abans del commit. Els builders/serveis UC-006 examinats no exigeixen `request_id` ni `correlation_id`.

**Resultat d’auditoria:** **DISSENY / PENDENT D’INTEGRACIÓ**, no “confirmat”.

### 5.2 payment_action_event

La fitxa antiga deia que tota petició/consulta/denegació/reús/resultat genera `payment_action_event`.

`PaymentActionGateway` existeix. Però:
- `ManualRefundService` delega directament a `PaymentService`;
- `CreditBalanceService` opera directament amb repositoris;
- els scripts inspeccionats instancien aquests serveis;
- `public/api/payments/register.php` instancia `PaymentService` directament.

**Resultat:** la garantia no queda acreditada per UC-006.

### 5.3 Persistència mínima

La fitxa antiga agrupava `sif_audit_event`, `operational_event`, `payment_action_event`, `payment_transaction/payment_allocation` com a mínim confirmat.

**Codi observat en refund:** `payment_transaction` + `payment_allocation` + estat cobrament.  
**Codi observat en saldo:** `credit_balance`.  
**Codi observat en compensació:** transaction/allocation + credit balance.

**Resultat:** s’ha corregit la fitxa per separar implementació real de contracte objectiu.

## 6. Revisió funcional per variant

### 6.1 REFUND — UC-28

**Implementat base**
- identificació de factura;
- import positiu;
- data;
- mètode manual/transferència;
- referència/banc opcionals;
- idempotència de pagament amb payload hash;
- estat de cobrament recalculat.

**Implementat en aquesta branca quan hi ha `source_enrollment_id`**
- `ManualRefundService` comparteix transacció amb `PaymentService::registerPaymentInTransaction()`;
- el REFUND confirmat queda vinculat a un `REFUND_EXIT` del ledger;
- `availableAmountForInscription()` bloqueja i reconstrueix el dret net;
- una sortida superior al dret disponible provoca 409 i rollback del REFUND, `payment_allocation` i estat de factura;
- la suma de `REFUND_EXIT` sobre un mateix `UUID_PAYMENT` no pot superar l'import del REFUND;
- reintent equivalent reutilitza el mateix moviment i la mateixa sortida.

**No implementat/acreditat**
- titular/receptor legítim del retorn;
- evidència bancària/Redsys genèrica abans de registrar el REFUND;
- deduplicació cross-channel del mateix retorn extern;
- partició automàtica de factures conjuntes si el caller no identifica la inscripció origen;
- `PaymentActionGateway` i adaptador productiu UC-006.

### 6.2 CREDIT_BALANCE — UC-29

**Implementat base**
- titular declarat;
- import;
- origen declarat;
- persistència `ACTIVE`.

**Implementat en aquesta branca**
- `IDEMPOTENCY_KEY` opcional + hash canònic;
- mateixa clau/payload reutilitza el mateix `UUID_CREDIT`; payload contradictori dona 409;
- si s'aporta `source_enrollment_id`, la clau idempotent passa a ser obligatòria;
- `CREDIT_CREATE` consumeix el dret de l'inscripció en la mateixa transacció que l'alta del saldo;
- disponibilitat insuficient fa rollback de `credit_balance` i del ledger;
- reintent amb `correlation_id` diferent continua essent el mateix fet econòmic, alineat amb el validador d'idempotència de `main`.

**No implementat/acreditat**
- derivació obligatòria de la clau de dret des de l'orquestrador/producte;
- titular/origen obtinguts i validats contra una font autoritativa de negoci;
- autorització/auditoria UC-006;
- wiring UI.

### 6.3 COMPENSATION — UC-29a

**Implementat base**
- lock saldo;
- lock/càrrega factura;
- saldo `ACTIVE`;
- import <= saldo disponible;
- import <= deute factura;
- moviment `COMPENSATION`;
- consum atòmic;
- reús idempotent amb comparació de payload.

**Implementat en aquesta branca quan hi ha `target_enrollment_id`**
- la inscripció destí ha de tenir exactament una `factura_linia` de la factura;
- `COMPENSATION_ALLOCATION` conserva `UUID_CREDIT`, `UUID_PAYMENT`, factura, línia i `ID_INSC_DESTI`;
- assentament del ledger i consum del saldo comparteixen transacció;
- destí no present a la factura provoca 409 i rollback del `COMPENSATION` i del consum;
- mateixa K amb payload diferent es rebutja explícitament.

**No implementat/acreditat**
- titular compatible entre saldo, pagador/receptor i factura destí;
- generació/autorització de la K explícita d'ordre des del canal productiu;
- gateway d'auditoria;
- wiring UI.

## 7. Revisió de seguretat de superfícies ACTUALS

### Baixa
`confirmacioBaixa_DonarBaixa.php`:
- exigeix POST;
- valida sessió;
- valida CSRF;
- same-origin;
- `assertCanEdit`;
- valida ID i motiu;
- usa guard USOC.

**Valoració:** hardening real present, però és del lifecycle llegat, no autorització econòmica UC-006.

### Canvi de curs
`realitzarCanviCurs_CanviCurs.php`:
- POST;
- sessió;
- CSRF;
- same-origin;
- `assertCanEdit`;
- guard lifecycle;
- preview SIF opcional i detecció de canvi de decisió.

**Valoració:** bona base d’integració; falta executar/traçar el resultat econòmic.

### Anul·lació factura
`anularFactura_Factures.php`:
- POST;
- sessió;
- same-origin;
- `assertCanEdit`;
- `SifLegacyInvoiceMutationGuard`.

**Valoració:** endpoint llegat endurit, però no és un endpoint REFUND.

### API genèrica de pagaments
`sif/public/api/payments/register.php` no mostra en el fitxer una capa explícita d’autorització ni `PaymentActionGateway`.

**Valoració:** no s’ha de presentar aquest endpoint per si sol com a endpoint final UC-006. La possible protecció externa del servidor/proxy no ha estat acreditada per aquesta auditoria de codi.

## 7.0. Contracte ja estable de derivació des de canvi de curs

`CourseChangeImpactClassifier` ja separa explícitament la decisió econòmica de la fiscal:

- `NONE`: cap diferència econòmica a resoldre;
- `AMOUNT_DUE`: el client encara deu import; deriva a cobrament, no a UC-006 de sortida;
- `EXCESS_TO_RESOLVE`: hi ha import pagat per sobre del nou total i **s'ha de resoldre**, però el classificador no decideix refund ni saldo.

`CourseChangePreviewService` reforça aquesta decisió amb dades SIF quan hi ha una factura única: usa línia d'inscripció i pagaments/assignacions reals en lloc dels imports del navegador. Amb múltiples factures força `REVIEW_REQUIRED` fiscal i impedeix confirmar el canvi llegat.

La prova `testLowerManualPriceProducesExcessButNotAutomaticRefund()` confirma explícitament el contracte: una diferència a favor produeix `EXCESS_TO_RESOLVE`, **no un refund automàtic**.

Per tant, la derivació segura queda definida sense inventar regles:

`UC-026 preview → EXCESS_TO_RESOLVE(excess_amount) → UC-006 preview/decisió → REFUND o CREDIT o REVIEW`.

El gap és el pas posterior al preview: `realitzarCanviCurs_CanviCurs.php` valida que la decisió no hagi canviat, però encara no executa ni persisteix una resolució UC-006.

## 7.1. Precedent reutilitzable al repositori — retorn aprovat ≠ retorn executat

UC-111 ja implementa un patró que es pot generalitzar conceptualment per UC-006:

- `NovicePromotionRootRefundPlanService` prepara un pla sense efectes laterals;
- l'estat `APPROVED_WAITING_REFUND` conserva una aprovació final **sense afirmar que el banc ja ha retornat diners**;
- `NovicePromotionOriginRefundEvidenceSourceInterface::confirmedOriginRefund()` exigeix una font autoritativa externa i explícitament **no inicia** el retorn;
- `NovicePromotionRootRefundExecutionService` només executa conseqüències internes després de validar l'evidència i comprovar que CHARGE/REFUND reals quadren.

Aquest codi és específic de promoció docent i **no s'ha de reutilitzar directament com a domini UC-006**, però demostra que el repositori ja ha adoptat la separació correcta entre:

`PREVIEW/PLA → APROVACIÓ → WAITING_REFUND → EVIDÈNCIA EXTERNA → EXECUCIÓ INTERNA`.

Per UC-006 convé extreure un contracte genèric equivalent (`RefundEvidenceSourceInterface` / estat pendent de retorn) en lloc de tornar a barrejar `A TORNAR` amb `REFUND` confirmat.

## 7.2. Implementació afegida — idempotència i consum de dret de `credit_balance`

En aquesta branca s'ha resolt tant el reintent tècnic com el consum quantitatiu opcional del dret:

- migració `2026_10_03_000033_add_credit_balance_idempotency.sql`;
- `IDEMPOTENCY_KEY` nullable/UNIQUE i `IDEMPOTENCY_PAYLOAD_HASH`;
- mateixa clau + mateix payload → mateix `UUID_CREDIT`;
- mateixa clau + payload diferent → 409/CONFLICT;
- duplicate-key concurrent → reload + comprovació de payload;
- sense `source_enrollment_id` es conserva compatibilitat amb el flux antic;
- amb `source_enrollment_id`, `idempotency_key` és obligatòria;
- `CREDIT_CREATE` es registra dins la mateixa transacció que el saldo;
- `availableAmountForInscription()` bloqueja el ledger i impedeix consumir més valor del disponible;
- si el dret no és suficient, no queda ni saldo nou ni assentament parcial;
- `correlation_id` és metadada de traça, no identitat del fet econòmic.

**Límit deliberat:** el servei no inventa la identitat del dret a partir de `SOURCE_TYPE/SOURCE_ID`; el futur orquestrador ha d'aportar una K estable i una inscripció origen autoritzada.

## 7.3. Enduriment afegit — compensació no reutilitza payload contradictori

`CreditBalanceService::applyCredit*` ja tenia una clau derivada de saldo + factura + import i evitava consumir dues vegades el saldo, però reutilitzava qualsevol moviment existent amb aquella K **sense comparar el payload**.

Aquesta branca afegeix `assertSamePaymentPayload()`, compatible amb hash V1/V2, abans de qualsevol reús normal o recuperació després de duplicate key:

- mateixa K + mateix payload → reús;
- mateixa K + data/notes/allocation o altre payload diferent → 409/CONFLICT;
- el saldo no es torna a consumir en cap dels dos casos.

S'afegeix `CreditBalanceServiceTest::testRejectsSameCompensationKeyWithDifferentPayload()`.

**Actualització 04/10:** el builder ja accepta `idempotency_key` explícita. Dues ordres legítimes del mateix import poden coexistir amb K diferents; el gap restant és generar/autoritzar aquesta K des de l'orquestrador i conservar-ne la traça.

## 8. Idempotència i concurrència

### Correcte/localitzat

- `PaymentService`: cerca `FOR UPDATE`, hash V1/V2 i recuperació de duplicate key;
- `PaymentService::registerPaymentInTransaction()` permet que REFUND i `REFUND_EXIT` comparteixin commit/rollback;
- `CreditBalanceService::createCredit()`: clau/hash, reús, conflicte i recovery; si consumeix fons d'inscripció exigeix K;
- `CREDIT_CREATE` i `REFUND_EXIT`: idempotents i limitats per `availableAmountForInscription()`;
- refund i saldo competeixen sobre el mateix dret disponible, evitant doble consum quan s'identifica la mateixa inscripció;
- `CreditBalanceService::applyCredit*`: locks, hash del payload, consum transaccional i `COMPENSATION_ALLOCATION` opcional;
- `correlation_id` queda fora de la identitat econòmica, coherent amb `PayloadIdempotencyValidator` actual de `main`.

### Buit residual

- la K derivada continua sent el fallback saldo+factura+import, però una K explícita permet ordres legítimes diferents; falta que la UI/orquestrador la construeixi;
- refund sense referència externa forta deriva la K de factura/data/import/banc;
- falta prova de concurrència real amb dues sessions SQL intentant consumir simultàniament el mateix dret;
- falta deduplicació cross-channel Redsys/manual basada en una identitat externa comuna.

## 9. Traçabilitat amb altres casos

| Relació | Tipus | Regla |
| --- | --- | --- |
| UC-006 → UC-28 | include/especialització | retorn monetari |
| UC-006 → UC-29 | include/especialització | creació de saldo |
| UC-006 → UC-29a | include/especialització | consum de saldo |
| UC-006 ↔ UC-05/74 | relació separada | fiscalitat no implica moviment econòmic |
| UC-026 → UC-006 | disparador possible | canvi de curs amb diferència a favor |
| UC-027 → UC-006 | disparador possible | baixa amb dret econòmic |
| UC-002 | infraestructura comuna | payment transaction/allocation |
| UC-056/105 | conciliació/reutilització | evitar duplicar un pagament/retorn existent |

## 10. Matriu DOCUMENTAT / IMPLEMENTAT / VERIFICAT / PENDENT

| Element | Documentat | Implementat | Verificat | Pendent |
| --- | :---: | :---: | :---: | --- |
| Decisió mare UC-006 | Sí | No | No | orquestrador/autorització |
| Devolució base | Sí | Sí + `REFUND_EXIT` opcional | contrast estàtic + tests afegits | evidència externa/titular/E2E |
| Saldo base | Sí | Sí + K/hash + `CREDIT_CREATE` | contrast estàtic + tests afegits | titular/orquestrador/E2E |
| Compensació base | Sí | Sí + ledger destí | contrast estàtic + tests afegits | titular/identitat d'ordre/E2E |
| Dret disponible per inscripció | Sí | `availableAmountForInscription()` | contrast estàtic + tests afegits | concurrència real/preprod |
| Baixa actual | Sí | Sí | contrast PHP/JS | derivació econòmica |
| Canvi actual | Sí | Sí parcial | contrast PHP/JS | executar `EXCESS_TO_RESOLVE`; `INTERNAL_TRANSFER` |
| Anul·lació factura actual | Sí | Sí | contrast PHP/JS | separar retorn real |
| Classes A/F | Sí | N/A | revisat | actualització final de l'estat |
| Seqüències A/F | Sí | N/A | revisat | actualització final de l'estat |
| Activitats per pàgina A/F | Sí | N/A | revisat | actualització final de l'estat |
| Audit gateway UC-006 | Sí objectiu | No acreditat | No | integrar |
| Preproducció | Sí criteris/CLI | scripts preparats | No executat | executar i conservar evidència |

## 11. Mancances prioritzades

### P0 — bloquejants abans d’operar UC-006 des de la UI

1. **UC006-GAP-P0-01 · Orquestrador/endpoint autoritzat.**  
   Les primitives de diners ja existeixen, però cap superfície llegada ha de decidir-les directament des de camps DOM o valors llegats.

2. **UC006-GAP-P0-02 · Titularitat.**  
   Validar pagador/receptor del refund, titular del saldo i compatibilitat amb la factura/inscripció destí.

3. **UC006-GAP-P0-03 · Evidència externa del retorn.**  
   Separar `RETURN_PENDING` de `REFUND CONFIRMED`; reutilitzar conceptualment el patró UC-111.

4. **UC006-GAP-P0-04 · Identitat externa/cross-channel.**  
   El mateix retorn real no pot quedar duplicat entre Redsys, banc i registre manual.

5. **UC006-GAP-P0-05 · `INTERNAL_TRANSFER` de canvi de curs.**  
   El ledger admet el tipus, però falta servei/orquestració A→B amb conservació i proves.

6. **UC006-GAP-P0-06 · Contracte obligatori del dret.**  
   UI/endpoint han d'aportar sempre la inscripció origen/destí i una identitat estable del dret quan el moviment prové de fons atribuïts.

### P1 — traça i integració

7. `PaymentActionGateway` o equivalent per REQUESTED/terminal.
8. request/correlation id estable de punta a punta.
9. sync llegat només post-COMMIT.
10. incidència automàtica en divergència.
11. concurrència real amb dues sessions i evidència de test.

### P2 — UX i operació

12. pantalla UC-006 final amb context complet;
13. indicadors RETURN_PENDING/CONFIRMED;
14. consulta del saldo i historial del ledger;
15. explicació separada de l’efecte fiscal.

## 12. Proves d’acceptació requerides

| ID | Prova | Estat/esperat |
| --- | --- | --- |
| UC006-T01 | baixa sense dret econòmic | PENDENT orquestrador → NO_CHANGE |
| UC006-T02 | baixa amb dret però retorn encara no executat | PENDENT evidence layer → RETURN_PENDING |
| UC006-T03 | refund confirmat vinculat a inscripció | test de `REFUND_EXIT` afegit; CI pendent |
| UC006-T04 | reintent mateix refund | mateix UUID_PAYMENT + una sola sortida; test afegit |
| UC006-T05 | mateix retorn via Redsys i manual | PENDENT identitat cross-channel |
| UC006-T06 | sortida superior al dret disponible | bloqueig + rollback implementat; test afegit |
| UC006-T07 | compensació a inscripció no present a factura | 409 + rollback implementat; test afegit |
| UC006-T08 | mateixa K saldo + mateix payload | mateix UUID_CREDIT; test afegit |
| UC006-T08b | mateixa K saldo + payload diferent | 409; test afegit |
| UC006-T08c | mateixa K/payload amb `correlation_id` nou | reús sense duplicar ledger; test afegit |
| UC006-T09 | titular saldo incompatible | PENDENT política titularitat |
| UC006-T10 | compensació > saldo | bloqueig existent |
| UC006-T11 | compensació > deute | bloqueig existent |
| UC006-T12 | mateixa K compensació amb payload diferent | 409; test afegit |
| UC006-T12b | dues ordres legítimes de mateix import amb K diferents | **test afegit: dos UUID_PAYMENT + reintent segur; CI pendent** |
| UC006-T13 | rectificativa sense retorn | cap REFUND automàtic |
| UC006-T14 | SIF confirma però sync llegat falla | PENDENT adaptador/sync |
| UC006-T15 | saldo consumeix 80 de 120 i després refund demana 50 | refund rollback; test creuat afegit |
| UC006-T15b | concurrència simultània refund vs saldo | PENDENT prova multi-sessió |
| UC006-T16 | permís denegat | PENDENT endpoint autoritzat |
| UC006-T17 | target enrollment vàlid en compensació | ledger credit→inscripció + reús; test afegit |
| UC006-T18 | E2E baixa/canvi → decisió → moviment | PENDENT UI/preproducció |

> Els tests marcats “afegit” són evidència de codi present a la branca, **no evidència d'execució** fins que GitHub Actions o `sif_test*`/`sif_pre` els executin.

## 13. Fitxers creats/modificats en aquesta auditoria

- **MODIFICAT** `documentacio/06-fitxes-funcionals/uc-006.md`
- **CREAT** `documentacio/07-uml-integrat/uc-006-classes-actual-final.md`
- **CREAT** `documentacio/07-uml-integrat/uc-006-sequencies-actual-final.md`
- **CREAT** `documentacio/07-uml-integrat/uc-006-activitats-pagines-actual-final.md`
- **CREAT** aquest document
- **CREAT** `uc-006-inventari-artefactes.md`
- **MODIFICAT** `00-revisio-moviments-inscripcions.md` per reconciliar la migració/repositori ja existents
- **ACTUALITZAT** índex UML i fitxa integrada amb enllaços d’auditoria

## 14. Decisió sobre canvis de codi

Després de la primera auditoria s'han implementat només primitives que es poden demostrar sense inventar titularitat ni política comercial:

- idempotència tècnica de `credit_balance`;
- `PaymentService::registerPaymentInTransaction()`;
- migració additiva del ledger amb `UUID_CREDIT`, `CREDIT_CREATE` i `REFUND_EXIT`;
- càlcul/lock de disponibilitat per inscripció;
- refund i saldo competint pel mateix dret disponible;
- `COMPENSATION_ALLOCATION` cap a factura/línia/inscripció explícita;
- rollback atòmic si el dret o el destí són invàlids;
- proves de reús, conflicte, conservació i rollback;
- flags CLI per validar els recorreguts en test/preproducció;
- merge real de `main` dins la branca abans de continuar, preservant els canvis recents d'UC-001.

No s'ha implementat per inferència la **titularitat**, l'autorització UC-006, la prova bancària genèrica, `INTERNAL_TRANSFER` ni la decisió final de UI. Aquestes continuen requerint contracte funcional explícit.

## 15. Criteri de tancament de l’auditoria

**Documentació:** paquet principal complet i en procés de reconciliació final amb el codi implementat.  
**Primitives econòmiques UC-006:** **PARCIALMENT IMPLEMENTADES**: refund→exit, enrollment→credit i credit→enrollment ja tenen camins transaccionals opcionals.  
**Orquestració UC-006:** **NO tancada**.  
**Acceptació operativa:** **NO tancada**.

**Motiu:** falten titularitat, evidència externa genèrica, identitat cross-channel, `INTERNAL_TRANSFER`, endpoint/UI autoritzat, auditoria transversal i evidència executada de CI/preproducció.
