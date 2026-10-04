# UC-007 · Matriu de traçabilitat i estats — 2026-10-04

## 1. Criteri d'estat

- **DOCUMENTAT**: contracte/diagrama/traça presents.
- **IMPLEMENTAT**: el codi existeix al head del PR #135.
- **PASS CI INICIAL**: la prova constava PASS al primer head auditat `215aee90...`.
- **CI HEAD FINAL PENDENT**: la prova/correcció s'ha afegit després i el run final encara no ha acabat.
- **RUNTIME PENDENT**: requereix navegador, rols, BD/storage o feature flags de test/preproducció.
- **PENDENT FINAL**: funcionalitat deliberadament fora del tall actual.

## 2. Matriu requisit → codi → UML → evidència

| Requisit / invariant | Codi | UML | Prova/evidència | Estat |
| --- | --- | --- | --- | --- |
| UUID és identitat canònica | validator/repository/query service + deep link | S1-S3, F02 | `InvoiceQueryServiceTest` + `Uc007IntranetBoundaryTest` | IMPLEMENTAT · PASS CI INICIAL |
| Consulta SIF no muta fiscal/econòmic | `InvoiceReadRepository` + `InvoiceQueryService` | S2 | test de zero mutació | IMPLEMENTAT · PASS CI INICIAL |
| Descàrrega llegada UC-007 no muta `generada` | `generaFactura(...,$marcaGenerada)` + `descarregaFactura.php` | S8, F07 | `testLegacyDownloadDoesNotMutateGeneratedMarker` | CORREGIT · CI HEAD FINAL PENDENT |
| Criteris whitelist/exactes | `InvoiceQueryCriteriaValidator` | S1/F02 | query tests + source type/id errors | IMPLEMENTAT · PASS BASE + AMPLIACIÓ CI PENDENT |
| Cerca per inscripció pot retornar múltiples UUID | repository/query service | S3/F02 | test múltiple `SOURCE_TYPE=INSCRIPCIO` | IMPLEMENTAT · CI HEAD FINAL PENDENT |
| Actor/rol no ve del navegador | sessió refrescada + `SifAuthenticatedActor` + HMAC | classes/S1 | `InternalApiAuthenticatorTest` + boundary | IMPLEMENTAT · PASS CI INICIAL |
| Rol intern resol FULL/MINIMAL fail-closed | `InternalInvoiceScopeResolver` | classes/S1 | `InternalInvoiceScopeResolverTest` | IMPLEMENTAT · CI HEAD FINAL PENDENT |
| Projecció MINIMAL no exposa fiscal/document | `ResolvedInvoiceVisibilityPolicy` | classes/S2 | `ResolvedInvoiceVisibilityPolicyTest` | IMPLEMENTAT · PASS CI INICIAL |
| Estat factura/cobrament/AEAT separat | read model | S2/F05 | query service tests | IMPLEMENTAT · PASS CI INICIAL |
| Original/rectificativa i pagaments separats | read repository | S2 | `testViewKeepsPaymentsAndRectificationAsSeparateRelations` | IMPLEMENTAT · PASS CI INICIAL |
| Metadata document no exposa path/hash físic | `findDocumentMetadata` | S2/S5 | service/boundary | IMPLEMENTAT · VERIFICAT ESTÀTICAMENT |
| Bytes només via UC-080 | `sifDocument.php` + document endpoint/service | S5/F07 | `InvoiceDocumentAccessServiceTest` | IMPLEMENTAT · CI HEAD FINAL PENDENT |
| Hash abans de stream | `PrivateDocumentStore::readVerified` | S5 | bytes correctes + hash mismatch | IMPLEMENTAT · CI HEAD FINAL PENDENT · ENTORN REAL PENDENT |
| Path fora de `SIF_DOCUMENT_ROOT` denegat | `PrivateDocumentStore` | S5 | PATH_OUTSIDE_STORAGE | IMPLEMENTAT · CI HEAD FINAL PENDENT |
| Accés document auditat | `FiscalDocumentAccessRepository` | S5 | ALLOWED/DENIED/FAILED | IMPLEMENTAT · CI HEAD FINAL PENDENT · ENTORN REAL PENDENT |
| AL-16 deep link UUID | dos JS canònics | S3/AL-16 | boundary | CORREGIT · PASS CI INICIAL |
| AL-17 modal SIF | `alumnes-mostrar-alumne.js` | S4/AL-17 | boundary | IMPLEMENTAT · E2E PENDENT |
| AL-18 usa UC-080 quan és SIF | student JS + `sifDocument.php` | S9/AL-18 | boundary/static | IMPLEMENTAT · E2E PENDENT |
| Una sola implementació UC-007 per pàgina | includes PHP | classes/inventari | boundary | CORREGIT · PASS CI INICIAL |
| Flag UI buit eliminat | pàgines PHP | F01 | boundary | CORREGIT · CI HEAD FINAL PENDENT |
| Fallback cerca no envia DNI/email per query string | JS + 3 wrappers legacy | S6/F02-F04 | boundary POST/same-origin | CORREGIT · CI HEAD FINAL PENDENT |
| Fallback limita candidats i longituds | wrappers legacy | S6/F02-F04 | validació server-side | CORREGIT · CI HEAD FINAL PENDENT |
| DNI/correu no es trunca silenciosament a 200 inscripcions | `sifFactures.php::enrollmentIdsByIdentity` | S1/F02 | LIMIT 201 + >200 → 422 + boundary | CORREGIT · CI HEAD FINAL PENDENT |
| F02 inicialitza estat de cerca | `Intranet::buscarUsuaris_Factures` | F02 | boundary | CORREGIT · CI HEAD FINAL PENDENT |
| F02 delimitador DNI/CIF estable | `buscarUsuaris_Factures` | F02/F03 | boundary | CORREGIT · CI HEAD FINAL PENDENT |
| F04 títol de cerca escapat | `mostrarTotesFacturesUsuari_Factures` | F04 | boundary | CORREGIT · CI HEAD FINAL PENDENT |
| F05 FACTURA_RELACIONADA immutable al wrapper | `guardarDadesFactura_Factures.php` | S7/F05 | inspecció + guard | IMPLEMENTAT · VERIFICAT ESTÀTICAMENT |
| F06 preview/PDF escapa valors de BD | `Intranet::generaFactura` | S8/F06 | boundary escaping | CORREGIT · CI HEAD FINAL PENDENT |
| F07 download és POST i lectura | JS + `descarregaFactura.php` | S8/F07 | boundary | CORREGIT · CI HEAD FINAL PENDENT |
| Guard llegat bloqueja factura SIF quan UC-007 està actiu | `SifLegacyInvoiceMutationGuard` | S7/S8 | boundary | CORREGIT · CI HEAD FINAL PENDENT |
| Paginació modal llegat conserva handlers | student JS | AL-17 | boundary | CORREGIT · PASS CI INICIAL |
| Errors d'anul·lació apareixen al modal correcte i sense logs de dades | invoice JS | F04/F05 adjacent | inspecció JS | CORREGIT · CI HEAD FINAL PENDENT |
| Feature flags fallen explícitament | bridges SIF | S1/S5 | static/runtime | IMPLEMENTAT · RUNTIME PENDENT |
| Canals externs alumne/empresa | UC-102/126 | classes FINAL | — | PENDENT FINAL |
| Retirada fallback llegat | — | FINAL activitats | — | PENDENT FINAL |

