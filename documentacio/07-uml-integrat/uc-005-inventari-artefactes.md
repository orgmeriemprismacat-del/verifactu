# UC-005 · Inventari d'artefactes, diagrames i codi

**Data de tall:** 2026-10-03  
**Branca d'auditoria:** `audit/uc-005-2026-10-03`

## 1. Resposta a la pregunta de cobertura

Abans d'aquesta auditoria, UC-005 tenia la fitxa funcional i una fitxa/UML integrada, però no disposava del mateix paquet documental que els casos ja auditats en profunditat.

| Tipus exigit | Fitxer | Estat |
| --- | --- | --- |
| Fitxa funcional | `documentacio/06-fitxes-funcionals/uc-005.md` | EXISTEIX · actualitzada |
| Fitxa/UML integrada | `uc-005-rectificar-factura.md` | EXISTEIX |
| Cas d'ús ACTUAL/FINAL | `uc-005-cas-us-actual-final.md` | CREAT |
| Classes ACTUAL/FINAL | `uc-005-classes-actual-final.md` | CREAT |
| Seqüències ACTUAL/FINAL | `uc-005-sequencies-actual-final.md` | CREAT |
| Activitats per superfície | `uc-005-activitats-pagines-actual-final.md` | CREAT |
| Auditoria/traçabilitat | `uc-005-auditoria-tracabilitat-2026-10-03.md` | CREAT |
| Inventari mestre | aquest fitxer | CREAT |

## 2. Codi SIF localitzat

- `sif/src/Service/ManualRectificationService.php`
- `sif/src/Service/ManualRectificationPayloadBuilder.php`
- `sif/src/Repository/RectificationRepository.php`
- `sif/src/Repository/ManualPaymentInvoiceRepository.php`
- `sif/src/Service/InvoiceService.php`
- `sif/src/Repository/InvoiceRepository.php`
- `sif/scripts/preview-manual-rectification.php`
- `sif/scripts/process-manual-rectification.php`
- `sif/tests/Integration/ManualRectificationServiceTest.php`
- `sif/tests/Unit/ManualRectificationPayloadBuilderTest.php`
- `sif/tests/Integration/ManualRectificationPreviewScriptTest.php`
- `sif/tests/Integration/ManualRectificationPreproductionScriptTest.php`
- `sif/src/Service/FiscalCorrectionDecisionGuard.php`
- `sif/src/Service/FiscalCorrectionDecisionResolver.php`
- `sif/src/Repository/FiscalCorrectionDecisionRepository.php`
- `sif/src/Service/RectificationDecisionFingerprint.php`
- `sif/src/Service/AeatRectificationMapper.php`
- `sif/src/Repository/InvoiceReadRepository.php` (read model de decisió UC-74)
- `sif/src/Service/InvoiceQueryService.php` (projecció FULL de decisió/correction)
- `sif/src/Service/InternalRectificationScopeResolver.php`
- `sif/src/Service/RectificationCommandService.php`
- `sif/src/Repository/SifAuditEventRepository.php`
- `sif/public/api/factures/rectify.php`
- `sif/tests/Integration/ManualRectificationAtomicityTest.php`
- `sif/tests/Integration/ManualRectificationFiscalTest.php`
- `sif/tests/Integration/ManualRectificationAeatMappingTest.php`
- `sif/tests/Integration/ManualRectificationConcurrencyTest.php`
- `sif/tests/Support/ConcurrentRectificationWorker.php`
- `sif/tests/Integration/FiscalCorrectionDecisionResolverTest.php`
- `sif/tests/Integration/RectificationDecisionReadModelTest.php`
- `sif/tests/Integration/RectificationHttpEndpointTest.php`
- `sif/tests/Integration/RectificationIntranetProxyContractTest.php`
- `sif/tests/Integration/RectificationCommandServiceTest.php`
- `sif/tests/Unit/FiscalCorrectionDecisionGuardTest.php`
- `sif/tests/Unit/InternalRectificationScopeResolverTest.php`
- `sif/tests/run-uc005-tests.php`
- `.github/workflows/uc005-rectification.yml`

## 3. Codi de pantalla/llegat localitzat

