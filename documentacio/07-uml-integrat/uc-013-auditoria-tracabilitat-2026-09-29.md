# UC-013 · Auditoria detallada i matriu de traçabilitat

**Data:** 29/09/2026 · actualització d'implementació 30/09/2026 · revalidació exhaustiva 02/10/2026  
**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Branca inicial d'auditoria:** `audit/uc-013-usoc-2026-09-29`  
**Revalidació actual:** `audit/uc-013-completitud-2026-10-02` sobre `main@4ddf6ef72cc8ee21bd6c6563285bd6d7677b5df1`

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
| Canvi/baixa | intranet + preview + planner USOC | `LegacyUsocLifecycleGuard` / `lifecycle_guard` / `lifecycle_plan` | `UsocLifecycleGuardService` + `UsocLifecyclePlanService` | bloqueig fail-closed + snapshot i pla separat per pagador; retorn màxim limitat al net real cobrat | UC-013/026/027 | Sí | Sí | Sí | runs `36733404401` i `36733404387` · 744/744 |
| Classes ACTUAL/FINAL | documentació | n/a | `uc-013-classes-actual-final.md` | responsabilitats legacy/SIF/persistència/final residual | UC-013 | **Sí 02/10** | n/a | Sí | n/a |
| Seqüències ACTUAL/FINAL | documentació | n/a | `uc-013-sequencies-actual-final.md` | sol·licitud, validació, doble factura, cobrament, lifecycle | UC-013 | **Sí 02/10** | n/a | Sí | n/a |

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
- `documentacio/07-uml-integrat/uc-013-activitats-pagines-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-auditoria-tracabilitat-2026-09-29.md`

## 7. Estat de tancament

**DOC:** ampliada i específica.  
**IMP:** parcial.  
**VERIFICACIÓ ESTÀTICA:** sí.  
**TEST EXECUTAT:** acreditat en commits específics del UC-013; el SHA actual de main no té workflow associat en aquesta revisió.  
**PREPRODUCCIÓ:** no acreditada.  
**PRODUCCIÓ:** no acreditada.

Els P0 estructurals estan implementats. El run CI principal actual `36663075293` acaba **SUCCESS, 666 passed / 0 failed**, incloent el protocol durable de validació; el run `36660979100` ja havia acreditat l'E2E de doble facturació. El UC-013 encara no es marca TANCAT per desplegament/preproducció, execució fiscal específica de canvi/baixa amb dos pagadors i decisions funcionals/fiscals pendents; el guard i el planner d'aquests canvis ja estan implementats i provats.


### Evidència addicional · regla comercial no codificada al SIF

El run `36730189405` acaba **SUCCESS, 730 passed / 0 failed** i incorpora:
- `testUsesExplicitAmountsWithoutFixedUsocPercentage`: un snapshot 73,00 € alumne + 27,00 € entitat es construeix sense cap regla 20/25 hardcoded;
- `testRejectsZeroStudentAmountUntilFreeUsocCircuitIsDefined`: 0,00 € per la part alumne es rebutja amb validació fins que existeixi un circuit funcional/fiscal específic.

Per tant, la discrepància 20 %/25 % queda com a decisió de negoci, no com a constant tècnica del SIF.


## 8. Revalidació exhaustiva 02/10/2026

### Main contrastat

`4ddf6ef72cc8ee21bd6c6563285bd6d7677b5df1`.

### Resultat per capa

