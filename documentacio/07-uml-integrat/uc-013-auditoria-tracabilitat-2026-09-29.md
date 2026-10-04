# UC-013 · Auditoria detallada i matriu de traçabilitat

**Data:** 29/09/2026 · actualització d'implementació 30/09/2026  
**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Branca d'auditoria:** `audit/uc-013-usoc-2026-09-29`

## 1. Llegenda d'estats

- **DOCUMENTAT**: existeix documentació específica del comportament.
- **IMPLEMENTAT**: existeix codi executable corresponent.
- **VERIFICAT**: contrast estàtic contra codi real del repositori.
- **PROVAT**: execució acreditada amb resultat.
- **PENDENT**: falta implementació, prova o decisió.

## 2. Matriu principal

| Acció | Pàgina/canal | JS/endpoint | PHP/servei | BD/efecte | UC relacionat | DOC | IMP | VER | TEST |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Consultar condicions USOC | web | `mostrarDescomptesUsoc.min.js` | `PaginaDescomptesUsoc.php` | lectura preus/descomptes | UC-013 | Sí | Sí | Sí | No |
| Sol·licitar USOC | web inscripció | `mostrarInscripcionsAfiliats.min.js` | `enviarInscripcioAfiliat.php` | `inscripcions`, TIPUS_DESC=4, VALID_DESC pendent | UC-019/013 | Sí | Sí | Sí | No |
| Comunicar a FEUSOC | web backend | endpoint alta | `enviarInscripcioAfiliat.php` | correu extern | UC-019 | Sí | Sí | Sí | No |
| Mostrar pendents | intranet | `alumnes-validar-descomptes.js` | `Intranet.php` | SELECT VALID_DESC=0 | UC-019 | Sí | Sí | Sí | No |
| Validar afiliació | intranet | POST `sendMsgValidatCurosDescomptes.php` + CSRF + requestId | `sendMsgValidatCurosDescomptes()` + `ROLS_EDITAR` | VALID_DESC=1 | UC-019/013 | Sí | Sí | Sí | test de regressió existent |
| Denegar afiliació | intranet | mateix endpoint | mateix mètode | VALID_DESC=2 i possible canvi A_PAGAR | UC-019 | Sí | Sí | Sí | No |
| Emetre/cobrar alumne | worker/SIF | callback UC-03 | `RedsysUsocInvoiceService` | factura + payment + allocation | UC-019a/013 | Sí | Sí | Sí | Tests existeixen |
| Persistir pendent entitat | SIF | resposta + checkpoint | `RedsysUsocInvoiceService` + `UsocFinancingCaseRepository` | `usoc_financing_case=PENDING_ENTITY_INVOICE` | UC-013 | Sí | Sí | Sí | VERIFICAT CI · run 36657971568 |
| Emetre factura entitat | UI autònoma + panell Consulta/Modifica alumne + API signada | `SifInternalUsocClient` / `/api/usoc/manage.php` | `UsocEntityInvoiceService` + `UsocFinancingCaseRepository::requireForEntityInvoice()` + `UsocStudentInvoiceLinkRepository` | valida checkpoint abans d'emetre; factura PENDING + `fact_rels` + `ENTITY_INVOICED` | UC-019b/013 | Sí | Sí | Sí | tests de servei/checkpoint i contractes UI PASS en CI |
| Cobrar entitat | UI autònoma + panell contextual + API signada + ruta preproducció | `register_entity_payment` / `process-usoc-entity-payment.php` | `UsocEntityPaymentService` → `PaymentService` → `UsocCaseReconciler` | payment/allocation + actualització immediata `usoc_financing_case` | UC-002/022/024/013 | Sí | Sí | Sí | VERIFICAT CI · run 36657971568 |
| Conciliar dues parts | CLI/preproducció | `reconcile-usoc-case.php` | `UsocCaseReconciler` | actualitza `usoc_financing_case` segons estats de factura i imports | UC-013 | Sí | Sí | Sí | Servei/reconciliació coberts per tests i E2E històric; execució CLI real de preproducció no acreditada |
| Canvi/baixa | intranet + preview/planner USOC | `LegacyUsocLifecycleGuard` / `lifecycle_guard` / `lifecycle_plan` | guard + planner | bloqueig fail-closed + snapshot/pla separat per pagador | UC-013/026/027 | Sí | Sí | Sí | 744/744 als runs previs |
| Executar baixa USOC | modal intranet + API signada | `execute_cancellation` + checkpoint sessió | `UsocCancellationExecutionService` → rectificativa/refund per pagador | `usoc_lifecycle_execution`, `operational_event`, `enrollment_cancellation_event`, factures/payment | UC-013/027/005/002 | Sí | Sí | Sí | **run `36942709607`: 838/838 servei**; contracte handoff **PASS · run 36943206570** |

