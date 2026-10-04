# UC-007 · Inventari de codi PHP/JS ACTUAL → FINAL

**Tall consolidat:** 2026-10-04.  
**Auditoria canònica:** [06-auditoria-final-uc-007-2026-10-04.md](06-auditoria-final-uc-007-2026-10-04.md).

## 1. Regla de lectura

- **ACTUAL**: codi que existeix al repositori o al fallback de convivència.
- **FINAL**: frontera que ha de quedar com a camí canònic.
- **Executable**: fitxer realment inclòs/invocat per una superfície.
- **Verificat**: requereix evidència estàtica o prova; existir no és suficient.

## 2. SIF — consulta UC-007

| Fitxer | Responsabilitat | Estat actual | FINAL |
| --- | --- | --- | --- |
| `sif/src/Repository/InvoiceReadRepository.php` | factura, línies, relacions, rectificacions, pagaments, AEAT i metadata document | implementat | conservar |
| `sif/src/Service/InvoiceQueryService.php` | `view/search` read-only | implementat | conservar |
| `sif/src/Service/InvoiceQueryCriteriaValidator.php` | whitelist, formats i `source_type/source_ids` | implementat | conservar |
| `sif/src/Contract/InvoiceVisibilityPolicyInterface.php` | contracte de visibilitat/projecció | implementat | conservar |
| `sif/src/Service/ResolvedInvoiceVisibilityPolicy.php` | FULL/MINIMAL fail-closed | implementat | conservar |
| `sif/src/Service/InternalInvoiceScopeResolver.php` | rols autenticats → scope | implementat | conservar per intranet |
| `sif/src/Service/InvoiceQueryGateway.php` | obliga resolver abans de query | implementat | conservar |
| `sif/src/Service/InternalApiAuthenticator.php` | HMAC, timestamp, request-id, anti-replay | implementat | conservar |
| `sif/public/api/factures/query.php` | POST intern `view/search` | implementat | conservar |
| `sif/scripts/query-invoice.php` | validació CLI no productiva | implementat | suport/proves |

## 3. SIF — document UC-080

| Fitxer | Responsabilitat | Estat actual | FINAL |
| --- | --- | --- | --- |
| `sif/public/api/documents/download.php` | endpoint intern de bytes | implementat | conservar |
| `sif/src/Service/InvoiceDocumentAccessService.php` | autorització, readVerified i audit | implementat | conservar |
| `sif/src/Service/PrivateDocumentStore.php` | root privat, realpath, mida, SHA-256 | implementat | conservar |
| `sif/src/Repository/DocumentAccessRepository.php` | metadata interna inclòs path/hash | implementat | conservar intern |
| `sif/src/Repository/FiscalDocumentAccessRepository.php` | ledger ALLOWED/DENIED/FAILED | implementat | conservar |
| `sif/src/Service/ResolvedDocumentAuthorizationPolicy.php` | document només amb scope FULL | implementat | conservar |

**No confondre projeccions:** UC-007 només exposa metadata documental mínima; path/hash físics no surten al navegador.

## 4. Intranet — `/alumnes/factura/`

| Fitxer | Abans de revalidació | Estat a la branca / FINAL |
| --- | --- | --- |
| `alumnes-factura.php` | carregava dos JS UC-007 | carrega només `alumnes-factura.js?ver=1.1` |
| `js/alumnes-factura.js` | canònic però coexistia amb override | únic JS UC-007 executable |
| `js/alumnes-factura-sif.js` | duplicat divergent | no executable; retirar després d'inventari repo-wide |
| `ajax/alumnes/sifFactures.php` | bridge SIF | conservar |
| `SifInternalApiClient.php` | HMAC server-to-server | conservar |
| `ajax/alumnes/sifDocument.php` | proxy UC-080 | conservar |
| `SifInternalDocumentClient.php` | HMAC document | conservar |
| `SifAuthenticatedActor.php` | actor/rol des de sessió refrescada | conservar |

### 4.1. Fallback de cerca llegat encara executable

| Fitxer | Enduriment actual | FINAL |
| --- | --- | --- |
| `ajax/alumnes/consultaUsuarisFacturaRelacionada.php` | UC-007 usa POST + same-origin + límits; GET compatible temporal | retirar quan no hi hagi callers |
| `ajax/alumnes/mostrarTaulaUsuaris2.php` | POST + same-origin + max 2.000 candidats | retirar |
| `ajax/alumnes/mostrarTotesFacturesUsuari_Factures.php` | POST + same-origin + inputs acotats | retirar |
| `LegacyInvoiceReadContext.php` | sessió/ROLS_VISUALITZAR | retirar amb fallback |
| `LegacyInvoiceReadAuthorization.php` | policy de pàgina | retirar amb fallback |
| `LegacyInvoiceMutationAuthorization.php` | same-origin i rol d'edició quan correspon | mantenir només mentre hi hagi mutacions llegades |
| `SifLegacyInvoiceMutationGuard.php` | bloqueja camí llegat si UC-007 està actiu i la factura ja és SIF | retirar després migració |

