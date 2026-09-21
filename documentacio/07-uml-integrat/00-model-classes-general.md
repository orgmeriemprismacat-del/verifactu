# UML transversal · Model de classes general del SIF

**Objectiu:** mantenir un únic model de classes de referència per a les fitxes individuals i evitar que cada cas d'ús inventi un sistema diferent. **Font contrastada:** fitxers PHP llegits a `sif/src/` de la branca documental `docs/uml-fitxes-integrades-2026-09-20` i els fluxos dels casos que s'enllacen al final. **Àmbit del document:** dependències PHP i interfícies executables; les taules SQL s'identifiquen com a persistència, **no** com si fossin automàticament entitats/classes PHP.

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

### 4.1. Subvista de classes del regal — compra PHP i frontera del dret comercial

El diagrama executiu de Redsys de l'apartat 4 mostra `RedsysGiftInvoiceService` com a handler; aquesta subvista concreta el camí **comprovat al codi de compra**, sense convertir el bescanvi o l'enviament de la targeta en mètodes existents de la classe.

```mermaid
classDiagram
direction LR
class RedsysGiftInvoiceService {
 <<PHP existent; compra/factura>>
 +sourceType() string
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
 +issueByGiftIdFromValidatedNotification(sifDb,legacyDb,dsOrder,giftId) array
 +issueByGiftCodeFromValidatedNotification(sifDb,legacyDb,dsOrder,giftCode) array
}
class LegacyGiftSnapshotRepository {
 <<PHP existent; dades llegades>>
 +loadById(legacyDb,giftId) array
 +loadByCode(legacyDb,code) array
}
class LegacyGiftInvoicePayloadBuilder {
 <<PHP existent; factura de compra>>
 +build(snapshot) array
}
class RedsysNotificationRepository {
 <<PHP existent; consulta DS_ORDER>>
 +findByDsOrder(db,dsOrder) array
}
class RedsysInvoicePayloadBuilder {
 <<PHP existent; payload cobrat>>
}
class InvoiceService {
 <<PHP existent; emissió fiscal>>
 +issueInvoice(payload) array
}
RedsysGiftInvoiceService ..|> RedsysIntentHandler
RedsysGiftInvoiceService --> RedsysNotificationRepository : exigeix VALIDATED
RedsysGiftInvoiceService --> LegacyGiftSnapshotRepository : opcional ID/codi
RedsysGiftInvoiceService --> LegacyGiftInvoicePayloadBuilder : prepara REGAL
RedsysGiftInvoiceService --> RedsysInvoicePayloadBuilder : dades TPV
RedsysGiftInvoiceService --> InvoiceService : emissió/reús
```

**Límit verificat:** `LegacyGiftInvoicePayloadBuilder::build()` incorpora `CODI` en clar a `lines[].detail` i `gift.code`, i deriva la clau fiscal de `gift.ID`. `RedsysGiftInvoiceService` verifica `STATUS=VALIDATED` i coincidència d'import abans de delegar a `InvoiceService`; **no** crea `commercial_entitlement`, no valida el consum de codi i no lliura cap targeta. Vegeu [UC-119, activació i lliurament](uc-119-cicle-complet-regal.md#6-activació-i-comunicació-del-regal--accions-diferenciades).
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

### 5.1. Subvista real de la importació de dades històriques — UC-11; bytes originals fora d'aquest PHP

```mermaid
classDiagram
direction LR
class HistoricalInvoiceMigrationService {
 <<PHP existent>>
 +importHistoricalInvoice(input) array
}
class HistoricalInvoicePayloadBuilder {
 <<PHP existent>>
 +build(input) array
}
class HistoricalInvoiceMigrationRepository {
 <<PHP existent>>
 +importHistoricalInvoice(db,payload) array
 +findByIdempotencyKey(db,key,forUpdate) array
}
class DocumentRepository {
 <<PHP existent: només metadata i hash dels contents aportats>>
 +registerDocument(db,uuidFactura,type,path,contents) array
}
class TransactionRunner {
 <<PHP existent>>
 +run(callback) mixed
}
HistoricalInvoiceMigrationService --> HistoricalInvoicePayloadBuilder : normalització
HistoricalInvoiceMigrationService --> TransactionRunner : BEGIN/COMMIT de dades
HistoricalInvoiceMigrationService --> HistoricalInvoiceMigrationRepository : factura, línies, relacions i metadata opcional
```