## 3. Evidència específica

### A. Sol·licitud USOC
`enviarInscripcioAfiliat.php` força `TIPUS_DESC=4`, usa `anticipi-preu-usoc`, crea la inscripció i envia petició de confirmació a FEUSOC.

### B. Validació
`Intranet.php` conté:
- `cnsAlumnDescNoValidat` → `VALID_DESC=0`;
- `updValidDescByInsc`;
- `updValidDescByInscPreu`;
- lògica específica per `TIPUS_DESC==4`.

### C. Factura alumne
`RedsysUsocInvoiceService`:
- exigeix import entitat positiu;
- emet factura alumne;
- incorpora el cobrament Redsys;
- retorna `entity_invoice_pending`.

### D. Factura entitat
`UsocEntityInvoiceService`:
- exigeix dades fiscals explícites;
- emet sense payment;
- retorna `payment_registered=false`.

### E. Idempotència
`InvoiceService` reutilitza només després de:
`PayloadIdempotencyValidator::assertMatches(payload, IDEMPOTENCY_PAYLOAD_HASH)`.

Per tant el conflicte semàntic d'una mateixa clau amb payload diferent queda protegit al nucli.

## 4. Mancances tècniques prioritzades

### P0 — implementats en repositori el 30/09/2026
1. **Identitat de la inscripció — TANCAT EN CODI:** `LegacyUsocSnapshotRepository` exigeix `ID_INSC` i consulta `IDPAG + ID`; s'ha eliminat `ORDER BY ID LIMIT 1`.
2. **Relació factura alumne — TANCAT EN CODI:** `UsocStudentInvoiceLinkRepository` valida UUID, `ID_INSC`, `IDPAG`, canal/tipus Redsys USOC i total de la factura alumne.
3. **Checkpoint durable — IMPLEMENTAT:** `usoc_financing_case` + `UsocFinancingCaseRepository` persisteixen factura alumne, factura entitat, imports i estat.
4. **Conciliació — IMPLEMENTADA EN LA RUTA USOC:** `UsocEntityPaymentService` registra el cobrament i invoca `UsocCaseReconciler`; `reconcile-usoc-case.php` queda com a eina controlada de recuperació. L'endpoint genèric de pagaments no té aquest hook específic.

### P1
4. Validació legacy via POST + CSRF + `ROLS_EDITAR` — IMPLEMENTADA; traça persistent SIF en dues fases també IMPLEMENTADA amb `usoc_validation_decision`.
5. `IDPAG` legacy — IMPLEMENTAT allocator compartit amb named lock MySQL als fluxos actuals identificats.
6. Adaptador/pantalla final — IMPLEMENTAT EN REPOSITORI: pantalla autònoma + panell contextual a Consulta/Modifica alumne, sobre API interna HMAC i `capabilities.manage`.
7. Menú implementat de forma fail-closed a `mostrarSideBarMenu.php` amb `SIF_USOC_MENU_ROLES`. Pendent executar en preproducció el preflight SIF i `codi-drive/intranet-actual/preflight-sif-usoc-runtime.php` al host intranet per acreditar URL/path HMAC, key/secret presents, rols, feature flag i fitxers desplegats.
8. E2E de servei amb reintent alumne, reintent entitat, pagament parcial i pagament complet — **PROVAT CI** al run `36660979100`; resta E2E navegador/preproducció i canvi/baixa.

### P2 · Traça durable de la decisió legacy — IMPLEMENTADA EN REPOSITORI

La decisió `VALID_DESC=0→1/2` afecta la BD legacy però ha de quedar auditable també al SIF. No s'ha d'afegir una simple inserció posterior a `sif_audit_event`, perquè això aparentaria una atomicitat entre dues BDs que no existeix.

