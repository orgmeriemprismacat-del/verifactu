# UC-111 · Diagrames de seqüència ACTUAL / FINAL

**Objectiu:** representar l'ordre de missatges i persistència dels subfluxos UC-111 sense confondre el codi legacy amb els serveis SIF desenvolupats a la branca.

## 1. ACTUAL · seqüència completa contrastada

```plantuml
@startuml
title UC-111 | ACTUAL contrastat | alta -> validació -> pagament -> comunicació legacy
actor Alumne
participant "enviarInscripcio.php" as Web
database inscripcions
database recent_titulat
participant "enviarImatgeSocRecentTitulat.php" as Upload
actor Secretaria
participant "alumnes-validar-descomptes.js" as JS
participant "sendMsgValidatProfessorNovell.php" as Endpoint
participant "Intranet::sendMsgValidatCurosProfessorNovell()" as Intranet
participant "PagamentCursAutomatic.php" as PaymentView
participant "realitzaPagamentAutomatic.php" as Payment
database promocions
participant MailSMTPComvive as Mail

Alumne -> Web : alta de curs + opció novell
Web -> inscripcions : INSERT matrícula
alt CURS=JASOM i novell
  Web -> recent_titulat : INSERT(ID_INSC), VALIDAT=0
end

Alumne -> Upload : POST resguard/titulació
Upload --> Alumne : resultat de pujada
note over Upload
  La còpia legacy no acredita encara
  hash, storage privat ni versionat d'evidència.
end note

Secretaria -> JS : alternar Sí/No visual
Secretaria -> JS : clicar validar
JS -> Endpoint : GET idInsc, verificat
Endpoint -> Intranet : sendMsgValidatCurosProfessorNovell()
alt verificat = 1
  Intranet -> recent_titulat : UPDATE VALIDAT=1
  Intranet -> Mail : aprovat + opcions de pagament
else verificat = 0
  Intranet -> recent_titulat : UPDATE VALIDAT=2
  Intranet -> Mail : denegat + opcions de pagament
end
Endpoint --> JS : "OK" / error textual

Alumne -> PaymentView : obrir pàgina de pagament
PaymentView -> recent_titulat : consulta legacy
PaymentView --> Alumne : mostrar/ocultar opcions
Alumne -> Payment : completar pagament

alt JASOM completament pagat
  Payment -> recent_titulat : SELECT ID_INSC AND VALIDAT=1
  alt VALIDAT=1
    Payment -> promocions : SELECT últim MACABODETITULAR del DNI
    promocions --> Payment : codi existent o buit
    note over Payment,promocions
      A la còpia auditada NO hi ha INSERT
      del nou dret en aquest punt.
      El correu antic conté un literal de codi.
    end note
    Payment -> Mail : confirmació + text promocional legacy
  else VALIDAT!=1
    Payment -> Mail : confirmació sense benefici novell
  end
end
@enduml
```

**Punt de fallada ACTUAL:** el lloc funcional correcte és el final del cobrament complet, després d'haver validat recent_titulat.VALIDAT=1; però el PHP legacy auditat només consulta promocions i no demostra una concessió nova idempotent.

## 2. FINAL implementat a la branca · pagament committed -> dret -> codi preparat

