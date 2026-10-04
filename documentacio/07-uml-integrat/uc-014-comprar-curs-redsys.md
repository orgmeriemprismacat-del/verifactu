# UC-14 · Comprar un curs normal per Redsys — fitxa i UML integrats

**Objectiu:** convertir una compra de curs/edició pagada realment per Redsys en una factura SIF i un cobrament econòmic atribuït a la **inscripció correcta**. Una intenció pendent, un callback denegat i un cobrament confirmat **no són el mateix estat**. Aquest cas és de compra de **curs ordinari**; taller i jornada tenen variants UC-14a/14b que no es donen per cobertes per aquesta fitxa.

**Codi contrastat:** `RedsysPaymentIntentService`/`RedsysCoursePaymentIntentService` (UC-63/UC-14), `RedsysCallbackService`/`RedsysCallbackWorker` (UC-03), `RedsysCourseInvoiceService`, `InvoiceService`, `CourseEnrollmentFundAllocationService`, `EnrollmentFundMovementRepository`, `CourseLegacyPaymentSyncService`, `CoursePaymentNotificationService`, `RedsysCoursePaymentStatusService`, PHP/JS ACTUAL i el pont candidat de `pay.prisma.cat`. **Revisió 02/10/2026:** intenció, callback, cua/worker, emissió, cobrament, atribució quantitativa per inscripció, projecció llegada, outbox i consulta read-only d'estat són implementats al repositori; Redsys real de preproducció i cutover continuen sense acreditar.

## 1. Fitxa del cas

| Camp | Contracte |
| --- | --- |
| Actors | Alumne/pagador a l'ecommerce; Redsys; worker SIF. |
| Entrada abans del TPV | Inscripció `ID`, curs i edició, import previst, receptor fiscal, descomptes aplicats i snapshot de l'operació; `DS_ORDER`/terminal i intenció UC-63. |
| Identitat fiscal de l'handler | `LegacyCourseInvoicePayloadBuilder` construeix `idempotency_key=LEGACY|CURS|INSCRIPCIO:<ID>` inicial; `RedsysInvoicePayloadBuilder` el substitueix per una clau de factura que conté tipus d'origen, `IDPAG` i `DS_ORDER`. |
| Dades de línia | Línia `source_type=INSCRIPCIO`, `source_id=ID`, concepte i convocatòria, import base, descompte congelat si n'hi ha, import final. Relació `fact_rels` a inscripció amb `VISIBLE_ALUMNE=1` al builder ordinari. |
| Import real cobrat | `RedsysInvoicePayloadBuilder` afegeix bloc `payment` a partir de notificació `VALIDATED`, amb `movement_type=CHARGE`, `method=REDSYS`, `DS_ORDER` i `IDPAG`. |
| Resultat | Factura i pagament inicial en UC-01, `UUID_FACTURA`, `UUID_PAYMENT`, relació `INSCRIPCIO(ID)`, `payment_transaction.IDPAG`, `payment_allocation` a factura i `enrollment_fund_movement.EXTERNAL_ALLOCATION` cap a la inscripció destí. |

### 1.1. Flux principal asíncron

1. L'alumne selecciona curs i edició i confirma compra; el pont candidat crida `SifRedsysCourseIntentClient`, i `RedsysCoursePaymentIntentService` rellegeix la inscripció/saldo i crea o reutilitza `redsys_payment_intent` amb `DS_ORDER` i snapshot. No es factura ni es registra `CHARGE` pel sol fet de preparar l'intent.
2. Redsys rep la petició i envia callback signat. La recepció verifica signatura, import, divisa i terminal contra la intenció, desa notificació i encua job (UC-03); **la recepció HTTP no emet factura**.
3. El worker reclama el job i `RedsysCallbackDispatcher` selecciona `RedsysCourseInvoiceService` amb `sourceType=CURS` i `SNAPSHOT_JSON` de la intenció.
4. `RedsysCourseInvoiceService::issueFromIntentSnapshot()` passa el snapshot a `LegacyCourseInvoicePayloadBuilder::build()` (inscripció, curs, import i dades fiscals). `RedsysInvoicePayloadBuilder::buildFromValidatedNotification()` exigeix notificació `VALIDATED` i incorpora `payment`, `DS_ORDER` i `IDPAG`.
5. `InvoiceService::issueInvoice()` crea/reutilitza factura, registre fiscal, cadena, cua AEAT, relació d'inscripció i moviment de cobrament inicial en la transacció del nucli.
6. `CourseEnrollmentFundAllocationService` valida el `CHARGE` confirmat, factura, línia `INSCRIPCIO` i import i crea/reutilitza un `EXTERNAL_ALLOCATION` idempotent amb clau `FUND|CURS|ORDER:<DS_ORDER>|INSC:<ID_INSC>`, vinculat a `UUID_PAYMENT` i `UUID_FACTURA`.
7. Només després d'aquesta atribució, `CourseLegacyPaymentSyncService` suma els moviments `CONFIRMED` per `IDPAG` i projecta `PAGAMENT/DATA PAG`; `CoursePaymentNotificationService` deixa l'avís durable a l'outbox i el job es marca processat.

