# UC-111 · Diagrames de seqüència ACTUAL / FINAL

**Objectiu:** representar l'ordre de missatges i persistència dels subfluxos UC-111 sense confondre el codi legacy amb els serveis SIF desenvolupats a la branca.

## 1. ACTUAL · alta web i decisió intranet observable

```plantuml
@startuml
title UC-111 | ACTUAL observable | alta i validació legacy
actor Alumne
participant "enviarInscripcio.php" as Web
database inscripcions
database recent_titulat
participant "enviarImatgeSocRecentTitulat.php" as Upload
actor Secretaria
participant "alumnes-validar-descomptes.js" as JS
participant "sendMsgValidatProfessorNovell.php" as Endpoint
participant "Intranet::sendMsgValidatCurosProfessorNovell" as Intranet

Alumne -> Web : alta JASOM + novell
Web -> inscripcions : INSERT
alt JASOM + novell
  Web -> recent_titulat : INSERT ID_INSC
end
Alumne -> Upload : pujar justificació
Upload --> Alumne : resultat legacy
Secretaria -> JS : Sí/No + validar
JS -> Endpoint : GET idInsc, verificat
Endpoint -> Intranet : delegar decisió
Intranet -> recent_titulat : VALIDAT 1/2 (segons codi contrastat)
Endpoint --> JS : resposta HTML
JS --> Secretaria : modal resultat
note over Intranet,recent_titulat
  Aquesta seqüència NO acredita per si sola
  concessió, pagament ni lliurament del codi.
end note
@enduml
```

## 2. FINAL/branca · expedient, decisió, cobrament i concessió

```plantuml
@startuml
title UC-111 | FINAL branca | preparar, validar, conciliar i concedir
actor "Adaptador web" as Web
participant NovicePromotionEnrollmentStager as Stage
database "commercial_operation / party" as Op
actor Secretaria
participant NovicePromotionSecretaryDecisionProjector as Decision
database discount_validation as Validation
participant NovicePromotionInvoiceLinkService as Link
participant NovicePromotionGrantReconciler as Reconcile
participant NovicePromotionGrantService as Grant
database "factura / fact_rels / payment_*" as Fiscal
database "commercial_entitlement / novice_promotion_grant" as Right

Web -> Stage : stage(inscripció JASOM)
Stage -> Op : create/reuse PREPARED
Stage --> Web : UUID operation
Secretaria -> Decision : projectDecision(ID_INSC)
Decision -> Validation : VALIDATED o REJECTED
alt VALIDATED
  Decision -> Op : READY_FOR_PAYMENT
end

Link -> Fiscal : vincular factures F1/F2 a l'operació
Reconcile -> Fiscal : buscar JASOM validat i completament cobrat
Reconcile -> Grant : issueForOperation(uuid)
Grant -> Fiscal : lock + verificar totals + CHARGE-REFUND
Grant -> Validation : verificar VALIDATED
Grant -> Right : INSERT/reuse dret únic per holder
Grant --> Reconcile : entitlement
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
participant NovicePromotionDerivedBalanceActivationService as Activate
participant NovicePromotionTransferredCancellationActivationService as TransferActivate
interface NovicePromotionAdjustmentApprovalSourceInterface as Approval
database novice_promotion_application_transfer as Transfer
database novice_promotion_derived_balance as Derived

alt Canvi primer destí
  Secretaria -> TransferReview : stage
  TransferReview -> Transfer : PENDING_FISCAL_REVIEW
  TransferConfirm -> Approval : approvedFirstTransfer
  Approval --> TransferConfirm : final APPROVED
  TransferConfirm -> Transfer : CONFIRMED
else Canvi successiu
  Secretaria -> NextReview : stageFromDerivedApplication / stageFromConfirmedTransfer
  NextReview -> Transfer : PENDING_FISCAL_REVIEW
  NextConfirm -> Approval : decisió successiva
  NextConfirm -> Transfer : CONFIRMED
else Baixa destí original
  Secretaria -> CancelReview : stageOriginalApplicationReview
  CancelReview -> Derived : PENDING_FISCAL_REVIEW
  Activate -> Approval : approvedCancellation
  Activate -> Derived : ACTIVE + nou venciment
else Baixa curs traspassat
  Secretaria -> TransferCancelReview : stageFirstTransferredDestinationReview
  TransferCancelReview -> Derived : PENDING amb SOURCE_UUID_TRANSFER
  TransferActivate -> Approval : approvedTransferredCancellation
  TransferActivate -> Transfer : CANCELLED / CONVERTED_TO_DERIVED
  TransferActivate -> Derived : ACTIVE + nou venciment
end
@enduml
```

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
database "refund review / recovery items" as Recovery

Checkout -> DerivedSpend : reserve(...)
DerivedSpend -> Balance : AVAILABLE -= amount
DerivedSpend -> DApp : RESERVED
Checkout -> DerivedSpend : confirmApplied(...)
DerivedSpend -> DApp : APPLIED

Review -> Snapshot : projectLocked(root)
Snapshot -> Projection : project(SQL rows)
Projection --> Snapshot : graf lògic
Review -> Plan : plan + fingerprint
Review -> Recovery : PENDING_APPROVAL + freeze

Execute -> Recovery : carregar review aprovada
Execute -> Execute : verificar evidència refund origen
Execute -> Balance : cancel·lar romanents vius
Execute -> Recovery : crear PENDING_RECOVERY pels consums actuals

Resolve -> Recovery : RECOVERED / WAIVED / CANCELLED amb evidència
Complete -> Recovery : tancar workflow només quan resolt
note over Execute,Recovery
  No crear un cobrament fictici.
  No reclamar predecessors històrics.
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

[Classes](uc-111-classes-actual-final.md) · [Activitats](uc-111-activitats-actual-final.md) · [Traçabilitat](uc-111-tracabilitat-implementacio.md)
