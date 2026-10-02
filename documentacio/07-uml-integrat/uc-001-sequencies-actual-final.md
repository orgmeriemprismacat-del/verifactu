# UC-001 · Seqüències ACTUAL / FINAL

## 1. ACTUAL — endpoint intern genèric

```mermaid
sequenceDiagram
autonumber
actor I as Intranet/adaptador intern
participant API as /api/factures/issue.php
participant A as InternalApiAuthenticator
participant R as InternalInvoiceIssueScopeResolver
participant P as InternalInvoiceIssuePayloadPolicy
participant S as InvoiceService
participant V as InvoicePayloadValidator
participant DB as MySQL SIF
I->>API: POST signat + body exacte
API->>A: authenticate(headers,body,POST,signed_path)
A->>DB: claim REQUEST_ID
A-->>API: actor_id + rols
API->>R: resolve(actor)
R-->>API: scope issue
API->>P: prepare(payload,actor)
P-->>API: created_by autenticat / issuer servidor
API->>S: issueInvoice(payload)
S->>V: validate(payload)
V-->>S: estructura + sumes coherents
S->>DB: BEGIN + lock idempotència
alt factura existent equivalent
 S->>DB: recuperar factura
 S-->>API: resultat reutilitzat
else factura nova
 S->>DB: numeració + cadena + factura/línies/registre/cua/relacions
 Note over S,DB: ID_FACTURA_LINIA si origen unívoc
 opt payment inicial no Redsys
  S->>DB: payment_transaction + allocation
 end
 S->>DB: COMMIT
 S-->>API: UUID_FACTURA / número / UUID_PAYMENT opcional
end
API-->>I: JSON
```

## 2. ACTUAL — Redsys separat del generic endpoint

```mermaid
sequenceDiagram
autonumber
actor B as Redsys
participant C as callback.php
participant Q as redsys_callback_queue
participant W as Worker
participant H as Handler Redsys
participant S as InvoiceService
B->>C: callback signat
C->>C: validar signatura/intent/import/moneda/terminal
C->>Q: enqueue
W->>Q: claim
W->>H: snapshot validat
H->>S: issueInvoice(payload REDSYS)
S-->>H: UUID_FACTURA + UUID_PAYMENT
H-->>W: resultat
W->>Q: PROCESSED
```

## 3. ACTUAL — branca AEAT condicional

```mermaid
sequenceDiagram
autonumber
participant S as InvoiceService
participant IR as InvoiceRepository
participant RS as RegistrationSnapshot
participant RF as RecordFactory
participant DB as factura_registres/fiscal_queue
S->>IR: createInvoiceGraph
IR->>RS: invoice(chain,payload,number,issuedAt)
alt sense aeat_fields
 RS-->>IR: null
else amb aeat_fields
 RS->>RF: freeze RegistroAlta
 RF-->>RS: snapshot AEAT
 RS-->>IR: aeat
end
IR->>DB: persistir registre + cua
```

## 4. FINAL pendent

```mermaid
sequenceDiagram
autonumber
actor C as Canal autoritzat
participant G as CoverageGuard
participant O as CommercialOperation
participant F as FiscalSnapshotAssembler
participant S as InvoiceService
participant A as FunctionalAuditWriter
C->>G: ordre + expected version
G->>O: lock i cobertura
alt equivalent
 G-->>C: UUID existent
else conflicte
 G-->>C: CONFLICT
else facturable
 G->>F: snapshot fiscal servidor
 F->>S: payload immutable
 S->>S: emetre/reutilitzar
 S->>A: actor + correlació + resultat
 S-->>C: resultat amb estats
end
```

La seqüència FINAL continua pendent d'implementació completa.