### 1.2. Alternatives, errors i control dels imports

| Escenari | Regla |
| --- | --- |
| TPV denega o no retorna cobrament | No executar handler emissor ni crear pagament inicial; gestionar notificació no autoritzada. |
| Callback duplicat o worker reexecutat | Reutilitzar intenció, notificació, job, factura, pagament, projecció llegada i outbox compatible; no generar segon ingrés. |
| Inscripció desplaçada o dada del llegat modificada després del pagament | El worker asíncron usa snapshot congelat, no reconstrueix el preu des de les dades vives. El canvi posterior és UC-71 i no una mutació silenciosa del document. |
| Descompte aplicat | El builder congela imports/descompte en la línia; la verificació del dret al descompte en el moment de compra correspon al canal/regles específiques, no s'infereix de `issueInvoice()`. |
| Preu notificador ≠ preu de la compra | Validar import pre-TPV i línia/totals congelats; no assumir que tots els controls fiscals finals es compleixen perquè el callback és signat. |
| Un mateix `IDPAG` cobreix pack o grup | No passar-lo al handler de `CURS` per comoditat; UC-15/16 tenen N inscripcions i regles de visibilitat diferents. |
| Compra confirmada però sincronització acadèmica falla | Factura i pagament persistits; incidència i recuperació idempotent del llegat, no una nova facturació. |

**Proves executades en CI:** `RedsysCourseInvoiceServiceTest`, `RedsysAsyncFlowTest`, `RedsysPaymentIntentTest`, `RedsysCourseEndToEndSimulatedTest`, `RedsysLegacySyncingProcessorCourseTest`, `RedsysCoursePreproductionBoundaryTest`, `RedsysCoursePaymentStatusServiceTest` i `RedsysCourseReturnBoundaryTest`. Això acredita el circuit intern simulat i el retorn autoritatiu al repositori; **no acredita** una transacció Redsys real ni el desplegament a preproducció/producció.

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Alumne / pagador" as Buyer
actor "Redsys" as R
actor "Worker SIF" as W
rectangle "Compra de curs SIF" {
 usecase "UC-14\nComprar curs ordinari" as C
 usecase "UC-63\nCrear intenció" as I
 usecase "UC-03\nProcessar callback i job" as CB
 usecase "UC-01\nEmetre factura amb cobrament" as Inv
 usecase "Atribuir cobrament\na la inscripció" as Alloc
}
Buyer --> C
C ..> I : <<include>>
R --> CB
W --> CB
CB ..> Inv : <<include>> (només autoritzat)
CB ..> Alloc : <<include>>
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Alumne / pagador"]
  actor_1["Redsys"]
  actor_2["Worker SIF"]
  subgraph SIF_BOX["Compra de curs SIF"]
    uc_0(["UC-14<br/>Comprar curs ordinari"])
    uc_1(["UC-63<br/>Crear intenció"])
    uc_2(["UC-03<br/>Processar callback i job"])
    uc_3(["UC-01<br/>Emetre factura amb cobrament"])
    uc_4(["Atribuir cobrament<br/>a la inscripció"])
  end
  actor_0 --> uc_0
  uc_0 -.->|include| uc_1
  actor_1 --> uc_2
  actor_2 --> uc_2
  uc_2 -.->|include| uc_3
  uc_2 -.->|include| uc_4