| Capa | Documentat | Implementat | Verificat 02/10 | Pendent |
|---|---:|---:|---:|---|
| Fitxa funcional | Sí | n/a | Sí | mantenir sincronitzada |
| UML integrat | Sí | n/a | Sí | mantenir sincronitzat |
| Classes ACTUAL/FINAL | **Sí, creat** | n/a | Sí | mantenir sincronitzat |
| Seqüències ACTUAL/FINAL | **Sí, creat** | n/a | Sí | mantenir sincronitzat |
| Activitats per pàgina/apartat | Sí | n/a | Sí | petites correccions d'estat aplicades |
| Factura/cobrament alumne | Sí | Sí | Sí | E2E entorn real |
| Checkpoint `usoc_financing_case` | Sí | Sí | Sí | evidència preproducció |
| Factura entitat | Sí | Sí | Sí | evidència preproducció |
| Cobrament/conciliació entitat | Sí | Sí | Sí | evidència preproducció |
| Decisió durable VALID_DESC | Sí | Sí | Sí | preflight/config real |
| UI/API USOC | Sí | Sí al repositori | Sí | desplegament + rols/secrets |
| Canvi/baixa | Sí | baixa: executor + guard + planner; canvi de curs: guard + planner | Sí | executor específic de canvi de curs; evidència CI/preproducció de la baixa |
| Curs gratuït alumne=0 | Sí com a variant | bloqueig fail-closed | Sí | decisió funcional/fiscal |
| IVA USOC | Sí | EXEMPT/E1 actual | Sí | validar cobertura fiscal |
| 20 % / 25 % | Sí com a discrepància | no hardcoded al SIF | Sí | decisió comercial |

### Evidència de proves

La carpeta de proves conserva evidència positiva en commits del UC-013. En aquesta revalidació, GitHub no retorna cap workflow associat directament al SHA actual de `main`, per tant:
- **VERIFICAT** = inspecció estàtica sobre main actual;
- **PROVAT** = evidència CI versionada de commits específics del UC-013;
- **PENDENT** = una nova execució de suite sobre el main actual i l'E2E navegador/preproducció.

### Mancances residuals reals

1. Executar `preflight-usoc-intranet.php` amb secrets, rols, DSN i feature flag de preproducció i conservar-ne l'evidència.
2. Fer E2E navegador → intranet → API SIF → BD legacy/SIF en preproducció.
3. Completar la capa executiva del canvi de curs (UC-026) per pagador/factura. La baixa (UC-027) ja té `UsocCancellationExecutionService`; cal acreditar-ne la suite i el flux de preproducció.
4. Decidir el circuit de curs gratuït USOC (`student_amount=0`).
5. Confirmar criteri comercial 20 %/25 % fora del nucli SIF.
6. Validar fiscalment `EXEMPT/E1` per totes les variants USOC que utilitzen aquest builder.


## 9. Delta de main · executor de baixa USOC

Entre `06ac56b9f...` i `4ddf6ef72...` s'han incorporat canvis directament relacionats amb UC-013/027:

- `sif/src/Service/UsocCancellationExecutionService.php`;
- ampliacions a `LegacyUsocLifecycleGuard.php`;
- `sifUsocLifecyclePreview.php`;
- `confirmacioBaixa_DonarBaixa.php`;
- proves `UsocCancellationExecutionServiceTest.php`.

### Classificació

| Bloc | Documentat | Implementat | Verificat | Provat |
|---|---:|---:|---:|---:|
| Pla baixa separat per pagador | Sí | Sí | Sí | evidència prèvia del planner |
| Executor baixa per pagador | Sí en aquesta revalidació | **Sí** | **Sí** | tests presents al main; run del SHA actual no acreditat |
| Rectificativa alumne/entitat | Sí | Sí via `ManualRectificationService` | Sí | test present |
| Refund alumne/entitat | Sí | Sí via `ManualRefundService` | Sí | test present |
| Límits contra `max_refundable` | Sí | Sí | Sí | test present |
| Accions diferides | Sí | Sí | Sí | test present al commit actual |
| Continuació cap a baixa legacy | Sí | Sí quan execution=COMPLETED | Sí | test present |
| Executor canvi de curs | Sí com a objectiu | **No localitzat** | n/a | pendent |


### UI i API de baixa · revalidació final del 02/10

El delta fins a `main@4ddf6ef72...` completa el cablejat de navegador:
`alumnes-usoc-lifecycle-preview.js`
→ `sifUsocLifecyclePreview.php`
→ `SifInternalUsocClient`
→ `/api/usoc/manage.php`
→ `UsocCancellationExecutionService`
→ rectificativa/refund/checkpoint
→ verificació `cancellation_execution_status`
→ continuació controlada de la baixa legacy.

Les proves contractuals i de seguretat també han estat ampliades al main, però **no hi ha workflow associat directament al SHA actual** en el moment de l'auditoria; per tant es classifiquen com a tests presents, no com a nova execució acreditada.
