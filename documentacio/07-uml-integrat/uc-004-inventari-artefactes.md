# UC-004 · Inventari d'artefactes, diagrames i codi

**Data de tall:** 2026-09-29  
**Branca:** `docs/auditoria-uc-004-2026-09-29`

Aquest inventari respon una pregunta concreta: **tenim totes les fitxes, tots els tipus de diagrama i el codi necessari per considerar UC-004 tancat?**  
Resposta: **la cobertura documental ja és completa; el nucli SIF és parcialment complet; la integració real de la pantalla encara no.**

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
| Emissió llegada | `ajax/alumnes/generaFacturaElectronica_Factures.php` | EXISTEIX |
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
| Repositori cobertura UC-004 | `sif/src/Repository/InvoiceBeforePaymentCoverageRepository.php` | **CREAT EN AQUESTA BRANCA** |
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

## 5. Codi que ENCARA falta

| ID | Peça necessària | Estat |
| --- | --- | --- |
| C-01 | Adaptador/controlador de la pantalla real cap a UC-004 SIF | **FALTA** |
| C-02 | Autorització d'emissió al backend mutador | **FALTA** |
| C-03 | Protecció CSRF o contracte equivalent de command autenticat | **FALTA PATRÓ GLOBAL** |
| C-04 | Loader servidor per IDs d'inscripció i deduplicació contra BD llegada | **IMPLEMENTAT A LA BRANCA** · `InvoiceBeforePaymentSelectionRepository` |
| C-04b | Classificador de cobertura transversal entre canals/pagadors | **FALTA; el guard actual és només UC-004** |
| C-05 | Resolver de receptor per ID intern i snapshot fiscal | **IMPLEMENTAT A LA BRANCA** · `InvoiceBeforePaymentBillingPartyRepository` |
| C-06 | Recalculador servidor de línies i total autoritatiu | **IMPLEMENTAT PARCIALMENT A LA BRANCA** · `InvoiceBeforePaymentServerPayloadAssembler`; usa `A_PAGAR` i IVA exempt del contracte vigent, però el classificador comercial/fiscal transversal de descomptes continua pendent |
| C-07 | Preview servidor amb fingerprint/versió abans de confirmar | **IMPLEMENTAT A LA BRANCA EN CLI** · `InvoiceBeforePaymentLegacyPreparationService` + `preview/process-invoice-before-payment-from-legacy.php`; pendent connexió UI |
| C-08 | Registre `operational_event` / auditoria dins del flux UC-004 | **FALTA INTEGRAR** |
| C-09 | Sincronització llegada idempotent després del COMMIT SIF | **FALTA / CAL DECIDIR** |
| C-10 | Document PDF/QR per UUID/snapshot i estat READY/PENDING/ERROR | **FALTA INTEGRAR** |
| C-11 | Endpoint HTTP UC-004 segur | **NO CREAT expressament** fins tenir autenticació/CSRF servidor; el flux executable actual és CLI no productiu |
| C-12 | E2E pantalla → SIF → document → cobrament posterior | **FALTA PROVA** |
| C-13 | Validar backfill de cobertura UC-004 en dades de preproducció i resoldre duplicats històrics, si n'hi ha | **FALTA EXECUCIÓ** |

## 6. Per què no s'ha creat encara l'endpoint HTTP UC-004

El repositori SIF conté endpoints JSON funcionals, però en la revisió actual no s'ha localitzat un patró complet d'autenticació/autorització/CSRF reutilitzable per a una ordre fiscal de la intranet.

Crear ara `public/api/factures/before-payment.php` sense aquest contracte ampliaria la superfície d'escriptura abans de poder demostrar qui pot invocar-la. El codi de domini i la restricció de BD sí es poden reforçar ara; l'endpoint ha de néixer ja protegit.

## 7. Estat global

**Documentació:** COMPLETA per a l'auditoria UC-004.  
**Diagrames exigits:** COMPLETS.  
**Codi llegat:** LOCALITZAT.  
**Nucli SIF UC-004:** IMPLEMENTAT PARCIALMENT i reforçat en aquesta branca, inclosa preparació autoritativa des de les dues BDs llegades.  
**Integració real:** PENDENT.  
**Tests al repositori:** DEFINITS/AMPLIATS.  
**Tests executats:** PENDENT d'evidència.  
**Producció:** NO MODIFICADA / NO VERIFICADA.
