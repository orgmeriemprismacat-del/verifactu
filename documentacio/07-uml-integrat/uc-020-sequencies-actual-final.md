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

## 2. Seqüència FINAL — decisió comercial i intenció

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant Web as Checkout servidor
participant Hist as LegacyPrismaStudentHistoryRepository
participant Policy as PrismaStudentDiscountPolicy
participant Decision as DiscountDecisionService [PENDENT]
participant DV as discount_validation
participant CO as commercial_operation
participant IV as CourseIntentSnapshotValidator
participant Intent as RedsysPaymentIntentService
participant DB as redsys_payment_intent
participant Bank as Redsys

A->>Web: Confirmar compra
Web->>Hist: findByDocument(DNI)
Hist-->>Web: historial estructurat
Web->>Policy: evaluate(historial)
Policy-->>Web: eligible, rule_version, evidence
Web->>Decision: calcular/autoritzar oferta
Decision->>DV: persistir regla i evidencia
Decision->>CO: persistir gross/discount/net + PRICE_SNAPSHOT
CO-->>Web: UUID_OPERATION / oferta
Web->>Intent: create(CURS, source_id, idpag, expected_amount, snapshot)
Intent->>IV: validate(snapshot,idpag,sourceId,expectedAmount)
IV-->>Intent: OK
Intent->>DB: INSERT/reuse intent
Intent-->>Web: UUID_INTENT
Web->>Bank: Redireccio pel mateix import congelat
```

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

- orquestrador de checkout que creï `discount_validation` i `commercial_operation`;
- vinculació runtime `UUID_OPERATION ↔ UUID_INTENT`;
- substitució de la confiança en imports del navegador;
- test E2E complet des d'historial fins a factura.
