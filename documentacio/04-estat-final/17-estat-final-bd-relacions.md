# 17 - Estat final de BD i relacions

> Document d'estat final del model de dades. Complementa `05-model-bd-sif.md` i `13-mapa-bases-dades-i-taules.md`.

## 1. Objectiu

Descriure el model final de dades quan el SIF estigui implementat.

## 2. Principis

- imports en `DECIMAL(12,2)`;
- factures immutables;
- snapshot fiscal a `factura`;
- linies a `factura_linia`;
- relacio amb operativa a `fact_rels`;
- pagaments a `payment_transaction`;
- assignacions a `payment_allocation`;
- documents a `factura_documents`;
- registres fiscals a `factura_registres`.

## 3. Estats tipificats

### factura.ESTAT_FACTURA

- `ISSUED`
- `RECTIFIED`
- `CANCELLED`

### factura.ESTAT_AEAT

- `PENDING`
- `SENT`
- `ACCEPTED`
- `REJECTED`
- `RETRY`
- `FAILED`

### fiscal_queue.STATUS

- `PENDING`
- `PROCESSING`
- `RETRY`
- `SENT`
- `FAILED`

### payment_transaction.TIPUS_MOVIMENT

- `CHARGE`
- `REFUND`
- `COMPENSATION`

### payment_transaction.METODE

- `REDSYS`
- `TRANSFERENCIA`
- `COMPENSACIO`
- `MANUAL`

## 4. Dates de registres fiscals

```sql
DATE_CREATED DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
DATE_SENT DATETIME NULL
```

## 5. Separacio factura electronica / factura abans de cobrar

```text
EMESA_ABANS_COBRAMENT = 1
    factura real emesa abans de cobrar.

E_FACT = 1
    factura marcada com a factura electronica.
```

## 6. Relacions finals

Pendent d'ampliar amb diagrames quan es tanqui SQL final.

