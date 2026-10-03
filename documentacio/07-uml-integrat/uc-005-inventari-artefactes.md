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

## 3. Codi de pantalla/llegat localitzat

- `codi-drive/intranet-actual/alumnes-factura.php`
- `codi-drive/intranet-actual/js/alumnes-factura.js`
- `codi-drive/intranet-actual/js/alumnes-factura-sif.js`
- `codi-drive/intranet-actual/ajax/alumnes/mostraModalConsultaFactura.php`
- `codi-drive/intranet-actual/ajax/alumnes/mostrarModalAnulaFactura_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/guardarDadesFactura_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/anularFactura_Factures.php`
- `codi-drive/intranet-actual/Intranet.php`

Els dos endpoints mutadors llegats revisats ja exigeixen POST, sessió i autorització de mutació, i passen per `SifLegacyInvoiceMutationGuard`. Això és una millora respecte de la documentació antiga que els descrivia com GET sense protecció, però **no converteix la mutació llegada en UC-005 SIF**.

## 4. Correcció aplicada en aquesta auditoria

S'ha detectat una inconsistència real: el builder acceptava `motiu` i `mode_rectificacio`, mentre el repositori de relació llegia només `reason` i `mode`. `ManualRectificationService` normalitza ara aquests alias abans de construir i persistir la rectificació. S'ha afegit una prova d'integració específica.

## 5. Peces que encara falten

1. Comanda HTTP/intranet específica per crear rectificatives SIF des de la factura consultada.
2. Classificador UC-74 integrat abans de decidir rectificativa/anul·lació/subsanació.
3. Correcció de receptor/concepte amb snapshot nou; el builder actual reutilitza el receptor original.
4. Tractament fiscal per IVA/règims diferents d'EXEMPT 0%.
5. Estratègia transaccional que inclogui emissió R + `factura_rectificacio` + canvi d'estat de l'original en una mateixa unitat atòmica.
6. Concorrència/lock explícit sobre l'original.
7. Auditoria operacional específica de l'ordre.
8. E2E pantalla → classificació → rectificativa → document → consulta.
9. Evidència executada sobre MySQL `sif_test*`/preproducció.

## 6. Estat global

- **Documentació estructural:** COMPLETADA en aquesta auditoria.
- **Codi SIF de rectificació manual:** IMPLEMENTAT PARCIALMENT.
- **Pantalla final UC-005:** PENDENT.
- **Fiscalitat general:** PENDENT.
- **Atomicitat completa:** PENDENT/BLOQUEJANT.
- **Proves definides:** SÍ.
- **Proves executades en aquesta auditoria:** NO acreditades.