**Frontera real:** `HistoricalInvoiceMigrationRepository::insertDocument()` insereix path/hash/estat declarat sense verificar físicament l'arxiu i `DocumentRepository::registerDocument()` només calcula SHA-256 del contingut **que se li passa**, no en fa la custòdia. `HistoricalInvoiceMigrationRepository` tampoc no crida `InvoiceService`, la cua AEAT ni `DocumentRepository` en importar metadades. El SQL `factura` té `UNIQUE(NUM_VISIBLE)` i `UNIQUE(TIPUS_SERIE,ANY_FACT,NUM_SEQ)` sense emissor. [UC-11](uc-011-importar-factura-historica.md), [UC-97](uc-097-consultar-historic-associacio-sl.md).

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
 <<DISSENY: UC-55/80, no implementada>>
 +listAuthorized(actor,scope) documents
 +download(actor,documentId,token) bytes
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

**Contrast nominal de l'API de l'auditoria anterior:** s'han comparat les **47 classes PHP del subconjunt inicial** i les **70 declaracions de mètode** que els seus subdiagrames mostren amb el codi de les classes homònimes; no hi ha cap nom de mètode absent d'aquests fitxers. Les **13 classes sense fitxer PHP homònim del subconjunt auditat original** eren propostes/disseny pendent; les subvistes de regal, conciliació i ajust incorporades després afegeixen altres classes expressament etiquetades `DISSENY` i **no** queden cobertes per aquell recompte inicial. Aquesta verificació **no inclou automàticament les subvistes afegides posteriorment sobre el regal, l'històric i la custòdia** i és només existència del nom, no equival a validar paràmetres, tipus, visibilitat, instanciació, relacions UML, fluxos o proves d'execució. Les proves que sí estan escrites al repositori i els contrasts no coberts figuren a l'[auditoria de consistència, apartat 4](00-auditoria-consistencia-142-fitxes.md#4-què-demostren-les-proves-existents-i-quina-evidència-falta).

### 6.1. Subvista de disseny del dret de regal, entrega i consum — NO IMPLEMENTAT

Les taules `commercial_entitlement`/`commercial_entitlement_event` existeixen com a model SQL, però els noms següents són **classes/serveis proposats** i no es poden incloure dins del nucli PHP executiu fins que tinguin codi i proves. El model permet seguir `UUID_PAYMENT` original, titular del dret, estat/versió del codi, notificació i `ID_INSC` de destí sense duplicar el cobrament.

```mermaid
classDiagram
direction LR
class GiftLifecycleCoordinator {
 <<DISSENY: no acreditat al PHP>>
 +activatePaidGift(command) result
 +deliverGift(uuidEntitlement,recipient,requestId) result
 +resendGift(uuidEntitlement,recipient,requestId) result
 +redeemGift(command) result
 +reconcileGift(uuidOperation) result
}
class CommercialEntitlementRepository {
 <<DISSENY: SQL existent, writer no acreditat>>
 +lockByCodeHash(db,hash) entitlement
 +activate(db,purchase,codeHash) result
 +reserve(db,id,operationId) result
 +consume(db,id,operationId) result
 +appendEvent(db,event) result
}
class GiftNotificationOutbox {
 <<DISSENY: adaptador/outbox no acreditat>>
 +enqueue(db,uuidEntitlement,recipient,requestId) result
 +recordDelivery(db,notificationId,outcome) result
}
class EnrollmentGateway {
 <<DISSENY: integració llegat>>
 +createOrLinkEnrollment(command) result
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: ledger per inscripció no implementat>>
 +append(db,movement) result
}
GiftLifecycleCoordinator --> CommercialEntitlementRepository : dret i events
GiftLifecycleCoordinator --> GiftNotificationOutbox : lliurament/reexpedició
GiftLifecycleCoordinator --> EnrollmentGateway : bescanvi
GiftLifecycleCoordinator --> EnrollmentFundMovementRepository : aplica valor existent
```

**Fronteres de transacció:** confirmar la compra/factura no és la mateixa operació que activar el dret o lliurar el codi; enviar/reenviar una targeta tampoc no és consumir-la. Entre la BD fiscal, notificacions i el llegat no s'ha acreditat un commit distribuït. [UC-17](uc-017-comprar-regal.md), [UC-18](uc-018-bescanviar-regal.md), [UC-18a](uc-018a-regal-caducat-duplicat.md), [UC-119](uc-119-cicle-complet-regal.md).
### 6.2. Subvista única de conciliació SIF–llegat — UC-82 per lot, UC-53 per item (DISSENY)

