# UC-002 · Seqüències ACTUAL / FINAL — Registrar pagament

**Data:** 2026-10-03 · **Base:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`

## 1. ACTUAL — registre genèric SIF

~~~mermaid
sequenceDiagram
autonumber
actor Caller as Caller
participant PS as PaymentService
participant V as PaymentPayloadValidator
participant TX as TransactionRunner
participant R as PaymentRepository
participant DB as MySQL SIF
participant C as PaymentStatusCalculator

Caller->>PS: registerPayment(payload)
PS->>V: validate(payload)
V->>V: amount > 0
V->>V: allocations > 0
V->>V: SUM(allocations) = amount
V-->>PS: payload validat
PS->>TX: run()
TX->>DB: BEGIN
PS->>R: findByIdempotencyKey(key, FOR UPDATE)
alt existeix
  R-->>PS: payment existent
  PS->>PS: assertSamePayload(v1/v2)
  alt equivalent
    PS-->>Caller: reused=true, UUID_PAYMENT
  else diferent
    PS--xCaller: 409 conflict
  end
else nou
  PS->>R: createPayment()
  R->>DB: INSERT payment_transaction
  loop allocations
    R->>DB: INSERT payment_allocation
    R->>DB: SELECT factura TOTAL FOR UPDATE
    R->>DB: SUM charges/refunds
    R->>C: calculate(...)
    C-->>R: estat
    R->>DB: UPDATE factura.ESTAT_COBRAMENT
  end
  PS-->>Caller: reused=false, UUID_PAYMENT
end
TX->>DB: COMMIT
~~~

**Acreditat:** transacció, hash idempotent versionat, assignacions, recalcul d’estat i no reemissió fiscal.

## 2. ACTUAL — pagament manual sobre factura existent

~~~mermaid
sequenceDiagram
autonumber
actor Op as Operador/CLI
participant M as ManualPaymentService
participant I as ManualPaymentInvoiceRepository
participant B as ManualPaymentPayloadBuilder
participant P as PaymentService
participant DB as SIF

Op->>M: registerByUuid() / registerByNumVisible()
M->>I: localitzar factura
I->>DB: SELECT factura
alt no existeix
 I--xM: 422
 M--xOp: error
else existeix
 I-->>M: factura
 M->>B: forExistingInvoice()
 B-->>M: CHARGE INTRANET + 1 allocation
 M->>P: registerPayment(payload)
 P-->>M: UUID_PAYMENT + reused
 M-->>Op: resultat + UUID_FACTURA + NUM_VISIBLE
end
~~~

El builder manual exigeix import positiu i fa coincidir el moviment amb una única assignació. La validació comuna ara imposa la mateixa conservació monetària a qualsevol caller.

## 3. ACTUAL — pantalla llegada «Passar pagaments»

~~~mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant JS as alumnes-pagaments.js
participant EP as efectuarPagament.php
participant U as Usuari
participant I as Intranet (implementacio absent)
participant Legacy as BD/codi llegat no traçat

Op->>JS: confirma cobrament
JS->>EP: POST id, tipus, import, data, banc, factura, efact
EP->>EP: valida sessio
EP->>EP: valida Sec-Fetch/Origin/Referer/XHR
EP->>I: consultaRolsEdiicio(/alumnes/pagaments/)
EP->>U: tePermisVisualitzacio(roles)
alt denegat o input invalid
 EP--xJS: HTTP 4xx + Error
else autoritzat
 EP->>I: efectuarPagament(...)
 Note over I,Legacy: cos del metode no disponible al repositori
 I-->>EP: resposta legacy
 EP-->>JS: HTML/text
 JS-->>Op: modal resultat
end
~~~

**No acreditat:** que aquest camí registri `payment_transaction`, clau idempotent SIF, audit event o ledger per inscripció.

## 4. ACTUAL — API genèrica SIF

~~~mermaid
sequenceDiagram
autonumber
actor Client as Client HTTP
participant E as api/payments/register.php
participant P as PaymentService
Client->>E: JSON
Note over E: no POST-only explícit
Note over E: no InternalApiAuthenticator
Note over E: no allowlist de rols
E->>P: registerPayment(payload)
P-->>E: resultat
E-->>Client: JSON
~~~

Aquesta seqüència és una **mancança de frontera**: altres endpoints interns recents ja utilitzen HMAC, request-id/replay guard i rols.

## 5. ACTUAL — reintent equivalent i conflicte

~~~mermaid
sequenceDiagram
autonumber
actor C as Caller
participant P as PaymentService
participant R as PaymentRepository
participant H as PayloadIdempotencyValidator
C->>P: key K + payload A
P->>R: find K FOR UPDATE
R-->>P: payment + HASH_VERSION + HASH
alt V2
 P->>H: assertMatches(payload A, hash canonic)
else V1 historic
 P->>H: assertMatches(json legacy, hash v1)
end
alt equivalent
 P-->>C: reused=true, mateix UUID
else incompatible
 P--xC: 409 conflict
end
~~~

## 6. FINAL — pantalla/intranet → SIF autoritatiu

~~~mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant UI as Intranet
participant A as Adaptador UC-002
participant Auth as InternalApiAuthenticator
participant Policy as PaymentAuthorizationPolicy
participant Recon as ExternalReceiptReconciler
participant Audit as PaymentActionGateway
participant P as PaymentService
participant Funds as EnrollmentFundAllocationService
participant Sync as LegacyPaymentSync
participant DB as SIF

Op->>UI: confirma cobrament
UI->>A: POST + actor + request_id + correlation_id
A->>Auth: HMAC + replay guard
Auth-->>A: actor/roles
A->>Policy: authorize(actor, invoice/payment)
Policy-->>A: allow
A->>Recon: verificar referencia bancaria/TPV
Recon-->>A: receipt unic
A->>Audit: REQUESTED
Audit->>P: registerPayment(payload)
P->>DB: payment + allocations + estat
P-->>Audit: UUID_PAYMENT
Audit->>Funds: atribucions per ID_INSC
Funds->>DB: enrollment_fund_movement
Audit->>DB: payment_action_event SUCCEEDED/REUSED
Audit-->>A: resultat tipificat
A->>Sync: sync post-commit
Sync-->>A: ok/retry independent
A-->>UI: JSON CREATED/REUSED/CONFLICT/PENDING_RETRY/ERROR
UI-->>Op: resultat
~~~

## 7. FINAL — fallada de sincronització llegada

~~~mermaid
sequenceDiagram
autonumber
participant SIF as SIF
participant Sync as LegacyPaymentSync
participant Legacy as Legacy
SIF->>SIF: commit payment
SIF->>Sync: syncAfterSifCommit
Sync->>Legacy: actualitzar resum
alt falla
 Legacy--xSync: error
 Sync-->>SIF: PENDING_RETRY
 Note over SIF: no crear segon CHARGE
else ok
 Sync-->>SIF: SYNCED
end
~~~

El fet econòmic confirmat al SIF no es desfà ni es duplica per una fallada acadèmica/llegada posterior.