Protocol definit:

1. **REQUESTED al SIF abans de mutar legacy**
   - actor autenticat del backend;
   - `ID_INSC`;
   - decisió sol·licitada (APPROVE/REJECT);
   - `requestId` estable del gest de secretaria;
   - `correlationId = USOC|VALIDATION|ID_INSC:<id>`;
   - cap factura ni cobrament.
2. Si el SIF no pot persistir `REQUESTED`, la mutació legacy no s'executa.
3. El legacy aplica `VALID_DESC=1/2` i la seva comunicació.
4. **COMMITTED al SIF** amb la decisió efectivament aplicada i hash de l'estat.
5. Si el pas 4 falla, el `REQUESTED` persistent permet detectar/reconciliar l'operació sense repetir cegament el correu o la mutació legacy.
6. El reconciliador ha de poder llegir `REQUESTED` sense `COMMITTED`, contrastar el `VALID_DESC` legacy real i completar o marcar `REVIEW_REQUIRED`.
7. Un reintent amb mateix `requestId` i mateixa decisió és idempotent; mateixa identitat amb decisió contradictòria requereix un nou esdeveniment auditat, mai sobreescriptura.

Aquesta peça està **IMPLEMENTADA I PROVADA EN CI** mitjançant `UsocValidationDecisionService`, `UsocValidationDecisionRepository`, la migració `000031`, les accions internes `begin_validation_decision` / `complete_validation_decision`, la classificació local `LegacyDiscountValidationLookup` i el reconciliador `reconcile-usoc-validation-decisions.php`. Run `36663075293`: **666 passed, 0 failed**, inclosa la prova de deriva post-commit.

### Decisió funcional
9. Variant curs gratuït USOC / alumne=0.
10. Consolidar percentatge comercial vigent.
11. Confirmar classificació fiscal de totes les variants que utilitzen el builder EXEMPT.

## 5. Matriu de proves

| ID | Escenari | Esperat | Estat |
| --- | --- | --- | --- |
| US13-01 | VALID_DESC=0 | no emissió USOC | TEST EXISTENT i evidència CI històrica; revalidació del SHA actual en cua |
| US13-02 | validació positiva | snapshot coherent | TEST EXISTENT + protocol `UsocValidationDecisionServiceTest` amb evidència CI històrica; revalidació del SHA actual en cua |
| US13-03 | callback alumne duplicat | mateixa factura/payment | TEST EXISTENT; evidència E2E històrica del flux; revalidació del SHA actual en cua |
| US13-04 | factura entitat repetida equivalent | reús | TEST EXISTENT |
| US13-05 | mateixa clau, amount diferent | CONFLICT | TEST AFEGIT EN AQUESTA BRANCA |
| US13-06 | mateixa clau, NIF diferent | CONFLICT | TEST AFEGIT EN AQUESTA BRANCA |
| US13-07 | UUID alumne aliè | bloqueig | TEST AFEGIT · CODI IMPLEMENTAT |
| US13-08 | IDPAG ambigu | `ID_INSC` obligatori; no fallback | TEST AFEGIT · CODI IMPLEMENTAT |
| US13-09 | factura entitat sense ingrés | PENDING, 0 payments | TEST EXISTENT |
| US13-10 | cobrament entitat parcial real via PaymentService | `ENTITY_PARTIAL` | PASS CI · run 36657971568 |
| US13-11 | reintents alumne/entitat + 10 € + 15 € sobre factura entitat de 25 € | `FINANCING_RECONCILED` | **PROVAT E2E CI · run 36660979100** |
| US13-12 | alumne=0 | circuit especial o bloqueig explícit | **BLOQUEIG PROVAT** · `testRejectsZeroStudentAmountUntilFreeUsocCircuitIsDefined`, run `36730189405`; decisió funcional/fiscal pendent |

## 6. Fitxers del paquet UC-013

- `documentacio/06-fitxes-funcionals/uc-013.md`
- `documentacio/07-uml-integrat/uc-013-orquestrar-doble-facturacio-usoc.md`
- `documentacio/07-uml-integrat/uc-013-classes-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-sequencies-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-canvi-curs-usoc-contracte-final.md`
- `documentacio/07-uml-integrat/uc-013-activitats-pagines-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-auditoria-tracabilitat-2026-09-29.md`