`reconciliation_run` té `IDEMPOTENCY_KEY` única; `reconciliation_item` té UUID propi i relació amb run, però **no** clau única de discrepància semàntica dins del run. Les taules existeixen en SQL i **no** equivalen a repositoris PHP implementats. Les fitxes [UC-82](uc-082-reconciliar-sif-bd-llegada.md) i [UC-53](uc-053-detectar-resoldre-divergencies.md) comparteixen expressament **una sola classe coordinadora proposada**; no convertir `ReconciliationService` i `SifLegacyReconciliationService` en serveis redundants d'un mateix flux.

```mermaid
classDiagram
direction LR
class SifLegacyReconciliationService {
 <<DISSENY: coordinador únic, no PHP>>
 +compare(scope,ruleVersion,requestId) differences
 +retryRun(runId,requestId) result
 +resolve(itemId,decision,actor,requestId) result
}
class ReconciliationRunRepository {
 <<DISSENY: reconciliation_run SQL definit>>
 +createOrReuse(db,scope,inputHash,requestId) run
 +markFinished(db,runId,summary) result
}
class ReconciliationItemRepository {
 <<DISSENY: reconciliation_item SQL definit>>
 +append(db,difference) item
 +getForUpdate(db,itemId) item
 +recordResult(db,itemId,resolution) result
}
class LegacySyncService {
 <<PHP existent: només projecció resum>>
 +syncAfterSifSuccess(legacyDb,relations,uuidFactura,numVisible,estatCobrament) void
}
class LegacySyncRepository {
 <<PHP existent: no idempotent a OBSERVACIONS>>
 +syncInscripcioSummary(legacyDb,idInsc,facturaRelacionada,uuidFactura,numVisible,estatCobrament) void
}
class IncidentRepository {
 <<PHP existent: incidència genèrica>>
 +open(db,uuidFactura,type,message) array
}
SifLegacyReconciliationService --> ReconciliationRunRepository : UC-82 crea/resumeix execució
SifLegacyReconciliationService --> ReconciliationItemRepository : UC-53 diagnostica/resol item
SifLegacyReconciliationService ..> LegacySyncService : UC-47, projecció autoritzada
SifLegacyReconciliationService ..> IncidentRepository : conflicte sense reparació segura
LegacySyncService --> LegacySyncRepository : SQL llegat
```

**Separació real/proposada:** les dependències `LegacySyncService → LegacySyncRepository` i el mètode `IncidentRepository::open()` consten al PHP. El coordinador, el repositori de runs/items, el guard d'autorització, la captura/versionat del llegat i el writer de resultat continuen pendents. La reconciliació no és un `UPDATE factura.TOTAL` ni una transacció distribuïda entre dues BDs.

### 6.3. Subvista de proposta i aprovació d'ajust manual — UC-94 (DISSENY amb peces PHP)

```mermaid
classDiagram
direction LR
class ManualPriceAdjustmentService {
 <<DISSENY: no acreditat al PHP>>
 +preview(operation,proposedAmount,reason,expectedVersion) impact
 +approve(proposalId,actor,requestId) decision
}
class PriceAdjustmentGuard {
 <<DISSENY: rol, proposta i idempotència>>
 +validateActorAndVersion(actor,proposal) result
 +findEquivalentDecision(requestId,payload) result
}
class OperationalEventRepository {
 <<PHP existent: només append genèric>>
 +append(db,event) string
}
class RedsysPaymentIntentService {
 <<PHP existent: comparació de DS_ORDER>>
 +create(db,input) array
}
class ManualRectificationService {
 <<PHP existent: emissió R separada>>
 +issueByUuid(sifDb,uuidFactura,input) array
}
ManualPriceAdjustmentService --> PriceAdjustmentGuard : precondicions/versió
ManualPriceAdjustmentService ..> OperationalEventRepository : decisió autoritzada (integració pendent)
ManualPriceAdjustmentService ..> RedsysPaymentIntentService : UC-63 si import TPV nou
ManualPriceAdjustmentService ..> ManualRectificationService : UC-74/05 si factura emesa
```

**No executar en cadena automàticament:** `OperationalEventRepository::append()` desa un event però no aprova l'import; `RedsysPaymentIntentService::create()` rebutja reusar `DS_ORDER` amb snapshot/import diferent; `ManualRectificationService` emet una factura R separada quan una classificació fiscal ho justifica. La UC-94 no té un únic commit demostrable que englobi proposta, canvi d'intenció, document fiscal, transferència i llegat.

