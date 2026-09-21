# UML transversal · Model de classes general del SIF

**Objectiu:** mantenir un únic model de classes de referència per a les fitxes individuals i evitar que cada cas d'ús inventi un sistema diferent. **Font contrastada:** fitxers PHP existents a `sif/src/` en `main` i els fluxos dels casos que s'enllacen al final. **Àmbit del document:** dependències PHP i interfícies executables; les taules SQL s'identifiquen com a persistència, **no** com si fossin automàticament entitats/classes PHP.

**Estat:** model lògic de revisió de codi, no diagrama desplegat ni prova d'integració de l'ecommerce, intranet, AEAT productiva o llegat. Quan una dependència és del disseny final i no apareix al codi, es representa **només** al diagrama de classes proposades de l'última secció.

## 1. Diagrama de paquets del sistema i casos d'ús relacionats (PlantUML)

```plantuml
@startuml
skinparam packageStyle rectangle
package "Canals i adaptadors [integració variable]" {
 [Ecommerce]
 [Intranet]
 [pay.prisma.cat]
}
package "SIF: emissió / registre fiscal" {
 [InvoiceService]
 [FiscalRecordService]
}
package "SIF: diners i atribució" {
 [PaymentService]
 [CreditBalanceService]
 [EnrollmentFundMovementRepository (PROPOSTA)]
}
package "SIF: TPV i cues" {
 [RedsysPaymentIntentService]
 [RedsysCallbackService]
 [RedsysCallbackWorker]
 [FiscalQueueProcessor]
}
package "SIF: consulta i operació" {
 [DocumentRepository]
 [IncidentRepository]
}
cloud "Redsys" as Redsys
cloud "AEAT proves" as AEAT
[Ecommerce] --> [RedsysPaymentIntentService] : UC-63
Redsys --> [RedsysCallbackService] : UC-03
[RedsysCallbackService] ..> [RedsysCallbackWorker] : job persistent
[RedsysCallbackWorker] --> [InvoiceService] : handler per origen
[Intranet] --> [InvoiceService] : UC-01/04 segons adaptador
[Intranet] --> [PaymentService] : UC-02 segons adaptador
[InvoiceService] ..> [FiscalQueueProcessor] : registre en cua
[FiscalQueueProcessor] --> AEAT : UC-09, transport limitat a proves
[PaymentService] ..> [EnrollmentFundMovementRepository (PROPOSTA)] : atribució per inscripció pendent
[CreditBalanceService] ..> [EnrollmentFundMovementRepository (PROPOSTA)] : traça de saldo pendent
@enduml
```

**Precisió UML:** les fletxes puntejades de la vista de paquets signifiquen **relació funcional via dades/cua o proposta**, no una crida PHP directa: `InvoiceService` no invoca `FiscalQueueProcessor` i `RedsysCallbackService` no invoca `RedsysCallbackWorker` dins la mateixa petició HTTP. Les dependències de classes **directes** són les dels subdiagrames següents.

## 2. Classes executives del nucli de factura i correcció

```mermaid
classDiagram
direction LR
class InvoiceService {
 +issueInvoice(payload) array
}
class InvoicePayloadValidator {
 +validate(payload) array
}
class TransactionRunner {
 +run(callback) mixed
}
class FiscalSequenceRepository {
 +next(db,series,year) int
}
class InvoiceRepository {
 +findByIdempotencyKey(db,key,forUpdate) array
 +lockChainState(db) array
 +createInvoiceGraph(db,payload,seq,chainState) array
}
class InvoiceBeforePaymentService {
 +issueBeforePayment(input) array
}
class InvoiceBeforePaymentPayloadBuilder {
 +build(input) array
}
class ManualRectificationService {
 +issueByUuid(db,uuidFactura,input) array
 +issueByNumVisible(db,numVisible,input) array
}
class ManualRectificationPayloadBuilder {
 +forOriginalInvoice(invoice,input) array
}
class RectificationRepository {
 +linkRectification(db,uuidRect,uuidOriginal,input) void
 +markOriginalRectified(db,uuidOriginal) void
}
class FiscalRecordService {
 +createCancellationByUuid(uuidFactura,input) array
 +createSubsanationByUuid(uuidFactura,input) array
}
class FiscalRecordPayloadBuilder {
 +cancellation(invoice,previous,input) array
 +subsanation(invoice,previous,input) array
}
class FiscalRecordRepository {
 +latestForInvoice(db,uuidFactura,forUpdate) array
 +create(db,invoice,type,key,payload,markCancelled) array
}
class ManualPaymentInvoiceRepository {
 +findByUuid(db,uuid,forUpdate) array
 +findByNumVisible(db,numVisible,forUpdate) array
}
class HashCalculator {
 +calculate(payload,previousHash) string
}
InvoiceBeforePaymentService --> InvoiceBeforePaymentPayloadBuilder
InvoiceBeforePaymentService --> InvoiceService
InvoiceService --> InvoicePayloadValidator
InvoiceService --> TransactionRunner
InvoiceService --> InvoiceRepository
InvoiceService --> FiscalSequenceRepository
InvoiceRepository --> HashCalculator
ManualRectificationService --> ManualPaymentInvoiceRepository
ManualRectificationService --> ManualRectificationPayloadBuilder
ManualRectificationService --> InvoiceService
ManualRectificationService --> RectificationRepository
FiscalRecordService --> ManualPaymentInvoiceRepository
FiscalRecordService --> FiscalRecordPayloadBuilder
FiscalRecordService --> FiscalRecordRepository
FiscalRecordService --> TransactionRunner
FiscalRecordRepository --> HashCalculator
```