## 7. Estat de tancament

**DOC:** ampliada i específica.  
**IMP:** completa al repositori per al flux UC-013 definit, amb variants explícitament fail-closed.  
**VERIFICACIÓ ESTÀTICA:** sí.  
**TEST EXECUTAT:** cobertura automatitzada i evidència CI històrica existents; el capçal anterior del PR #152 va donar 1017 PASS / 2 FAIL per dos asserts intercanviats del test de handoff, corregits al nou capçal. CI del SHA actual en cua.  
**PREPRODUCCIÓ:** no acreditada.  
**PRODUCCIÓ:** no acreditada.

Els P0 estructurals estan implementats. L'E2E històric acredita la doble facturació i la decisió durable `VALID_DESC`; la baixa USOC també disposa d'executor i handoff. El canvi de curs queda implementat en el PR reconciliat #152 amb pricing server-side, preparation, reserva/binding, executor, reemissió, compensacions i handoff idempotent. El UC-013 no es considera acceptat operativament fins completar CI del PR reconciliat, preproducció/navegador i les decisions funcionals/fiscals explícitament pendents.


### Evidència addicional · regla comercial no codificada al SIF

El run `36730189405` acaba **SUCCESS, 730 passed / 0 failed** i incorpora:
- `testUsesExplicitAmountsWithoutFixedUsocPercentage`: un snapshot 73,00 € alumne + 27,00 € entitat es construeix sense cap regla 20/25 hardcoded;
- `testRejectsZeroStudentAmountUntilFreeUsocCircuitIsDefined`: 0,00 € per la part alumne es rebutja amb validació fins que existeixi un circuit funcional/fiscal específic.

Per tant, la discrepància 20 %/25 % queda com a decisió de negoci, no com a constant tècnica del SIF.


## Canvi de curs USOC · mancança executable revalidada 02/10/2026

**ID:** `UC13-GAP-COURSE-EXEC`

El canvi de curs USOC està protegit, però no és encara executable end-to-end.

### Implementat

- `LegacyUsocLifecycleGuard` detecta `TIPUS_DESC=4`.
- `lifecycle_guard` i `lifecycle_plan` separen alumne i entitat.
- `UsocLifecyclePlanService` retorna `RECTIFY_BEFORE_REISSUE` per cada factura existent.
- `CourseChangePreviewService` i `CourseChangeImpactClassifier` existeixen per al canvi de curs genèric.
- `realitzarCanviCurs_CanviCurs.php` és POST + CSRF + same-origin + permís.
- El flux USOC queda fail-closed abans de mutar legacy quan existeix un expedient amb dues parts.

### Implementació executiva actual

El canvi de curs USOC ja disposa de:

1. contracte d'entrada i preparation durable `REQUESTED`;
2. pricing server-side i split destí alumne/entitat;
3. reserva durable de la inscripció destí legacy;
4. binding del destí al SIF;
5. idempotència específica `OPERATION=COURSE_CHANGE`;
6. materialització exacta de la mutació legacy reservada;
7. verificació durable `LEGACY_COMPLETED` i `source_closed=true` abans d'efectes fiscals;
8. rectificació separada de cada factura origen;
9. reemissió separada de les factures destí;
10. compensacions de fons per pagador, sense creuament;
11. recuperació/retry després de pèrdua de sessió;
12. proves específiques de preparation, binding, executor, handoff, preview, retries i fail-closed.

El pendent és **operatiu/acceptació**, no d'arquitectura executiva: CI final del PR reconciliat #152, preproducció/navegador, configuració real, resolució de follow-up/excessos i decisions funcionals/fiscals encara bloquejades.

### Regla funcional revalidada

La incertesa inicial sobre conservar o recalcular l'aportació ha quedat **resolta pel contrast amb el legacy real**:

- el canvi conserva `TIPUS_DESC=4` i `VALID_DESC=1`;
- el preu USOC es **recalcula sobre el curs/edició destí**;
- la part entitat és la diferència entre preu estàndard destí i preu USOC/alumne destí;
- les despeses de gestió corresponen a l'alumne;
- si la regla USOC destí és absent/ambigua o la variant és alumne=0, el cas queda `REVIEW_REQUIRED`.

