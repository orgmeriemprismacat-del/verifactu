# UC-020 — Diagrames de seqüència ACTUAL i FINAL

**Data d'auditoria:** 30/09/2026 · reconciliació runtime 02/10/2026

## 1. Seqüència ACTUAL — preview i alta

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant JS as mostrarInscripcions.min.js
participant P as ajax/calcularPreu.php
participant AP as inc/buscarAlumnePrisMa.php
participant DB as inscripcions/descomptes
participant E as ajax/enviarInscripcio.php

A->>JS: Introdueix DNI / canvia opcio
JS->>P: GET curs, edicio, ID_PREU, DNI, checks
P->>DB: Carregar preu i candidats de descompte
P->>AP: Comprovar Alumne PrisMa
AP->>DB: SELECT historial per DNI
DB-->>AP: files
AP-->>P: boolea elegible
P-->>JS: TIPUS|PREU|MISSATGES
JS->>JS: Desa tipusPreuAplicat/preuInscripcio
A->>JS: Confirmar
JS->>E: tipusDescompte, preuCar, preuDescompte...
E->>DB: INSERT inscripcio
E-->>JS: resultat
```

**Problema de frontera:** la confirmació confia en dades comercials mantingudes al navegador; no existeix una oferta servidor immutable entre preview i commit.

## 2. Seqüència IMPLEMENTADA — pagament CURS amb Alumne PrisMa

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant Pay as pay.prisma.cat
participant Client as SifRedsysCourseIntentClient
participant API as /api/redsys/course-intent.php
participant Course as RedsysCoursePaymentIntentService
participant Price as LegacyPrismaStudentPriceSnapshotResolver
participant Checkout as PrismaStudentCourseCheckoutService
participant Hist as LegacyPrismaStudentHistoryRepository
participant Policy as PrismaStudentDiscountPolicy
participant CO as commercial_operation
participant DV as discount_validation
participant IV as CourseIntentSnapshotValidator
participant Intent as RedsysPaymentIntentService
participant DB as redsys_payment_intent
participant Bank as Redsys

A->>Pay: Continuar amb targeta
Pay->>Client: create(IDPAG, requestedAmount)
Client->>API: POST intern signat
API->>Course: create(...)
Course->>Course: rellegir matrícula i pendent
alt TIPUS_DESC = 1
  Course->>Price: resolve(context)
  Price-->>Course: base/descompte/net històrics
  Course->>Checkout: stageAndCreateIntent(...)
  Checkout->>Hist: findByDocument(DNI)
  Hist-->>Checkout: historial
  Checkout->>Policy: evaluate(historial)
  Policy-->>Checkout: eligible + rule_version + evidence
  Checkout->>CO: create/reuse operació
  Checkout->>DV: create/reuse validation
  Checkout->>Intent: create(CURS,snapshot AP)
  Intent->>IV: validate(snapshot,idpag,sourceId,amount)
  IV-->>Intent: OK
  Intent->>DB: INSERT/reuse intent
  Intent-->>Checkout: UUID_INTENT
  Checkout->>CO: link UUID_INTENT + INTENT_CREATED
  Checkout-->>Course: intent autoritatiu
else no AP
  Course->>Intent: create(CURS,snapshot curs)
  Intent-->>Course: intent autoritatiu
end
Course-->>API: amount + DS_ORDER
API-->>Client: resultat
Client-->>Pay: amount autoritatiu SIF
Pay->>Bank: redirecció Redsys
```

**Important:** aquesta seqüència és executable en el camí de pagament real. El que continua llegat és la generació/acceptació de l'oferta durant preview/alta.

## 3. Seqüència FINAL — callback i factura

```mermaid
sequenceDiagram
autonumber
participant Bank as Redsys
participant CB as RedsysCallbackService
participant Q as Callback queue
participant W as RedsysCallbackWorker
participant D as RedsysCallbackDispatcher
participant H as RedsysCourseInvoiceService
participant B as LegacyCourseInvoicePayloadBuilder
participant I as InvoiceService

Bank->>CB: Callback signat
CB->>CB: validar ordre/import/divisa/terminal contra intent
CB->>Q: encolar si VALIDATED
W->>Q: claim job
W->>D: process(job)
D->>H: issueFromIntentSnapshot(snapshot congelat)
H->>B: build(snapshot)
B->>B: validar base - descompte = total
B-->>H: payload factura
H->>I: issueInvoice(payload)
I-->>H: UUID_FACTURA + UUID_PAYMENT
H-->>W: resultat
W->>Q: markProcessed
```

**Regla:** el callback no reavalua Alumne PrisMa. Consumeix la decisió congelada abans del TPV.

## 4. Invariants CURS implementats

Abans de crear la intenció:

- `source_id == snapshot.inscription.ID`;
- `intent.IDPAG == snapshot.inscription.IDPAG`;
- `expected_amount == snapshot.payment.amount`;
- snapshot amb `inscription`, `course` i `payment` obligatoris;
- si existeix `discount`, exigeix `origin` i `mode`;
- `discount.base - discount.amount == payment.amount`.

## 5. Pendent

- substituir preview/alta llegats perquè la confirmació accepti una oferta servidor i no imports/tipus del navegador;
- integrar, si es manté el disseny, `payment_link` amb les rutes actives de confirmació/pagament;
- ratificar les decisions de negoci de la policy futura;
- test E2E complet navegador → oferta → pagament → callback → factura i evidència de preproducció.
