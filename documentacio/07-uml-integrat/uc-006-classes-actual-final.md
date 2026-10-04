# UC-006 · Diagrames de classes ACTUAL / FINAL

**Cas d'ús:** UC-006 — Registrar devolució, saldo o compensació  
**Data d'auditoria estàtica:** 2026-10-03  
**Principi:** ACTUAL descriu els circuits que existeixen avui al repositori; FINAL descriu l’arquitectura necessària per tancar UC-006. Cap classe marcada `PROPOSAT` s’ha de llegir com a implementada.

## 1. Fonts directes contrastades

### Intranet / llegat
- `codi-drive/intranet-actual/alumnes-mostrar-alumne.php`
- `codi-drive/intranet-actual/js/alumnes-mostrar-alumne.min.js`
- `codi-drive/intranet-actual/ajax/alumnes/mostraModalDonarBaixa.php`
- `codi-drive/intranet-actual/ajax/alumnes/confirmacioBaixa_DonarBaixa.php`
- `codi-drive/intranet-actual/ajax/alumnes/realitzarCanviCurs_CanviCurs.php`
- `codi-drive/intranet-actual/alumnes-factura.php`
- `codi-drive/intranet-actual/js/alumnes-factura.js`
- `codi-drive/intranet-actual/ajax/alumnes/mostrarModalAnulaFactura_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/anularFactura_Factures.php`
- `codi-drive/intranet-actual/LegacyInvoiceMutationAuthorization.php`
- `codi-drive/intranet-actual/SifLegacyInvoiceMutationGuard.php`

### SIF
- `sif/src/Service/ManualRefundService.php`
- `sif/src/Service/ManualRefundPayloadBuilder.php`
- `sif/src/Service/CreditBalanceService.php`
- `sif/src/Service/CreditBalancePayloadBuilder.php`
- `sif/src/Service/PaymentService.php`
- `sif/src/Service/PaymentActionGateway.php`
- `sif/src/Repository/PaymentRepository.php`
- `sif/src/Repository/CreditBalanceRepository.php`
- `sif/src/Repository/ManualPaymentInvoiceRepository.php`
- `sif/public/api/payments/register.php`

## 2. Llegenda

- **LLEGAT:** codi executat per les superfícies actuals.
- **EXISTEIX:** classe SIF localitzada i executable.
- **PARCIAL:** existeix, però no cobreix tot UC-006.
- **PROPOSAT:** responsabilitat necessària no localitzada com a component executable.
- **PENDENT INTEGRACIÓ:** codi existent sense wiring acreditat a la pantalla UC-006.

## 3. Classes ACTUAL — baixa / canvi de curs

```mermaid
classDiagram
direction LR

class AlumnePage {
  <<LLEGAT>>
  +csrf_alumnes_lifecycle
  +carrega alumnes-mostrar-alumne.min.js
}
class AlumneJsRuntime {
  <<LLEGAT>>
  +mostrarModalDonarBaixa(id)
  +confirmarBaixa(id,motiu)
  +confirmarCanviCurs(...)
}
class MostraBaixaEndpoint {
  <<LLEGAT>>
  +GET(idInsc)
}
class ConfirmaBaixaEndpoint {
  <<LLEGAT>>
  +POST(idinsc,motiu,enviarCoreu,csrfToken)
}
class CanviCursEndpoint {
  <<LLEGAT>>
  +POST(idinsc,target,imports,motiu,csrfToken)
}
class LegacyInvoiceMutationAuthorization {
  <<LLEGAT>>
  +assertSameOrigin()
  +assertCanEdit(user,intranet,path)
}
class LegacyUsocLifecycleGuard {
  <<LLEGAT>>
  +assertMayUseLegacyMutation(...)
}
class SifInternalApiClient {
  <<LLEGAT ADAPTER>>
  +previewCourseChange(...)
}
class IntranetLegacy {
  <<LLEGAT>>
  +confirmaBaixa_modalDonarBaixa(...)
  +realitzarCanviCurs_modalCanviCurs(...)
}

AlumnePage --> AlumneJsRuntime
AlumneJsRuntime --> MostraBaixaEndpoint
AlumneJsRuntime --> ConfirmaBaixaEndpoint
AlumneJsRuntime --> CanviCursEndpoint
ConfirmaBaixaEndpoint --> LegacyInvoiceMutationAuthorization
ConfirmaBaixaEndpoint --> LegacyUsocLifecycleGuard
ConfirmaBaixaEndpoint --> IntranetLegacy
CanviCursEndpoint --> LegacyInvoiceMutationAuthorization
CanviCursEndpoint --> LegacyUsocLifecycleGuard
CanviCursEndpoint --> SifInternalApiClient : preview opcional
CanviCursEndpoint --> IntranetLegacy : mutació final
```