La fórmula, la materialització fiscal/econòmica i el handoff legacy ja estan implementats al PR reconciliat #152. El pendent és operatiu: CI reconciliat, preproducció/navegador, configuració real i decisions de negoci/fiscals encara fail-closed.


## Canvi de curs USOC · REGLA DESTÍ REVALIDADA 02/10/2026

El contrast amb `Intranet.php` i el builder SIF permet tancar part de la incertesa de `UC13-GAP-COURSE-EXEC`:

- **VALID_DESC:** el legacy el conserva al nou registre.
- **TIPUS_DESC:** el legacy el conserva; USOC continua essent tipus 4.
- **Preu destí:** es recalcula contra la regla de descompte del nou curs/edició.
- **Part entitat:** al model SIF existent `entity_amount` és la diferència/descompte aplicada a la línia alumne.
- **Despeses de gestió:** s'afegeixen a la part alumne.
- **Fons reals:** no es poden copiar des de `PAGAMENT`; existeix infraestructura `COMPENSATION_ALLOCATION` per atribuir un CHARGE confirmat a la inscripció destí.
- **Excessos:** poden requerir refund o `credit_balance`, sempre per pagador.

Aquesta evolució queda reconciliada en el PR #152: resolver, fund planner, pricing server-side, preview, preparation, reserva/binding, executor d'efectes i handoff estan implementats.


## Delta implementació 02/10/2026 · resolver d'imports de canvi de curs

`UsocCourseChangeTargetResolver` — **IMPLEMENTAT**:

- rep preu estàndard destí, preu USOC/alumne destí i despeses de gestió;
- calcula en cèntims, sense floats;
- `entity = standard - student`;
- `student_total = student + management_fee`;
- valida `student <= standard`;
- determina si cal factura entitat;
- no emet factures ni mou diners.

Proves afegides a `UsocCourseChangeTargetResolverTest` per split 80/20 + fee, decimals amb coma, import alumne superior/al mateix nivell que el base, imports malformats i `target_student_course_amount=0`. Tant alumne=0 com entitat=0 continuen fail-closed perquè no formen part del contracte USOC executiu actual.


## Delta implementació 02/10/2026 · pla econòmic de canvi de curs

`UsocCourseChangeFundPlanService` — **IMPLEMENTAT**:

- consumeix el `lifecycle_plan` separat alumne/entitat;
- consumeix els totals destí resolts;
- per cada pagador calcula:
  - `source_net_paid`;
  - `target_obligation`;
  - `compensate_amount = min(net_paid, target)`;
  - `amount_due`;
  - `excess_amount`;
- mai compensa més fons que els realment cobrats;
- mai compensa més que l'obligació destí;
- qualsevol excés queda marcat per resolució explícita;
- no emet factures, no crea refunds i no mou diners.

Proves afegides a `UsocCourseChangeFundPlanServiceTest` per parcial, excés separat per pagador, entitat origen sense factura/cobrament i rebuig d'un pla que no sigui `course_change`.


## Delta implementació 02/10/2026 · preview server-side canvi de curs

**IMPLEMENTAT:**

- `LegacyUsocCourseChangePricingResolver` + font MySQL:
  - exigeix origen `TIPUS_DESC=4 / VALID_DESC=1`;
  - exigeix un únic curs/jornada destí;
  - exigeix un únic preu estàndard actiu;
  - exigeix una única regla USOC tipus 4;
  - rebutja alumne=0 i entitat=0;
  - les despeses de gestió només s'apliquen a `change_number=4` i es calculen sobre les **hores de l'edició origen**.
- `UsocCourseChangePreviewService`:
  - combina lifecycle plan + target resolver + fund planner;
  - no crea factures, rectificatives, payments, refunds ni compensacions.
- API USOC signada:
  - nova acció `course_change_preview`.
- Intranet:
  - `sifUsocCourseChangePreview.php` resol pricing server-side i crida l'API signada;
  - el JS genèric cedeix els USOC validats;
  - `alumnes-usoc-lifecycle-preview.js` mostra alumne/entitat, compensable, pendent i excés;
  - després del preview, el flux prepara el checkpoint, reserva/binda el destí i només permet la mutació legacy exacta associada a aquell `requestId`.

