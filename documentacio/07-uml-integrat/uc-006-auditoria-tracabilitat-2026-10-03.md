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

**Implementat**
- identificació de factura;
- import positiu;
- data;
- mètode manual/transferència;
- referència/banc opcionals;
- idempotència de pagament amb payload hash;
- estat de cobrament recalculat.

**No implementat/acreditat**
- dret retornable per inscripció/origen;
- retorn no superior al valor net disponible;
- titular;
- sortida bancària/Redsys confirmada;
- deduplicació cross-channel del mateix retorn extern;
- adaptador productiu UC-006.

### 6.2 CREDIT_BALANCE — UC-29

**Implementat**
- titular declarat;
- import;
- origen declarat;
- persistència ACTIVE.

**No implementat/acreditat**
- unicitat/idempotència del dret origen;
- prova que titular/origen provenen de dades autoritatives;
- reserva/consum del dret origen en la mateixa transacció;
- wiring UI.

### 6.3 COMPENSATION — UC-29a

**Implementat**
- lock saldo;
- lock/càrrega factura;
- saldo ACTIVE;
- import <= saldo disponible;
- import <= deute factura;
- moviment COMPENSATION;
- consum atòmic;
- reús idempotent.

**No implementat/acreditat**
- titular compatible;
- request ID independent de la key derivada;
- dues compensacions legítimes idèntiques diferenciables;
- gateway d’auditoria;
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

## 7.2. Implementació afegida — idempotència tècnica de `credit_balance`

En aquesta branca s'ha eliminat el buit tècnic de reintent de `CreditBalanceService::createCredit()` **sense imposar una regla de negoci inventada**:

- nova migració `2026_10_03_000033_add_credit_balance_idempotency.sql`;
- `IDEMPOTENCY_KEY` nullable/UNIQUE i `IDEMPOTENCY_PAYLOAD_HASH` nullable a `credit_balance`;
- el builder accepta `idempotency_key` opcional;
- mateixa clau + mateix payload normalitzat → mateix `UUID_CREDIT`, `idempotency_reused=true`;
- mateixa clau + payload diferent → 409/CONFLICT;
- col·lisió concurrent UNIQUE → reload + comprovació de payload;
- sense clau es conserva el comportament anterior per compatibilitat;
- preview/process CLI accepten `--idempotency-key`;
- `CreditBalanceServiceTest` incorpora reús i conflicte.

**Límit deliberat:** el servei **no deriva** la clau de `SOURCE_TYPE/SOURCE_ID`, perquè això podria fusionar dos drets legítims diferents. El caller/orquestrador UC-006 ha d'aportar una identitat estable del dret/tram econòmic, i encara falta consumir/bloquejar aquest mateix dret al ledger `enrollment_fund_movement`.

## 7.3. Enduriment afegit — compensació no reutilitza payload contradictori

`CreditBalanceService::applyCredit*` ja tenia una clau derivada de saldo + factura + import i evitava consumir dues vegades el saldo, però reutilitzava qualsevol moviment existent amb aquella K **sense comparar el payload**.

Aquesta branca afegeix `assertSamePaymentPayload()`, compatible amb hash V1/V2, abans de qualsevol reús normal o recuperació després de duplicate key:

- mateixa K + mateix payload → reús;
- mateixa K + data/notes/allocation o altre payload diferent → 409/CONFLICT;
- el saldo no es torna a consumir en cap dels dos casos.

S'afegeix `CreditBalanceServiceTest::testRejectsSameCompensationKeyWithDifferentPayload()`.

**Gap que queda:** dues compensacions **legítimes** del mateix import sobre el mateix saldo/factura continuen necessitant un identificador d'ordre diferent perquè la K actual no les pot representar com dues operacions noves.

## 8. Idempotència i concurrència

### Correcte/localitzat

- `PaymentService`: cerca `FOR UPDATE`, hash de payload i recuperació de duplicate key.
- `CreditBalanceService::applyCredit*`: transacció, locks i recovery duplicate key.
- consum de saldo i alta de compensació queden dins una operació transaccional.

### Buit

- `createCredit()` no té contracte d’idempotency key.
- key de compensació = saldo + factura + import: pot col·lidir amb una segona intenció legítima exactament igual.
- key de refund sense referència = factura + data + import + banc: dos retorns reals idèntics el mateix dia poden necessitar un identificador extern més fort.
- amb `reference`, el builder usa `REFUND|REF:<reference>`; cal garantir semàntica global i canal.

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
| Decisió mare UC-006 | Sí | No | No | orquestrador |
| Devolució base | Sí | Sí | tests existents + contrast estàtic | guards negoci/E2E |
| Saldo base | Sí | Sí | tests existents + contrast estàtic | idempotència origen/E2E |
| Compensació base | Sí | Sí | tests existents + contrast estàtic | titular/E2E |
| Baixa actual | Sí | Sí | contrast PHP/JS | derivació econòmica |
| Canvi actual | Sí | Sí parcial | contrast PHP/JS | execució economic_decision |
| Anul·lació factura actual | Sí | Sí | contrast PHP/JS | separar retorn real |
| Classes A/F | Sí | N/A | revisat | — |
| Seqüències A/F | Sí | N/A | revisat | — |
| Activitats per pàgina A/F | Sí | N/A | revisat | — |
| Audit gateway UC-006 | Sí objectiu | No acreditat | No | integrar |
| Preproducció | Sí criteris | No acreditat | No | executar i conservar evidència |