### Lectura

- La baixa i el canvi tenen hardening parcial de servidor.
- El canvi pot obtenir una `economic_decision` d’un preview SIF, però l’endpoint final **no invoca** els serveis econòmics UC-006.
- No existeix en aquest circuit una classe que decideixi i executi `REFUND`/`CREDIT_BALANCE`/`COMPENSATION`.

## 4. Classes ACTUAL — anul·lació llegada de factura

```mermaid
classDiagram
direction LR

class FacturaPage {
  <<LLEGAT>>
  +carrega alumnes-factura.js
}
class FacturaJs {
  <<LLEGAT>>
  +mostrarModalAnulaFactura(id)
  +valida A_TORNAR
  +valida DATA_DEVOLUCIO
}
class MostrarAnulacioEndpoint {
  <<LLEGAT>>
  +GET(id)
}
class AnularFacturaEndpoint {
  <<LLEGAT>>
  +POST(id,tornar,dataAnulacio,obs)
}
class SifLegacyInvoiceMutationGuard {
  <<LLEGAT GUARD>>
  +assertLegacyMutationAllowed(user,id)
}
class LegacyInvoiceMutationAuthorization2 {
  <<LLEGAT>>
  +assertSameOrigin()
  +assertCanEdit(...)
}
class IntranetAnularFactura {
  <<LLEGAT>>
  +modalAnularFactura_Factures(id)
  +anularFactura(id,tornar,dataDevol,obs)
}

FacturaPage --> FacturaJs
FacturaJs --> MostrarAnulacioEndpoint
FacturaJs --> AnularFacturaEndpoint
MostrarAnulacioEndpoint --> SifLegacyInvoiceMutationGuard
MostrarAnulacioEndpoint --> IntranetAnularFactura
AnularFacturaEndpoint --> LegacyInvoiceMutationAuthorization2
AnularFacturaEndpoint --> SifLegacyInvoiceMutationGuard
AnularFacturaEndpoint --> IntranetAnularFactura
```

**Risc funcional:** `A TORNAR` i `DATA DEVOLUCIÓ` viuen al mateix modal que “anul·lar factura”, però el camí observat no prova ni executa un retorn bancari SIF. La UI barreja decisió fiscal/administrativa i econòmica.

## 5. Classes ACTUAL — serveis SIF disponibles

