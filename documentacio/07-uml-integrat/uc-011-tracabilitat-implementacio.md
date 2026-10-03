# UC-011 · Matriu de traçabilitat funcional, codi, UML i proves

**Data de tall:** 03/10/2026.  
**Branca auditada/modificada:** audit/uc-011-2026-10-03.  
**Regla:** un fitxer present no equival a desplegat ni a verificat en producció.

## 0. Inventari documental

| Artefacte | Fitxer | Estat |
| --- | --- | --- |
| Fitxa funcional | ../06-fitxes-funcionals/uc-011.md | EXISTIA · reescrita en auditoria |
| UML integrat | uc-011-importar-factura-historica.md | EXISTIA · base tècnica útil |
| Classes ACTUAL/FINAL | uc-011-classes-actual-final.md | CREAT |
| Seqüències ACTUAL/FINAL | uc-011-sequencies-actual-final.md | CREAT |
| Activitats ACTUAL/FINAL | uc-011-activitats-actual-final.md | CREAT |
| Traçabilitat | aquest document | CREAT |
| Auditoria detallada | 05-auditoria-detallada-uc-011-2026-10-03.md | CREAT |

No existeix una pàgina/JS que executi UC-011. Les activitats principals es divideixen pels dos punts d'entrada reals: preview CLI i process CLI. Sí s'ha incorporat als diagrames la pàgina llegada `alumnes-factura.php`/`alumnes-factura.js` com a **sistema origen/upstream**, perquè pot consultar, editar, anul·lar i regenerar representacions de les factures abans del cut-over.

## 1. Inventari de codi real

| Component | Ruta | Funció | Estat |
| --- | --- | --- | --- |
| Preview | sif/scripts/preview-historical-invoice-migration.php | dry-run de payload | IMPLEMENTAT |
| Processor | sif/scripts/process-historical-invoice-migration.php | import en entorns no-production | IMPLEMENTAT |
| Servei | sif/src/Service/HistoricalInvoiceMigrationService.php | orquestra builder + transacció + repo | IMPLEMENTAT |
| Builder | sif/src/Service/HistoricalInvoicePayloadBuilder.php | normalitza/valida | IMPLEMENTAT · REFORÇAT |
| Repository | sif/src/Repository/HistoricalInvoiceMigrationRepository.php | factura/línies/rels/document metadata | IMPLEMENTAT · REFORÇAT |
| Idempotència comuna | sif/src/Contract/PayloadIdempotencyValidatorInterface.php + Service/PayloadIdempotencyValidator.php | hash canònic i conflict 409 | REUTILITZAT |
| Unit test | sif/tests/Unit/HistoricalInvoicePayloadBuilderTest.php | builder | EXISTIA · AMPLIAT |
| Integration test | sif/tests/Integration/HistoricalInvoiceMigrationServiceTest.php | MySQL, no fiscal queue | EXISTIA · AMPLIAT |
| Test preview | sif/tests/Integration/HistoricalInvoiceMigrationPreviewScriptTest.php | frontera dry-run | EXISTIA |
| Test processor | sif/tests/Integration/HistoricalInvoiceMigrationPreproductionScriptTest.php | frontera CLI/no InvoiceService | EXISTIA |
| UI origen llegada | codi-drive/intranet-actual/alumnes-factura.php + js/alumnes-factura.js | consulta SIF/fallback llegat; edició/anul·lació/descàrrega | IMPLEMENTAT · UPSTREAM |
| Consulta llegada | ajax/alumnes/consultaUsuarisFacturaRelacionada.php | cerca web.factures per criteris llegats | IMPLEMENTAT · UPSTREAM |
| Mutació llegada | guardarDadesFactura_Factures.php + anularFactura_Factures.php | edició/anul·lació amb autorització i guard SIF | IMPLEMENTAT · CONDICIONAL |
| Regeneració PDF | descarregaFactura.php | genera PDF temporal via Intranet->generaFactura | IMPLEMENTAT · NO PROVA ORIGINAL |
| Guard de cut-over | SifLegacyInvoiceMutationGuard.php | bloqueja llegat si el SIF ja governa la factura | IMPLEMENTAT · FEATURE FLAG |