```plantuml
@startuml
title UC-111 | FINAL | validació + pagament complet -> entitlement -> codi
actor Secretaria
participant NovicePromotionSecretaryDecisionProjector as Decision
database discount_validation as Validation
database commercial_operation as Operation
participant RedsysCourseInvoiceService as CoursePayment
participant InvoiceService as Invoice
participant NovicePromotionInvoiceLinkService as Link
participant NovicePromotionGrantService as Grant
participant NovicePromotionCodePreparationService as Prepare
database "factura / payment_*" as Fiscal
database "commercial_entitlement / novice_promotion_grant" as Right
database novice_promotion_code_outbox as Outbox

Secretaria -> Decision : projectDecision(...)
Decision -> Validation : VALIDATED / REJECTED
Decision -> Operation : READY_FOR_PAYMENT

... Redsys validat ...

CoursePayment -> Invoice : issueInvoice(...)
Invoice -> Fiscal : factura + payment + allocation
Invoice --> CoursePayment : COMMIT + uuid_factura

CoursePayment -> Link : attach(enrollment, invoice)
Link -> Validation : comprovar decisió
Link -> Fiscal : verificar F1/F2 i estat PAID
Link -> Operation : PAYMENT_PENDING o PAID
Link --> CoursePayment : grant_eligible?

alt VALIDATED + JASOM completament pagat
  CoursePayment -> Grant : issueForOperation(uuid)
  Grant -> Fiscal : lock + CHARGE - REFUND
  Grant -> Validation : VALIDATED
  Grant -> Right : INSERT o reuse dret únic per persona
  Grant --> CoursePayment : uuid_entitlement
  CoursePayment -> Prepare : prepare(entitlement, runtime secret, key version)
  Prepare -> Right : CODE_HASH + ACTIVE
  Prepare -> Outbox : token aleatori xifrat + PREPARED
  Prepare --> CoursePayment : prepared/reused
else pendent o denegat
  CoursePayment --> CoursePayment : no concedir dret
end

note over Grant,Prepare
  Són transaccions separades i idempotents.
  Si la preparació del codi falla, el grant ja
  existent es reutilitza en el reintent.
end note
@enduml
```

## 3. FINAL/branca · preparar i lliurar codi

```plantuml
@startuml
title UC-111 | FINAL branca | codi xifrat, destinatari verificat i worker privat
participant NovicePromotionCodePreparationService as Prepare
database "commercial_entitlement / grant" as Right
database novice_promotion_code_outbox as Outbox
participant NovicePromotionEmailVerificationService as Verify
database novice_promotion_verified_recipient as Recipient
participant NovicePromotionDeliveryAttemptService as Delivery
participant NovicePromotionPrivateMailWorker as Worker
interface NovicePromotionMailTransportInterface as Mail

Prepare -> Right : lock i revalidar origen/JASOM
Prepare -> Prepare : generar NOV-* aleatori
Prepare -> Right : CODE_HASH + ACTIVE
Prepare -> Outbox : token xifrat + PREPARED
Verify -> Recipient : registrar email verificat
Worker -> Delivery : claim(entitlement)
Delivery -> Right : revalidar dret/origen
Delivery -> Outbox : SENDING + claim_id
Worker -> Delivery : loadClaimForPrivateMailer
Delivery --> Worker : token desxifrat només al worker
Worker -> Mail : send(token)
Mail --> Worker : accepted / failed
Worker -> Delivery : recordResult
Delivery -> Outbox : SENT o FAILED/backoff
note over Worker,Mail
  Transport real i autenticació de la
  verificació continuen pendents d'integració.
end note
@enduml
```

## 4. FINAL/branca · consum parcial del dret original

```plantuml
@startuml
title UC-111 | FINAL branca | reserva i aplicació del saldo original
actor "Checkout autenticat" as Checkout
participant NovicePromotionRedemptionService as Redeem
participant NovicePromotionAmountPolicy as Amount
database novice_promotion_grant as Grant
database novice_promotion_application as App
database commercial_operation as Op
database "factura / payment_*" as Fiscal

Checkout -> Redeem : reserve(code, holder, destination, key)
Redeem -> Grant : lock dret + available
Redeem -> Op : lock destí READY_FOR_PAYMENT
Redeem -> Redeem : verificar holder, vigència, JASOM pagat
Redeem -> Amount : allocate(available, ordinaryNet, requested?)
Amount --> Redeem : applied + remaining
Redeem -> Grant : AVAILABLE -= applied
Redeem -> App : INSERT RESERVED
Redeem --> Checkout : reservation

... checkout real calcula/emeteix/cobra ...

Checkout -> Redeem : confirmApplied(application, invoice)
Redeem -> App : lock RESERVED
Redeem -> Op : validar snapshot final
Redeem -> Fiscal : factura F1/F2 + fact_rels + CHARGE-REFUND
Redeem -> App : APPLIED
Redeem --> Checkout : applied
@enduml
```