```mermaid
classDiagram
direction LR

class ManualRefundService {
  <<EXISTEIX · UC-28>>
  +registerByUuid(db,uuidFactura,input)
  +registerByNumVisible(db,numVisible,input)
}
class ManualRefundPayloadBuilder {
  <<EXISTEIX>>
  +forExistingInvoice(uuidFactura,input)
}
class ManualPaymentInvoiceRepository {
  <<EXISTEIX>>
  +findByUuid(db,uuid,forUpdate)
  +findByNumVisible(db,numVisible,forUpdate)
}
class PaymentService {
  <<EXISTEIX>>
  +registerPayment(payload)
  +registerPaymentInTransaction(db,payload)
}
class PaymentPayloadValidator {
  <<EXISTEIX>>
  +validate(payload)
}
class PayloadIdempotencyValidator {
  <<EXISTEIX>>
  +calculateHash(payload)
  +assertMatches(payload,hash)
}
class PaymentRepository {
  <<EXISTEIX>>
  +findByIdempotencyKey(db,key,forUpdate)
  +createPayment(db,payload)
}
class PaymentStatusCalculator {
  <<EXISTEIX>>
  +calculate(total,charges,refunds)
}
class CreditBalanceService {
  <<EXISTEIX · UC-29/29a>>
  +createCredit(input)
  +applyCreditByUuid(uuidCredit,uuidFactura,input)
  +applyCreditByNumVisible(uuidCredit,numVisible,input)
}
class CreditBalancePayloadBuilder {
  <<EXISTEIX>>
  +forCreditBalance(input)
  +forCompensation(uuidCredit,uuidFactura,input,invoice)
}
class CreditBalanceRepository {
  <<EXISTEIX>>
  +createCredit(db,payload)
  +findByUuid(db,uuid,forUpdate)
  +updateAvailableAmount(...)
  +invoiceOutstandingAmount(...)
}
class EnrollmentFundTransferActionService {
  <<EXISTEIX · UC-006 PARCIAL>>
  +transfer(auditContext,input) array
  +reverseTransfer(auditContext,input) array
}
class PaymentActionGateway {
  <<EXISTEIX · WIRING TRANSFER/REVERSAL>>
  +run(auditContext,operation)
}
class PayloadIdempotencyValidator {
  <<EXISTEIX · REUTILITZAT A LA BRANCA>>
  +calculateHash(payload) string
  +assertMatches(payload,storedHash) void
}

ManualRefundService --> ManualPaymentInvoiceRepository
ManualRefundService --> ManualRefundPayloadBuilder
ManualRefundService --> PaymentService
PaymentService --> PaymentPayloadValidator
PaymentService --> PaymentRepository
PaymentService --> PayloadIdempotencyValidator
PaymentRepository --> PaymentStatusCalculator

CreditBalanceService --> CreditBalancePayloadBuilder
CreditBalanceService --> CreditBalanceRepository
CreditBalanceService --> ManualPaymentInvoiceRepository
CreditBalanceService --> PaymentPayloadValidator
CreditBalanceService --> PaymentRepository
CreditBalanceService --> PayloadIdempotencyValidator : createCredit + reús COMPENSATION

EnrollmentFundTransferActionService --> PaymentActionGateway : REALLOCATE / UNALLOCATE
EnrollmentFundTransferActionService --> EnrollmentFundTransferService
PaymentActionGateway ..> PaymentService : possible FINAL, no camí observat
PaymentActionGateway ..> CreditBalanceService : possible FINAL, no camí observat
```

## 5.1. Classes ACTUAL — ledger d'atribució i consum per inscripció

```mermaid
classDiagram
direction LR
class EnrollmentFundMovementRepository {
  <<EXISTEIX · AMPLIAT UC-006>>
  +lockPayment(db,uuidPayment)
  +findInvoiceLineForInscription(db,uuidFactura,idInsc)
  +insertOrReuseExternalAllocation(db,movement)
  +availableAmountForInscription(db,idInsc,forUpdate)
  +insertOrReuseInternalTransfer(db,movement)
  +insertOrReuseInternalTransferReversal(db,movement)
  +insertOrReuseCreditCreate(db,movement)
  +insertOrReuseRefundExit(db,movement)
  +insertOrReuseCompensationAllocation(db,movement)
  +findByIdempotencyKey(db,key,forUpdate)
}
class CourseEnrollmentFundAllocationService {
  <<EXISTEIX>>
  +allocate(db,dsOrder,snapshot,invoiceResult)
}
class PackEnrollmentFundAllocationService {
  <<EXISTEIX>>
  +allocate(db,dsOrder,snapshot,invoiceResult)
}
class EnrollmentFundMovementTable {
  <<SIF DB · EXISTEIX>>
  +UUID_MOVEMENT
  +IDEMPOTENCY_KEY
  +MOVEMENT_TYPE
  +UUID_PAYMENT
  +UUID_CREDIT
  +UUID_FACTURA
  +ID_FACTURA_LINIA
  +ID_INSC_ORIGEN
  +ID_INSC_DESTI
  +IMPORT
  +UUID_OPERATION
  +CORRELATION_ID
}
CourseEnrollmentFundAllocationService --> EnrollmentFundMovementRepository
PackEnrollmentFundAllocationService --> EnrollmentFundMovementRepository
EnrollmentFundMovementRepository --> EnrollmentFundMovementTable
```

La base admet `EXTERNAL_ALLOCATION`, `INTERNAL_TRANSFER`, `REVERSAL`, `COMPENSATION_ALLOCATION`, `REFUND_EXIT` i `CREDIT_CREATE`. A la branca UC-006, `ManualRefundService`, `CreditBalanceService` i `EnrollmentFundTransferService` ja utilitzen aquest repositori. `INTERNAL_TRANSFER` és executable; continua pendent la decisió/orquestració de negoci.

