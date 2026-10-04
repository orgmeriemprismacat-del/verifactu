# UC-011 · Custòdia privada de documents històrics

**Data:** 04/10/2026  
**Branca:** `audit/uc-011-2026-10-03`

## Objectiu

Incorporar els bytes d'un original històric a `SIF_DOCUMENT_ROOT` sense alterar la factura, la cadena fiscal ni la referència llegada original.

## Disseny implementat

`HistoricalInvoiceDocumentCustodyService`:

- només accepta factures `HISTORICAL/NO_VERIFACTU`;
- només accepta originals PDF/XML;
- calcula mida i SHA-256 directament del fitxer font;
- genera una referència opaca/determinista:
  `historical-invoices/<uuid>/<sha256>.<ext>`;
- crea directoris privats amb permisos restrictius;
- usa escriptura exclusiva i comprova de nou mida/hash;
- és idempotent per factura + tipus + hash;
- rebutja un segon original diferent del mateix tipus;
- preserva `PATH_FITXER` llegat;
- desa la còpia privada a `factura_documents.STORAGE_REF`;
- verifica la còpia amb `PrivateDocumentStore`;
- registra `operational_event` i `sif_audit_event`;
- si la transacció falla després de crear un objecte nou, intenta eliminar-lo.

## Integració documental

La consulta/descàrrega del SIF ha de preferir `STORAGE_REF` quan existeixi i conservar `PATH_FITXER` només com a referència històrica/fallback.

## Script

`sif/scripts/custody-historical-invoice-document.php`

Requereix:

- `--uuid=`
- `--type=PDF|XML`
- `--source-file=`
- `--actor-id=`
- `--correlation-id=` opcional

Refusa `SIF_ENV=production` fins que la via sigui validada a preproducció.

## Regles

- No crea `factura_registres`.
- No crea `fiscal_queue`.
- No modifica `fiscal_sequence`.
- No crea moviments de pagament.
- No substitueix un original per bytes diferents.
- No publica el path font local ni els bytes a la resposta.

## Pendent

La custòdia queda implementada tècnicament, però encara cal:

1. provar-la sobre `sif_pre` amb un original real controlat;
2. decidir el procediment d'obtenció/identificació dels originals en el servidor llegat;
3. conservar evidència de la prova i aprovar l'activació productiva.