## 5. FINAL/branca · canvi/baixa i dret derivat

```plantuml
@startuml
title UC-111 | FINAL branca | canvi, baixa i saldo derivat
actor Secretaria
participant NovicePromotionCourseTransferReviewService as TransferReview
participant NovicePromotionFirstTransferConfirmationService as TransferConfirm
participant NovicePromotionSuccessiveTransferReviewService as NextReview
participant NovicePromotionSuccessiveTransferConfirmationService as NextConfirm
participant NovicePromotionDestinationCancellationReviewService as CancelReview
participant NovicePromotionTransferredDestinationCancellationReviewService as TransferCancelReview
participant NovicePromotionDerivedApplicationCancellationReviewService as DerivedCancelReview
participant NovicePromotionDerivedBalanceActivationService as Activate
participant NovicePromotionTransferredCancellationActivationService as TransferActivate
participant NovicePromotionDerivedApplicationCancellationActivationService as DerivedActivate
interface NovicePromotionAdjustmentApprovalSourceInterface as Approval
database novice_promotion_application_transfer as Transfer
database novice_promotion_derived_balance as Derived
database novice_promotion_derived_application as DerivedApp

alt Canvi primer destí
  Secretaria -> TransferReview : stage
  TransferReview -> Transfer : PENDING_FISCAL_REVIEW
  TransferConfirm -> Approval : approvedFirstTransfer
  Approval --> TransferConfirm : final APPROVED
  TransferConfirm -> Transfer : CONFIRMED
else Canvi successiu
  Secretaria -> NextReview : stageFromDerivedApplication / stageFromConfirmedTransfer
  NextReview -> Transfer : PENDING_FISCAL_REVIEW
  NextConfirm -> Approval : approvedSuccessiveTransfer
  Approval --> NextConfirm : final APPROVED
  NextConfirm -> Transfer : predecessor històric + successor CONFIRMED
else Baixa destí original
  Secretaria -> CancelReview : stageOriginalApplicationReview
  CancelReview -> Derived : PENDING_FISCAL_REVIEW
  Activate -> Approval : approvedCancellation
  Approval --> Activate : final APPROVED
  Activate -> Derived : ACTIVE + nou venciment
else Baixa curs traspassat
  Secretaria -> TransferCancelReview : stageCurrentTransferredDestinationReview
  TransferCancelReview -> Derived : PENDING amb SOURCE_UUID_TRANSFER
  TransferActivate -> Approval : approvedTransferredCancellation
  Approval --> TransferActivate : final APPROVED
  TransferActivate -> Transfer : CANCELLED / CONVERTED_TO_DERIVED
  TransferActivate -> Derived : ACTIVE + nou venciment
else Baixa curs pagat amb saldo derivat
  Secretaria -> DerivedCancelReview : stageDerivedApplicationReview
  DerivedCancelReview -> Derived : child PENDING amb parent/source dapp
  DerivedActivate -> Approval : approvedDerivedApplicationCancellation
  Approval --> DerivedActivate : final APPROVED
  DerivedActivate -> DerivedApp : CONVERTED_TO_DERIVED
  DerivedActivate -> Derived : child ACTIVE + nou venciment
end
@enduml
```

**Tall actual:** primer canvi, canvis successius i baixes amb origen aplicació original, `derived_application.APPLIED` o **qualsevol últim transfer confirmat sense successor** tenen servei de branca. En la baixa del transfer, la cadena es recorre fins identificar el dret promocional real que s'estava movent i es conserva com a parent si era derivat.
## 6. FINAL/branca · consum derivat i devolució JASOM