## 5.2. ACTUAL ampliat 04/10 — sortides i entrada de saldo ja cablejades

```mermaid
classDiagram
direction LR
class ManualRefundService {
  +registerByUuid(db,uuidFactura,input)
  +registerByNumVisible(db,numVisible,input)
}
class PaymentService {
  +registerPayment(payload)
  +registerPaymentInTransaction(db,payload)
}
class CreditBalanceService {
  +createCredit(input)
  +applyCreditByUuid(uuidCredit,uuidFactura,input)
  +applyCreditByNumVisible(uuidCredit,numVisible,input)
  -ensureCreditFundExit(db,payload,uuidCredit)
  -ensureCompensationFundAllocation(db,payload,uuidPayment,credit,invoice)
}
class EnrollmentFundTransferService {
  +transfer(input) array
  +reverseTransfer(input) array
}
class EnrollmentFundTransferPayloadBuilder {
  +build(input) array
  +buildReversal(input) array
}
class EnrollmentFundMovementRepository {
  +availableAmountForInscription(db,idInsc,forUpdate)
  +insertOrReuseCreditCreate(db,movement)
  +insertOrReuseRefundExit(db,movement)
  +insertOrReuseCompensationAllocation(db,movement)
}
class CreditBalance {
  <<SIF DB>>
  +UUID_CREDIT
  +IDEMPOTENCY_KEY
  +IDEMPOTENCY_PAYLOAD_HASH
}
class EnrollmentFundMovement {
  <<SIF DB>>
  +UUID_PAYMENT
  +UUID_CREDIT
  +ID_INSC_ORIGEN
  +ID_INSC_DESTI
  +MOVEMENT_TYPE
}
EnrollmentFundTransferService --> EnrollmentFundTransferPayloadBuilder
EnrollmentFundTransferService --> EnrollmentFundMovementRepository : INTERNAL_TRANSFER / REVERSAL
ManualRefundService --> PaymentService : REFUND compartint transacció
ManualRefundService --> EnrollmentFundMovementRepository : REFUND_EXIT
CreditBalanceService --> CreditBalance : crea/consumeix
CreditBalanceService --> EnrollmentFundMovementRepository : CREDIT_CREATE / COMPENSATION_ALLOCATION
EnrollmentFundMovementRepository --> EnrollmentFundMovement : persisteix/locka
```

**Implementat:** refund/saldo/compensació/transfer A→B són codi real quan hi ha IDs explícits; transfer/reversal també passen per un wrapper auditat. **Pendent:** controlador UC-006, titularitat, evidence guard genèric, wiring del transfer al coordinator i auditoria obligatòria de la resta d'accions.
## 6. Mancances del model ACTUAL

1. **No hi ha orquestrador UC-006** que converteixi una baixa/canvi/anul·lació en una única decisió econòmica autoritzada.
2. **No hi ha command/controller UC-006** que obligui identificadors d'origen/destí, titularitat, evidència i identitat estable del dret.
3. `ManualRefundService` ja pot limitar el valor per inscripció quan rep `source_enrollment_id`, però encara no acredita:
   - titular/receptor;
   - evidència externa genèrica del retorn;
   - deduplicació cross-channel.
4. `CreditBalanceService::createCredit()` ja té K/hash i `CREDIT_CREATE` opcional, però la K de dret no es deriva/obliga des de la UI.
5. `applyCredit*` comprova saldo, deute i inscripció/línia destí quan s'informa, però no titularitat compatible.
6. `INTERNAL_TRANSFER` té builder/servei/repositori/CLI/proves i reversió restringida; falta l'orquestració UC-006/UC-071 que decideixi l'import, actor/event i si una reversió funcional és admissible.
7. `PaymentActionGateway` ja està cablejat a transfer/reversal; refund/saldo/compensació i superfícies productives continuen fora del gateway.
8. `public/api/payments/register.php` instancia `PaymentService` directament: no és un endpoint UC-006 complet.
9. La UI llegada d’anul·lació conserva semàntica “A TORNAR” sense separar estat pendent de retorn vs retorn confirmat.

## 7. Classes FINAL — UC-006 tancat

