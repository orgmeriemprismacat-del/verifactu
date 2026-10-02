# UC-004 · Inventari d'artefactes, diagrames i codi

**Data de tall actualitzada:** 2026-10-02  
**Branca de continuació:** `feat/uc-004-adaptador-servidor-2026-10-02`

Aquest inventari respon una pregunta concreta: **tenim totes les fitxes, tots els tipus de diagrama i el codi necessari per considerar UC-004 tancat?**  
Resposta: **la cobertura documental és completa; el backend SIF i el bridge de la pantalla UC-004 ja estan implementats al codi versionat; en aquesta branca el mutador fiscal llegat queda retirat. Continuen pendents el **renderitzat/custòdia final** del document per UUID, la classificació transversal, l'E2E/preproducció i la sync llegada si encara cal.**

## 1. Artefactes documentals

| Tipus exigit | Fitxer | Estat |
| --- | --- | --- |
| Fitxa funcional | `documentacio/06-fitxes-funcionals/uc-004.md` | EXISTEIX · s'actualitza en aquesta branca |
| Fitxa/UML integrada | `uc-004-emetre-factura-abans-cobrar.md` | EXISTEIX · s'actualitza en aquesta branca |
| Diagrama de cas d'ús | `uc-004-cas-us-actual-final.md` | **CREAT** |
| Diagrama de classes | `uc-004-classes-actual-final.md` | **CREAT** |
| Diagrames de seqüència | `uc-004-sequencies-actual-final.md` | **CREAT** |
| Diagrames d'activitat | `uc-004-activitats-actual-final.md` | **CREAT · 14 diagrames** |
| Traçabilitat/mancances | `uc-004-auditoria-tracabilitat-mancances.md` | **CREAT** |
| Inventari mestre UC-004 | aquest fitxer | **CREAT** |

### Cobertura d'activitats

- Pàgina completa ACTUAL + FINAL.
- P01 accés/càrrega/permisos ACTUAL + FINAL.
- P02 cerca/selecció ACTUAL + FINAL.
- P03 imports/conceptes ACTUAL + FINAL.
- P04 receptor/dades ACTUAL + FINAL.
- P05 emissió ACTUAL + FINAL.
- P06 resultat/document ACTUAL + FINAL.

No cal crear més diagrames només per duplicar informació. Un nou fitxer només és necessari si apareix un apartat funcional nou de la pantalla o un nou component executable.

## 2. Codi ACTUAL localitzat

| Peça | Fitxer / mètode | Estat |
| --- | --- | --- |
| Shell pàgina | `codi-drive/intranet-actual/alumnes-genera-factura-abans-pagar.php` | EXISTEIX |
| Lògica navegador | `codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js` | EXISTEIX |
| Permisos UI | `codi-drive/intranet-actual/js/general.js` | EXISTEIX |
| Càrrega main | `codi-drive/intranet-actual/ajax/mostrarMain.php` | EXISTEIX |
| Cerca inscripcions | `ajax/alumnes/mostrarInformacioInscripcio_generaFactura.php` | EXISTEIX |
| Emissió llegada | `ajax/alumnes/generaFacturaElectronica_Factures.php` | **RETIRADA EN AQUESTA BRANCA · 410 Gone abans de dependències** |
| Dades factura | `ajax/alumnes/mostraDadesFacturaElectronica_Factures.php` | EXISTEIX |
| Inscripcions factura | `ajax/alumnes/mostraInscripcionsFacturaElectronica_Factures.php` | EXISTEIX |
| Preview | `ajax/alumnes/mostraPrevFactura_Factures.php` | EXISTEIX |
| Descàrrega | `ajax/alumnes/descarregaFactura.php` | EXISTEIX |
| Eliminació temporal | `ajax/alumnes/eliminarArxiu.php` | EXISTEIX · no usar com a patró FINAL |
| Implementació monolítica | `codi-drive/intranet-actual/Intranet.php` | EXISTEIX |

## 3. Codi SIF UC-004 existent abans d'aquesta continuació