### 6.4. Subvista transversal de job, integritat, descàrrega i històric — UC-55/80/11/97 (DISSENY)

`factura_documents` només imposa un ID únic de fila i `document_job` només una `IDEMPOTENCY_KEY` única; **no** hi ha garantia automàtica d'un document per factura/tipus/versió ni de fitxer físic disponible. El repo PHP `DocumentRepository::registerDocument()` **no** retorna `factura_documents.ID`. Les classes proposades de custòdia han de recuperar/contrastar la identitat de la metadata i els bytes abans de marcar un job com a complet.

```mermaid
classDiagram
direction LR
class DocumentWorker {
 <<DISSENY: no PHP acreditat>>
 +runOne(now) result
 +recover(jobId) result
}
class DocumentJobRepository {
 <<DISSENY: document_job SQL>>
 +enqueue(command) job
 +claimNext() job
 +complete(job,documentId,hash) result
 +fail(job,error,nextAttempt) result
}
class FiscalDocumentGenerator {
 <<DISSENY: PDF/QR/XML, no PHP acreditat>>
 +generate(snapshot,type,version) bytes
}
class PrivateDocumentStore {
 <<DISSENY: custòdia físicament verificada>>
 +writeAndVerify(bytes) key
 +readVerified(key,sha256) bytes
}
class DocumentAvailabilityService {
 <<DISSENY: no PHP acreditat>>
 +verify(uuidFactura,documentId) status
}
class HistoricalOriginalCustodyService {
 <<DISSENY: original antic, no importador PHP>>
 +attachOriginal(uuidFactura,issuer,sourceId,bytes) result
 +auditInventory(scope) report
}
class InvoiceDocumentAccessService {
 <<DISSENY: servei únic UC-55/80>>
 +listAuthorized(actor,scope) documents
 +download(actor,documentId,token) bytes
}
class VisibilityPolicy {
 <<DISSENY: autorització per actor/document>>
 +canView(actor,factura,relations) bool
}
class DocumentRepository {
 <<PHP real: metadata sense storage>>
 +registerDocument(db,uuidFactura,type,path,contents) array
}
DocumentWorker --> DocumentJobRepository : encolat/reintent
DocumentWorker --> FiscalDocumentGenerator : bytes de font fiscal
DocumentWorker --> PrivateDocumentStore : desar/verificar
DocumentWorker ..> DocumentRepository : registra metadata; recuperar ID per via addicional
HistoricalOriginalCustodyService --> PrivateDocumentStore : bytes ORIGINALS de l'arxiu llegat
HistoricalOriginalCustodyService ..> DocumentRepository : només si metadata no existent i validada
DocumentAvailabilityService --> PrivateDocumentStore : llegir i recalcular hash
InvoiceDocumentAccessService --> VisibilityPolicy : consulta per document
InvoiceDocumentAccessService --> DocumentAvailabilityService : prova de bytes
```

**Límit multiemissor:** `HistoricalOriginalCustodyService` només ha d'adjuntar l'original a la factura **unívocament** identificada. Si Associació i SL aporten dues factures amb mateix número, l'únic model `factura` actual no pot guardar-les com dues files (UNIQUE global). El model d'emissor/persistència històrica és una decisió prèvia bloquejant de [UC-97](uc-097-consultar-historic-associacio-sl.md), no una funcionalitat que la classe proposada resolgui per màgia.

## 7. Traçabilitat i criteri de manteniment

- [Model de classes ja existent al projecte](../04-estat-final/31-diagrames-classes-sif.md) i [matriu transversal de diagrames](../04-estat-final/35-matriu-tracabilitat-diagrames.md).
- [Fitxes integrades i estat individual](README.md).
- Codi base a [`sif/src/Service`](../../sif/src/Service/), [`sif/src/Repository`](../../sif/src/Repository/), [`sif/src/Aeat`](../../sif/src/Aeat/) i [`sif/src/Contract`](../../sif/src/Contract/).

**Regla:** qualsevol dependència nova que es vulgui representar com a **implementada** ha de tenir un fitxer/mètode PHP i una crida real a la branca que s'estigui documentant. Les taules SQL, les pantalles de disseny i les interaccions entre processos via cua es representen separadament. Els mètodes mostrats resumeixen l'API rellevant i no pretenen ser un inventari exhaustiu de signatures.