```plantuml
@startuml
title UC-111 | FINAL branca | consum derivat, projecció i root refund
actor Checkout
participant NovicePromotionDerivedBalanceRedemptionService as DerivedSpend
database novice_promotion_derived_balance as Balance
database novice_promotion_derived_application as DApp
participant NovicePromotionLineageSnapshotService as Snapshot
participant NovicePromotionLineageProjectionPolicy as Projection
participant NovicePromotionRootRefundPlanService as Plan
participant NovicePromotionRootRefundReviewService as Review
participant NovicePromotionRootRefundExecutionService as Execute
participant NovicePromotionRootRefundRecoveryResolutionService as Resolve
participant NovicePromotionRootRefundRecoveryCompletionService as Complete
interface NovicePromotionAdjustmentApprovalSourceInterface as Approval
interface NovicePromotionOriginRefundEvidenceSourceInterface as RefundEvidence
interface NovicePromotionRecoveryResolutionSourceInterface as RecoveryEvidence
database "refund review / recovery items" as Recovery

Checkout -> DerivedSpend : reserve(...)
DerivedSpend -> Balance : AVAILABLE -= amount
DerivedSpend -> DApp : RESERVED
alt factura/residual final correctes
  Checkout -> DerivedSpend : confirmApplied(...)
  DerivedSpend -> DApp : APPLIED
else fracàs segur abans d'intent/factura
  Checkout -> DerivedSpend : release(...)
  DerivedSpend -> Balance : restaurar import
  DerivedSpend -> DApp : RELEASED
end

Review -> Snapshot : projectLocked(root)
Snapshot -> Projection : project(SQL rows)
Projection --> Snapshot : graf lògic
Review -> Plan : plan + fingerprint
Review -> Recovery : PENDING_APPROVAL + freeze

Execute -> Approval : approvedRootRefund(review)
Approval --> Execute : final APPROVED
Execute -> RefundEvidence : confirmedOriginRefund(review)
RefundEvidence --> Execute : refund JASOM real acreditat
Execute -> Recovery : revalidar plan/fingerprint
Execute -> Balance : cancel·lar romanents vius
Execute -> Recovery : EXECUTED + PENDING_RECOVERY pels usos actuals

Resolve -> RecoveryEvidence : evidència recovery / waiver / cancel·lació
RecoveryEvidence --> Resolve : resolució verificada
Resolve -> Recovery : RECOVERED / WAIVED / CANCELLED
Complete -> Recovery : RECOVERY_RESOLVED quan tots estan tancats
note over Execute,Recovery
  No crear un cobrament fictici.
  No reclamar predecessors històrics.
  El model final (000027) admet també
  APPROVED_WAITING_REFUND com a handoff,
  però el servei actual executa directament
  PENDING_APPROVAL -> EXECUTED quan ja té
  aprovació i refund d'origen confirmat.
end note
@enduml
```

## 7. Punts de tall transaccionals

| Seqüència | Punt de commit que no s'ha de travessar parcialment |
| --- | --- |
| concessió | validació de factures/cash + concessió única |
| preparació codi | hash al dret + outbox xifrat |
| reserva original/derivada | dèbit de saldo + fila RESERVED |
| confirmació aplicació | factura/cash final verificats + APPLIED |
| traspàs | tancar atribució anterior + confirmar nova atribució |
| baixa | tancar predecessor + activar dret derivat |
| root refund | freeze/review abans de conseqüències comercials; després recovery items auditables |

## 8. Estat

Aquestes seqüències reflecteixen el codi actual de la branca i el llegat contrastat, però **no impliquen execució de MySQL ni desplegament**. Els connectors de sessió, secretaria, pricing, emissió fiscal, transport de correu i evidències externes continuen sent portes d'integració.

[Classes](uc-111-classes-actual-final.md) · [Activitats](uc-111-activitats-actual-final.md) · [Dades i estats](uc-111-dades-estats-actual-final.md) · [Traçabilitat](uc-111-tracabilitat-implementacio.md)
