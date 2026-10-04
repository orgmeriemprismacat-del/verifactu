# UC-011 · Diagrames de seqüència ACTUAL / FINAL

## 1. ACTUAL abans de l'auditoria

~~~mermaid
sequenceDiagram
autonumber
actor O as Operador
participant S as MigrationService
participant B as Builder
participant R as Repository
participant DB as SIF
O->>S: import(input)
S->>B: build()
S->>DB: BEGIN
S->>R: importHistoricalInvoice()
R->>DB: SELECT IDEMPOTENCY_KEY FOR UPDATE
alt existeix
 R-->>S: reuse sense comparar contingut
else nou
 R->>DB: INSERT factura/línies/rels/document metadata
end
S->>DB: COMMIT
S-->>O: resultat
~~~

## 2. FINAL implementat

~~~mermaid
sequenceDiagram
autonumber
actor O as Operador
participant S as MigrationService
participant B as Builder
participant P as Preflight
participant R as Repository
participant I as PayloadIdempotencyValidator
participant OP as OperationalEventRepository
participant AU as SifAuditEventRepository
participant DB as SIF

O->>S: importHistoricalInvoice(input)
S->>B: build(input)
B-->>S: payload validat
S->>DB: BEGIN
S->>P: assertSafe()
P->>DB: SELECT factura per número FOR UPDATE
P->>DB: SELECT fiscal_sequence FOR UPDATE
alt número d'una altra factura o seqüència activa incompatible
 P--xS: 409
 S->>DB: ROLLBACK
else segur / retry mateixa clau
 S->>R: importHistoricalInvoice()
 R->>DB: SELECT IDEMPOTENCY_KEY FOR UPDATE
 alt mateixa clau existent
  R->>I: assertMatches(projecció material,hash)
  alt diferent
   I--xR: 409
  else igual
   R-->>S: reused=true
  end
 else nova
  R->>I: calculateHash(projecció material)
  R->>DB: INSERT factura
  R->>DB: INSERT línies + relacions
  opt document metadata
   R->>DB: INSERT factura_documents
  end
  R-->>S: created
 end
 S->>OP: append event
 OP->>DB: operational_event
 S->>AU: append audit
 AU->>DB: sif_audit_event
 S->>DB: COMMIT
 S-->>O: resultat + correlation_id
end
~~~

## 3. ACTUAL upstream intranet/cut-over

~~~mermaid
sequenceDiagram
actor U as Usuari intranet
participant JS as alumnes-factura-sif.js
participant API as sifFactures.php
participant G as SifLegacyInvoiceMutationGuard
participant L as Llegat

U->>JS: cercar factura
JS->>API: consulta SIF
alt SIF té resultat
 API-->>JS: factura SIF
else no
 JS->>L: fallback consulta llegada
end

U->>L: editar/anul·lar/regenerar
L->>G: verificar govern SIF
alt factura migrada + flags actius
 G--xL: 409
else no governada
 G-->>L: permet
end
~~~

## 4. Seqüència productiva encara pendent

Abans d'un lot real encara cal afegir inventari, autorització, custòdia d'originals i reconciliació de completitud.
