# UC-011 · Governança de preflight i auditoria

**Data:** 04/10/2026  
**Branca:** `audit/uc-011-2026-10-03`

## Objectiu

Aquest complement tanca dues mancances detectades a l'auditoria principal sense alterar la numeració fiscal ni inventar un model multiemissor:

1. detectar abans del `INSERT` una col·lisió de número històric;
2. bloquejar una importació que ocupi un número situat per davant d'una `fiscal_sequence` activa;
3. deixar traça funcional i d'auditoria per cada importació o reintent idempotent reeixit.

## Implementació

### Preflight

`HistoricalInvoiceMigrationPreflight::assertSafe()` s'executa dins la mateixa transacció abans del repository.

- Si `NUM_VISIBLE` o `TIPUS_SERIE+ANY_FACT+NUM_SEQ` ja pertanyen a una altra clau idempotent: **409 CONFLICT**.
- Si la mateixa clau idempotent ja existeix: el preflight no bloqueja el reintent i el repository continua sent l'autoritat que compara `IDEMPOTENCY_PAYLOAD_HASH`.
- Si existeix una fila de `fiscal_sequence` per sèrie/any i el número històric és superior a `LAST_NUM`: **409 CONFLICT**. No s'avança ni es modifica la seqüència.
- Si el número històric és igual o inferior a `LAST_NUM` i no està ocupat a `factura`: el preflight permet l'importació i conserva `LAST_NUM`.

Això no resol el model multiemissor; simplement falla de forma explícita abans d'una col·lisió SQL o d'una futura emissió incompatible.

## Auditoria implementada

`HistoricalInvoiceMigrationService` escriu dins la mateixa transacció:

- `operational_event`
  - `OPERATION_TYPE=HISTORICAL_INVOICE_IMPORT`
  - `FISCAL_IMPACT=HISTORICAL_NO_VERIFACTU`
  - `ECONOMIC_IMPACT=NONE`
  - `REASON_CODE=HISTORICAL_INVOICE_IMPORTED|HISTORICAL_INVOICE_REUSED`
- `sif_audit_event`
  - `ACTION=IMPORT_HISTORICAL_INVOICE`
  - `RESULT=SUCCEEDED|REUSED`
  - recurs `FACTURA`
  - `request_id`, `correlation_id`, actor i rol quan s'aporten.

L'auditoria no crea `factura_registres`, `fiscal_queue`, `payment_transaction` ni efectes AEAT.

## Seqüència FINAL implementada

~~~plantuml
@startuml
actor Operador
participant HistoricalInvoiceMigrationService as Service
participant HistoricalInvoiceMigrationPreflight as Preflight
participant HistoricalInvoiceMigrationRepository as Repo
participant OperationalEventRepository as Operational
participant SifAuditEventRepository as Audit
database SIF

Operador -> Service : importHistoricalInvoice(input)
Service -> SIF : BEGIN
Service -> Preflight : assertSafe(db,payload)
Preflight -> SIF : lock número existent
Preflight -> SIF : lock fiscal_sequence sèrie/any
alt col·lisió o risc de seqüència activa
  Preflight --> Service : 409
  Service -> SIF : ROLLBACK
else segur o reintent mateix idempotency_key
  Service -> Repo : importHistoricalInvoice()
  Repo -> SIF : INSERT o REUSE idempotent
  Service -> Operational : append()
  Operational -> SIF : operational_event
  Service -> Audit : append()
  Audit -> SIF : sif_audit_event
  Service -> SIF : COMMIT
end
@enduml
~~~

## Proves creades

`HistoricalInvoiceMigrationGovernanceTest` cobreix:

- event operatiu + event d'auditoria en creació i reús;
- mateix número amb una altra clau idempotent → 409;
- número històric per davant d'una seqüència fiscal activa → 409;
- número històric ja superat per la seqüència → import permès sense alterar `LAST_NUM`;
- reintent idempotent continua funcionant encara que la seqüència s'activi després del primer import.

## Estat restant

Continuen pendents i no s'han maquillat com a resolts:

- model de persistència multiemissor;
- inventari/reconciliació completa del lot llegat;
- custòdia física i verificació real dels bytes originals;
- canal productiu autenticat/autoritzat;
- política definitiva sobre sèrie↔tipus i diferències històriques totals↔línies;
- prova de cut-over en `sif_pre` amb bloqueig de mutacions llegades.
