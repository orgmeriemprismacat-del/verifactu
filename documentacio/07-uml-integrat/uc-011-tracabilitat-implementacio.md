# UC-011 · Matriu de traçabilitat funcional, codi, UML i proves

**Tall:** 04/10/2026  
**Branca:** `audit/uc-011-2026-10-03`

## 1. Inventari

| Artefacte | Estat |
| --- | --- |
| Fitxa funcional | REESCRITA 3.0 |
| UML integrat | RECONCILIAT |
| Classes ACTUAL/FINAL | CREAT/ACTUALITZAT |
| Seqüències ACTUAL/FINAL | CREAT/ACTUALITZAT |
| Activitats ACTUAL/FINAL | CREAT/ACTUALITZAT |
| Governança preflight/auditoria | CREAT |
| Auditoria detallada | ACTUALITZADA |
| Builder | IMPLEMENTAT/REFORÇAT |
| Preflight | IMPLEMENTAT |
| Service | IMPLEMENTAT/REFORÇAT |
| Repository | IMPLEMENTAT/REFORÇAT |
| Tests unit/integration | AMPLIATS |
| JS executor UC-011 | NO EXISTEIX; canal és CLI |

## 2. Traçabilitat funcional

| Requisit | Documentat | Implementat | Evidència | Estat |
| --- | --- | --- | --- | --- |
| Preservar NUM_VISIBLE | sí | sí | builder/integration | implementat |
| Data original obligatòria | sí | sí | unit test | implementat |
| Coherència components número | sí | sí | unit test | implementat |
| HISTORICAL/NO_VERIFACTU | sí | sí | unit/integration | implementat |
| No factura_registres/fiscal_queue | sí | sí | integration | implementat |
| No tocar fiscal_sequence | sí | sí | integration + governance | implementat |
| VISIBLE_ALUMNE=0 per defecte | sí | sí | tests | implementat |
| Hash idempotent material | sí | sí | tests | implementat |
| Mateixa clau + dades diferents → 409 | sí | sí | integration | implementat |
| Número ocupat altra clau → 409 | sí | sí | governance test | CI pendent |
| Número davant seqüència activa → 409 | sí | sí | governance test | CI pendent |
| Número darrere seqüència → no mutar LAST_NUM | sí | sí | governance test | CI pendent |
| operational_event | sí | sí | governance test | CI pendent |
| sif_audit_event | sí | sí | governance test | CI pendent |
| Emissor/fiscalitat ampliada | sí | sí si font ho aporta | tests | implementat |
| Metadata document | sí | sí | integration | implementat |
| Verificar bytes originals | sí | no | absència de writer/storage | pendent |
| Inventari complet lot | sí | no | — | pendent |
| Multiemissor | sí | no | UNIQUE globals | bloquejant |
| Canal productiu | sí | no | scripts rebutgen production | bloquejant |
| Cut-over llegat | sí | guard existent | revisió codi | prova sif_pre pendent |

## 3. Persistència

### Escriu

`factura`, `factura_linia`, `fact_rels`, `factura_documents` opcional, `operational_event`, `sif_audit_event`.

### No escriu

`factura_registres`, `factura_registre_control`, `fiscal_queue`, `payment_transaction`, `payment_allocation`.

### No modifica

`fiscal_sequence`, `fiscal_chain_state`.

## 4. Proves

### HistoricalInvoicePayloadBuilderTest

Valida número, data, estructura, tipus, fiscalitat, relacions, visibilitat i aliases.

### HistoricalInvoiceMigrationServiceTest

Valida import/reuse, hash idempotent, persistència fiscal històrica, no registre/cua/seqüència.

### HistoricalInvoiceMigrationGovernanceTest

Valida:

1. operational_event + sif_audit_event create/reuse;
2. col·lisió de número amb altra clau;
3. risc contra seqüència activa;
4. import segur darrere LAST_NUM sense alterar-lo;
5. retry idempotent després d'activar seqüència.

Els tres fitxers de governança nous/modificats passen `php -l` local. Resultat MySQL del head actual: pendent dels workflows GitHub.

## 5. PHP/JS

### PHP d'UC-011

Implementació específica completa per import unitari no-production.

### JavaScript

No hi ha JS executor. `alumnes-factura-sif.js` és consulta/cut-over.

## 6. Bloquejants reals

1. **Multiemissor:** `UNIQUE(NUM_VISIBLE)` i `UNIQUE(TIPUS_SERIE,ANY_FACT,NUM_SEQ)` no inclouen emissor.
2. **Custòdia:** metadata de document no equival a bytes originals verificats.
3. **Lot:** no hi ha extractor/inventari/reconciliació completa.
4. **Producció:** no hi ha canal productiu autoritzat.
5. **Cut-over:** manca prova integrada a `sif_pre`.
6. **Política fiscal històrica:** decidir sèrie↔tipus i tolerància totals↔línies.

## 7. Estat global

- **Documentació:** COMPLETA per l'abast auditat.
- **Import unitari no-production:** IMPLEMENTAT.
- **Preflight/auditoria:** IMPLEMENTATS, CI MySQL pendent.
- **Migració massiva productiva:** NO TANCADA.