**Fronteres:** UC-05 crea factura R i després vincula la rectificativa/estat de l'original en passos separats: no inventar una transacció conjunta. UC-30 i UC-31 generen **nous registres fiscals per una factura existent**, no una nova factura fiscal amb un número nou. Les responsabilitats concretes consten a [UC-01](uc-001-emetre-o-reutilitzar-factura.md), [UC-04](uc-004-emetre-factura-abans-cobrar.md), [UC-05](uc-005-rectificar-factura.md), [UC-30](uc-030-anul-lar-registre-improcedent.md) i [UC-31](uc-031-subsanar-registre.md).

## 3. Classes executives de pagament, devolució i crèdit

```mermaid
classDiagram
direction LR
class PaymentService {
 +registerPayment(payload) array
}
class PaymentPayloadValidator {
 +validate(payload) array
}
class PaymentRepository {
 +findByIdempotencyKey(db,key,forUpdate) array
 +createPayment(db,payload) array
}
class PaymentStatusCalculator {
 +calculate(total,charges,refunds) string
}
class ManualPaymentService {
 +registerByUuid(db,uuidFactura,input) array
}
class ClaimPaymentService {
 +registerByUuid(db,uuidFactura,input) array
}
class ManualInstallmentPaymentService {
 +registerByUuid(db,uuidFactura,input) array
}
class ManualRefundService {
 +registerByUuid(db,uuidFactura,input) array
}
class CreditBalanceService {
 +createCredit(input) array
 +applyCreditByUuid(uuidCredit,uuidFactura,input) array
}
class CreditBalanceRepository {
 +createCredit(db,payload) array
 +findByUuid(db,uuidCredit,forUpdate) array
 +updateAvailableAmount(db,uuidCredit,available,status) void
}
class CreditBalancePayloadBuilder {
 +forCreditBalance(input) array
 +forCompensation(uuidCredit,uuidFactura,input,invoice) array
}
class ManualPaymentInvoiceRepository {
 +findByUuid(db,uuid,forUpdate) array
}
class TransactionRunner {
 +run(callback) mixed
}
PaymentService --> PaymentPayloadValidator
PaymentService --> PaymentRepository
PaymentService --> TransactionRunner
PaymentRepository --> PaymentStatusCalculator
ManualPaymentService --> PaymentService
ManualPaymentService --> ManualPaymentInvoiceRepository
ClaimPaymentService --> PaymentService
ClaimPaymentService --> ManualPaymentInvoiceRepository
ManualInstallmentPaymentService --> PaymentService
ManualInstallmentPaymentService --> ManualPaymentInvoiceRepository
ManualRefundService --> PaymentService
ManualRefundService --> ManualPaymentInvoiceRepository
CreditBalanceService --> CreditBalanceRepository
CreditBalanceService --> CreditBalancePayloadBuilder
CreditBalanceService --> PaymentRepository
CreditBalanceService --> PaymentPayloadValidator
CreditBalanceService --> ManualPaymentInvoiceRepository
CreditBalanceService --> TransactionRunner
```

**Font de veritat:** `payment_transaction` és el **moviment real** `CHARGE`/`REFUND` o l'aplicació `COMPENSATION`; `payment_allocation` enllaça amb la factura. `credit_balance` és un saldo disponible. **Cap d'aquestes taules registra avui per si sola l'origen/destí quantitatiu a cada inscripció.** Consulteu [revisió dels fons per inscripció](00-revisio-moviments-inscripcions.md). Les accions d'auditoria sobre pagaments es gestionen en `payment_action_event`, diferent de l'assentament econòmic.

## 4. Classes executives de Redsys i integracions de venda

