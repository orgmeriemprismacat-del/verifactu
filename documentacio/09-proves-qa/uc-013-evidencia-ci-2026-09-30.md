# UC-013 · Evidència CI de proves USOC

**Data:** 30/09/2026  
**Workflow:** `SIF PHP MySQL tests`  
**Run acreditat principal:** `36660979100`  
**Commit provat:** `e455d9682223bf7d26efb7129edf6f616ae4fcf4`  
**Entorn:** GitHub Actions · PHP 8.4 · MySQL 8.4 · `sif_test` + `sif_legacy_test`

## Resultat global acreditat

- **646 proves passades**
- **0 proves fallides**
- Workflow: **SUCCESS**

Aquest run incorpora el nucli UC-013, la protecció de checkpoint abans d'emetre la factura entitat, les dues superfícies d'intranet i la prova E2E transversal.

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
- traça SIF durable en dues fases de la decisió legacy `VALID_DESC` (cal evitar falsa atomicitat entre BDs);
- circuit funcional/fiscal del curs gratuït USOC amb part alumne 0,00 €;
- decisió de negoci sobre 20 % públic vs 25 % històric;
- confirmació fiscal que la classificació EXEMPT del builder és correcta per totes les variants;
- canvi/baixa/rectificativa E2E amb dos pagadors.