## 5. Intranet — fitxa alumne

| Fitxer | Abans de revalidació | Estat a la branca / FINAL |
| --- | --- | --- |
| `alumnes-mostrar-alumne.php` | executava minificat 1.6 + mòdul SIF separat | carrega `alumnes-mostrar-alumne.js?ver=1.7` |
| `js/alumnes-mostrar-alumne.min.js` | obsolet: GET download, `resD`, cleanup GET | no executable des de la pàgina |
| `js/alumnes-mostrar-alumne.js` | font actualitzat | canònic |
| `js/alumnes-mostrar-alumne-sif.js` | override duplicat | no executable |
| `ajax/alumnes/mostraModalConsultaFactura.php` | AL-17 fallback | temporal |
| `ajax/alumnes/descarregaFactura.php` | fallback download | POST + same-origin + guard + `generaFactura(...,true,false)` |

## 6. `Intranet.php` — mètodes llegats auditats directament

La limitació inicial del Contents API s'ha superat llegint el **git blob complet**. Per tant, aquesta auditoria ja no es basa només en wrappers: s'han inspeccionat els cossos reals.

| Mètode | Funció UC-007 | Troballa/correcció |
| --- | --- | --- |
| `buscarUsuaris_Factures` | F02 cerca fallback | inicialitza `$existeixCerca=false`; delimitador DNI/CIF estable |
| `mostrarTaulaUsuaris2_Alumnes` | F03 selector | ordenació acotada i HTML escapat |
| `mostrarTotesFacturesUsuari_Factures` | F04 llistat | títol `$cercaPer` escapat; cel·les ja escapades |
| `modalConsultaInformacio_Factures` | F05 detall | valors visibles escapats |
| `guardarDadesFactura_Factures` | mutació llegada adjacent | wrapper recupera `FACTURA_RELACIONADA` real i impedeix reassignació |
| `modalPrevisualitzaFactura_Factures` | F06 preview | deriva a `generaFactura(...,false)` |
| `modalConsultaFactura_resultatCerca` | AL-17 fallback | consulta factura de la inscripció |
| `generaFactura` | F06/F07 | valors BD escapats; nou `$marcaGenerada=true`; UC-007 passa `false` |
| `anularFactura` | mutació adjacent | no redissenyada en UC-007; protegida al wrapper per POST/rol/guard |

### 6.1. Semàntica `generada`

Abans, `generaFactura($factura,true)` podia executar `updGeneratFactura`.

Ara la signatura és:

`generaFactura($factura, $descarrega, $marcaGenerada = true)`

i **el wrapper UC-007** crida:

`generaFactura((int) $id, true, false)`

Això fa la descàrrega UC-007 read-only sense canviar accidentalment altres callers llegats.

## 7. Proves específiques

| Prova | Cobertura |
| --- | --- |
| `InvoiceQueryServiceTest.php` | read model, zero mutació, criteris, múltiples factures/source_ids |
| `ResolvedInvoiceVisibilityPolicyTest.php` | FULL/MINIMAL/fail-closed |
| `InternalApiAuthenticatorTest.php` | HMAC + replay |
| `InvoiceQueryScriptTest.php` | CLI read-only/no production |
| `Uc007IntranetBoundaryTest.php` | assets executables, deep links, PII POST, guard, escaping, fallback, zero mutació |
| `InvoiceDocumentAccessServiceTest.php` | bytes/hash/root/audit UC-080 |
| `InternalInvoiceScopeResolverTest.php` | rols FULL/MINIMAL/403 |

Estat detallat de CI: [05-evidencia-ci-uc-007-pr135-2026-10-03.md](05-evidencia-ci-uc-007-pr135-2026-10-03.md).

## 8. Peces que no són FINAL

- `alumnes-factura-sif.js`
- `alumnes-mostrar-alumne-sif.js`
- `alumnes-mostrar-alumne.min.js` com a asset d'aquesta pàgina
- wrappers de cerca/preview/download llegats
- reconstrucció PDF viva per `FACTURA_RELACIONADA`
- UPDATE directe de factura llegada

No s'eliminen físicament els JS antics en aquesta branca sense completar l'inventari repo-wide de referències.

## 9. Estat final d'inventari

**Hi ha fitxa, PHP/JS, classes, seqüències, activitats i traçabilitat per UC-007.** El paquet documental 1:1 ja existeix.

Continuen pendents:
- CI del head final;
- E2E/preproducció;
- storage/rols/flags reals;
- regressió de dades històriques;
- retirada definitiva del fallback.
