# UC-024 — Matriu de proves i evidència

**Revisió:** 03/10/2026  
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
| T24-10 | Mateix ingrés ja registrat UC-022/UC-023/Redsys | cap test UC-024 específic | PENDENT |
| T24-11 | OVERPAID: bloqueig o flux autoritzat | no hi ha política UC-024 específica | PENDENT DECISIÓ |
| T24-12 | actor + correlation + payment_action_event | no acreditat | PENDENT |
| T24-13 | endpoint intranet: rol mutació | no acreditat | PENDENT |
| T24-14 | endpoint intranet: CSRF | no acreditat | PENDENT |
| T24-15 | correu post-commit/outbox | llegat SMTP directe | PENDENT |
| T24-16 | segona quota parcial E2 registrada correctament amb receipt diferent | contracte no ho permet de manera robusta | BLOQUEJAT |
| T24-17 | E2E UI → SIF → DB → UI sobre sif_test/sif_pre | no acreditat | PENDENT |
| T24-18 | concurrència real de dos intents del mateix rebut | no específica UC-024 | PENDENT |

## Evidència que s'ha de conservar per tancar

- SHA del commit provat.
- sortida completa de `php sif/tests/run-tests.php`;
- migracions aplicades i nom real de la BD `sif_test*`;
- payloads anonimitzats E1/E2 i UUID_PAYMENT resultants;
- comprovació de no increment de `factura_registres`/`fiscal_queue`;
- comprovació del ledger i `ESTAT_COBRAMENT`;
- prova de permís denegat i CSRF denegat a l'endpoint final;
- evidència de deduplicació intercanal;
- evidència d'outbox/reintent de comunicació.