## 11. Mancances prioritzades

### P0 — bloquejants abans d’operar diners

1. **UC006-GAP-P0-01 · Completar el dret econòmic sobre `enrollment_fund_movement`.**  
   La base de ledger ja existeix; falta calcular/lockar disponibilitat i evitar que el mateix tram es transformi en refund i saldo, o dos saldos.

2. **UC006-GAP-P0-02 · Límit de devolució.**  
   `REFUND` no pot superar el que s’ha cobrat i continua disponible per retornar.

3. **UC006-GAP-P0-03 · Evidència externa.**  
   Diferenciar `RETURN_PENDING` de `REFUND CONFIRMED`.

4. **UC006-GAP-P0-04 · Titularitat.**  
   Retorn i saldo han d’anar al titular econòmic correcte; compensació ha de validar compatibilitat.

5. **UC006-GAP-P0-05 · Clau de negoci obligatòria per crear saldo.**  
   La idempotència tècnica ja existeix en aquesta branca; falta que l'orquestrador derivi/aporti una clau estable per dret/tram i el consumeixi una sola vegada.

6. **UC006-GAP-P0-06 · Orquestrador/endpoint autoritzat.**  
   Cap superfície llegada ha de decidir diners només amb camps DOM/llegats.

### P1 — traça i integració

7. `PaymentActionGateway` o equivalent per REQUESTED/terminal.
8. correlació/request id estable.
9. cross-channel dedup Redsys/manual.
10. sync llegat només post-COMMIT.
11. incidència automàtica en divergència.

### P2 — UX i operació

12. pantalla UC-006 final amb context complet;
13. indicadors RETURN_PENDING/CONFIRMED;
14. consulta del saldo i historial de consums;
15. explicació separada de l’efecte fiscal.

## 12. Proves d’acceptació requerides

| ID | Prova | Esperat |
| --- | --- | --- |
| UC006-T01 | baixa sense dret econòmic | NO_CHANGE |
| UC006-T02 | baixa amb 40 € retornables, encara no retornats | RETURN_PENDING, cap REFUND |
| UC006-T03 | retorn bancari confirmat 40 € | un REFUND |
| UC006-T04 | reintent mateix external operation id | mateix UUID_PAYMENT |
| UC006-T05 | mateix retorn registrat via Redsys i manual | un sol fet econòmic |
| UC006-T06 | demanar 120 € amb només 100 € retornables | bloqueig |
| UC006-T07 | factura grup, només una inscripció afectada | límit/traça per inscripció |
| UC006-T08 | mateixa clau de saldo + mateix payload | mateix UUID_CREDIT (prova afegida; CI pendent) |
| UC006-T08b | mateixa clau de saldo + payload diferent | 409/CONFLICT (prova afegida; CI pendent) |
| UC006-T09 | aplicar saldo a factura d’altre titular | bloqueig/revisió |
| UC006-T10 | compensació > saldo | bloqueig |
| UC006-T11 | compensació > deute | bloqueig |
| UC006-T12 | dues compensacions legítimes del mateix import | diferenciades per request/operació |
| UC006-T13 | fiscal rectificativa però cap retorn | cap REFUND automàtic |
| UC006-T14 | REFUND real però sync llegat falla | SIF es manté; només reintentar sync |
| UC006-T15 | concurrent refund/saldo sobre mateix dret | només un consumeix el dret |
| UC006-T16 | permís denegat | zero efecte |
| UC006-T17 | payload mateixa K però diferent | 409/CONFLICT |
| UC006-T18 | E2E des de baixa/canvi | resultat traçable de punta a punta |

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

Aquesta auditoria **no modifica la lògica PHP econòmica** perquè els gaps bloquejants requereixen una decisió explícita del model de dret econòmic i de la font de veritat del titular/origen. Afegir un `if` local a `ManualRefundService` o `CreditBalanceService` sense aquest contracte podria:
- bloquejar retorns legítims;
- permetre retorns incorrectes en factures agrupades;
- consumir saldo del titular equivocat;
- crear una falsa sensació de seguretat.

La feina de codi queda especificada amb contractes i proves perquè es pugui implementar sense inventar regles.

## 15. Criteri de tancament de l’auditoria

**Documentació:** tancable amb aquesta branca un cop l’índex i la fitxa integrada quedin actualitzats.  
**Implementació UC-006:** **NO tancada**.  
**Acceptació operativa:** **NO tancada**.  
**Motiu:** falten els P0 d’apartat 11 i evidència E2E/preproducció.
