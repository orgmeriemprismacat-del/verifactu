# UC-111 — Reconciliació de la PR #32

**Data:** 30/09/2026  
**Origen:** `feat/uc-111-termini-i-auditoria-2026-09-22` / PR #32  
**Base de reconciliació:** `main` actual.

## Decisió

La PR #32 no es fusiona directament perquè està 452 commits per darrere de `main` i els seus tests trenquen el contracte UC-111 actual.

Errors observats a la PR #32:
- `NovicePromotionEnrollmentStagerTest::testStagesRealPendingJasomWithoutPaymentOrUnapprovedDiscount`;
- `NovicePromotionSecretaryDecisionProjectorTest::testApprovedDecisionOpensPaymentAndIsIdempotent`;
- `NovicePromotionSecretaryDecisionProjectorTest::testRejectedDecisionOpensNormalPaymentWithoutNoviceGrant`.

## Recuperat en aquesta branca

Es recupera únicament el bloc tècnic autònom d'evidència documental:
- migració de lifecycle d'emmagatzematge privat, renumerada a `000031`;
- `NovicePromotionEvidenceFilePolicy`;
- `NovicePromotionPrivateEvidenceStorageInterface`;
- test unitari de la política de fitxers.

## Expressament exclòs

No es copien des de la PR #32:
- `NovicePromotionEnrollmentStager.php`;
- `NovicePromotionSecretaryDecisionProjector.php`;
- `NovicePromotionDerivedApplicationCancellationActivationService.php`;
- `NovicePromotionTransferredCancellationActivationService.php`;
- `NovicePromotionTransferredDestinationCancellationReviewService.php`;
- documentació que descriu aquests fluxos com si fossin l'estat autoritatiu actual.

Aquests elements requereixen una auditoria separada sobre el model UC-111 actual i proves específiques abans de recuperar-los.

## Criteri de merge

Aquesta reconciliació només s'ha de fusionar si:
- la suite global és verda;
- la migració `000031` s'aplica sobre l'esquema actual;
- el nou test de política passa;
- no es modifica el comportament de pagament, staging, decisió de secretaria ni lineage UC-111.
