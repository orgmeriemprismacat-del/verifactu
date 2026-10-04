# UC-002 · Diagrames de seqüència ACTUAL / FINAL

**Data:** 2026-10-04  
**Base:** `main@2bd2a751832fc3f767b1e250b955922145a5577a` + branca de reconciliació.

## 1. ACTUAL — registre SIF genèric

~~~mermaid
sequenceDiagram
autonumber
actor Caller
participant API as payments/register.php
participant Auth as InternalApiAuthenticator
participant PS as PaymentService
participant V as PaymentPayloadValidator
participant TX as TransactionRunner
participant R as PaymentRepository
participant DB as MySQL SIF
participant C as PaymentStatusCalculator

Caller->>API: POST body signat
API->>Auth: authenticate(headers, body, POST, path)
Auth->>DB: claim request_id / replay guard
Auth-->>API: actor + roles + request_id
API->>API: comprovar payments.write_roles
API->>PS: registerPayment(payload)
PS->>V: validate(payload)
V->>V: import > 0, <= 2 decimals
V->>V: allocations > 0
V->>V: SUM(allocations) = amount
PS->>TX: run()
TX->>DB: BEGIN
PS->>R: findByIdempotencyKey(key, FOR UPDATE)
alt clau existent
  R-->>PS: payment existent
  PS->>PS: assertSamePayload(v1/v2)
  alt equivalent
    PS-->>API: reused=true + UUID_PAYMENT
  else payload diferent
    PS--xAPI: 409 conflict
  end
else clau nova
  PS->>R: createPayment()
  R->>DB: INSERT payment_transaction
  loop assignacions
    R->>DB: INSERT payment_allocation
    R->>DB: SELECT factura FOR UPDATE
    R->>DB: SUM charges/refunds
    R->>C: calculate(...)
    C-->>R: ESTAT_COBRAMENT
    R->>DB: UPDATE factura.ESTAT_COBRAMENT
  end
  PS-->>API: reused=false + UUID_PAYMENT
end
TX->>DB: COMMIT
API-->>Caller: JSON + actor_id + request_id
~~~

**Implementat:** sí.  
**Pendent:** audit event genèric, evidència externa i ledger genèric per inscripció.

## 2. ACTUAL — pantalla llegada, cerca i confirmació

~~~mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant UI as alumnes-pagaments.js
participant Search as buscarInfomacioPagament.php
participant I as Intranet
participant Modal as mostrarModalConfPag.php

Op->>UI: cerca DNI/codi/factura
UI->>Search: GET criteris
Search->>I: mostrarPagaments(...)
I-->>Search: HTML resultats
Search-->>UI: HTML
Op->>UI: informa import/data/banc
alt efact = 1
  UI->>Modal: GET numFact
  Modal->>I: mostrarModalConfPag(numFact)
  I-->>Modal: HTML confirmació
  Modal-->>UI: modal
end
~~~

La cerca i la modal són lectura. La mutació és una seqüència separada.

## 3. ACTUAL — mutació llegada després de l'auditoria

~~~mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant JS as alumnes-pagaments.js
participant EP as efectuarPagament.php
participant U as Usuari
participant I as Intranet
participant LDB as BD web/intranet llegada
participant Mail as MailSMTPComvive

Op->>JS: confirma pagament
JS->>EP: POST id/tipus/import/data/banc/factura/efact
EP->>EP: validar mètode i no-store
EP->>EP: validar sessió
EP->>EP: validar Sec-Fetch/Origin/Referer/XHR
EP->>I: consultaRolsEdiicio(/alumnes/pagaments/)
EP->>U: tePermisVisualitzacio(roles)
EP->>EP: validar import > 0, data, banc, efact
alt error frontera
  EP--xJS: HTTP 4xx / Error
else autoritzat
  EP->>I: efectuarPagament(...)
  alt efact = 1
    I->>I: efectuarPagamentFacturaGenerada(...)
    I->>LDB: buscar factura i membres
    I->>LDB: UPDATE data_pagament/forma (no IMPORT)
    loop membres
      I->>LDB: acumular PAGAMENT i DATA PAG
    end
    opt fraccionat
      I->>LDB: UPDATE FRACCIO
    end
    I->>Mail: correus confirmació
  else efact = 0
    Note over I,LDB: lògica llegada de factura-en-cobrament, fora del FINAL UC-002
  end
  I-->>EP: retorn llegat
  EP-->>JS: resposta text/html
end
~~~

### Mancances de la seqüència llegada