| Peça | Fitxer | Estat |
| --- | --- | --- |
| Servei de cas | `sif/src/Service/InvoiceBeforePaymentService.php` | EXISTEIX |
| Builder | `sif/src/Service/InvoiceBeforePaymentPayloadBuilder.php` | EXISTEIX |
| Emissor comú | `sif/src/Service/InvoiceService.php` | EXISTEIX |
| Validador factura | `sif/src/Service/InvoicePayloadValidator.php` | EXISTEIX |
| Idempotència payload | `sif/src/Service/PayloadIdempotencyValidator.php` | EXISTEIX |
| Repositori factura | `sif/src/Repository/InvoiceRepository.php` | EXISTEIX |
| Repositori cobertura UC-004 | `sif/src/Repository/InvoiceBeforePaymentCoverageRepository.php` | EXISTEIX |
| Loader selecció | `sif/src/Repository/InvoiceBeforePaymentSelectionRepository.php` | EXISTEIX |
| Resolver receptor | `sif/src/Repository/InvoiceBeforePaymentBillingPartyRepository.php` | EXISTEIX |
| Preparació servidor | `sif/src/Service/InvoiceBeforePaymentLegacyPreparationService.php` | EXISTEIX |
| Assembler servidor | `sif/src/Service/InvoiceBeforePaymentServerPayloadAssembler.php` | EXISTEIX |
| Command preview/confirm | `sif/src/Service/InvoiceBeforePaymentCommandService.php` | EXISTEIX |
| Autenticació API interna | `sif/src/Service/InternalApiAuthenticator.php` | EXISTEIX |
| Autorització UC-004 | `sif/src/Service/InternalInvoiceBeforePaymentScopeResolver.php` | EXISTEIX |
| Anti-replay requests | `sif/src/Repository/InternalApiRequestRepository.php` | EXISTEIX |
| Endpoint HTTP específic | `sif/public/api/factures/before-payment.php` | EXISTEIX |
| Auditoria operacional | `sif/src/Repository/OperationalEventRepository.php` | EXISTEIX · **INTEGRAT EN AQUESTA BRANCA** |
| Seqüència fiscal | `sif/src/Repository/FiscalSequenceRepository.php` | EXISTEIX |
| Preview CLI | `sif/scripts/preview-invoice-before-payment.php` | EXISTEIX |
| Preflight CLI | `sif/scripts/preflight-invoice-before-payment.php` | EXISTEIX |
| Processador CLI | `sif/scripts/process-invoice-before-payment.php` | EXISTEIX |
| Test servei | `InvoiceBeforePaymentServiceTest.php` | EXISTEIX |
| Test flow | `InvoiceBeforePaymentFlowTest.php` | EXISTEIX |
| Tests scripts | preview/preflight/preproduction tests | EXISTEIXEN |

## 4. Codi afegit/modificat en aquesta branca

### 4.1 Builder UC-004 reforçat

`InvoiceBeforePaymentPayloadBuilder` ara:

- exigeix almenys una `relation`;
- exigeix `source_type=INSCRIPCIO`;
- exigeix `source_id` enter positiu;
- rebutja el mateix `source_id` repetit dins de la petició;
- normalitza `relation_type=ORIGIN`;
- continua rebutjant qualsevol bloc inicial `payment`;
- continua forçant `source_channel=INTRANET` i `EMESA_ABANS_COBRAMENT=1`.

### 4.2 Guard concurrent de cobertura a BD

Nou fitxer:

`sif/database/migrations/2026_09_29_000009_guard_uc004_inscription_coverage.sql`

Crea la taula específica `invoice_before_payment_coverage` i la UNIQUE `uq_invoice_before_payment_source (SOURCE_TYPE, SOURCE_ID)`. `InvoiceBeforePaymentCoverageRepository` insereix els claims dins de la mateixa transacció que crea el graf fiscal.

Efecte: dues peticions **UC-004** amb claus idempotents diferents no poden confirmar dues emissions abans de cobrar que reclamin la mateixa inscripció; el conflicte desfà tota la segona transacció. La mateixa migració fa backfill de les factures SIF preexistents marcades `EMESA_ABANS_COBRAMENT=1` a partir de `fact_rels INSCRIPCIO/ORIGIN`, i falla si el passat ja conté una doble reclamació UC-004.

No s'ha aplicat una UNIQUE global sobre `fact_rels`, perquè podria interferir amb variants legítimes d'altres canals (pagador dividit, USOC, grups, rectificacions). La cobertura transversal continua pendent d'una regla de negoci específica.

### 4.3 Tractament del conflicte

`InvoiceService` converteix la col·lisió de `uq_invoice_before_payment_source` en `SifException::conflict(...)` amb codi 409.

### 4.4 Proves noves

`InvoiceBeforePaymentServiceTest` incorpora:

- falta de relations → 422;
- source_type diferent d'INSCRIPCIO → 422;
- source_id duplicat dins petició → 422;
- normalització INSCRIPCIO/ORIGIN;
- dues claus diferents sobre la mateixa inscripció → 409;
- verificació que el segon intent no deixa una segona factura, registre, cua o relació.

## 5. Codi que ENCARA falta després del tall 2026-10-02