## 2. Traçabilitat de requisits

| Requisit | Documentat | Implementat | Verificació disponible | Estat |
| --- | --- | --- | --- | --- |
| Preservar NUM_VISIBLE històric | sí | sí | builder + integration test | IMPLEMENTAT |
| No consumir fiscal_sequence | sí | sí | integration test comprova count=0 | IMPLEMENTAT |
| No crear factura_registres/fiscal_queue | sí | sí | integration test | IMPLEMENTAT |
| issue_date original obligatòria | sí | sí a la branca | unit test nou | PENDENT CI |
| NUM_VISIBLE coherent amb sèrie/any/seq | sí | sí a la branca | unit test nou | PENDENT CI |
| Estat sempre HISTORICAL/NO_VERIFACTU | sí | sí a la branca | unit test + integration | PENDENT CI |
| Reintent mateix payload reutilitza | sí | sí | integration test existent | IMPLEMENTAT |
| Mateixa clau + payload diferent = conflict | sí | sí a la branca | integration test nou | PENDENT CI |
| Hash idempotent persistent | sí | sí a la branca | integration test nou | PENDENT CI |
| Hash sobre dades persistides, no aliases crus | sí | sí a la branca | test de reintent amb aliases | PENDENT CI |
| Emissor històric | sí | persistit si s'aporta | integration test nou | PENDENT CI |
| Descripció operació i fiscalitat ampliada | sí | persistides si s'aporten | integration/unit tests nous | PENDENT CI |
| Billing/totals/línies/relacions validats abans de DB | sí | sí a la branca | unit tests nous | PENDENT CI |
| Causa d'exempció E1-E8 + règim EXEMPT | sí | sí a la branca | unit test nou | PENDENT CI |
| Relació no visible si no s'acredita | sí | sí a la branca | test nou | PENDENT CI |
| Document metadata | sí | sí | integration test | IMPLEMENTAT |
| Verificar bytes/hash físic | sí | no | cap | PENDENT |
| Reconciliar inventari complet | sí | no | cap | PENDENT |
| Multiemissor/números homònims | sí | no | cap | BLOQUEJANT ABANS DE LOT REAL |
| operational_event/sif_audit_event | sí com a objectiu | no al servei | cap | PENDENT |
| Autorització productiva | sí com a objectiu | no; script rebutja production | tests estàtics | PENDENT |
| UI/JS d'importació | no necessària per al CLI actual | no | n/a | NO EXISTEIX |
| UI/JS llegada com a origen | sí | sí | revisió de pàgina/JS/endpoints | IMPLEMENTAT |
| Bloqueig de mutacions llegades post-migració | sí | guard existent | codi + relació UC-011 contrastats | PENDENT PROVA FLAGS |

## 3. Persistència real

UC-011 escriu: factura, factura_linia, fact_rels i, opcionalment, factura_documents. La branca també persisteix factura.IDEMPOTENCY_PAYLOAD_HASH i, quan són presents a la font, EMISSOR_NIF/EMISSOR_NOM, DESCRIPCIO_OPERACIO, INVERSIO_SUBJECTE_PASSIU, CAUSA_EXEMPCIO_NO_SUBJECTA i RECARREC_EQUIVALENCIA_* a capçalera/línies.

UC-011 no escriu: factura_registres, factura_registre_control, fiscal_queue, fiscal_sequence, payment_transaction, payment_allocation, operational_event ni sif_audit_event.

Per tant, qualsevol fitxa que afirmi que aquests darrers recursos formen part del commit actual s'ha de considerar corregida per aquesta matriu.

## 4. Correccions de codi d'aquesta auditoria

