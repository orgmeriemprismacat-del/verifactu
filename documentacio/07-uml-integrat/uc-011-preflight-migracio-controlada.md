# UC-011 · Preflight de migració controlada

**Data:** 04/10/2026

`preflight-historical-invoices.php` és una porta **read-only** per preparar una execució a `sif_pre`.

## Comprova

- connexió SIF;
- connexió legacy;
- files llegades mínimament vàlides;
- imports ja existents sense ambigüitats;
- imports ja existents sense divergències materials;
- `SIF_DOCUMENT_ROOT` disponible;
- `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1`;
- `SIF_UC007_QUERY_ENABLED=1`.

## Dos resultats diferents

### ready_for_controlled_migration

Pot ser `true` encara que hi hagi files `NOT_IMPORTED`, perquè precisament són les candidates a migrar. Exigeix que la font sigui consistent i que les proteccions de cut-over/storage estiguin preparades.

### ready_to_close_reconciliation

Només és `true` quan l'inventari ja queda completament reconciliat: cap omissió, cap discrepància i tota metadata documental existent verificada.

## Seguretat

- no escriu BD;
- no importa factures;
- no crea documents;
- refusa production;
- retorna `production_authorized=false`.
