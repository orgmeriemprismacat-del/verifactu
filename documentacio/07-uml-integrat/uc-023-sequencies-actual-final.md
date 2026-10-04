# UC-023 — Seqüències ACTUAL / FINAL

**Data:** 03/10/2026

## 1. ACTUAL — pantalla intranet llegada

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant JS as alumnes-pagaments.js
participant Q as buscarInfomacioPagament.php
participant M as mostrarModalConfPag.php
participant E as efectuarPagament.php
participant L as Objecte Intranet llegat

O->>JS: cerca per DNI/codi/factura
JS->>Q: GET
Q->>L: mostrarPagaments(...)
L-->>Q: HTML
Q-->>JS: HTML
O->>JS: import + data + banc + observacions
JS->>JS: suma() i validacions client
opt factura electrònica/preview
 JS->>M: GET numFact
 M->>L: mostrarModalConfPag(...)
 M-->>JS: HTML
end
O->>JS: confirmar
JS->>E: GET amb id, tipus, pagament, data, banc, obs, factura
E->>L: efectuarPagament(...)
L-->>E: HTML
E-->>JS: HTML
JS-->>O: èxit si el text no conté "error"
```

**No acreditat:** que `L.efectuarPagament()` delegui a `ManualInstallmentPaymentService`.

## 2. ACTUAL — nucli SIF

```mermaid
sequenceDiagram
autonumber
actor A as Adaptador no acreditat
participant S as ManualInstallmentPaymentService
participant IR as ManualPaymentInvoiceRepository
participant B as ManualInstallmentPaymentPayloadBuilder
participant P as PaymentService
participant PR as PaymentRepository
participant DB as MySQL SIF

A->>S: registerByUuid(F,input)
S->>IR: findByUuid(F)
IR->>DB: SELECT factura
alt factura absent
 S--xA: validation 422
else factura existent
 S->>B: forExistingInvoice(F,input)
 B-->>S: CHARGE/MANUAL + allocation
 S->>P: registerPayment(payload)
 P->>DB: BEGIN
 P->>PR: findByIdempotencyKey(K,true)
 alt K existent i payload equivalent
  PR-->>P: moviment existent
  P-->>S: idempotency_reused=true
 else K existent i payload diferent
  P--xS: conflict
 else K nou
  P->>PR: createPayment(payload)
  PR->>DB: INSERT payment_transaction
  PR->>DB: INSERT payment_allocation
  PR->>DB: UPDATE factura.ESTAT_COBRAMENT
  P->>DB: COMMIT
  P-->>S: uuid_payment nou
 end
 S-->>A: uuid_payment + uuid_factura + num_visible
end
```

## 3. ACTUAL en aquesta branca — event immutable opcional

```mermaid
sequenceDiagram
autonumber
actor A as Adaptador
participant B as Builder
participant P as PaymentService
participant DB as SIF

A->>B: F,I,40,D,U,operation_id=E1
B-->>A: K=MANUAL|FRACCIO|EVENT:E1
A->>P: registerPayment(payload E1)
P->>DB: INSERT CHARGE E1
A->>B: mateix F,I,40,D,U,operation_id=E2
B-->>A: K=MANUAL|FRACCIO|EVENT:E2
A->>P: registerPayment(payload E2)
P->>DB: INSERT CHARGE E2
A->>B: reintent operation_id=E1
B-->>A: mateixa K E1
A->>P: registerPayment(payload E1)
P-->>A: reutilitza UUID_PAYMENT E1
```

Si no hi ha `operation_id`, es manté la clau històrica exacta.

## 4. FINAL — registre segur d'una fracció

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant C as InstallmentCommandController
participant Auth as AuthorizationPolicy
participant G as DestinationGuard
participant R as ExternalReceiptReconciler
participant S as ManualInstallmentPaymentService
participant P as PaymentService
participant Audit as Audit/Event repositories
participant DB as SIF

O->>C: POST command + CSRF + request_id + event_id
C->>Auth: assertAllowed(actor,F,I)
Auth-->>C: allowed
C->>G: verify(F,I,amount,event_id)
G->>R: resolve(event_id,channel,reference)
alt ingrés ja registrat
 R-->>G: existing UUID_PAYMENT
 G-->>C: REUSE existing payment
 C->>Audit: terminal REUSED
 C-->>O: resultat tipificat
else relació/quantia/event invàlid
 R-->>G: conflict/not found
 G--xC: reject
 C->>Audit: REJECTED/CONFLICT
 C-->>O: cap CHARGE
else ingrés nou verificat
 R-->>G: verified receipt
 G-->>C: verified destination + pending amount
 C->>Audit: REQUESTED
 C->>S: registerByUuid(F,input amb operation_id)
 S->>P: registerPayment(...)
 P->>DB: moviment + assignació + estat, transaccional
 P-->>S: UUID_PAYMENT
 S-->>C: resultat
 C->>Audit: COMPLETED
 C-->>O: JSON tipificat
end
```

## 5. Punts pendents

- transport POST autenticat;
- vincle factura-inscripció;
- saldo pendent server-side;
- reconciliació global de l'event bancari;
- atribució per inscripció;
- evidència E2E.


## 6. IMPLEMENTAT EN BRANCA — reconciliació cross-channel atòmica

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant I as Intranet POST+CSRF
participant API as /api/payments/installment.php
participant PS as PaymentService
participant PR as PaymentRepository
participant C as payment_external_receipt_claim
participant DB as payment_transaction/allocation

O->>I: confirmar fracció amb operationId i referència/DS_ORDER quan existeix
I->>API: HMAC + actor + rols + payload
API->>PS: registerPayment(payload)
PS->>PR: findByIdempotencyKey(K,true)
alt mateixa K
 PR-->>PS: moviment existent
 PS-->>API: REUSED
else K nova
 PS->>PR: findByExternalReceipt(payload,true)
 alt rebut extern ja reclamat/existent
  PR->>C: resoldre claim
  PR-->>PS: UUID_PAYMENT existent
  PS->>PR: carregar assignacions
  alt import + factura coincideixen
   PS-->>API: REUSED reconciled_existing=true
  else divergència econòmica
   PS--xAPI: 409 CONFLICT
  end
 else rebut extern nou
  PS->>PR: createPayment(payload)
  PR->>DB: INSERT payment_transaction
  PR->>C: INSERT claim únic DS_ORDER/BANK_REF
  PR->>DB: INSERT allocation + recalcular factura
  PR-->>PS: UUID_PAYMENT nou
 end
end
API-->>I: resultat tipificat
```

La restricció única de `payment_external_receipt_claim` és la barrera de concurrència: dues peticions simultànies amb claus funcionals diferents no poden reclamar el mateix fet bancari com dos cobraments.

## 7. Pendents després de la implementació

- configurar URL/secrets/rols als entorns;
- activar `SIF_INSTALLMENT_PAYMENT_ENFORCED=1` a test/pre;
- conservar evidència E2E amb `verify-manual-installment-evidence.php`;
- decidir quins fluxos manuals han d'exigir obligatòriament referència bancària o DS_ORDER, perquè un `operationId` generat per la UI identifica l'intent però no prova per si sol l'existència del moviment bancari;
- retirar el fallback llegat quan el tall SIF estigui validat.
