# UC-007 · Inventari de codi PHP/JS ACTUAL → FINAL

## 1. Regla de lectura

- **ACTUAL**: què existeix avui al repositori i/o al fallback.
- **FINAL**: frontera que ha de quedar com a camí canònic.
- **Executable**: fitxer realment inclòs o invocat per una superfície.
- **No acreditat**: no es considera provat només perquè el fitxer existeixi.

## 2. SIF — consulta

| Fitxer | Funció UC-007 | ACTUAL | FINAL |
| --- | --- | --- | --- |
| `sif/src/Repository/InvoiceReadRepository.php` | lectura exacta factura/relacions/línies/pagaments/documents | implementat | conservar |
| `sif/src/Service/InvoiceQueryService.php` | view/search sense mutació | implementat | conservar |
| `sif/src/Service/InvoiceQueryCriteriaValidator.php` | whitelist i formats | implementat | conservar |
| `sif/src/Contract/InvoiceVisibilityPolicyInterface.php` | contracte d'autorització/projecció | implementat | conservar |
| `sif/src/Service/ResolvedInvoiceVisibilityPolicy.php` | FULL/MINIMAL fail-closed | implementat | conservar |
| `sif/src/Service/InternalInvoiceScopeResolver.php` | rol intern → scope | implementat | conservar per intranet |
| `sif/src/Service/InvoiceQueryGateway.php` | obliga scope server-side | implementat | conservar |
| `sif/src/Service/InternalApiAuthenticator.php` | HMAC/timestamp/anti-replay | implementat | conservar |
| `sif/public/api/factures/query.php` | POST view/search | implementat | conservar |

## 3. SIF — document UC-080

| Fitxer | Funció | ACTUAL | FINAL |
| --- | --- | --- | --- |
| `sif/public/api/documents/download.php` | endpoint intern document | implementat | conservar |
| `sif/src/Service/InvoiceDocumentAccessService.php` | auth + readVerified + audit | implementat parcial entorn | conservar |
| `sif/src/Repository/FiscalDocumentAccessRepository.php` | ledger accessos | implementat | conservar |
| `PrivateDocumentStore` | root privat/hash/mida | implementat | validar runtime |

## 4. Intranet — `/alumnes/factura/`

| Fitxer | ACTUAL main abans revalidació | FINAL/branca |
| --- | --- | --- |
| `alumnes-factura.php` | carregava `alumnes-factura-sif.js` **i** `alumnes-factura.js` | una sola implementació: `alumnes-factura.js?ver=1.1` |
| `js/alumnes-factura.js` | implementació UC-007 completa, hash route | canònica + compatibilitat query UUID |
| `js/alumnes-factura-sif.js` | implementació duplicada, contracte documental divergent | deixa de ser executable; retirar després |
| `ajax/alumnes/sifFactures.php` | sessió/actor + bridge | conservar |
| `SifInternalApiClient.php` | HMAC server-side | conservar |
| `ajax/alumnes/sifDocument.php` | proxy bytes | conservar |
| `SifInternalDocumentClient.php` | HMAC document | conservar |

## 5. Intranet — fitxa alumne

| Fitxer | ACTUAL main abans revalidació | FINAL/branca |
| --- | --- | --- |
| `alumnes-mostrar-alumne.php` | executava `.min.js?ver=1.6` + mòdul SIF separat | executa `alumnes-mostrar-alumne.js?ver=1.7` |
| `js/alumnes-mostrar-alumne.min.js` | **obsolet**: GET download, `resD`, cleanup GET | no executable per aquesta pàgina |
| `js/alumnes-mostrar-alumne.js` | font actualitzat amb SIF + POST | canònic |
| `js/alumnes-mostrar-alumne-sif.js` | override duplicat de `.cns-factura` | deixa de ser executable |
| `ajax/alumnes/mostraModalConsultaFactura.php` | fallback llegat autoritzat | temporal |
| `ajax/alumnes/descarregaFactura.php` | POST + guard + filename segur | temporal |
| `LegacyInvoiceReadContext.php` | rol de lectura server-side | temporal fins retirada llegat |
| `SifLegacyInvoiceMutationGuard.php` | bloqueig de mutació si factura és SIF | necessari durant convivència |

## 6. Codi llegat dins `Intranet.php`

`Intranet.php` és un blob molt gran (~1,4 MB) i el connector no el retorna complet via Contents API. La revalidació del comportament llegat es fonamenta en:
- wrappers que invoquen els mètodes;
- JS que consumeix els wrappers;
- auditoria estàtica anterior que va inventariar els mètodes;
- comportament observable dels contractes d'entrada/sortida.

Això és una **limitació de lectura del connector**, no evidència d'absència. Per tancar runtime cal regressió amb la còpia desplegada/preproducció.

## 7. Artefactes de prova

- `InvoiceQueryServiceTest.php`
- `ResolvedInvoiceVisibilityPolicyTest.php`
- `InvoiceQueryScriptTest.php`
- `InternalApiAuthenticatorTest.php`
- `Uc007IntranetBoundaryTest.php` (nou)
- matriu runtime de `03-proves-pendents-uc-007-implementacio.md`
