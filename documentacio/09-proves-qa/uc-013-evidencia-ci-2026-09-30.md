# UC-013 · Evidència CI de proves USOC

**Data:** 30/09/2026  
**Workflow:** `SIF PHP MySQL tests`  
**Run:** `36657971568`  
**Commit provat:** `dc83eb61bbc69dbcd6534b06525e49efb531e193`  
**Entorn:** GitHub Actions · PHP 8.4 · MySQL 8.4 · `sif_test` + `sif_legacy_test`

## Resultat global del run

- **610 proves passades**
- **1 prova fallida**
- La fallida global és aliena al UC-013:
  - `InvoiceQueryServiceTest::testViewReturnsNotFoundForUnknownInvoice`
  - esperava HTTP-like `404`, va obtenir `422`.

Per tant, el workflow global conclou `failure`, però les proves identificades del UC-013/USOC executades en aquest mateix run consten `PASS`.

## Proves UC-013 verificades en CI

### Seguretat i flux legacy

- `LegacyUsocDiscountValidationSecurityTest::testLegacyDiscountValidationUsesPostCsrfAndEditPermission` — PASS.

### Payload i snapshot USOC

- `LegacyUsocInvoicePayloadBuilderTest::testBuildsStudentPayloadFromValidatedUsocSnapshot` — PASS.
- `LegacyUsocInvoicePayloadBuilderTest::testComposesStudentPayloadWithValidatedRedsysNotificationAndPayment` — PASS.
- `LegacyUsocInvoicePayloadBuilderTest::testBuildsEntityPayloadWithExplicitUsocBillingData` — PASS.
- `LegacyUsocInvoicePayloadBuilderTest::testRejectsSnapshotsThatAreNotValidatedUsocDiscounts` — PASS.
- `LegacyUsocInvoicePayloadBuilderTest::testEntityPayloadRequiresExplicitBillingAmountAndStudentInvoice` — PASS.
- `LegacyUsocSnapshotRepositoryTest` — totes les proves USOC del run PASS, inclosa selecció explícita per `ID_INSC`.

### Factura alumne

- `RedsysUsocInvoiceServiceTest::testIssuesStudentInvoiceAndPaymentFromValidatedNotification` — PASS.
- Validacions de notificació/import USOC — PASS.
- Preflight, processor i preview Redsys USOC — PASS.

### Factura entitat

- `UsocEntityInvoiceServiceTest::testIssuesPendingEntityInvoiceFromExplicitBillingInput` — PASS.
- Conflicte per canvi d'import amb mateixa idempotència — PASS.
- Conflicte per canvi de receptor — PASS.
- Billing explícit obligatori — PASS.
- UUID factura alumne obligatori — PASS.
- Import de factura alumne divergent — PASS.
- Factura alumne d'una altra inscripció — PASS.

### Expedient i conciliació

- `UsocCaseReconcilerTest::testReconcilesEntityPendingPartialAndPaidStates` — PASS.
- `UsocCaseReconcilerTest::testRequiresReviewWhenStudentInvoiceIsNoLongerPaid` — PASS.

### Cobrament entitat

- `UsocEntityPaymentServiceTest::testPartialAndCompleteEntityPaymentsReconcileUsocCase` — PASS.
- `UsocEntityPaymentServiceTest::testRejectsInvoiceOutsideUsocCaseBeforePayment` — PASS.
- `UsocEntityPaymentPreproductionScriptTest` — PASS.

### API interna i UI

- `UsocInternalApiContractTest::testSignedUsocApiAndIntranetClientExposeExpectedActions` — PASS.
- `UsocIntranetUiContractTest::testStandaloneUsocIntranetUiUsesServerSideSignedClientAndCsrf` — PASS.

## Interpretació

A data d'aquest run es pot marcar com a **VERIFICAT EN CI**:

- selecció inequívoca `ID_INSC + IDPAG`;
- validació factura alumne↔inscripció/import;
- factura alumne USOC;
- factura entitat USOC;
- checkpoint persistent;
- reconciliació d'estats;
- cobrament parcial/complet de la part entitat;
- POST + CSRF + permisos de la validació legacy;
- contracte API interna signada USOC;
- contracte de la UI autònoma d'intranet.

No queda acreditat encara:

- desplegament/configuració real de secrets/rols a preproducció;
- navegació des del menú habitual de la intranet;
- prova manual amb dades reals anonimitzades o dataset de preproducció;
- decisió funcional de curs gratuït i regla percentual històrica;
- estat verd de la suite completa del repositori, perquè existeix una fallida no-UC-013.
