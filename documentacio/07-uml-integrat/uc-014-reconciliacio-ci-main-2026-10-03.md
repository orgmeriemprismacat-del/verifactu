# UC-014 — Reconciliació del CI posterior al merge

**Data:** 03/10/2026  
**Main auditat:** `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Origen:** merge del PR #118 — tancament final UC-014

## 1. Per què es reobre aquesta verificació

El PR #118 va quedar fusionat i la documentació del UC-014 marcava l'auditoria i la implementació com a tancades. Els workflows `push` del commit de merge, però, van acabar en vermell:

- `SIF PHP MySQL tests` — run `37061206441`;
- `UC-111 integration verification` — run `37061206452`.

La fallada era dins `php sif/tests/run-tests.php`, no en checkout, PHP, MySQL ni lints previs.

## 2. Resultat real de la suite del merge

La suite va acabar amb **917 passed / 6 failed**.

### Fallades fora de UC-014 — UC-015/PACK

1. `PackEnrollmentIdempotencyBoundaryTest::testEnrollmentReusesSingleAuthoritativePriceSnapshot`
2. `PackEnrollmentTransportBoundaryTest::testPackEnrollmentMutationUsesPostAndDoesNotReadGetParameters`
3. `PackPaymentPrivacyBoundaryTest::testPackRedsysPayloadUsesNameNotDniAndOmitsEmailFromReturnUrls`
4. `PackPaymentPrivacyBoundaryTest::testPaymentResponsePagesTreatEmailAsOptionalEscapedHint`
5. `PackPublicEnrollmentBoundaryTest::testPublicPackEnrollmentHasSameSiteRequestBoundaryBeforeInputProcessing`

Aquestes cinc fallades pertanyen a UC-015/PACK i no es corregeixen des del UC-014.

### Fallada pròpia del nucli Redsys/UC-014

`RedsysSignatureValidatorTest::testValidNotificationDecodesAndNormalizesSignedPayload`

El test esperava:

`8d4b744ee7c64f817594c7102b10d191ed99a26619a9f5da4501539984d079e1`

però el servei retornava:

`b585ea0d53cc71fc58e366ccde647457220e9e7732734c0b904a014f589813ff`

El valor retornat és el SHA-256 correcte del `Ds_MerchantParameters` utilitzat pel mateix vector del test. Per tant, la regressió era **l'expected desactualitzat del test**, no la implementació de `RedsysSignatureValidator`.

## 3. Warning addicional detectat

`RedsysCourseLegacyFallbackBoundaryTest` generava:

`Undefined variable $fractional`

per interpolació accidental dins l'string de l'assert. El test passava, però deixava soroll i podia ocultar regressions futures.

## 4. Correccions 03/10

Branca: `fix/uc-014-main-ci-reconciliation-2026-10-03`

- `RedsysSignatureValidatorTest`: vector SHA-256 actualitzat al valor corresponent exactament al `Ds_MerchantParameters` del test.
- `RedsysCourseLegacyFallbackBoundaryTest`: `$fractional` escapat com a literal dins l'assert i eliminat el warning.

No s'ha modificat la implementació productiva del validador ni cap flux PACK.

## 5. Verificació posterior al PR #119

Sobre el head `bb5993eb6ac6457c99965f4b2589a8f2c915587c`:

- `RedsysSignatureValidatorTest::testValidNotificationDecodesAndNormalizesSignedPayload`: **PASS**;
- `RedsysCourseLegacyFallbackBoundaryTest::testCurrentCheckoutUsesServerAuthoritativeFractionalStateAndEscapesPostOutput`: **PASS**;
- no torna a aparèixer `Undefined variable $fractional`;
- resultat global: **918 passed / 5 failed**.

Les cinc fallades restants són exactament les cinc de UC-015/PACK enumerades a l'apartat 2. No queda cap fallada UC-014/Redsys en aquesta execució.

## 6. Estat final

**UC-014:** CI propi reconciliat i verificat.  
**Suite global:** continua vermella per cinc boundaries de UC-015/PACK.  
**Conclusió:** aquests cinc vermells no reobren UC-014; s'han de resoldre dins l'auditoria/implementació UC-015.


## 7. CI selectiu UC-014

Per no confondre regressions d'altres UC amb el tancament d'aquest cas, el PR #119 afegeix:

- `sif/tests/run-uc014-tests.php`;
- `.github/workflows/uc014-sif-checks.yml`;
- check GitHub Actions: **UC-014 SIF course checks**.

La suite selectiva inclou els serveis/boundaries de curs Redsys, intent/callback, worker, factura, `EXTERNAL_ALLOCATION`, status, preflight/preproducció, retorn, cutover, JASOM i primitives Redsys compartides necessàries per UC-014.

**Evidència del 03/10/2026:**  
`UC-014 selective suite: 125 passed, 0 failed`.

Aquesta és la comprovació canònica específica d'UC-014. La suite global continua sent necessària per detectar regressions transversals, però els seus cinc vermells actuals corresponen a UC-015/PACK i no invaliden aquest resultat.
