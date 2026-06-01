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
- callbacks Redsys a `redsys_notifications`;
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

### redsys_notifications.STATUS

- `RECEIVED`
- `VALIDATED`
- `DUPLICATE`
- `PROCESSED`
- `ERROR`

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

Relacio Redsys / TPV / SIF:

```text
redsys_notifications.DS_ORDER
    -> payment_transaction.PROVIDER_REF / DS_ORDER
    -> payment_allocation.UUID_FACTURA
    -> factura.UUID_FACTURA
```

Relacio operativa:

```text
IDPAG / ID_INSC / FACTURA_RELACIONADA
    -> fact_rels
    -> factura.UUID_FACTURA
```

Regles:

- `DS_ORDER` ha de ser unic a `redsys_notifications` per evitar reprocessar callbacks;
- `IDPAG` identifica el pagament logic o URL de pagament, pero no substitueix `DS_ORDER`;
- una mateixa `IDPAG` pot tenir diversos intents Redsys amb `DS_ORDER` diferents;
- `payment_transaction` representa el moviment economic real o la compensacio;
- `payment_allocation` aplica el moviment a una o mes factures;
- la factura no s'ha de buscar nomes per dades de receptor o import quan existeixin `UUID_FACTURA`, `FACTURA_RELACIONADA`, `IDPAG` o `DS_ORDER`;
- si hi ha diverses candidates, el sistema ha de crear incidencia o requerir seleccio explicita.

## 7. Relacio amb taules historiques

Els camps historics continuen sent utils per migracio, consulta i compatibilitat:

- `web.factures.NUM_COMANDA` conserva la comanda Redsys / `Ds_Order` quan existeix;
- `web.inscripcions.IDPAG` relaciona inscripcions i pagaments antics;
- `web.inscripcions.PAGAMENT`, `DATA PAG`, `PAGAT`, `BANC` i `OBSERVACIONS` poden quedar sincronitzats com a resum operatiu;
- `E_FACT` nomes indica factura electronica;
- `EMESA_ABANS_COBRAMENT` indica factura real emesa abans de cobrar.

La BD fiscal final no ha de dependre d'actualitzacions directes sobre aquests camps per crear factures o cobrar-les. Les actualitzacions historiques han de venir despres de `issueInvoice()` o `registerPayment()`.