**IMPLEMENTAT:** executor d'efectes, reemissió destí, materialització de compensacions, checkpoint/handoff i retry idempotent. Els excessos no es resolen automàticament: queden marcats per decisió explícita.

## Delta implementació 02/10/2026 · checkpoint COURSE_CHANGE

**IMPLEMENTAT EN BRANCA:**

- migració `2026_10_02_000033_allow_usoc_course_change_execution.sql`:
  - amplia `chk_usoc_lifecycle_operation`;
  - admet `CANCELLATION` i `COURSE_CHANGE`.
- `UsocCourseChangeExecutionPreparationService`:
  - valida identitat/requestId;
  - consumeix el preview server-side;
  - congela target + lifecycle/target/fund plan;
  - persisteix `usoc_lifecycle_execution.STATE=REQUESTED`;
  - mateix `requestId` + mateix payload → reutilització;
  - mateix `requestId` + payload diferent → `CONFLICT`;
  - no reutilitza estats diferents de `REQUESTED`;
  - `effects_applied=false`.
- prova `UsocCourseChangeExecutionPreparationServiceTest`:
  - checkpoint inicial;
  - retry idempotent;
  - conflicte de payload;
  - cap factura/payment addicional.

**IMPLEMENTAT I ENDURIT 03/10/2026:** `UsocCourseChangeExecutionService` ja no accepta només `DESTINATION_RESERVED`. Abans de qualsevol efecte exigeix `REQUESTED + RESULT_JSON.phase=LEGACY_COMPLETED + legacy_handoff_completed=true + source_closed=true`. `UsocCourseChangeLegacyHandoffService` contrasta directament la BD legacy i persisteix aquesta fase; estat parcial/incoherent → `REVIEW_REQUIRED` durable.


## Revalidació 03/10/2026 · origen PR #120 · substituït pel PR #152

La branca anterior del UC-013 havia quedat divergit del `main`. El paquet es va reconstruir al PR #152 i, el 04/10/2026, s'ha tornat a reconciliar sobre `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757` després que el repositori avancés de nou. La reconstrucció conserva els 53 fitxers UC-013 i no introdueix solapaments amb els canvis intermedis de `main`.

### Estat contrastat

- **DOCUMENTAT:** fitxa, UML integrat, classes, seqüències, activitats, contracte de canvi de curs i traçabilitat.
- **IMPLEMENTAT:** doble facturació, cobrament/reconciliació, validació durable, baixa executable i canvi de curs executable.
- **CANVI DE CURS IMPLEMENTAT:** pricing server-side, target resolver, fund planner, preview, preparation REQUESTED, reserva legacy, binding SIF, mutació legacy reservada, executor, rectificatives origen, factures destí, compensacions, reconciliació i `COMPLETED`.
- **IDEMPOTÈNCIA:** `requestId`, binding immutable de destí, claus estables per efecte i retry de `COMPLETED`.
- **HANDOFF:** el legacy es materialitza una sola vegada. La sessió `legacy_completed` és només una protecció immediata; el recovery durable depèn de la reserva marcada a legacy i del checkpoint SIF `LEGACY_COMPLETED`, verificat contra origen `INSC CURS='C'`, `PAGAMENT=0`, `DATA_BAIXA` i destí coherent. Si es perd la sessió, es recupera sense repetir la mutació.
- **FAIL-CLOSED:** alumne=0, entitat=0, pricing ambigu/incoherent, evidència fiscal incompleta i payload divergent.
- **PENDENT OPERATIU:** CI final del PR reconciliat, preproducció/navegador, secrets/rols/configuració real, resolució d'excessos i validacions 20/25 % + EXEMPT/E1.


## Preflight operatiu canvi de curs · 03/10/2026

`sif/scripts/preflight-usoc-course-change.php` — **IMPLEMENTAT · READ-ONLY**.

Comprova abans de provar el flux en preproducció:

- entorn diferent de producció;
- connectivitat SIF;
- `usoc_financing_case`, `usoc_lifecycle_execution`, `enrollment_fund_movement`, factures, rectificatives, payments i `operational_event`;
- que `usoc_lifecycle_execution.OPERATION` admeti `COURSE_CHANGE`;
- `fiscal_chain_state` sembrat;
- connectivitat i taules necessàries de la BD legacy web;
- connectivitat i `params` de la BD legacy intranet;
- HMAC/API interna USOC;
- rols de gestió;
- billing mínim de l'entitat USOC;
- classes del resolver, fund planner, preview, preparation, binding i executor;
- endpoints i fitxers de wiring intranet.

La prova `UsocCourseChangePreflightScriptTest` força que el preflight es mantingui **sense efectes**: no pot emetre factura, registrar pagament ni executar la mutació legacy.

**PENDENT D'EVIDÈNCIA:** executar-lo a l'entorn de preproducció real i conservar JSON, timestamp, SHA desplegat i configuració no secreta associada.


### Troballa d'auditoria · handoff només en sessió → TANCADA EN CODI 03/10/2026

**Problema detectat durant la fase del PR #120 i corregit abans de la reconciliació #152:** la primera versió reservava/bindava el destí de manera durable, però després de la mutació legacy només persistia `legacy_completed=true` a `$_SESSION`. Una pèrdua de sessió entre legacy i SIF podia impedir reconstruir de manera fiable que el tram acadèmic ja s'havia materialitzat; a més, l'executor acceptava `DESTINATION_RESERVED` sense verificar la BD legacy.

**Correcció implementada:**

- `UsocCourseChangeLegacyHandoffService` llegeix directament origen i destí legacy;
- origen complet exigeix `INSC CURS='C'`, `PAGAMENT=0` i `DATA_BAIXA`;
- destí exigeix ID/IDPAG/any/mes/curs/import/marker/TIPUS_DESC/VALID_DESC/status coherents;
- persisteix `LEGACY_COMPLETED` dins `usoc_lifecycle_execution.RESULT_JSON`;
- `UsocCourseChangeExecutionService` rebutja qualsevol efecte abans d'aquest checkpoint;
- una reserva existent es pot recuperar encara que l'origen ja sigui `C`; una reserva nova no;
- un rebind no degrada `LEGACY_COMPLETED` a `DESTINATION_RESERVED`;
- `REVIEW_REQUIRED` es commiteja abans de retornar el conflicte;
- retry d'un SIF ja `COMPLETED` → èxit idempotent, sense repetir legacy/fiscal.

**Proves afegides/actualitzades:** servei de handoff, recuperació de reserva amb origen tancat, no executar abans del checkpoint, rebind després de `LEGACY_COMPLETED`, contracte API/UI i retry completat.


## Revalidació 04/10/2026 · capçal reconciliat i regressió de test