| ID | Peça necessària | Estat |
| --- | --- | --- |
| C-01 | Bridge de la pantalla intranet real cap a l'endpoint UC-004 SIF | **IMPLEMENTAT AL MAIN** · JS + proxy + client HMAC |
| C-02 | Autorització d'emissió al backend SIF | **IMPLEMENTADA AL MAIN** · HMAC + actor + rols |
| C-03 | Contracte autenticat / anti-replay servidor-servidor | **IMPLEMENTAT AL MAIN** · timestamp + request UUID + HMAC + claim |
| C-03b | Protecció de la pantalla intranet abans de signar/enviar el command | **IMPLEMENTADA AL MAIN** · sessió/rol vigent + CSRF |
| C-04 | Loader servidor per IDs d'inscripció i deduplicació contra BD llegada | **IMPLEMENTAT A LA BRANCA** · `InvoiceBeforePaymentSelectionRepository` |
| C-04b | Classificador de cobertura transversal entre canals/pagadors | **FALTA; el guard actual és només UC-004** |
| C-05 | Resolver de receptor per ID intern i snapshot fiscal | **IMPLEMENTAT A LA BRANCA** · `InvoiceBeforePaymentBillingPartyRepository` |
| C-06 | Recalculador servidor de línies i total autoritatiu | **IMPLEMENTAT PARCIALMENT A LA BRANCA** · `InvoiceBeforePaymentServerPayloadAssembler`; usa `A_PAGAR` i IVA exempt del contracte vigent, però el classificador comercial/fiscal transversal de descomptes continua pendent |
| C-07 | Preview servidor amb fingerprint/versió abans de confirmar | **IMPLEMENTAT EN CLI + HTTP + UI** · en aquesta branca comprova també coverage UC-004 abans de retornar preview |
| C-08 | Registre `operational_event` / auditoria dins del flux UC-004 | **IMPLEMENTAT EN AQUESTA BRANCA dins la mateixa transacció** |
| C-09 | Compatibilitat llegada post-COMMIT | **DECIDIT:** no crear factura shadow ni sentinel a `FACTURA_RELACIONADA`; adaptar lectures a SIF/read-model. Projeccions futures només no fiscals, idempotents i recuperables |
| C-10 | Document PDF/QR per UUID/snapshot i estat READY/PENDING/ERROR | **PARCIAL** · `DocumentJobRepository` + `InvoiceBeforePaymentDocumentQueueService` encolen PDF idempotent/versionat i retornen PENDING; worker/renderitzat/storage pendents |
| C-11 | Endpoint HTTP UC-004 segur | **IMPLEMENTAT AL MAIN** · `public/api/factures/before-payment.php` |
| C-12 | E2E pantalla → SIF → document → cobrament posterior | **FALTA PROVA** |
| C-13 | Validar backfill de cobertura UC-004 en dades de preproducció i resoldre duplicats històrics, si n'hi ha | **FALTA EXECUCIÓ** |

## 6. Contracte HTTP UC-004 existent

El `main` actual ja conté `public/api/factures/before-payment.php`.

La frontera és servidor-servidor, no browser-direct:

- HMAC SHA-256 sobre mètode, path, timestamp, request UUID, actor, rols i hash del cos exacte;
- tolerància temporal configurable;
- `request_id` únic persistit a `internal_api_request` per evitar replay;
- actor i rols signats;
- `InternalInvoiceBeforePaymentScopeResolver` exigeix rol d'escriptura;
- `preview` no emet i retorna fingerprint;
- `confirm` rellegeix les dues BDs i exigeix el fingerprint esperat;
- no accepta `created_by` des del JSON de negoci;
- no registra cap pagament inicial.

**Implementat al `main`:** `alumnes-genera-factura-abans-pagar.js` consumeix `sifFacturaAbansPagar.php`; `SifInvoiceBeforePaymentAccess` valida sessió/permís/CSRF i `SifInternalApiClient` signa la petició interna. En aquesta branca, el mutador llegat queda explícitament retirat amb `410 Gone`.

## 7. Estat global

**Documentació:** COMPLETA per a l'auditoria UC-004.  
**Diagrames exigits:** COMPLETS.  
**Codi llegat:** LOCALITZAT.  
**Backend SIF UC-004:** IMPLEMENTAT per autenticació interna, preview/confirmació, preparació autoritativa, emissió i cobertura; aquesta branca afegeix auditoria operacional atòmica.  
**Integració de la pantalla real:** **IMPLEMENTADA AL CODI VERSIONAT · preview amb coverage inclòs · PENDENT E2E/PREPRODUCCIÓ.**  
**Tests al repositori:** DEFINITS/AMPLIATS.  
**Tests executats:** els checks anteriors del PR #111 havien passat abans del cutover 410; **la revisió final d'aquesta nova punta de branca queda pendent del rerun CI**.  
**Producció:** NO MODIFICADA / NO VERIFICADA.
