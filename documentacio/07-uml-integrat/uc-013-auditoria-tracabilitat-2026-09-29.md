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
| Conciliar dues parts | CLI/preproducció | `reconcile-usoc-case.php` | `UsocCaseReconciler` | actualitza `usoc_financing_case` segons estats de factura i imports | UC-013 | Sí | Sí | Sí | Test afegit, execució no acreditada |
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
7. Menú implementat de forma fail-closed a `mostrarSideBarMenu.php` amb `SIF_USOC_MENU_ROLES`. Pendent validar configuració/rols/secrets amb `preflight-usoc-intranet.php` i desplegament real.
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
| US13-01 | VALID_DESC=0 | no emissió USOC | PENDENT EXECUCIÓ |
| US13-02 | validació positiva | snapshot coherent | PENDENT EXECUCIÓ |
| US13-03 | callback alumne duplicat | mateixa factura/payment | TEST EXISTENT, EXECUCIÓ NO ACREDITADA |
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
**IMP:** parcial.  
**VERIFICACIÓ ESTÀTICA:** sí.  
**TEST EXECUTAT:** no acreditat.  
**PREPRODUCCIÓ:** no acreditada.  
**PRODUCCIÓ:** no acreditada.

Els P0 estructurals estan implementats. El run `36660979100` acredita l'E2E de doble facturació; la decisió durable `VALID_DESC` també està implementada/provada. Per la baixa USOC, el run `36942709607` acaba **SUCCESS, 838 passed / 0 failed** i acredita l'executor per pagador, límits de refund, retry idempotent i decisions diferides. El UC-013 encara no es marca TANCAT perquè falta acreditar el contracte final de handoff UI→SIF→legacy, provar-lo en preproducció/navegador, implementar l'execució de canvi de curs i resoldre decisions funcionals/fiscals pendents.


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

### No implementat

No existeix cap servei equivalent a `UsocCancellationExecutionService` per a `course_change`.

Falten, com a mínim:

1. contracte d'entrada per congelar curs origen i curs destí;
2. import destí separat per **alumne** i **entitat USOC**;
3. regla sobre manteniment/recalcul del finançament USOC al curs destí;
4. rectificació de cada factura origen per separat;
5. reemissió de cada factura destí amb receptor i import propis;
6. tractament separat de diferències a cobrar, excessos i refunds per pagador;
7. checkpoint idempotent `OPERATION=COURSE_CHANGE`;
8. handoff cap a la mutació legacy només quan l'execució SIF sigui coherent;
9. reconciliació posterior del nou expedient i relacions origen/destí;
10. proves de retry, fallada parcial, factura entitat no emesa, entitat parcialment cobrada i imports destí diferents.

### Bloqueig funcional necessari abans d'implementar

El repositori no defineix encara si el canvi de curs:
- conserva exactament la mateixa aportació USOC;
- recalcula el percentatge/import USOC sobre el nou curs;
- requereix nova validació de l'entitat;
- o pot deixar part de l'import com a saldo/pendent independent.

Aquesta decisió afecta directament les dues factures noves. Per tant el comportament actual correcte és **bloquejar la mutació legacy** i no inventar una reemissió automàtica.


## Canvi de curs USOC · REGLA DESTÍ REVALIDADA 02/10/2026

El contrast amb `Intranet.php` i el builder SIF permet tancar part de la incertesa de `UC13-GAP-COURSE-EXEC`:

- **VALID_DESC:** el legacy el conserva al nou registre.
- **TIPUS_DESC:** el legacy el conserva; USOC continua essent tipus 4.
- **Preu destí:** es recalcula contra la regla de descompte del nou curs/edició.
- **Part entitat:** al model SIF existent `entity_amount` és la diferència/descompte aplicada a la línia alumne.
- **Despeses de gestió:** s'afegeixen a la part alumne.
- **Fons reals:** no es poden copiar des de `PAGAMENT`; existeix infraestructura `COMPENSATION_ALLOCATION` per atribuir un CHARGE confirmat a la inscripció destí.
- **Excessos:** poden requerir refund o `credit_balance`, sempre per pagador.

Això redueix el pendent del canvi de curs a la implementació del resolver destí + executor + handoff, no a una decisió oberta sobre si s'ha de conservar l'import antic.
