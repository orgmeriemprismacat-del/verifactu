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
JS->>E: tipusDescompte, preuCar, preuDescompte... (proposta client)
alt tipusDescompte = 1
  E->>DB: Rellegir TIPUS_CURS, historial i tarifa base/AP
  DB-->>E: metadades i tarifa servidor
  E->>E: Rebutjar AP+promoció/incoherència i fixar preu AP servidor
end
E->>DB: INSERT inscripcio amb import autoritatiu per AP
E-->>JS: resultat
```

**Problema de frontera revalidat:** el navegador encara transporta globals comercials, però per Alumne PrisMa la confirmació rellegeix i imposa l'autoritat monetària de servidor. El buit que resta és de model: preview i commit no comparteixen encara una oferta servidor immutable/`offer_id`, i la protecció equivalent no està generalitzada a tots els tipus de descompte.

## 2. Seqüència FINAL — decisió comercial i intenció

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant Web as Checkout servidor
participant Hist as LegacyPrismaStudentHistoryRepository
participant Policy as PrismaStudentDiscountPolicy
participant Decision as PrismaStudentDiscountPolicy [IMPLEMENTAT]
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
Decision->>CO: persistir BILLABLE / READY_FOR_PAYMENT + imports + PRICE_SNAPSHOT
CO-->>Web: UUID_OPERATION / oferta
Web->>Intent: create(CURS, source_id, idpag, expected_amount, snapshot)
Intent->>IV: validate(snapshot,idpag,sourceId,expectedAmount)
IV-->>Intent: OK
Intent->>DB: INSERT/reuse intent
Intent-->>Web: UUID_INTENT
Web->>Bank: Redireccio pel mateix import congelat
```

## 2.1. Seqüència FINAL canònica — payment_link (infra implementada, wiring pendent)

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant Web as P03/P04 canònic
participant Link as PaymentLinkService
participant CO as commercial_operation
participant Intent as RedsysPaymentIntentService
participant Bank as Redsys

A->>Web: Obrir token de pagament
Web->>Link: resolve(token)
Link->>CO: findByUuid(UUID_OPERATION)
alt CLASSIFICATION != BILLABLE
  Link-->>Web: 409 no billable
else STATUS no és READY_FOR_PAYMENT/PAYMENT_PENDING
  Link-->>Web: 409 no pagable
else Operació pagable
  Link-->>Web: UUID_OPERATION + EXPECTED_AMOUNT + estat
  Web->>Intent: crear/reutilitzar intenció des del snapshot autoritatiu
  Intent-->>Web: DS_ORDER / UUID_INTENT
  Web->>Bank: redirecció
