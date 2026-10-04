# UC-011 · Importar factura històrica sense reemetre-la

**Tall reconciliat:** 04/10/2026.  
**Estat:** importador reforçat amb idempotència material, preflight de numeració i auditoria transaccional. Producció encara bloquejada.

## 1. Resum

UC-011 importa una factura anterior al SIF com a `HISTORICAL/NO_VERIFACTU`, preservant número, data, receptor, imports, línies, relacions i fiscalitat disponible. No reemet, no crea registre AEAT nou, no crea cobrament nou i no modifica la seqüència fiscal.

## 2. Cas d'ús

~~~plantuml
@startuml
left to right direction
actor "Operador/procés de migració" as Op
rectangle "SIF" {
  usecase "UC-011
Importar factura històrica" as UC
  usecase "Validar payload històric" as V
  usecase "Preflight numeració" as P
  usecase "Persistir o reutilitzar" as R
  usecase "Auditar import/reús" as A
}
Op --> UC
UC ..> V : <<include>>
UC ..> P : <<include>>
UC ..> R : <<include>>
UC ..> A : <<include>>
@enduml
~~~

## 3. Classes FINAL

~~~mermaid
classDiagram
direction LR
class HistoricalInvoiceMigrationService
class HistoricalInvoicePayloadBuilder
class HistoricalInvoiceMigrationPreflight
class HistoricalInvoiceMigrationRepository
class PayloadIdempotencyValidator
class OperationalEventRepository
class SifAuditEventRepository
class TransactionRunner

HistoricalInvoiceMigrationService --> HistoricalInvoicePayloadBuilder
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationPreflight
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationRepository
HistoricalInvoiceMigrationService --> OperationalEventRepository
HistoricalInvoiceMigrationService --> SifAuditEventRepository
HistoricalInvoiceMigrationService --> TransactionRunner
HistoricalInvoiceMigrationRepository --> PayloadIdempotencyValidator
~~~

## 4. Seqüència FINAL

~~~mermaid
sequenceDiagram
autonumber
actor O as Operador
participant S as MigrationService
participant B as PayloadBuilder
participant P as Preflight
participant R as Repository
participant OP as OperationalEventRepository
participant AU as SifAuditEventRepository
participant DB as MySQL SIF

O->>S: importHistoricalInvoice(input)
S->>B: build(input)
B-->>S: payload HISTORICAL/NO_VERIFACTU
S->>DB: BEGIN
S->>P: assertSafe(db,payload)
P->>DB: lock número + fiscal_sequence
alt conflicte
 P--xS: 409
 S->>DB: ROLLBACK
else segur / retry mateixa clau
 S->>R: importHistoricalInvoice()
 R->>DB: SELECT IDEMPOTENCY_KEY FOR UPDATE
 alt retry
  R->>R: comparar hash material
 else nou
  R->>DB: INSERT factura/línies/rels/document metadata
 end
 S->>OP: append()
 OP->>DB: operational_event
 S->>AU: append()
 AU->>DB: sif_audit_event
 S->>DB: COMMIT
 S-->>O: UUID + correlation_id
end
~~~

## 5. Activitat FINAL

~~~plantuml
@startuml
start
:Llegir payload;
:Validar número, data, receptor, imports i fiscalitat;
:BEGIN;
:Preflight NUM_VISIBLE + sèrie/any/seq + fiscal_sequence;
if (conflicte?) then (sí)
  :409 + ROLLBACK;
  stop
endif
:Buscar IDEMPOTENCY_KEY;
if (existeix?) then (sí)
  :Comparar hash material;
  if (coincideix?) then (sí)
    :REUSE;
  else (no)
    :409 + ROLLBACK;
    stop
  endif
else (no)
  :INSERT factura HISTORICAL/NO_VERIFACTU;
  :INSERT línies i relacions;
  :INSERT metadata document opcional;
endif
:INSERT operational_event;
:INSERT sif_audit_event;
:COMMIT;
stop
@enduml
~~~

## 6. PHP/JS real

La migració s'executa per CLI. No hi ha endpoint/JS d'importació.

La intranet llegada és upstream:

- `alumnes-factura-sif.js` consulta SIF;
- fallback al llegat si no hi ha resultats;
- mutacions/regeneracions passen per `SifLegacyInvoiceMutationGuard`.

## 7. Què queda fora

- model multiemissor;
- inventari complet del llegat;
- custòdia de bytes originals;
- canal productiu autoritzat;
- prova de cut-over `sif_pre`.

## 8. Navegació

[Fitxa funcional](../06-fitxes-funcionals/uc-011.md) · [Classes](uc-011-classes-actual-final.md) · [Seqüències](uc-011-sequencies-actual-final.md) · [Activitats](uc-011-activitats-actual-final.md) · [Traçabilitat](uc-011-tracabilitat-implementacio.md) · [Governança](uc-011-governanca-preflight-auditoria.md)
