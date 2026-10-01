# UC-013 · Evidència CI de proves USOC

**Data:** 30/09/2026  
**Workflow:** `SIF PHP MySQL tests`  
**Run acreditat principal:** `36663075293`  
**Commit provat:** `3a15cb99b613c16a3e64c5ab96985b6bf3f2b387`  
**Entorn:** GitHub Actions · PHP 8.4 · MySQL 8.4 · `sif_test` + `sif_legacy_test`

## Resultat global acreditat

- **666 proves passades**
- **0 proves fallides**
- Workflow: **SUCCESS**

Aquest run incorpora el nucli UC-013, la prova E2E de doble facturació, les dues superfícies d'intranet i el protocol durable de decisió legacy↔SIF `REQUESTED/COMMITTED/REVIEW_REQUIRED`.

## Prova E2E UC-013

PASS:

`Prisma\Sif\Tests\Integration\UsocEndToEndFlowTest::testStudentRetryEntityRetryPartialAndFinalPaymentReachSingleReconciledCase`

La prova executa sobre la BD SIF real de test:

1. notificació Redsys USOC validada;
2. emissió de factura + cobrament de la part alumne;
3. reintent de la part alumne i reutilització idempotent de la mateixa factura/payment;
4. persistència d'un únic `usoc_financing_case`;
5. emissió de factura de la part entitat amb checkpoint obligatori;
6. reintent equivalent de la factura entitat i reutilització idempotent;
7. cobrament entitat parcial de 10,00 €;
8. estat `ENTITY_PARTIAL`;
9. segon cobrament de 15,00 €;
10. estat final `FINANCING_RECONCILED`;
11. exactament 2 factures, 3 payment transactions, 3 allocations i 1 expedient USOC.

El legacy només és substituït per un spy de lectura d'inscripció/curs; `InvoiceService`, `PaymentService`, idempotència, repositoris SIF, allocations, checkpoint i reconciliació són els components reals de la suite.

## Proves UC-013 verificades

### Seguretat i flux legacy

- `LegacyUsocDiscountValidationSecurityTest::testLegacyDiscountValidationUsesPostCsrfAndEditPermission` — PASS.
- `LegacyIdpagAllocatorSecurityTest` — PASS.

### Payload i snapshot USOC

- `LegacyUsocInvoicePayloadBuilderTest` — PASS.
- `LegacyUsocSnapshotRepositoryTest` — PASS, inclosa selecció obligatòria per `ID_INSC`.

### Factura alumne

- `RedsysUsocInvoiceServiceTest` — PASS.
- Callback/notificació validada, import entitat obligatori, idempotència i checkpoint — PASS.
- Preflight, processor i preview Redsys USOC — PASS.

### Factura entitat

- `UsocEntityInvoiceServiceTest::testIssuesPendingEntityInvoiceFromExplicitBillingInput` — PASS.
- `testRequiresPersistedUsocCaseBeforeEntityInvoice` — PASS.
- `testRejectsEntityAmountMismatchBeforeIssuingInvoice` — PASS.
- Conflicte per import/receptor divergent — PASS.
- Billing explícit obligatori — PASS.
- UUID factura alumne obligatori — PASS.
- Import alumne divergent — PASS.
- Factura alumne d'una altra inscripció — PASS.

Això acredita que un checkpoint incoherent es rebutja **abans** de crear una factura entitat.

### Expedient i conciliació

- `UsocCaseReconcilerTest` — PASS.
- Estats PENDING / PARTIAL / PAID i `REVIEW_REQUIRED` — PASS.

### Cobrament entitat

- `UsocEntityPaymentServiceTest::testPartialAndCompleteEntityPaymentsReconcileUsocCase` — PASS.
- `testRejectsInvoiceOutsideUsocCaseBeforePayment` — PASS.
- `UsocEntityPaymentPreproductionScriptTest` — PASS.

### API interna i intranet

- `UsocInternalApiContractTest` — PASS.
- `UsocIntranetMenuContractTest` — PASS.
- `UsocIntranetUiContractTest::testStandaloneUsocIntranetUiUsesServerSideSignedClientAndCsrf` — PASS.

Queden coberts:
- API HMAC server-side;
- rols read/manage fail-closed;
- menú USOC controlat per rol;
- UI autònoma;
- panell contextual dins Consulta/Modifica alumne;
- secrets fora del navegador;
- feature flag `SIF_USOC_UI_ENABLED`.

## Runs previs rellevants

El run `36657971568` havia demostrat que totes les proves USOC presents en aquell SHA passaven, tot i que la suite global tenia una fallada aliena al UC-013.

Runs intermedis també van detectar regressions de contract tests i fallades d'altres dominis. Aquestes evidències queden superades, per a l'estat del UC-013, pel run verd `36660979100`.

## Decisió durable VALID_DESC

Al run `36663075293` consten PASS:

