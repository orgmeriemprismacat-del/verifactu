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
- registres fiscals a `factura_registres`;
- hash chain global unica per tot el SIF;
- idempotencia amb claus uniques per operacio facturable, pagament i cua.

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

### factura.ESTAT_COBRAMENT

- `PENDING`
- `PARTIAL`
- `PAID`
- `OVERPAID`
- `PARTIALLY_REFUNDED`
- `REFUNDED`

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

### payment_transaction.ESTAT

- `CONFIRMED`
- `PENDING_REVIEW`
- `CANCELLED`
- `ERROR`

### payment_allocation.TIPUS_ASSIGNACIO

- `INVOICE_PAYMENT`
- `PARTIAL_PAYMENT`
- `REFUND`
- `COMPENSATION`
- `ADJUSTMENT`

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

## 6. Contracte funcional SIF

### `issueInvoice()`

`issueInvoice()` crea una factura fiscal nova i es l'unic flux que pot assignar numero fiscal, crear linies fiscals, crear registre fiscal, actualitzar hash chain i inserir la cua AEAT.

Operacions dins la mateixa transaccio:

```text
IDEMPOTENCY_KEY factura
    -> fiscal_sequence
    -> fiscal_chain_state
    -> factura
    -> factura_linia
    -> factura_registres
    -> fiscal_queue
    -> fact_rels
    -> payment_transaction/payment_allocation si el cobrament neix en el mateix flux
```

Si el retry arriba amb la mateixa `IDEMPOTENCY_KEY`, el SIF retorna la factura existent i no crea numero nou.

### `registerPayment()`

`registerPayment()` registra un moviment economic sobre factura existent. Crea `payment_transaction` i `payment_allocation`, recalcula `factura.ESTAT_COBRAMENT` i deixa event/auditoria.

No crea numero fiscal, no modifica linies ni receptor, no crea `factura_registres`, no actualitza `fiscal_chain_state` i no envia res nou a AEAT. Si durant la conciliacio no existeix factura i el cobrament crea obligacio fiscal, el flux correcte es `issueInvoice()` amb bloc `payment`, no `registerPayment()` sol.

## 7. Relacions finals

Relacio Redsys / TPV / SIF:

```text
redsys_notifications.DS_ORDER
    -> payment_transaction.DS_ORDER
    -> payment_transaction.PROVIDER_REF
    -> payment_allocation.UUID_FACTURA
    -> factura.UUID_FACTURA
```

Relacio operativa:

```text
IDPAG / ID_INSC / FACTURA_RELACIONADA
    -> fact_rels
    -> factura.UUID_FACTURA
```

Relacio de linia:

```text
factura_linia.ID
    -> fact_rels.ID_FACTURA_LINIA
    -> origen operatiu concret quan la relacio es de linia
```

Regles:

- `DS_ORDER` ha de ser unic a `redsys_notifications` per evitar reprocessar callbacks;
- `IDPAG` identifica el pagament logic o URL de pagament, pero no substitueix `DS_ORDER`;
- una mateixa `IDPAG` pot tenir diversos intents Redsys amb `DS_ORDER` diferents;
- `payment_transaction` representa el moviment economic real o la compensacio;
- `payment_allocation` aplica el moviment a una o mes factures;
- `fact_rels` es el pont fiscal amb BD antiga i conserva `SOURCE_TYPE`, `SOURCE_ID`, `FACTURA_RELACIONADA`, `IDPAG`, `DS_ORDER` i visibilitat;
- no es creen foreign keys entre BD fiscal i BD web/intranet; la integritat fiscal es controla pel SIF i per `fact_rels`;
- la factura no s'ha de buscar nomes per dades de receptor o import quan existeixin `UUID_FACTURA`, `FACTURA_RELACIONADA`, `IDPAG` o `DS_ORDER`;
- si hi ha diverses candidates, el sistema ha de crear incidencia o requerir seleccio explicita.

## 8. Relacio amb taules historiques

Els camps historics continuen sent utils per migracio, consulta i compatibilitat:

- `web.factures.NUM_COMANDA` conserva la comanda Redsys / `Ds_Order` quan existeix;
- `web.inscripcions.IDPAG` relaciona inscripcions i pagaments antics;
- `web.inscripcions.PAGAMENT`, `DATA PAG`, `PAGAT`, `BANC` i `OBSERVACIONS` poden quedar sincronitzats com a resum operatiu;
- `E_FACT` nomes indica factura electronica;
- `EMESA_ABANS_COBRAMENT` indica factura real emesa abans de cobrar.

La BD fiscal final no ha de dependre d'actualitzacions directes sobre aquests camps per crear factures o cobrar-les. Les actualitzacions historiques han de venir despres de `issueInvoice()` o `registerPayment()`.

La sincronitzacio cap a `web.inscripcions` o `web.factures` nomes pot ser resum operatiu o compatibilitat. La font de veritat per factures VERI*FACTU es `dades_fiscals.factura` i taules relacionades.

## 9. Sequencia, hash chain i concurrencia

Hi ha dues sequencies diferents:

```text
fiscal_sequence
    -> numeracio humana per serie i any
    -> exemple: A2026/000010, R2026/000002

fiscal_chain_state / FISCAL_ORDER
    -> ordre fiscal temporal global
    -> exemple: 1001, 1002, 1003
```

La hash chain no segueix `NUM_SEQ`. Segueix `FISCAL_ORDER`.

Exemple:

```text
FISCAL_ORDER 1001 -> A2026/000010
FISCAL_ORDER 1002 -> R2026/000002
FISCAL_ORDER 1003 -> A2026/000011
```

Regla de concurrencia:

- `fiscal_sequence` es bloqueja per `TIPUS_SERIE + ANY_FACT`;
- `fiscal_chain_state` es bloqueja com a fila unica global;
- la factura, linies, registre fiscal, cua i relacions s'insereixen dins la mateixa transaccio;
- no es pot fer `SELECT MAX(NUM)+1`;
- no es pot reconstruir el hash anterior des d'una lectura no bloquejada.

Ordre de bloqueig recomanat:

```text
1. idempotencia
2. fiscal_sequence
3. fiscal_chain_state
4. inserts finals
```

## 10. Idempotencia per taula

| Taula | Clau idempotent / unica | Risc que evita |
| --- | --- | --- |
| `factura` | `IDEMPOTENCY_KEY UNIQUE` | Dues factures per la mateixa operacio facturable. |
| `payment_transaction` | `IDEMPOTENCY_KEY UNIQUE` | Doble cobrament registrat per reintent o doble clic. |
| `fiscal_queue` | `IDEMPOTENCY_KEY UNIQUE` | Dues entrades AEAT per la mateixa factura. |
| `redsys_notifications` | `DS_ORDER UNIQUE` | Reprocessar el mateix callback Redsys. |
| `factura_registres` | `FISCAL_ORDER UNIQUE` | Fork o duplicacio d'ordre fiscal. |

Una mateixa `IDPAG` pot tenir mes d'un intent de pagament. Per això la idempotencia no pot dependre nomes d'`IDPAG`.

Claus recomanades:

| Operacio | Patró |
| --- | --- |
| Redsys curs | `REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| Redsys pack | `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| Redsys grup | `REDSYS|GRUP|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| Regal | `REDSYS|REGAL|ID:{ID_REGAL}|ORDER:{DS_ORDER}` |
| Factura abans de cobrament | `INTRANET|FACTURA_ABANS_COBRAMENT|REL:{FACTURA_RELACIONADA}|TOTAL:{TOTAL}` |
| Transferencia amb referencia | `TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}` |
| Transferencia sense referencia | `TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}` |
| Compensacio/saldo | `COMPENSATION|CREDIT:{UUID_CREDIT}|FACT:{UUID_FACTURA}|IMPORT:{IMPORT}` |

## 11. Permisos i immutabilitat BD

Regla final:

```text
Factura emesa = no UPDATE/DELETE manual.
Canvi fiscal = rectificativa, event o moviment nou.
```

L'aplicacio ha de bloquejar l'edicio directa i els permisos MySQL han d'impedir que l'usuari operatiu pugui modificar factures emeses. Abans d'emetre factura, les dades fiscals es preparen des de la intranet o el flux de pagament. Despres d'emetre, el SIF conserva el snapshot i qualsevol canvi queda traçat.
