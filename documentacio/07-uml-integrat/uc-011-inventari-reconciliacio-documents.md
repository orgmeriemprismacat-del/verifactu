# UC-011 · Inventari, reconciliació i verificació documental

**Data:** 04/10/2026  
**Branca:** `audit/uc-011-2026-10-03`

## Objectiu

Afegir una comprovació **read-only** del lot històric que compari la font real `web.factures` amb les factures històriques importades al SIF i verifiqui, quan hi ha storage privat configurat, que els bytes documentals coincideixen amb el SHA-256 registrat.

## Font llegada contrastada

El codi real de la intranet utilitza els camps:

- `id`
- `factura_relacionada`
- `any`
- `ordre`
- `num`
- `data`
- `data_pagament`
- `generada`
- `rao`
- `cif`
- `adreca`
- `cp`
- `poblacio`
- `concepte1`
- `concepte2`
- `import`
- `entitat`
- `forma_pagament`
- `curs`
- `hores`
- `observacions`
- `E_FACT`.

## Implementació

`HistoricalInvoiceInventoryService`:

1. llegeix `factures` sense escriure al llegat;
2. resol la factura SIF per `fact_rels(SOURCE_TYPE=HISTORIC_WEB_FACTURES,SOURCE_ID=legacy_id)`;
3. compara:
   - número visible;
   - data d'emissió;
   - NIF/CIF;
   - total;
   - `HISTORICAL`;
   - `NO_VERIFACTU`;
   - `factura_relacionada`;
4. classifica:
   - `NOT_IMPORTED`;
   - `IMPORTED_MATCH`;
   - `IMPORTED_MISMATCH`;
   - `AMBIGUOUS_SIF_MATCH`;
   - `INVALID_LEGACY_INVOICE`;
5. llegeix metadata de `factura_documents`;
6. si `SIF_DOCUMENT_ROOT` està configurat, reutilitza `PrivateDocumentStore::readVerified()` per verificar existència, confinament al storage i SHA-256.

L'inventari **no retorna els bytes** ni el path intern en clar: retorna hash del path, hash declarat, mida i resultat de verificació.

## Script

`sif/scripts/inventory-historical-invoices.php`

- només CLI;
- `ConnectionFactory::makeLegacy()`;
- `--legacy-id=<id>` opcional;
- `read_only=true`;
- `production_authorized=false`;
- no emet factures;
- no importa;
- no actualitza el llegat.

## Criteri de reconciliació

`fully_reconciled=true` només quan:

- no hi ha bloquejos d'identitat/dades;
- no queda cap fila llegada sense importar;
- tota metadata documental existent està verificada físicament.

Una factura sense metadata documental no es converteix automàticament en error de dades; però no acredita custòdia d'original.

## Límit

Aquest bloc verifica documents ja presents a storage privat. **No copia ni ingereix l'original** des del llegat. La custòdia inicial dels bytes continua sent un pas productiu separat.