- `LegacyUsocDiscountValidationSecurityTest::testLegacyDiscountValidationUsesPostCsrfAndEditPermission`;
- `UsocInternalApiContractTest::testSignedUsocApiAndIntranetClientExposeExpectedActions`;
- `UsocValidationDecisionReconcileScriptTest::testReconcileScriptProcessesOnlyPersistedRequestedDecisions`;
- `UsocValidationDecisionServiceTest::testBeginCreatesRequestedAndRetryAfterLegacyMutationAutoCommitsWithoutReapply`;
- `testCompleteCommitsOnlyAfterLegacyReachedDesiredState`;
- `testConflictingLegacyDecisionMovesRequestToReviewRequired`;
- `testSameRequestIdCannotBeReusedForDifferentDecision`;
- `testNonUsocDiscountIsNotTrackedAndMayContinueLegacyFlow`;
- `testCommittedDecisionDetectsLaterLegacyDrift`;
- `testCompleteRejectsAnotherActor`.

Això acredita:

1. `REQUESTED` es persisteix abans de la mutació legacy USOC.
2. Si legacy ja va quedar modificat en un intent anterior, el retry reconcilia i retorna `should_apply_legacy=false`; no repeteix el mètode/correu.
3. Una decisió contradictòria passa a `REVIEW_REQUIRED`.
4. El mateix `requestId` no es pot reutilitzar amb un payload diferent.
5. Els descomptes no-USOC no queden dependents del SIF.
6. Una deriva posterior a `COMMITTED` es detecta com a conflicte.
7. El reconciliador CLI només processa checkpoints `REQUESTED`.
8. El preflight exigeix la taula `usoc_validation_decision`.

## Què es pot marcar ara com PROVAT

- `ID_INSC + IDPAG` inequívocs;
- factura alumne + payment Redsys;
- reintent idempotent alumne;
- checkpoint durable;
- obligatorietat del checkpoint abans de la factura entitat;
- vinculació factura alumne ↔ inscripció ↔ imports;
- factura entitat;
- reintent idempotent entitat;
- cobrament entitat parcial i complet;
- conciliació final;
- POST + CSRF + permisos de validació legacy;
- allocator `IDPAG` concurrent-safe als fluxos coberts;
- API interna signada;
- client intranet;
- UI autònoma i panell contextual;
- E2E de servei fins a `FINANCING_RECONCILED`.

## Encara no acreditat / pendent

- desplegament real a preproducció dels secrets i rols `SIF_INTERNAL_USOC_*`, `SIF_USOC_*_ROLES`, menú i feature flag;
- execució del preflight contra l'entorn de preproducció;
- prova navegador → intranet real → API SIF real amb dataset anonimitzat;
- circuit funcional/fiscal del curs gratuït USOC amb part alumne 0,00 €;
- decisió de negoci sobre 20 % públic vs 25 % històric;
- confirmació fiscal que la classificació EXEMPT del builder és correcta per totes les variants;
- canvi/baixa/rectificativa E2E amb dos pagadors.


## Lifecycle USOC · snapshot separat per pagador

**Run:** `36728324711`  
**Commit:** `43d6ad3ceec5daa3ed8dbb141d1acec940c95052`  
**Resultat:** **SUCCESS · 728 passed / 0 failed**

PASS específic:
- `UsocLifecycleGuardServiceTest::testLifecycleGuardReturnsSeparatedPayerSnapshot`

Aquesta prova acredita que, davant un expedient USOC amb dues factures:
- la part alumne conserva la seva factura, total, cobrat, retornat i net pagat;
- la part entitat conserva una factura i saldo independents;
- un cobrament parcial d'USOC no altera ni es barreja amb el cobrament de l'alumne;
- el lifecycle guard continua bloquejant el flux legacy i exposa `payer_snapshot` com a base per a UC-026/027.

En l'escenari provat:
- alumne: factura de 75,00 €, cobrada 75,00 €, net 75,00 €;
- entitat: factura de 25,00 €, cobrada parcialment 10,00 €, net 10,00 €;
- resultat del guard: `allowed=false`, `USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION`.


## Lifecycle · preview i bloqueig abans de confirmar

**Run:** `36729541064`  
**Resultat:** **SUCCESS · 728 passed / 0 failed**

PASS específic:
- `UsocLegacyLifecycleSecurityTest::testCourseChangeAndCancellationArePostCsrfAndFailClosedForUsoc`

Acredita:
- canvi de curs POST + CSRF + same-origin + permís;
- baixa POST + CSRF + same-origin + permís;
- guard USOC obligatori al backend;
- guard USOC també al preview de canvi de curs;
- preservació de `409/422/403` funcionals al preview;
- errors 5xx redactats;
- cap mutació legacy quan el SIF exigeix orquestració específica.


## Imports explícits USOC i curs gratuït

**Run:** `36730189405`  
**Resultat:** **SUCCESS · 730 passed / 0 failed**

PASS:
- `LegacyUsocInvoicePayloadBuilderTest::testUsesExplicitAmountsWithoutFixedUsocPercentage`
- `LegacyUsocInvoicePayloadBuilderTest::testRejectsZeroStudentAmountUntilFreeUsocCircuitIsDefined`

