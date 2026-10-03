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
P-->>API: actor + emissor + SistemaInformatico server-side
API->>S: issueInvoice(payload)
S->>V: validate(payload)
V-->>S: estructura + sumes coherents
S->>S: si PREPROD/PROD, exigir aeat_fields
S->>DB: BEGIN + lock idempotència
alt factura existent equivalent
 S->>DB: recuperar factura
 S-->>API: resultat reutilitzat
else factura nova
 S->>DB: numeració + cadena + factura/línies/registre/control/cua/relacions
 Note over S,DB: ID_FACTURA_LINIA si origen unívoc
 opt payment inicial no Redsys
  S->>S: exigir movement_date explícita i estable
  alt data absent/buida
   S--xAPI: 422; TransactionRunner fa ROLLBACK
  else data vàlida
   S->>DB: payment_transaction + allocation
  end
 end
 S->>DB: projectar estats factura/cobrament/AEAT/cua/document
 S->>DB: append operational_event + sif_audit_event
 S->>DB: COMMIT
 S-->>API: UUIDs + número + correlació + estats
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

## 4. ACTUAL — `alumnes-factura.php`: consulta SIF i mutació llegada protegida

```mermaid
sequenceDiagram
autonumber
actor U as Usuari intranet
participant JS as alumnes-factura.js
participant Q as sifFactures.php
participant SIF as SIF internal API
participant LEG as endpoint llegat
participant G as SifLegacyInvoiceMutationGuard
U->>JS: cercar factura
JS->>Q: POST search/view
Q->>Q: sessió + same-origin + actor
Q->>SIF: consulta signada
SIF-->>Q: resultats/VIEW
Q-->>JS: dades SIF read-only
alt fila llegada i usuari intenta editar/anul·lar
 U->>JS: guardar/anul·lar
 JS->>LEG: POST
 LEG->>LEG: sessió + rol + same-origin
 LEG->>G: comprovar cobertura SIF
 alt factura governada pel SIF i guard activat
  G--xLEG: 409
  LEG-->>JS: bloqueig
 else no coberta / guard no activat
  LEG->>LEG: mutació llegada
  LEG-->>JS: resultat
 end
end
```

## 5. ACTUAL — `alumnes-genera-factura-abans-pagar.php`: preview i confirmació UC-004

```mermaid
sequenceDiagram
autonumber
actor U as Usuari intranet
participant JS as alumnes-genera-factura-abans-pagar.js
participant P as sifFacturaAbansPagar.php
participant API as /api/factures/before-payment.php
participant C as UC-004 CommandService
participant S as InvoiceService UC-001
U->>JS: seleccionar inscripcions + entitat
JS->>P: POST preview + CSRF
P->>API: petició interna signada
API->>C: reconstruir selecció/receptor/imports
C-->>JS: preview + fingerprint
U->>JS: confirmar
JS->>P: POST confirm + expected_fingerprint
P->>API: confirm signat
API->>C: validar fingerprint/coverage
C->>S: issueInvoice(payload autoritatiu, sense payment)
S-->>C: UUID + número + cobrament PENDING
C-->>JS: resultat
JS-->>U: pas 3
```

## 6. FINAL pendent

```mermaid
sequenceDiagram
autonumber
actor C as Canal autoritzat
participant G as CoverageGuard
participant O as CommercialOperation
participant F as FiscalSnapshotAssembler
participant S as InvoiceService
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
 S-->>C: resultat amb estats
end
```

La seqüència FINAL continua pendent només en la cobertura comercial transversal i l’assembler fiscal servidor complet; la traça i la resposta d’estats ja formen part de l’ACTUAL.

## 7. Evidència 03/10

Les seqüències del nucli, UC-004 i callers Redsys/manuals tenen proves específiques PASS a l’execució del commit `88e5c922…`. La nova validació de `movement_date` queda coberta per `IssueInvoiceTest::testInvoiceInitialPaymentRequiresStableMovementDateBeforeMutation` al commit `276fb390…` i necessita el seu run de CI.
