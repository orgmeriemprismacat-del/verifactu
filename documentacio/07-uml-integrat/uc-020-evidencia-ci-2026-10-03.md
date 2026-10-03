# UC-020 — Evidència CI de revalidació

**Data:** 03/10/2026  
**PR:** #112 — `refactor/uc-020-reconcile-runtime-2026-10-02`  
**Commit verificat:** `9a70516ca27ee831b8ef78e61cea524c3c3ea5f6`  
**Base `main` contrastada:** `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Resultat UC-020:** `VERIFICAT_CI_ESPECIFIC`  
**Resultat suite global:** `FAILED_UNRELATED_TO_UC020`

## 1. Execucions observades

Al commit verificat van executar-se els workflows següents:

| Workflow | Run ID | Resultat |
| --- | ---: | --- |
| UC-004 SIF secure flow checks | 37129497825 | SUCCESS |
| SIF PHP MySQL tests | 37129497834 | FAILURE |
| SIF checks | 37129497836 | FAILURE |
| UC-111 integration verification | 37129497865 | FAILURE |

Els tres workflows que fallen executen la mateixa suite SIF compartida. La suite va acabar amb **924 proves passades i 6 fallades**.

## 2. Proves UC-020 observades en PASS

### Frontera web llegada

`LegacyPrismaStudentEnrollmentAuthorityBoundaryTest` — 4/4 PASS:

- el preu AP autoritatiu de servidor no és sobreescrit abans de l'INSERT;
- AP + promoció falla tancat;
- `TIPUS_CURS` prové de `informacio`;
- el preview no reintrodueix la drecera de factura no cobrada.

### Política i historial

`PrismaStudentDiscountPolicyTest` — 5/5 PASS:

- pagament positiu/parcial;
- curs regal;
- `GENERAT=1`;
- factura només emesa/no cobrada no acredita;
- estats D/M exclosos.

`LegacyPrismaStudentPriceSnapshotResolverTest` — 4/4 PASS:

- reconstrucció històrica;
- selector per hores;
- mismatch amb matrícula;
- tarifa AP ambigua.

### Checkout comercial AP

`PrismaStudentCourseCheckoutServiceTest` — 7/7 PASS:

- operació + validació + intenció des d'un snapshot únic;
- reintent equivalent idempotent;
- segon `DS_ORDER` incompatible rebutjat;
- matrícula no elegible sense estat comercial;
- no autoacreditació;
- tall temporal a `DATA_INSC`;
- mismatch de preu rebutjat.

`RedsysCoursePaymentIntentPrismaStudentTest` — 3/3 PASS:

- staging comercial abans de la intenció;
- fraccionament AP falla tancat mentre no hi hagi model fiscal;
- tarifa històrica ambigua rebutjada.

`RedsysPaymentIntentTest::testCreatesCourseIntentWithPrismaStudentDiscountSnapshot` — PASS.

Això dona **24 proves directament vinculades a Alumne PrisMa/UC-020 en PASS** al commit verificat.

## 3. Proves compartides rellevants també en PASS

`RedsysCourseEndToEndSimulatedTest` — 3/3 PASS:

- pagament complet + callback duplicat idempotent;
- parcial → complet amb projecció del total confirmat;
- exactitud de cèntims en parcial → complet.

`CommercialOfferServiceTest` — 6/6 PASS i `PaymentLinkServiceTest` — 6/6 PASS. Són infraestructura comercial compartida; no impliquen que l'alta web UC-020 ja utilitzi `CommercialOfferService/payment_link` de forma canònica.

## 4. Les 6 fallades globals no són UC-020

Les fallades observades són:

1. `PackEnrollmentIdempotencyBoundaryTest::testEnrollmentReusesSingleAuthoritativePriceSnapshot`;
2. `PackEnrollmentTransportBoundaryTest::testPackEnrollmentMutationUsesPostAndDoesNotReadGetParameters`;
3. `PackPaymentPrivacyBoundaryTest::testPackRedsysPayloadUsesNameNotDniAndOmitsEmailFromReturnUrls`;
4. `PackPaymentPrivacyBoundaryTest::testPaymentResponsePagesTreatEmailAsOptionalEscapedHint`;
5. `PackPublicEnrollmentBoundaryTest::testPublicPackEnrollmentHasSameSiteRequestBoundaryBeforeInputProcessing`;
6. `RedsysSignatureValidatorTest::testValidNotificationDecodesAndNormalizesSignedPayload`.

Per tant, el vermell global del workflow **no és evidència d'una fallada funcional del UC-020**. Tampoc s'ha d'interpretar com a suite global superada.

## 5. Classificació d'evidència

- **DOCUMENTAT:** complet per fitxa, UML integrat, classes, seqüències, activitats P01…P06, traçabilitat i matriu AP-01…AP-84.
- **IMPLEMENTAT:** hardening d'alta AP llegada, policy v2, historial temporal, reconstrucció de tarifa, staging comercial AP i intenció Redsys.
- **VERIFICAT CI:** les proves UC-020 enumerades en aquest document passen al commit `9a70516`.
- **PENDENT:** E2E real/controlat navegador → Redsys/callback → worker → cobrament/factura en preproducció; migració completa de l'alta/preview/intranet a oferta SIF nativa; `payment_link` canònic; unificació de transferència.
- **BLOQUEIG GLOBAL DEL PR:** la suite compartida continua vermella per 6 fallades alienes o transversals, que s'han de resoldre abans d'usar el verd global com a gate de merge/desplegament.

## 6. Criteri

Una prova UC-020 només es marca com a `VERIFICAT_CI_*` quan el seu nom i resultat PASS consten al log del commit anterior. Els escenaris sense execució directa o que només tenen disseny/cobertura parcial continuen com a pendents.
