# UC-111 · Revalidació post-merge sobre main · 02/10/2026

## 1. Tall verificat

- Repositori: `orgmeriemprismacat-del/verifactu`
- Branca: `main`
- Commit verificat: `f26625b0f80c1f9b030bd043ffbd5394150983e2`
- PR d'integració UC-111: #14
- Merge commit: `d237da4d47e0d01910188ea155ebdbeddb97a9c2`
- Workflow: `UC-111 integration verification`
- Run de main revisat: `36912820545`
- Motor: MySQL 8 + PHP 8.3
- Resultat: **821 passed · 0 failed**

## 2. Proves UC-111 verificades explícitament

PASS:
- `NovicePromotionPostPaymentFlowTest::testPartialThenFullPaymentThenDuplicateCallbackKeepsOneGrantAndOneCode`
- `NovicePromotionStudentSummaryServiceTest::testStudentSummaryShowsGrantedAppliedAndAvailableWithoutExposingCode`
- `NovicePromotionSecretaryDecisionProjectorTest::testApprovedDecisionOpensPaymentAndIsIdempotent`
- `NovicePromotionSecretaryDecisionProjectorTest::testRejectedDecisionOpensNormalPaymentWithoutNoviceGrant`
- `NovicePromotionSecretaryDecisionProjectorTest::testPendingDecisionNeverOpensPayment`
- `NovicePromotionSecretaryDecisionProjectorTest::testWrongLegacyHolderCannotApproveSifParticipant`
- `NovicePromotionSecretaryDecisionProjectorTest::testDecisionCannotBeNewlyProjectedAfterPaymentGateOpened`
- tots els casos `JasomNovicePaymentGateTest` del run: pendent, decisió desconeguda fail-closed, aprovació, denegació, no-sol·licitud, curs manipulat, excés/repetició de pagament i imports invàlids.

## 3. Estat executable constatat

### Decisió i pagament
- PENDING no obre pagament.
- APPROVED obre pagament de manera idempotent.
- REJECTED manté JASOM i obre pagament normal sense concedir promoció.
- Una identitat legacy que no correspon al participant SIF no pot aprovar-lo.
- No es pot projectar una decisió nova després d'haver obert el gate econòmic.
- El gate de pagament no confia en curs/import enviats pel navegador.

### Concessió
- pagament parcial: no concedeix dret;
- pagament complet reconciliat: concedeix/reutilitza un únic dret;
- callback/job duplicat: no duplica factura, payment, grant ni preparació de codi;
- el codi legacy fix/reutilitzat no és l'autoritat del model FINAL.

### Consulta / Modifica alumne
- el read model retorna concedit/utilitzat/reservat/disponible i historial;
- no exposa token `NOV-*` ni ciphertext;
- endpoint actual: feature flag + POST + same-origin + sessió/autorització + CSRF;
- mòdul UI UC-111 separat del JS principal i del mòdul SIF de factures.

## 4. Canvis posteriors al merge revisats

Després del PR #14, `main` ha continuat avançant. Els canvis UC-111 observats no desfan la integració:
- s'han afegit `LegacyNovicePromotionContext.php` i `SifInternalNovicePromotionClient.php`;
- s'ha endurit `mostrarPromocioDocentNovell.php`;
- el mòdul JS usa POST + CSRF;
- el checkout JASOM integra el gate autoritatiu i la creació/reutilització d'intenció SIF/Redsys.

## 5. Pendent real

Aquesta revalidació **no acredita**:
- desplegament final en producció de tots els components;
- prova visual/manual de navegador del panell UC-111;
- configuració real dels secrets UC-111 i endpoints interns;
- transport real del correu privat;
- custòdia segura i cicle de vida dels justificants legacy;
- decisions fiscal/comptables expressament obertes;
- tots els connectors externs de canvi/baixa/refund en entorn productiu.

## 6. Criteri documental

Les frases històriques dels documents UC-111 que indiquen `TEST: NO EXECUTAT`, `MySQL pendent`, `no fusionat a main` o `generador SIF no acreditat` s'han de llegir segons el tall datat corresponent. Per a l'estat executable actual, aquest document i la matriu de traçabilitat prevalen.