end
```

**Estat:** el guard `PaymentLinkService → commercial_operation` és executable. L'entrada de les pantalles llegades P03/P04 encara no usa aquest servei com a ruta canònica; per això el wiring continua `PENDENT MIGRACIÓ`.

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

## 5. Estat de tancament

- l'alta AP llegada encara no crea una oferta SIF nativa, però **revalida al servidor** historial i tarifa abans de persistir;
- la resolució d'intranet actual ja és POST + sessió + permís + CSRF + `requestId`;
- les decisions UC20-DEC-001…006 queden tancades a `ALUMNE_PRISMA_WEB_LEGACY_V2`;
- el guard de pagabilitat de `payment_link` ja està implementat; continuen pendents l'adopció canònica per P03/P04, la unificació de transferència i l'E2E navegador → callback → factura.

El **checkout de targeta actiu** crea operació/validació/intenció i vincula `UUID_OPERATION ↔ UUID_INTENT` via `course-intent`. El navegador pot continuar mostrant un preview llegat, però ja no pot fixar l'import AP persistit ni el que s'envia finalment a Redsys.

### 5.1. Tall temporal de l'elegibilitat

Abans de crear la intenció, `PrismaStudentCourseCheckoutService` exclou la matrícula actual de l'historial i només admet antecedents amb `DATA_INSC <= DATA_INSC` de la matrícula tarifada. Això elimina autoacreditació i elegibilitat retroactiva.


## 6. Revalidació de seqüències — 03/10/2026

- **Alta web AP:** el navegador proposa TIPUS/import, però `enviarInscripcio.php` rellegeix historial i tarifa; UC020-94 garanteix que la tarifa servidor no torna a ser sobreescrita abans de persistir.
- **Checkout targeta:** `SifRedsysCourseIntentClient` → `course-intent.php` → `RedsysCoursePaymentIntentService` → checkout AP → intenció autoritativa.
- **Payment link:** `PaymentLinkService` ja bloqueja operacions que no siguin `BILLABLE` o que no estiguin `READY_FOR_PAYMENT/PAYMENT_PENDING`; les pantalles llegades encara no hi entren canònicament.
- **Callback:** valida signatura/DS_ORDER/import/moneda/terminal contra la intenció i encola; no reavalua AP.
- **Worker/factura:** el `main` vigent disposa de prova E2E simulada de callback → worker → pagament/factura/sync/outbox.
- **Pendent:** E2E real navegador/Redsys/preproducció i migració de tots els canals a la mateixa oferta/`payment_link`.

## 7. P06 · canvi de curs — autoritat AP servidor

```mermaid
sequenceDiagram
autonumber
actor S as Secretaria
participant E as realitzarCanviCurs_CanviCurs.php
participant L as BD legacy
participant R as resolveLegacyPrismaStudentCourseChangePrice
participant P as SIF course-change preview
participant I as Intranet::realitzarCanviCurs_modalCanviCurs

S->>E: POST canvi + CSRF
E->>E: sessió + same-origin + permís
E->>L: loadLegacyCourseChangeSource(idInsc)
L-->>E: CURS, A_PAGAR, PAGAMENT, TIPUS_DESC, VALID_DESC
alt TIPUS_DESC = 1
  E->>R: any/mes/curs destí
  R->>L: edició única + preu base + tarifa AP exacta
  L-->>R: ID_PREU, HORES, PREU
  R-->>E: tarifa AP autoritativa
  E->>E: substituir A_PAGAR/PAGAT/PENDENT client
end
opt SIF_COURSE_CHANGE_PREVIEW_ENFORCED=1
  E->>P: preview signat amb import autoritatiu
  P-->>E: impacte fiscal/econòmic + can_confirm
end
E->>I: executar mutació amb valors servidor
```

**Límit pendent:** aquesta frontera ja elimina l'autoritat monetària del navegador per AP i evita seleccionar una tarifa d'un altre curs/edició, però l'elegibilitat P06 continua amb la regla legacy pròpia i encara no reutilitza `PrismaStudentDiscountPolicy`.


## 8. Seqüència FINAL de reintent — 04/10/2026

```mermaid
sequenceDiagram
autonumber
participant C as Canal pagament
participant S as PrismaStudentCourseCheckoutService
participant O as CommercialOperationRepository
participant P as CommercialOperationPartyRepository
participant L as CommercialOperationLineRepository
participant I as RedsysPaymentIntentRepository

C->>S: repetir checkout matrícula + DS_ORDER
S->>O: findByIdempotencyKey(FOR UPDATE)
O-->>S: operació existent
S->>S: validar NET_AMOUNT + PRICE_SNAPSHOT_JSON
S->>P: findByOperationAndRole(PARTICIPANT, FOR UPDATE)
P-->>S: participant existent
S->>S: validar PARTY_KEY/NIF/nom/producte/edició/import
S->>L: findByOperationAndOrder(1, FOR UPDATE)
L-->>S: línia existent
S->>S: validar producte/participant/net/rule version
S->>I: findByUuid(UUID_INTENT, FOR UPDATE)
I-->>S: DS_ORDER congelat
alt qualsevol divergència
  S-->>C: 409 + rollback
else coherent
  S-->>C: reutilització idempotent
end
```

Aquest delta és **IMPLEMENTAT** al HEAD 04/10 i **PENDENT_CI_HEAD**. Preserva l'exclusió de matrícula actual, `evaluation_at=DATA_INSC` i `CLASSIFICATION=BILLABLE`.