```mermaid
classDiagram
direction LR
class RedsysPaymentIntentService {
 +create(db,input) array
}
class RedsysPaymentIntentRepository {
 +findByDsOrder(db,dsOrder,forUpdate) array
 +insert(db,intent) array
}
class RedsysSignatureValidator {
 +decodeAndVerify(request,context) array
}
class RedsysCallbackService {
 +receiveCallback(db,payload,signatureValid) array
}
class RedsysNotificationRepository {
 +recordReceived(db,dsOrder,idpag,amount,response,valid,payload,status) array
}
class RedsysCallbackQueueRepository {
 +enqueue(db,notificationId,uuidIntent) array
 +claimNext(db,workerId,now) array
}
class RedsysCallbackWorker {
 +runOne(db,workerId,now) array
}
class RedsysCallbackDispatcher {
 +process(db,job) array
}
class RedsysJobProcessor {
 <<interface>>
 +process(db,job) array
}
class RedsysIntentHandler {
 <<interface>>
 +sourceType() string
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}
class RedsysCourseInvoiceService
class RedsysPackInvoiceService
class RedsysGroupInvoiceService
class RedsysGiftInvoiceService
class RedsysUsocInvoiceService
class InvoiceService {
 +issueInvoice(payload) array
}
RedsysPaymentIntentService --> RedsysPaymentIntentRepository
RedsysCallbackService --> RedsysPaymentIntentRepository
RedsysCallbackService --> RedsysNotificationRepository
RedsysCallbackService --> RedsysCallbackQueueRepository
RedsysCallbackWorker --> RedsysCallbackQueueRepository
RedsysCallbackWorker --> RedsysJobProcessor
RedsysCallbackDispatcher ..|> RedsysJobProcessor
RedsysCallbackDispatcher --> RedsysIntentHandler
RedsysCourseInvoiceService ..|> RedsysIntentHandler
RedsysPackInvoiceService ..|> RedsysIntentHandler
RedsysGroupInvoiceService ..|> RedsysIntentHandler
RedsysGiftInvoiceService ..|> RedsysIntentHandler
RedsysUsocInvoiceService ..|> RedsysIntentHandler
RedsysCourseInvoiceService --> InvoiceService
RedsysPackInvoiceService --> InvoiceService
RedsysGroupInvoiceService --> InvoiceService
RedsysGiftInvoiceService --> InvoiceService
RedsysUsocInvoiceService --> InvoiceService
```

**Fases separades:** intenció UC-63 → recepció i encolat UC-03 → worker UC-03 → handler per producte UC-14/15/16/17/19a → UC-01. La creació d'una intenció no acredita pagament; el callback HTTP no emet factura; un job `PROCESSED` no implica que l'operació del llegat hagi quedat reconciliada. El model de classes no dibuixa una dependència directa fictícia entre el servei de recepció i el worker.

## 5. Classes executives de cua fiscal i consulta/operació

```mermaid
classDiagram
direction LR
class FiscalQueueProcessor {
 +processNext() array
 +processBatch(limit) array
 +recoverStaleLocks(seconds,now) int
}
class FiscalQueueRepository {
 +claimNext(db,maxAttempts) array
 +complete(db,item,status,response,xml) void
 +fail(db,item,error,maxAttempts,nextRetryAt) string
}
class AeatTransport {
 <<interface>>
 +send(payload) array
}
class SoapTransport {
 +send(payload) array
}
class ClientCertificate {
 +inspect(now) array
}
class XmlCodec {
 +request(snapshot) string
}
class ResponseParser {
 +parse(xml,snapshot) array
}
class EvidenceStore {
 +begin(request,metadata) string
}
class AeatPreflight {
 +check(config) array
}
class IncidentRepository {
 +open(db,uuidFactura,type,message) array
}
class DocumentRepository {
 +registerDocument(db,uuidFactura,type,path,contents) array
}
FiscalQueueProcessor --> FiscalQueueRepository
FiscalQueueProcessor --> AeatTransport
SoapTransport ..|> AeatTransport
SoapTransport --> ClientCertificate
SoapTransport --> XmlCodec
SoapTransport --> ResponseParser
SoapTransport --> EvidenceStore
```

`SoapTransport` només accepta l'endpoint AEAT de **proves** segons el seu constructor; `AeatPreflight` comprova prerequisits locals, no una acceptació externa ni l'aptitud de producció. `DocumentRepository` registra metadades i hash, no serveix un document autoritzat. `IncidentRepository` obre incidències, no implementa tot el cicle d'assignació/resolució. [UC-09](uc-009-remetre-registre-aeat.md), [UC-07](uc-007-consultar-factura-estat-document.md), [UC-08](uc-008-gestionar-incidencia-sif.md).

## 6. Classes del **disseny pendent** (NO són el PHP actual)

