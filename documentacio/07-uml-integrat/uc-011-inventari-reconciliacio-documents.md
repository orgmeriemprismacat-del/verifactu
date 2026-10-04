# UC-011 · Inventari, reconciliació i verificació documental

**Data:** 04/10/2026  
**Branca:** `audit/uc-011-2026-10-03`

## Objectiu

Comprovació **read-only** del lot històric que compara la font real `web.factures` amb les factures històriques del SIF i, quan existeix storage privat, verifica els bytes contra el SHA-256 registrat.

## Font llegada contrastada

El codi real de la intranet utilitza `id`, `factura_relacionada`, `any`, `ordre`, `num`, `data`, `data_pagament`, `generada`, `rao`, `cif`, adreça, conceptes, `import`, entitat, forma de pagament, curs, hores, observacions i `E_FACT`.

## Implementació

`HistoricalInvoiceInventoryService`:

1. llegeix `factures` sense escriure;
2. resol SIF per `HISTORIC_WEB_FACTURES + legacy_id`;
3. compara número, data, NIF/CIF, total, `HISTORICAL`, `NO_VERIFACTU` i `factura_relacionada`;
4. classifica `NOT_IMPORTED`, `IMPORTED_MATCH`, `IMPORTED_MISMATCH`, `AMBIGUOUS_SIF_MATCH` o `INVALID_LEGACY_INVOICE`;
5. agrega per **any/sèrie**:
   - recompte;
   - primer/últim número;
   - import llegat total;
   - coincidències, omissions i incidències;
6. comprova `factura_documents`;
7. amb `SIF_DOCUMENT_ROOT`, reutilitza `PrivateDocumentStore::readVerified()`.

## Minimització de dades

L'informe no exposa el NIF/CIF en clar. Utilitza un fingerprint SHA-256 per identificar si els dos costats coincideixen o divergeixen.

Tampoc retorna el path físic del document: conserva `path_hash`, hash declarat, tipus, estat, mida i verificació.

## Script

`sif/scripts/inventory-historical-invoices.php`:

- només CLI;
- connexió SIF + `ConnectionFactory::makeLegacy()`;
- filtre opcional `--legacy-id=<id>`;
- read-only;
- no importa ni actualitza;
- `production_authorized=false`.

## Criteri de reconciliació

`fully_reconciled=true` només quan:

- no hi ha discrepàncies/ambigüitats/factures llegades invàlides;
- no queda cap fila llegada sense importar;
- tota metadata documental existent ha estat verificada físicament.

Una factura sense metadata documental no es declara automàticament errònia, però tampoc acredita custòdia de l'original.

## Límit

Aquest bloc **verifica** bytes ja presents a storage privat. Encara no existeix un procés UC-011 que copiï l'original des de la font llegada cap a `SIF_DOCUMENT_ROOT`.
