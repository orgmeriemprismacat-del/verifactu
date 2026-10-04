# UC-024 — Matriu de proves i evidència

**Revisió:** 04/10/2026 — HEAD de branca pendent d’execució final  
**Regla:** “fitxer de test existent” ≠ “test executat sobre el commit objectiu”.

| ID | Prova | Cobertura actual | Estat al tall |
| --- | --- | --- | --- |
| T24-01 | Registrar cobrament total sobre factura existent sense nova emissió fiscal | `ClaimPaymentServiceTest` | IMPLEMENTADA, pendent execució branca |
| T24-02 | Reintent idempotent mateix payload | `ClaimPaymentServiceTest` | IMPLEMENTADA, pendent execució branca |
| T24-03 | Cobrament parcial per número visible | `ClaimPaymentServiceTest` | IMPLEMENTADA, pendent execució branca |
| T24-04 | Factura inexistent | `ClaimPaymentServiceTest` | IMPLEMENTADA, pendent execució branca |
| T24-05 | Builder: import/data/mètode/usuari | `ClaimPaymentPayloadBuilderTest` | IMPLEMENTADA, pendent execució branca |
| T24-06 | Preview no muta | `ClaimPaymentPreviewScriptTest` | IMPLEMENTADA, pendent execució branca |
| T24-07 | Process no prod usa ClaimPaymentService | `ClaimPaymentPreproductionScriptTest` | IMPLEMENTADA, pendent execució branca |
| T24-08 | E2 diferent amb mateixa claim_reference | afegida a l'auditoria | IMPLEMENTADA, pendent execució branca |
| T24-09 | Mateixa claim_reference contra una altra factura | afegida a l'auditoria | IMPLEMENTADA, pendent execució branca |
| T24-10 | Mateix ingrés ja registrat per un altre canal | `ClaimPaymentReceiptResolverTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-11 | OVERPAID: bloqueig de cobrament nou > pendent | `ClaimPaymentBalanceGuardTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-12 | actor + correlation + payment_action_event | `ClaimPaymentAuditFlowTest` + contracte API | IMPLEMENTADA, pendent execució HEAD |
| T24-13 | endpoint intranet: POST/origen/permís mutació | `ClaimPaymentIntranetBoundaryTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-14 | endpoint intranet: CSRF | `ClaimPaymentIntranetBoundaryTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-15 | correu post-commit/outbox | requisit de UC-12/UC-43, no del registre econòmic UC-024 | FORA D'ABAST DE TANCAMENT UC-024 |
| T24-16 | segona quota parcial E2 amb receipt diferent | `ClaimPaymentServiceTest` + builder tipificat | IMPLEMENTADA, pendent execució HEAD |
| T24-17 | E2E UI → SIF → DB → UI sobre sif_test/sif_pre | no acreditat | PENDENT |
| T24-18 | concurrència real de dos intents del mateix rebut | idempotència/unique key existent; manca prova concurrent específica | PENDENT |
| T24-19 | handler JS llegat i variable de curs en correus morositat | `ClaimPaymentLegacyBoundaryTest` | IMPLEMENTADA; va passar en CI anterior, HEAD nou pendent |
| T24-20 | factura SIF correspon a `source_inscription_id` i IDPAG | `ClaimPaymentInvoiceLinkRepositoryTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-21 | receipt tipificat BANK_REFERENCE / DS_ORDER / PROVIDER_REF | `ClaimPaymentPayloadBuilderTest` + `ClaimPaymentReceiptResolverTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-22 | baseline legacy ha de coincidir abans d’un CHARGE nou | `ClaimPaymentLegacySyncServiceTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-23 | projecció legacy, retry ja sincronitzat i delta anòmal | `ClaimPaymentLegacySyncServiceTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-24 | API signada, rols, anti-replay i contracte server-side | `ClaimPaymentInternalApiContractTest` + `InternalApiAuthenticatorTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-25 | UI feature-flagged no envia factura/actor/claimCase | `ClaimPaymentIntranetBoundaryTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-26 | recuperació després de commit SIF + fallada de projecció legacy, sense duplicar cobrament | `ClaimPaymentLegacyRecoveryTest` | IMPLEMENTADA, pendent execució HEAD |
| T24-27 | qualsevol error post-commit SIF manté `payment_persisted=true` + `requires_reconciliation=true` | `ClaimPaymentInternalApiContractTest` + contracte endpoint | IMPLEMENTADA, pendent execució HEAD |

## Evidència que s'ha de conservar per tancar

- SHA del commit provat.
- sortida completa de `php sif/tests/run-tests.php`;
- migracions aplicades i nom real de la BD `sif_test*`;
- payloads anonimitzats E1/E2 i UUID_PAYMENT resultants;
- comprovació de no increment de `factura_registres`/`fiscal_queue`;
- comprovació del ledger i `ESTAT_COBRAMENT`;
- prova de permís denegat i CSRF denegat a l'endpoint final;
- evidència de deduplicació intercanal;
- evidència de baseline/projecció legacy i retry `requires_reconciliation`;
- comprovació que la UI continua OFF sense `SIF_CLAIM_PAYMENT_UI_ENABLED=1`;
- les proves d’outbox/reintent de comunicació es conservaran al tancament d’UC-12/UC-43, no són prerequisit d’UC-024.
