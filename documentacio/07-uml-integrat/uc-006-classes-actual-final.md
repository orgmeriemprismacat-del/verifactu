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
class PaymentActionGateway {
  <<EXISTEIX · NO WIRING UC006 ACREDITAT>>
  +run(auditContext,operation)
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

PaymentActionGateway ..> PaymentService : possible FINAL, no camí observat
PaymentActionGateway ..> CreditBalanceService : possible FINAL, no camí observat
```

## 6. Mancances del model ACTUAL

1. **No hi ha orquestrador UC-006.**
2. **No hi ha command/controller UC-006** que obligui a triar una decisió econòmica única.
3. `ManualRefundService` no acredita:
   - límit retornable;
   - titular;
   - inscripció/línia origen;
   - evidència externa del retorn.
4. `CreditBalanceService::createCredit()` no mostra idempotència per origen.
5. `applyCredit*` comprova saldo i deute, però no titularitat compatible.
6. `PaymentActionGateway` existeix, però els serveis i scripts examinats no hi passen.
7. `public/api/payments/register.php` instancia `PaymentService` directament: no és un endpoint UC-006 complet.
8. La UI llegada d’anul·lació conserva semàntica “A TORNAR” sense separar estat pendent de retorn vs retorn confirmat.

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
  <<PROPOSAT>>
  +loadOriginRights(...)
  +lockAvailableAmount(...)
  +reserveOrConsume(...)
}
class RefundEvidenceGuard {
  <<PROPOSAT>>
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

### `CreditOwnershipPolicy`
- evita usar un saldo d’un titular en una factura incompatible sense regla explícita.

## 9. Criteri de pas a FINAL implementat

El diagrama FINAL només serà “implementat/verificat” quan el camí real sigui:

`pàgina intranet → Uc006Controller → Authorization → DecisionService → rights/evidence guards → audit gateway → servei UC-28/29/29a → COMMIT SIF → sync llegada`

i existeixin proves d’autorització, titularitat, límits, concurrència, idempotència, fallada parcial i E2E.