```

## 3. Subdiagrama de classes reals i traçabilitat econòmica

```mermaid
classDiagram
direction LR
class RedsysPaymentIntentService {
 +create(db,input) array
}
class RedsysCallbackService {
 +receiveCallback(db,payload,signatureValid) array
}
class RedsysCallbackWorker {
 +runOne(db,workerId,now) array
}
class RedsysCallbackDispatcher {
 +process(db,job) array
}
class RedsysIntentHandler {
 <<interface>>
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}
class RedsysCourseInvoiceService {
 +sourceType() string
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}
class LegacyCourseInvoicePayloadBuilder {
 +build(snapshot) array
}
class RedsysInvoicePayloadBuilder {
 +buildFromValidatedNotification(db,dsOrder,payload) array
}
class InvoiceService {
 +issueInvoice(payload) array
}
class PaymentRepository {
 +create(db,payload) array
 +createAllocation(db,uuidPayment,allocation)
}
class CourseEnrollmentFundAllocationService {
 +allocate(db,dsOrder,snapshot,invoiceResult) array
}
class EnrollmentFundMovementRepository {
 +lockPayment(db,uuidPayment) array
 +findInvoiceLineForInscription(db,uuidFactura,idInsc) array
 +insertOrReuseExternalAllocation(db,input) array
}
class CourseLegacyPaymentSyncService {
 +sync(sifDb,legacyDb,idpag,idInsc,date) array
}
class CoursePaymentNotificationService {
 +enqueue(db,dsOrder,snapshot,invoiceResult,legacySync) array
}
RedsysCallbackWorker --> RedsysCallbackDispatcher : processa job
RedsysCallbackDispatcher --> RedsysIntentHandler : selecciona CURS
RedsysCourseInvoiceService ..|> RedsysIntentHandler
RedsysCourseInvoiceService --> LegacyCourseInvoicePayloadBuilder : línia inscripció
RedsysCourseInvoiceService --> RedsysInvoicePayloadBuilder : notificació
RedsysCourseInvoiceService --> InvoiceService : factura+CHARGE
InvoiceService --> PaymentRepository : payment_transaction + allocation
RedsysCourseInvoiceService --> CourseEnrollmentFundAllocationService : atribució postcommit
CourseEnrollmentFundAllocationService --> EnrollmentFundMovementRepository : EXTERNAL_ALLOCATION idempotent
RedsysCallbackWorker --> CourseLegacyPaymentSyncService : projecció IDPAG
RedsysCallbackWorker --> CoursePaymentNotificationService : outbox post-sync
```

`RedsysPaymentIntentService`, `RedsysCallbackService` i `RedsysCallbackWorker` pertanyen a fases separades; la seva col·locació al mateix diagrama **no** implica crides directes entre elles.

## 4. Seqüència — compra confirmada asíncrona

```mermaid
sequenceDiagram
autonumber
actor A as Alumne/pagador
participant Web as pay.prisma.cat [pont candidat]
participant Intent as RedsysPaymentIntentService
participant Bank as Redsys
participant C as RedsysCallbackService
participant Q as Cua callback
participant W as RedsysCallbackWorker
participant D as RedsysCallbackDispatcher
participant H as RedsysCourseInvoiceService
participant Builder as LegacyCourseInvoicePayloadBuilder
participant R as RedsysInvoicePayloadBuilder
participant I as InvoiceService
participant Fund as CourseEnrollmentFundAllocationService
participant L as EnrollmentFundMovementRepository
participant Sync as CourseLegacyPaymentSyncService
participant Outbox as CoursePaymentNotificationService
A->>Web: Confirmar curs, edició i pagament
Web->>Intent: client HMAC crea/reutilitza intenció CURS
Intent-->>Web: UUID_INTENT pendent
Web->>Bank: Redirecció TPV
Bank->>C: Callback signat
C->>Q: Registrar notificació validada i encolar
C-->>Bank: Resposta HTTP sense factura
W->>Q: claimNext() job autoritzat
W->>D: process(job snapshot)
D->>H: issueFromIntentSnapshot(db,dsOrder,snapshot)
H->>Builder: build(snapshot)
Builder-->>H: Línia i relació INSCRIPCIO
H->>R: buildFromValidatedNotification()
R-->>H: Payload amb CHARGE
H->>I: issueInvoice(payload)
I-->>H: UUID_FACTURA i UUID_PAYMENT
H->>Fund: allocate(DS_ORDER, snapshot, invoiceResult)
Fund->>L: validar CHARGE/factura/línia i insertOrReuseExternalAllocation()
L-->>Fund: UUID_MOVEMENT + reused?
Fund-->>H: EXTERNAL_ALLOCATION
H-->>W: Resultat + fund_allocations
W->>Sync: sync(IDPAG, ID_INSC, data)
Sync-->>W: confirmed_amount + projected_payment + PAID/PARTIALLY_PAID
W->>Outbox: enqueue(COURSE_PAYMENT_CONFIRMED, DS_ORDER)
W->>Q: markProcessed(job,result)
Note over I,Outbox: Ordre final: factura/CHARGE → EXTERNAL_ALLOCATION → sync llegada → outbox → PROCESSED
```

## 5. Traçabilitat

[Fitxa original UC-14](../06-fitxes-funcionals/uc-014.md) · [UC-63](uc-063-crear-intencio-redsys.md) · [UC-03](uc-003-processar-cobrament-redsys-asincron.md) · [UC-01](uc-001-emetre-o-reutilitzar-factura.md) · [Revisió dels fons](00-revisio-moviments-inscripcions.md) · [RedsysCourseInvoiceService](../../sif/src/Service/RedsysCourseInvoiceService.php) · [LegacyCourseInvoicePayloadBuilder](../../sif/src/Service/LegacyCourseInvoicePayloadBuilder.php) · [RedsysInvoicePayloadBuilder](../../sif/src/Service/RedsysInvoicePayloadBuilder.php) · [CourseEnrollmentFundAllocationService](../../sif/src/Service/CourseEnrollmentFundAllocationService.php) · [EnrollmentFundMovementRepository](../../sif/src/Repository/EnrollmentFundMovementRepository.php) · [CourseEnrollmentFundAllocationServiceTest](../../sif/tests/Integration/CourseEnrollmentFundAllocationServiceTest.php) · [RedsysCourseInvoiceServiceTest](../../sif/tests/Integration/RedsysCourseInvoiceServiceTest.php).


## 6. Lliurables detallats ACTUAL/FINAL — revalidació 02/10/2026

Aquest document principal conserva el model integrat del cas. La cobertura exhaustiva RM-037 es troba als documents següents:

- [Diagrames de classes ACTUAL i FINAL](uc-014-classes-actual-final.md)
- [Diagrames de seqüència ACTUAL i FINAL](uc-014-sequencies-actual-final.md)
- [Diagrames d'activitat ACTUAL/FINAL de pàgines i apartats](uc-014-activitats-pagines-redsys-actual-final.md)
- [Auditoria exhaustiva i matriu de traçabilitat 02/10](uc-014-auditoria-tracabilitat-2026-10-02.md)
- [Inventari executable PHP/JS ACTUAL, pont candidat i SIF](uc-014-inventari-codi-php-js-actual-final-2026-10-02.md)

### Estat

- **DOCUMENTAT:** fitxa, casos d'ús, classes ACTUAL/FINAL, seqüències ACTUAL/FINAL i activitats per superfícies principals.
- **IMPLEMENTAT:** nucli SIF Redsys, handler CURS, pont candidat d'intenció, `EXTERNAL_ALLOCATION` per inscripció, projecció llegada, productor outbox, retorn navegador autoritatiu i hardening del fallback fusionat via PR #118; el circuit llegat continua com a fallback fins al tall.
- **VERIFICAT:** CI amb E2E intern simulat, fund allocation idempotent, mismatch fail-closed, duplicats, parcial→complet, boundaries de preproducció i retorn OK/KO read-only; PR #95 amb suites SIF **841 passed / 0 failed** i quatre workflows verds.
- **PENDENT:** Redsys/preproducció real, activació del cutover amb `SIF_REDSYS_CALLBACK_URL`, retirada de l'autoritat fiscal llegada, rotació/configuració de secrets i delivery/retries d'UC-58.