**Base:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`  
**PR:** #152  
**Abast reconstruït:** 53 fitxers UC-013, sense solapament amb els 22 commits que havien avançat `main` respecte de la base anterior.

### Troballa CI

El capçal anterior `0ad09aff557e7da0c2034fb45ed29105ea41df11` va fallar en tres workflows que executaven la mateixa suite amb **1017 PASS / 2 FAIL**. Les dues fallades eren exclusivament:

1. `testPendingLegacySourceKeepsExecutionReadyWithoutApplyingEffects`;
2. `testDestinationMismatchFailsBeforeAdvancingCheckpoint`.

El contrast amb `UsocCourseChangeLegacyHandoffService` i amb el contracte funcional demostra que el servei és coherent:

- origen pendent (`0/1/M`) → continua preparat per al tram legacy, `STATE=REQUESTED`, `phase=DESTINATION_RESERVED`, sense efectes;
- divergència del destí reservat → `STATE=REVIEW_REQUIRED`, `REVIEW_REASON=LEGACY_DESTINATION_MISMATCH`, `phase=LEGACY_REVIEW_REQUIRED`.

Els asserts del test estaven invertits i s'han corregit. No s'ha relaxat el fail-closed ni s'ha modificat la semàntica del servei.

### Estat de verificació

- **DOCUMENTAT:** complet.
- **IMPLEMENTAT:** complet per l'abast executiu UC-013 definit.
- **VERIFICAT ESTÀTICAMENT:** sí, contra PHP/JS/SIF real.
- **VERIFICAT AUTOMÀTICAMENT:** evidència històrica sí; revalidació del SHA actual en cua.
- **PREPRODUCCIÓ/NAVEGADOR:** pendent.
- **PRODUCCIÓ:** no acreditada.


## Hardening monetari exacte · 04/10/2026

**ID:** `UC13-GAP-MONEY-FLOAT`  
**Severitat:** P0 integritat econòmica/fiscal  
**Estat:** TANCAT EN CODI · CI PENDENT

Durant la revalidació del flux complet s'han localitzat conversions `float` en la càrrega de snapshot legacy, payloads de factura, serveis Redsys/entitat, checkpoint financer, enllaç de factura alumne, conciliador, moviments de fons, reserva legacy i part de l'executor de canvi de curs.

S'ha introduït `DecimalAmount` i s'han substituït aquestes comparacions/càlculs per cèntims enters. El legacy que no carrega l'autoload SIF utilitza el mateix patró decimal estricte localment.

Invariants reforçats:

- màxim dues posicions decimals;
- cap notació científica;
- cap arrodoniment silenciós;
- `import_base - discount_amount = student_amount` exactament;
- reserva i handoff comparen el mateix `target_student_total` exactament;
- checkpoints i conciliació comparen imports normalitzats al cèntim.

Proves noves:
- `25.005` → rebuig, no `25.01`;
- base/descompte incoherents → 422;
- parser decimal rebutja notació científica i valors malformats;
- contract-tests verifiquen absència de float al store/handoff.


### Coherència del snapshot monetari

Afegida garantia que les metadades `usoc.student_amount/entity_amount` són canòniques i coherents amb els imports fiscals. S'han afegit proves de conflicte per snapshot entitat ≠ descompte alumne i snapshot entitat ≠ factura entitat.


## Troballa preflight multi-host · 04/10/2026

**ID:** `UC13-GAP-PREFLIGHT-INTRANET-RUNTIME`  
**Severitat:** P1 operativa/seguretat  
**Estat:** TANCAT EN CODI · EXECUCIÓ PREPRODUCCIÓ PENDENT

Els preflights SIF enumeraven les variables que necessita la intranet però no podien acreditar el seu valor al host `intranet-pre`. S'ha creat un preflight CLI específic d'intranet que valida configuració real i, especialment, que la URL HTTP utilitzada pel client i el path HMAC signat siguin el mateix path.

L'evidència d'acceptació haurà d'incloure:
- JSON del preflight SIF;
- JSON del preflight runtime intranet;
- SHA desplegat als dos hosts;
- timestamp;
- sense secrets en clar.


## Troballa aïllament preproducció · 04/10/2026

**ID:** `UC13-GAP-PREPROD-ASSET-HOST`  
**Severitat:** P1 operativa  
**Estat:** TANCAT EN CODI · CI/E2E PENDENTS

Les pàgines UC-013 podien carregar JavaScript de `intranet.prisma.cat` mentre s'executaven a `intranet-pre`. Les dependències UC-013 s'han passat a same-origin i s'han afegit contract-tests. Això evita validar una preproducció que en realitat executi assets de producció.


## Troballa compatibilitat cross-host · 04/10/2026

**ID:** `UC13-GAP-CROSS-HOST-CONFIG`  
**Severitat:** P1 operativa/seguretat  
**Estat:** TANCAT EN CODI · EVIDÈNCIA PREPRODUCCIÓ PENDENT

Dos preflights verds per separat no acreditaven que la intranet i el SIF compartissin el mateix key-id/path ni que els rols amb menú fossin realment rols de gestió al SIF.

S'ha afegit `sif/scripts/compare-usoc-preflight-evidence.php`, que rep els dos JSON i valida, sense secrets:
- scope i `ok` dels dos preflights;
- key-id igual;
- path signat igual;
- `menu_roles ⊆ manage_roles`;
- feature flag intranet actiu.


### Hardening d'entorn i feature flag

El preflight runtime intranet exigeix `SIF_INTERNAL_USOC_EXPECTED_HOST` i compara aquest valor amb l'host de `SIF_INTERNAL_USOC_URL`. El menú USOC exigeix alhora `SIF_USOC_UI_ENABLED=1` i un rol autoritzat, amb normalització de rols a majúscules. Això tanca la divergència menú/pantalla i el risc de target cross-environment.
