# UC-020 — Diagrames de seqüència ACTUAL i FINAL

**Data d'auditoria:** 30/09/2026

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

## 2. Seqüència FINAL — checkout CURS integrat i intenció

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
participant CO as CommercialOperationRepository
participant Party as CommercialOperationPartyRepository
participant Line as CommercialOperationLineRepository
participant DV as DiscountValidationRepository
participant Intent as RedsysPaymentIntentService
participant IV as CourseIntentSnapshotValidator
participant RI as RedsysPaymentIntentRepository
participant Bank as Redsys

A->>Pay: Confirmar pagament
Pay->>Client: create(IDPAG, requestedAmount)
Client->>API: POST signat
API->>Course: create(sifDb, legacyDb, input)
Course->>Course: rellegir matrícula / saldo servidor
alt TIPUS_DESC = 1
    Course->>Price: resolve(context legacy)
    Price-->>Course: base/descompte/net + PRICE_RULE_VERSION
    Course->>Checkout: stageAndCreateIntent(...)
    Checkout->>Hist: findByDocument(DNI)
    Hist-->>Checkout: historial
    Checkout->>Policy: evaluate(historial)
    Policy-->>Checkout: eligible + rule_version + evidence
    Checkout->>CO: find/insert operation
    Checkout->>Party: find/insert participant
    Checkout->>Line: find/insert line
    Checkout->>DV: find/insert validation
    Checkout->>Intent: create(CURS, snapshot autoritatiu)
    Intent->>IV: validate(source,IDPAG,amount,discount)
    IV-->>Intent: OK
    Intent->>RI: insert/reuse intent
    Intent-->>Checkout: UUID_INTENT
    Checkout->>CO: linkIntent + INTENT_CREATED
    Checkout-->>Course: amount autoritatiu + intent
else Altres tarifes
    Course->>Intent: create(CURS, context servidor)
end
Course-->>API: intent
API-->>Client: intent
Client-->>Pay: DS_ORDER + amount autoritatiu
Pay->>Bank: Formulari Redsys
```

**Regla:** el navegador no és l'autoritat de l'import que arriba a Redsys. El servei rellegeix el context servidor; per Alumne PrisMa, el mateix snapshot comercial origina operació, validació, línia i intenció.

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

## 4. Invariants CURS implementats en aquesta branca

Abans de crear la intenció:

- `source_id == snapshot.inscription.ID`;
- `intent.IDPAG == snapshot.inscription.IDPAG`;
- `expected_amount == snapshot.payment.amount`;
- snapshot amb `inscription`, `course` i `payment` obligatoris;
- si existeix `discount`, exigeix `origin` i `mode`;
- `discount.base - discount.amount == payment.amount`.

## 5. Pendent

- retirar completament l'autoritat dels imports/tipus del navegador en l'**alta** llegat, anterior al checkout;
- harmonitzar el checkout CURS amb `PaymentLinkService` / `CommercialOfferService` com a model únic;
- definir pagament Alumne PrisMa fraccionat/reprès, actualment fail-closed;
- ratificar les decisions `UC20-DEC-001…006`;
- conservar evidència E2E/preproducció completa.