- `codi-drive/intranet-actual/alumnes-factura.php`
- `codi-drive/intranet-actual/js/alumnes-factura.js`
- `codi-drive/intranet-actual/js/alumnes-factura-sif.js`
- `codi-drive/intranet-actual/SifRectificationAccess.php`
- `codi-drive/intranet-actual/SifInternalApiClient.php` (client signat UC-005)
- `codi-drive/intranet-actual/ajax/alumnes/sifRectificarFactura.php`
- `codi-drive/intranet-actual/ajax/alumnes/mostraModalConsultaFactura.php`
- `codi-drive/intranet-actual/ajax/alumnes/mostrarModalAnulaFactura_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/guardarDadesFactura_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/anularFactura_Factures.php`
- `codi-drive/intranet-actual/Intranet.php`

Els dos endpoints mutadors llegats revisats ja exigeixen POST, sessió i autorització de mutació, i passen per `SifLegacyInvoiceMutationGuard`. Això és una millora respecte de la documentació antiga que els descrivia com GET sense protecció, però **no converteix la mutació llegada en UC-005 SIF**.

## 4. Correcció aplicada en aquesta auditoria

S'ha detectat una inconsistència real: el builder acceptava `motiu` i `mode_rectificacio`, mentre el repositori de relació llegia només `reason` i `mode`. `ManualRectificationService` normalitza ara aquests alias abans de construir i persistir la rectificació. S'ha afegit una prova d'integració específica.

## 5. Peces que encara falten

1. **UI CONSUMIDORA IMPLEMENTADA:** consulta SIF + decisió UC-74 + panell read-only + proxy sessió/permís/same-origin/CSRF + HMAC + preview/confirm. Pendent només el productor UC-74 que alimenta el snapshot executable i validació E2E/preproducció.
2. **PENDENT UC-74:** productor/classificador fiscal genèric executable. UC-005 ja exigeix una decisió persistida vinculada per `correction_fingerprint`; vegeu `uc-005-contracte-uc074.md`.
3. **AEAT PARCIAL:** mapper server-side implementat per un únic desglossament compatible; pendents perfils fiscals complexos, XSD/worker E2E i evidència d'enviament real.
4. **PENDENT DECISIÓ:** correccions sense variació d'import; el builder continua rebutjant total zero fins que el criteri fiscal ho defineixi.
5. **IMPLEMENTAT:** emissió R + `factura_rectificacio` + estat original + audit terminal comparteixen la transacció d'`InvoiceService`.
6. **IMPLEMENTAT:** `FOR UPDATE` i revalidació del snapshot original abans del COMMIT; falta prova de concurrència E2E amb dues sessions.
7. **IMPLEMENTAT:** `sif_audit_event` i `operational_event` del command, amb `REQUESTED/SUCCEEDED/REUSED/FAILED`.
8. **CONCURRÈNCIA:** `ManualRectificationConcurrencyTest` + worker multiprocés implementats; pendent execució CI. Verifiquen una sola R sota dues connexions i reintent posterior idempotent.
9. **PENDENT E2E:** productor UC-74 → read model/pantalla → preview → confirm → R → document → consulta.
10. **PENDENT DOCUMENTS:** existeixen registre, storage privat, descàrrega i auditoria, i l'esquema `document_job`, però no s'ha localitzat productor/worker que generi PDF/QR/XML després d'emetre la R.
11. **PENDENT EVIDÈNCIA:** conclusió verda de la suite UC-005 i preproducció.

## 6. Estat global

- **Documentació estructural:** COMPLETADA en aquesta auditoria.
- **Codi SIF de rectificació manual:** IMPLEMENTAT PARCIALMENT.
- **Pantalla UC-005:** CONSUMIDOR IMPLEMENTAT EN BRANCA; mostra la decisió i la correcció aprovades i només permet preview/confirm. No inclou selector fiscal manual.
- **Fiscalitat local SIF:** IMPLEMENTADA EN MODE FAIL-CLOSED · AEAT específic pendent.
- **Atomicitat del nucli UC-005:** IMPLEMENTADA I PASSADA A LA SUITE ESPECÍFICA · pendent concurrència/preproducció.
- **Command backend segur:** IMPLEMENTAT · endpoint intern signat, rols explícits i preview/confirm.
- **Classificador UC-74:** PENDENT com a productor; consum de decisió persistida, R1-R5 i fingerprint de la correcció ja implementats.
- **Proxy/panell intranet UC-005:** IMPLEMENTAT EN BRANCA (sessió, edit permission, same-origin, CSRF, HMAC, decisió UC-74 al read model, preview/confirm).
- **Proves definides:** SÍ, inclosa suite aïllada UC-005.
- **Proves executades:** suite UC-005 verda 34/34 abans del resolver persistit; la nova passada amb `classification_event_uuid` està pendent. La suite global manté fallades alienes documentades.