```mermaid
classDiagram
direction LR
class EnrollmentFundMovement {
 <<PROPOSTA: no implementada>>
 +uuidMovement string
 +originType string
 +originEnrollmentId int
 +targetType string
 +targetEnrollmentId int
 +amount decimal
 +uuidPaymentOrigin string
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: no implementada>>
 +append(db,movement) string
 +balanceForEnrollment(db,id) decimal
}
class EnrollmentFundsOrchestrator {
 <<PROPOSTA: no implementada>>
 +reallocate(command) result
 +allocateReceipt(command) result
}
class CourseChangeCoordinator {
 <<DISSENY: no implementada>>
 +preview(command) result
 +confirm(command) result
}
class CancellationCoordinator {
 <<DISSENY: no implementada>>
 +preview(command) result
 +confirm(command) result
}
class VisibilityPolicy {
 <<DISSENY: no implementada>>
 +canView(actor,factura,relations) bool
}
class InvoiceDocumentAccessService {
 <<DISSENY: no implementada>>
 +view(actor,uuidFactura) result
 +download(actor,documentId) stream
}
class IncidentWorkflowService {
 <<DISSENY: no implementada>>
 +assign(id,actor) result
 +resolve(id,evidence) result
}
class AuthorizationGateway {
 <<DISSENY TRANSVERSAL: no acreditat>>
 +authorize(actor,action,resource,requestId) decision
}
class IdentityResolver {
 <<SQL parcial / servei no acreditat>>
 +resolve(system,externalId) subject
}
class IssuerRoutingRegistry {
 <<DISSENY: multiemissor no acreditat>>
 +route(product,legalEntity) instance
}
class NotificationWorker {
 <<DISSENY: outbox SQL sense worker acreditat>>
 +processNext() result
}
class AcademicEconomicPolicy {
 <<DISSENY: regla/writer no acreditats>>
 +decide(enrollment,state,ruleVersion) decision
}
CourseChangeCoordinator --> EnrollmentFundsOrchestrator
CancellationCoordinator --> EnrollmentFundsOrchestrator
EnrollmentFundsOrchestrator --> EnrollmentFundMovementRepository
EnrollmentFundMovementRepository --> EnrollmentFundMovement
InvoiceDocumentAccessService --> VisibilityPolicy
AuthorizationGateway --> IdentityResolver
AuthorizationGateway ..> InvoiceDocumentAccessService : lectura fiscal autoritzada
IssuerRoutingRegistry ..> AuthorizationGateway : ruta només després d'autorització
NotificationWorker ..> AuthorizationGateway : productor autoritzat abans de l'outbox
AcademicEconomicPolicy ..> EnrollmentFundsOrchestrator : estat econòmic individual quan existeixi
```

Aquest últim diagrama és un **contracte de treball**, no una afirmació que hi ha classes, repositoris o migracions implementats. No s'ha creat la taula proposada `enrollment_fund_movement` en aquesta branca de documentació. Després de revisar els 142 casos, també es consideren transversals pendents l'**autorització servidor de les comandes**, la resolució d'identitat, el routing multiemissor, el worker d'outbox i la política acadèmica-econòmica. El detall i les evidències són a [Revisió transversal 142/142](00-revisio-transversal-142-casos.md).

**Contrast nominal de l'API del model general:** s'han comparat les **47 classes PHP identificades** i les **70 declaracions de mètode** que els seus subdiagrames mostren amb el codi de les classes homònimes; no hi ha cap nom de mètode absent d'aquests fitxers. Les **13 classes sense fitxer PHP homònim** són propostes/disseny pendent al diagrama final. Aquesta verificació és només **existència del nom**, no equival a validar paràmetres, tipus, visibilitat, instanciació, relacions UML, fluxos o proves d'execució. Les proves que sí estan escrites al repositori i els contrasts no coberts figuren a l'[auditoria de consistència, apartat 4](00-auditoria-consistencia-142-fitxes.md#4-què-demostren-les-proves-existents-i-quina-evidència-falta).

## 7. Traçabilitat i criteri de manteniment

- [Model de classes ja existent al projecte](../04-estat-final/31-diagrames-classes-sif.md) i [matriu transversal de diagrames](../04-estat-final/35-matriu-tracabilitat-diagrames.md).
- [Fitxes integrades i estat individual](README.md).
- Codi base a [`sif/src/Service`](../../sif/src/Service/), [`sif/src/Repository`](../../sif/src/Repository/), [`sif/src/Aeat`](../../sif/src/Aeat/) i [`sif/src/Contract`](../../sif/src/Contract/).

**Regla:** qualsevol dependència nova que es vulgui representar com a **implementada** ha de tenir un fitxer/mètode PHP i una crida real a la branca que s'estigui documentant. Les taules SQL, les pantalles de disseny i les interaccions entre processos via cua es representen separadament. Els mètodes mostrats resumeixen l'API rellevant i no pretenen ser un inventari exhaustiu de signatures.
