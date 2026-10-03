# UC-022 — Diagrames de seqüència ACTUAL i FINAL

**Data:** 03/10/2026

## 1. ACTUAL — pantalla «Pagaments» i endpoint llegat

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant Page as alumnes-pagaments.php
participant JS as alumnes-pagaments.js
participant Search as buscarInfomacioPagament.php
participant EP as efectuarPagament.php
participant L as Intranet::efectuarPagament
O->>Page: Obre Pagaments
Page->>Page: comprovarSessio.php
Page-->>O: UI
O->>JS: Cerca per DNI/codi regal/factura
JS->>Search: GET criteri únic
Search-->>JS: HTML files
O->>JS: Introdueix import/data/banc
JS->>JS: valida client-side
opt efact=1
 JS->>JS: previsualització/confirmació
end
JS->>EP: GET id,numFact,tipus,pagament,dataPag,banc,obs,efact
EP->>EP: session_start + unserialize
EP->>L: efectuarPagament(...)
L-->>EP: text resultat
EP-->>JS: HTML/text
JS-->>O: èxit si resposta no conté "error"
```

## 2. ACTUAL — nucli SIF manual disponible

```mermaid
sequenceDiagram
autonumber
participant C as Canal no acreditat
participant M as ManualPaymentService
participant IR as ManualPaymentInvoiceRepository
participant B as ManualPaymentPayloadBuilder
participant PS as PaymentService
participant PR as PaymentRepository
participant DB as BD SIF
C->>M: registerByNumVisible/Uuid(input)
M->>IR: find invoice
IR->>DB: SELECT factura
alt No existeix
 IR-->>M: null
 M--xC: 422
else Existeix
 M->>B: forExistingInvoice(uuid,input)
 B-->>M: CHARGE + allocation + idempotency key
 M->>PS: registerPayment(payload)
 PS->>PR: findByIdempotencyKey(... FOR UPDATE)
 alt Clau existent
  PR-->>PS: existing + PAYLOAD_HASH
  PS->>PS: assertSamePayload
  alt equivalent
   PS-->>M: reused UUID_PAYMENT
  else contradictori
   PS--xM: CONFLICT
  end
 else nova
  PS->>PR: createPayment()
  PR->>DB: INSERT payment_transaction
  PR->>DB: INSERT payment_allocation
  PR->>DB: UPDATE factura.ESTAT_COBRAMENT
  PS-->>M: UUID_PAYMENT
 end
 M-->>C: resultat + factura
end
```

## 3. FINAL — transferència d'una sola factura

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Pagaments
participant Auth as InternalApiAuthenticator [IMPLEMENTAT]
participant Bank as BankReceiptResolver [CANAL PENDENT]
participant M as ManualPaymentService
participant PS as PaymentService
participant Audit as PaymentActionAudit [PENDENT]
participant Sync as LegacySync [PENDENT]
O->>UI: Selecciona entrada bancària i factura
UI->>Auth: POST signat HMAC + request UUID + actor/roles
Auth-->>UI: actor autenticat + anti-replay
UI->>Bank: resolve(external_bank_event_id)
Bank-->>UI: import/titular/ref verificats
UI->>M: registerByUuid(uuidFactura,input normalitzat)
M->>PS: registerPayment()
alt reintent equivalent
 PS-->>M: REUSED
else mateixa clau/payload diferent
 PS--xM: CONFLICT
else nou
 PS-->>M: CREATED
end
M-->>UI: UUID_PAYMENT + UUID_FACTURA
UI->>Audit: resultat correlacionat
UI->>Sync: enqueue after commit
UI-->>O: JSON tipificat
```

## 4. FINAL — una transferència, diverses factures

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Pagaments
participant Bank as BankReceiptResolver [PENDENT]
participant Multi as UC-105 MultiInvoiceTransferService [PENDENT]
participant PS as PaymentService
participant DB as Ledger SIF
O->>UI: Selecciona transferència i trams A/B
UI->>Bank: validar entrada única i import total
Bank-->>UI: receipt immutable
UI->>UI: validar suma allocations <= import disponible
UI->>Multi: registerOrAllocate(receipt,[A,B])
Multi->>PS: registerPayment(CHARGE, N allocations)
PS->>DB: 1 payment_transaction + N allocations
PS-->>Multi: UUID_PAYMENT
Multi-->>UI: CREATED/REUSED/CONFLICT
UI-->>O: Resultat sense duplicar l'ingrés
```


**Nota d'implementació:** el SIF ja exigeix `external_bank_event_id` i el persisteix com `PROVIDER_REF`; la resolució/obtenció d'aquest identificador des del banc encara pertany a la integració de la intranet.


## FINAL implementat en branca — factura ja generada

```mermaid
sequenceDiagram
    actor U as Usuari intranet
    participant JS as alumnes-pagaments.js
    participant I as registrarTransferenciaSif.php
    participant G as SifPaymentSessionGuard
    participant C as SifInternalApiClient
    participant A as InternalApiAuthenticator
    participant M as ManualTransferCommandService
    participant PA as PaymentActionGateway
    participant P as PaymentService
    participant L as GeneratedInvoiceLegacyPaymentSyncService

    U->>JS: confirma efact=1 + ID moviment bancari
    JS->>I: POST JSON + CSRF
    I->>G: sessió + rols vigents + CSRF
    G-->>I: actor/roles
    I->>C: comanda UC-022
    C->>A: POST HMAC + request UUID
    A-->>M: actor signat / anti-replay
    M->>PA: CREATE / REQUESTED
    PA->>P: registre dins transacció existent
    P-->>PA: CREATED o REUSED
    PA-->>M: event terminal atòmic
    M-->>L: projectar total confirmat SIF
    alt projecció OK
        L-->>I: SYNCED
        I-->>JS: CREATED/REUSED
    else projecció falla
        L--xI: error
        I-->>JS: 202 PENDING_RETRY
        Note over JS,I: mateix external_bank_event_id reintenta sense duplicar
    end
```

**Frontera:** `efact=0` continua sent emissió + cobrament i no forma part d'UC-022. Una factura absent del SIF es bloqueja i es deriva a migració/reconciliació; no s'autoemet cap substitut.
