# UC-018 · Seqüències ACTUAL/FINAL — Bescanviar regal

## 1. ACTUAL — què passa avui

El repositori acredita la **compra** del regal, però no el bescanvi.

```mermaid
sequenceDiagram
autonumber
actor C as Comprador
participant R as Redsys
participant W as RedsysCallbackWorker
participant G as RedsysGiftInvoiceService
participant L as LegacyGiftInvoicePayloadBuilder
participant I as InvoiceService
participant DB as SIF

C->>R: Compra i paga regal
R->>W: Callback validat / job
W->>G: issueFromIntentSnapshot(...)
G->>L: build(snapshot REGAL)
L-->>G: payload factura compra
G->>I: issueInvoice(payload + CHARGE)
I->>DB: factura + pagament + relació REGAL
I-->>G: UUID_FACTURA + UUID_PAYMENT
G-->>W: compra processada
Note over G,DB: Aquí acaba el codi acreditat del cicle de regal
```

No hi ha una seqüència executable posterior que rebi el codi i creï la inscripció.

## 2. FINAL — preview del bescanvi

```mermaid
sequenceDiagram
autonumber
actor B as Beneficiari
participant UI as Canal bescanvi
participant API as GiftRedemptionController
participant S as GiftRedemptionService
participant E as CommercialEntitlementRepository
participant P as GiftPurchaseRepository
participant A as EnrollmentGateway

B->>UI: codi + curs/edició + dades pròpies
UI->>API: POST preview (CSRF/auth + request id)
API->>S: preview(command)
S->>S: hash(codi) i validar request
S->>E: findByCodeHash(hash)
E-->>S: dret + estat + regla
S->>P: resolvePaidOrigin(dret)
P-->>S: compra/factura/pagament real
S->>A: previewEnrollment(...)
A-->>S: disponibilitat + proposta
S-->>API: resultat sense secrets ni dades fiscals alienes
API-->>UI: previsualització
```

### Invariants del preview

- cap mutació;
- no exposar si un codi desconegut pertany a una persona concreta;
- no retornar factura/PDF del comprador;
- no reconstruir l'import original des del preu viu del curs;
- diferència econòmica explicitada però no cobrada encara.

## 3. FINAL — confirmació nominal

```mermaid
sequenceDiagram
autonumber
actor B as Beneficiari
participant UI as Canal bescanvi
participant API as GiftRedemptionController
participant S as GiftRedemptionService
participant E as CommercialEntitlementRepository
participant A as EnrollmentGateway
participant F as EnrollmentFundMovementRepository
participant X as IncidentLifecycleService

B->>UI: Confirmar
UI->>API: POST redeem + idempotency_key
API->>S: redeem(command)
S->>E: lockByCodeHash(hash)
E-->>S: dret bloquejat

alt dret ja CONSUMED i mateix reintent
 E-->>S: operació anterior
 S-->>API: REUSED + ID_INSC anterior
else EXPIRED/CANCELLED/CONSUMED contradictori
 S-->>API: REJECTED / derivació UC-18a
else dret aplicable
 S->>E: reserve() + event RESERVE
 S->>A: createOrReuseEnrollment(command)
 alt alta falla
  A--xS: error
  S->>E: release() o markIncident()
  S->>X: obrir incidència correlacionada si no es pot revertir net
  S-->>API: PENDING_RECONCILIATION/ERROR
 else alta confirmada
  A-->>S: ID_INSC
  S->>F: append(REGAL -> ID_INSC, origen pagament UC-017)
  S->>E: consume() + event CONSUME
  S-->>API: CONSUMED + ID_INSC
 end
end
API-->>UI: resultat tipificat
```

## 4. FINAL — concurrència

```mermaid
sequenceDiagram
participant A as Request A
participant B as Request B
participant E as commercial_entitlement
participant S as GiftRedemptionService

A->>S: redeem(K)
B->>S: redeem(K)
S->>E: SELECT ... FOR UPDATE
Note over E: només una transacció adquireix el lock
S->>E: RESERVED/CONSUMED + operació X
S-->>A: SUCCESS X
B->>E: lock després del commit
E-->>B: CONSUMED + X
B-->>B: comparar idempotency/payload
alt equivalent
 B-->>B: REUSED X
else contradictori
 B-->>B: CONFLICT
end
```

## 5. FINAL — diferència de preu

Per un regal ja cobrat de 100 € aplicat a un curs de 120 €:

1. reservar el dret de 100 €;
2. no crear cap `CHARGE` nou pels 100 €;
3. generar una obligació/intenció separada de 20 €;
4. només completar el consum quan la regla aprovada ho permeti;
5. mantenir dues procedències de fons diferenciades.

La política exacta de regal de valor superior al curs i del romanent continua essent una decisió de negoci/fiscal pendent; no s'ha d'inventar en codi.

## 6. Estat

| Seqüència | ACTUAL | FINAL documentat | Testada |
| --- | --- | --- | --- |
| Compra UC-017 | Sí | Sí | Sí, proves existents UC-017 |
| Preview UC-018 | No | Sí | No |
| Redeem UC-018 | No | Sí | No |
| Idempotència/concurrència | No | Sí | No |
| Error alta / release | No | Sí | No |
| Diferència addicional | No | Parcial (regla pendent) | No |