## 3. Evidència CI

El primer run auditat del PR #135 va executar **921 tests PASS i 6 FAIL globals**. Les proves UC-007 que existien en aquell head —query read-only, policy FULL/MINIMAL, HMAC/anti-replay, CLI query i `Uc007IntranetBoundaryTest` inicial— consten PASS. Les sis fallades observades eren PACK/Redsys i no corresponien a fitxers modificats per aquell diff.

El `main` base ja tenia workflows SIF en `failure`; no s'afirma que fossin exactament les mateixes sis assertions. Evidència detallada: [05-evidencia-ci-uc-007-pr135-2026-10-03.md](05-evidencia-ci-uc-007-pr135-2026-10-03.md).

Les correccions i proves incorporades després del primer run s'han de validar amb el **head final** abans de promocionar-les a PASS CI.

## 4. Runtime que continua pendent

- rols reals FULL/MINIMAL i revocació de sessió;
- AL-16/17/18 al navegador;
- cache-busting dels assets canònics;
- storage privat real, permisos de filesystem i hashes reals;
- feature flags per entorn;
- regressió amb dades històriques complexes;
- comprovació que una factura llegada amb `generada IS NULL` continua NULL després d'una descàrrega UC-007;
- retirada definitiva del fallback.

### Auditoria del rol efectiu

- `InternalInvoiceScopeResolver` conserva `invoice_scope_role`.
- UC-080 grava aquest rol efectiu a `fiscal_document_access.ACTOR_ROLE`.
- Un actor `SUPORT + FACTURACIO` amb FULL per `FACTURACIO` queda auditat com `FACTURACIO`.