```mermaid
classDiagram
direction LR

class Uc006Controller {
  <<PROPOSAT>>
  +preview(command)
  +confirm(command)
}
class Uc006Authorization {
  <<PROPOSAT>>
  +assertCanDecide(actor,operation)
}
class Uc006DecisionService {
  <<PROPOSAT>>
  +classify(context) EconomicDecision
  +execute(decision,context)
}
class EconomicRightsRepository {
  <<PROPOSAT · pot evolucionar EnrollmentFundMovementRepository>>
  +loadOriginRights(...)
  +lockAvailableAmount(...)
  +reserveOrConsume(...)
}
class RefundEvidenceGuard {
  <<PROPOSAT GENÈRIC · patró UC-111 existent>>
  +assertConfirmedExternalRefund(...)
  +findExistingExternalOperation(...)
}
class CreditOwnershipPolicy {
  <<PROPOSAT>>
  +assertCompatible(credit,invoice)
}
class Uc006AuditGateway {
  <<PROPOSAT / pot reutilitzar PaymentActionGateway>>
  +run(context,operation)
}
class ManualRefundServiceF {
  <<EXISTEIX>>
}
class CreditBalanceServiceF {
  <<EXISTEIX>>
}
class PaymentServiceF {
  <<EXISTEIX>>
}
class PaymentRepositoryF {
  <<EXISTEIX>>
}
class CreditBalanceRepositoryF {
  <<EXISTEIX>>
}
class FiscalDecision {
  <<UC-05/74 · SEPARAT>>
  +classifyFiscalImpact(...)
}
class LegacySync {
  <<PROPOSAT>>
  +syncAfterSifCommit(...)
}
class IncidentService {
  <<PROPOSAT/REUTILITZABLE>>
  +openOnAmbiguity(...)
}

Uc006Controller --> Uc006Authorization
Uc006Controller --> Uc006DecisionService
Uc006DecisionService --> EconomicRightsRepository
Uc006DecisionService --> RefundEvidenceGuard : REFUND
Uc006DecisionService --> CreditOwnershipPolicy : CREDIT/COMPENSATION
Uc006DecisionService --> Uc006AuditGateway
Uc006AuditGateway --> ManualRefundServiceF : REFUND
Uc006AuditGateway --> CreditBalanceServiceF : CREDIT/COMPENSATION
ManualRefundServiceF --> PaymentServiceF
PaymentServiceF --> PaymentRepositoryF
CreditBalanceServiceF --> CreditBalanceRepositoryF
CreditBalanceServiceF --> PaymentRepositoryF
Uc006DecisionService ..> FiscalDecision : decisió independent
Uc006Controller --> LegacySync : post-COMMIT
Uc006DecisionService --> IncidentService : REVIEW/conflicte
```

## 8. Responsabilitats FINAL

### `Uc006Controller`
- rep identificadors estables, no imports autoritatius calculats només al DOM;
- separa preview de confirm;
- exigeix actor, request/correlation id i versió/fingerprint de l’estat previsualitzat.

### `Uc006DecisionService`
- decideix `NO_CHANGE | REFUND | CREDIT_BALANCE | COMPENSATION | REVIEW`;
- no crea rectificatives: deriva la decisió fiscal al cas corresponent;
- no permet que baixa/canvi “inventin” moviments econòmics.

### `EconomicRightsRepository`
- calcula el valor econòmic disponible per origen/inscripció;
- bloqueja concurrentment;
- impedeix doble consum entre retorn, saldo i compensació.

### `RefundEvidenceGuard`
- diferencia retorn aprovat/pending de retorn bancari real;
- correlaciona Redsys/transferència/operació externa;
- evita duplicat manual d’un retorn ja conciliat.

- pot generalitzar el patró existent de `NovicePromotionOriginRefundEvidenceSourceInterface` i l'estat `APPROVED_WAITING_REFUND`, sense acoblar-se al domini UC-111;

### `CreditOwnershipPolicy`
- evita usar un saldo d’un titular en una factura incompatible sense regla explícita.

## 9. Criteri de pas a FINAL implementat

El diagrama FINAL només serà “implementat/verificat” quan el camí real sigui:

`pàgina intranet → Uc006Controller → Authorization → DecisionService → rights/evidence guards → audit gateway → servei UC-28/29/29a → COMMIT SIF → sync llegada`

i existeixin proves d’autorització, titularitat, límits, concurrència, idempotència, fallada parcial i E2E.