Acredita:
- el SIF no aplica un 20 % ni 25 % universal;
- imports alumne/entitat provenen del snapshot explícit;
- combinació 73/27 funciona correctament;
- part alumne 0,00 € queda fail-closed;
- el curs gratuït USOC continua requerint una decisió funcional/fiscal específica abans d'obrir un circuit nou.


## Preflight intranet USOC · feature flag

**Run:** `36730434161`  
**Resultat:** **SUCCESS · 730 passed / 0 failed**

PASS específic:
- `UsocIntranetUiContractTest::testStandaloneUsocIntranetUiUsesServerSideSignedClientAndCsrf`

Acredita que el contracte de preflight declara també `SIF_USOC_UI_ENABLED` juntament amb secrets, signed path i rols USOC. El que resta pendent és executar aquest preflight contra la configuració real de preproducció i conservar-ne l'evidència.


## Lifecycle USOC · planner per pagador

**Runs:** `36733404401` i `36733404387`  
**Resultat:** **744 passed / 0 failed** en tots dos workflows.

PASS:
- `UsocLifecyclePlanServiceTest::testCancellationPlanSeparatesPayersAndCapsRefundByRealFunds`
- `UsocLifecyclePlanServiceTest::testCourseChangePlanNeverRefundsEntityWhenEntityInvoiceNotIssued`

Acredita que:
- alumne i entitat es planifiquen com a pagadors independents;
- el màxim retornable de cada pagador no pot superar el seu `net_paid` real;
- una factura entitat encara no emesa produeix `invoice_action=NONE`, `economic_action=NONE` i `max_refundable=0.00`;
- el planner no executa rectificatives ni devolucions;
- `lifecycle_plan` queda disponible a l'API interna USOC per construir un flux executiu posterior sense tornar al legacy cec.

Continua pendent la capa **executiva fiscal/econòmica** de UC-026/027 per USOC: crear/autoritzar rectificatives, reemissions o refunds separats per factura i pagador segons el cas concret.


## Protocol durable de validació · evidència actualitzada

**Commit:** `b32f932bc841ee3362fbc578bd0ee11f5708498f`  
**Runs:** `36732949788` i `36732950018`  
**Resultat:** **742 passed / 0 failed** als dos workflows.

PASS:
- `UsocValidationDecisionBoundaryContractTest::testLegacyMutationIsStrictlyBetweenRequestedAndCommittedSifPhases`
- `UsocValidationDecisionBoundaryContractTest::testSignedUsocApiExposesTwoPhaseValidationActionsAndRecoveryComponents`
- `UsocValidationDecisionReconcileScriptTest::testReconcileScriptProcessesOnlyPersistedRequestedDecisions`
- totes les proves de `UsocValidationDecisionServiceTest`.

Acredita:
- `REQUESTED` abans de la mutació legacy;
- `COMMITTED` només després de contrastar el `VALID_DESC` real;
- retry idempotent per `requestId`;
- conflicte si el mateix `requestId` es reutilitza amb una decisió diferent;
- `REVIEW_REQUIRED` davant divergència;
- detecció de drift posterior;
- reconciliació batch dels `REQUESTED` persistents.

## Preflight USOC reforçat

**Commit:** `d23848ce1ed511f86e76092dbce683cf9bc7f0a5`  
**Runs:** `36734421729` i `36734421750`  
**Resultat:** **SUCCESS** als dos workflows.

En el run `36734421729`:
- `UsocIntranetPreflightScriptTest::testPreflightRequiresCoreTablesServicesApiAndLegacyConnectivity` — PASS.
- **748 passed / 0 failed**.

El preflight exigeix ara:
- taula `usoc_financing_case`;
- taula `usoc_validation_decision`;
- secrets i signed path de l'API interna;
- rols read/manage;
- DSN legacy i connectivitat real `SELECT 1`;
- càrrega de `UsocLifecyclePlanService`;
- càrrega de `UsocValidationDecisionService`;
- existència de `public/api/usoc/manage.php`;
- llistat explícit dels env vars necessaris d'intranet i SIF.

Això tanca la validació estàtica/CI del preflight. Encara cal executar-lo amb la **configuració real de preproducció** i conservar-ne el JSON d'evidència.


## Revalidació documental 02/10/2026

**Main inspeccionat:** `4ddf6ef72cc8ee21bd6c6563285bd6d7677b5df1`.

Els components UC-013 documentats continuen presents al main actual per inspecció estàtica. GitHub no retorna cap workflow associat directament a aquest SHA, de manera que aquest document manté les execucions positives anteriors com a **evidència versionada dels commits UC-013 corresponents**, però no afirma una nova suite completa executada sobre el main del 02/10.

Per tancar l'evidència d'execució actualitzada falten:
1. una nova suite CI sobre el main/branch actual;
2. preflight real de preproducció;
3. E2E navegador → intranet → API SIF → persistència;
4. execució específica de canvi de curs; la baixa ja té executor SIF.