- No crida el SIF abans de mutar.
- No té idempotency key durable.
- Les escriptures i el correu no formen una única operació retry-safe.
- No deixa `payment_action_event`.
- Si hi ha error a mig procés, no existeix una reconciliació automàtica general.

## 4. ACTUAL — factura existent i pagament parcial

~~~mermaid
sequenceDiagram
autonumber
participant I as Intranet::efectuarPagamentFacturaGenerada
participant F as factures
participant Ins as inscripcions

I->>F: SELECT per NUM
F-->>I: factura_relacionada + pendent/pagat
I->>F: UPDATE data_pagament, FORMA_PAGAMENT
Note over F: IMPORT fiscal es conserva
I->>Ins: SELECT membres ORDER BY ID
loop mentre auxPagat > 0
  Ins-->>I: A_PAGAR, PAGAMENT anterior
  alt nova fracció menor que pendent del membre
    I->>I: pagat = PAGAMENT anterior + auxPagat
    I->>I: auxPagat = 0
  else cobreix el pendent
    I->>I: pagat = A_PAGAR
    I->>I: auxPagat -= pendent
  end
  I->>Ins: UPDATE PAGAMENT = pagat
end
~~~

Aquesta correcció evita perdre l'acumulat anterior i evita alterar el total de factura.

## 5. ACTUAL — reintent SIF equivalent / conflicte

~~~mermaid
sequenceDiagram
autonumber
actor C as Caller
participant P as PaymentService
participant R as PaymentRepository
participant H as PayloadIdempotencyValidator

C->>P: key K + payload A
P->>R: find K FOR UPDATE
R-->>P: payment + hash + version
alt v2
  P->>H: assertMatches(payload A, hash canònic)
else v1
  P->>H: assertMatches(serialització legacy, hash històric)
end
alt equivalent
  P-->>C: reused=true + mateix UUID
else incompatible
  P--xC: 409
end
~~~

Les metadades de traça no alteren el fingerprint v2.

## 6. FINAL — intranet → SIF autoritatiu → sync llegat

~~~mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant UI as Intranet
participant Adapter as Adaptador UC-002
participant Auth as InternalApiAuthenticator
participant Policy as PaymentAuthorizationPolicy
participant Recon as ExternalReceiptReconciler
participant Audit as PaymentActionGateway
participant P as PaymentService
participant Funds as EnrollmentFundAllocationService
participant Sync as LegacyPaymentSync
participant Outbox as NotificationOutbox
participant DB as SIF

Op->>UI: confirma cobrament
UI->>Adapter: comanda + request/idempotency/correlation
Adapter->>Auth: POST signat
Auth-->>Adapter: actor + roles
Adapter->>Policy: authorize(actor, invoice)
Policy-->>Adapter: allow
Adapter->>Recon: verificar referència externa
Recon-->>Adapter: receipt únic
Adapter->>Audit: REQUESTED
Audit->>P: registerPayment(payload)
P->>DB: payment_transaction + allocations + estat
P-->>Audit: UUID_PAYMENT / reused
Audit->>Funds: atribuir per ID_INSC quan aplica
Funds->>DB: enrollment_fund_movement
Audit->>DB: event terminal
Audit-->>Adapter: CREATED/REUSED
Adapter->>Sync: sync post-commit
alt sync falla
  Sync-->>Adapter: PENDING_RETRY
  Note over Adapter,DB: no crear segon CHARGE
else sync ok
  Sync-->>Adapter: SYNCED
end
Adapter->>Outbox: enqueue notificació post-commit
Adapter-->>UI: JSON tipificat
~~~

## 7. FINAL — pèrdua de resposta HTTP

~~~mermaid
sequenceDiagram
autonumber
actor UI
participant API as UC-002 API
participant SIF

UI->>API: request_id R + idempotency K
API->>SIF: crear CHARGE
SIF-->>API: commit UUID P
Note over API,UI: resposta es perd
UI->>API: retry R/K mateix payload
API->>SIF: lookup K + compare payload
SIF-->>API: reused UUID P
API-->>UI: REUSED + UUID P
~~~

No hi ha un segon moviment econòmic.

## 8. FINAL — fallada de sync llegat o correu

~~~mermaid
sequenceDiagram
autonumber
participant SIF
participant Sync
participant Legacy
participant Outbox

SIF->>SIF: commit cobrament
SIF->>Sync: sync result
Sync->>Legacy: actualitzar resum
alt error legacy
  Legacy--xSync: error
  Sync-->>SIF: marcar retry
else ok
  Sync-->>SIF: synced
end
SIF->>Outbox: notificació
Note over SIF,Outbox: cap error secundari reverteix ni duplica el CHARGE
~~~