1. S'elimina el default temporal de issue_date: una factura històrica ha d'aportar data original.
2. S'impedeix que series/year/num_seq contradiguin NUM_VISIBLE.
3. invoice_status queda forçat a HISTORICAL.
4. VISIBLE_ALUMNE per defecte passa de 1 a 0.
5. El repository guarda a IDEMPOTENCY_PAYLOAD_HASH un hash canònic de la projecció material que realment persisteix.
6. En un reintent, aquesta projecció es compara amb el hash guardat; discrepància real o hash absent produeix 409 i rollback, però aliases equivalents no provoquen fals conflicte.
7. Es valida estructura i contingut mínim de billing/totals/línies/relacions, dates, tipus de factura i causa d'exempció.
8. Es preserven emissor, descripció d'operació, inversió del subjecte passiu, causa d'exempció i recàrrec d'equivalència quan la font els aporta.
9. S'afegeixen proves unitàries i d'integració per aquests controls.
10. S'audita el frontend/backend llegat i es documenta el guard que ha de bloquejar mutacions després del cut-over.

## 5. Estat: documentat / implementat / verificat / pendent

| Bloc | Documentat | Implementat | Verificat estàticament | Prova executable | Producció |
| --- | --- | --- | --- | --- | --- |
| preview CLI | sí | sí | sí | test existent | bloquejat explícitament |
| process CLI | sí | sí | sí | test existent | bloquejat explícitament |
| persistència històrica | sí | sí | sí | integration MySQL | no acreditada |
| no VERIFACTU retroactiu | sí | sí | sí | integration MySQL | no acreditada |
| idempotència material normalitzada | sí | sí branca | sí | tests nous, CI pendent | no |
| data/número/estructura/visibilitat | sí | sí branca | sí | tests nous, CI pendent | no |
| emissor i fiscalitat històrica | sí | sí si payload ho aporta | sí | tests nous, CI pendent | no acreditada |
| guard de mutacions llegades | sí | ja existent | sí | prova feature flags pendent | depèn configuració |
| inventari del llegat | sí | no | n/a | no | no |
| custòdia original | sí | no | n/a | no | no |
| auditoria operativa | sí objectiu | no | sí, absència contrastada | no | no |
| multiemissor/preflight | sí | no | sí, risc contrastat | no | no |

## 6. Gaps prioritaris

### BLOQUEJANTS abans d'una migració productiva
- Model de coexistència per emissor + origen + número original; els UNIQUE actuals són globals.
- Preflight contra factura i fiscal_sequence per evitar que l'històric bloquegi emissions noves.
- Inventari i reconciliació completa del lot per ID d'origen, sèrie, any, emissor i imports.
- Canal productiu amb autenticació/rol, correlació i auditoria; els scripts actuals no es poden executar amb SIF_ENV=production.
- Política de custòdia de bytes originals, no només metadata/path/hash declarat.

### IMPORTANTS però separables
- operational_event i sif_audit_event amb actor/request/correlation reals.
- Classificació document original verificat / metadata sense bytes / original absent; el PDF regenerat per `generaFactura(..., true)` no és per si sol un original custodiat.
- Prova de cut-over amb `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1` i UC-007 activa: editar, anul·lar i descarregar/regenerar una factura migrada ha de quedar bloquejat pel guard.
- Proves específiques de col·lisió multiemissor i de numeració històrica vs fiscal_sequence.

## 7. Porta de tancament

UC-011 pot considerar-se tancat documentalment quan les peces d'aquest inventari estan coherents. No es pot considerar tancat operativament/productiu fins que el preflight multiemissor/numeració, la reconciliació del lot, la custòdia documental i la traça d'operador estiguin implementats i provats sobre sif_test/sif_pre amb evidència conservada.

## 8. Navegació

[Fitxa](../06-fitxes-funcionals/uc-011.md) · [UML integrat](uc-011-importar-factura-historica.md) · [Classes](uc-011-classes-actual-final.md) · [Seqüències](uc-011-sequencies-actual-final.md) · [Activitats](uc-011-activitats-actual-final.md) · [Auditoria](05-auditoria-detallada-uc-011-2026-10-03.md)
