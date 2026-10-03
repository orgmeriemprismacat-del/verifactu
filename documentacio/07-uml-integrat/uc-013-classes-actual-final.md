# UC-013 · Classes ACTUAL / FINAL — Orquestrar la doble facturació USOC

**Data d'auditoria:** 2026-10-02  
**Main contrastat:** `f7fa0822f82be96e842d9f2d031e643ab07f617c`  
**Abast:** web PrisMa, intranet, pay.prisma.cat/SIF, doble pagador alumne + entitat USOC, conciliació i lifecycle.  
**Criteri:** ACTUAL descriu responsabilitats observades al codi; FINAL descriu l'arquitectura objectiu o residual. No es marca com implementat allò que només existeix com a disseny.

## 1. Classes ACTUAL — web i intranet legacy

```mermaid
classDiagram
direction LR
class PaginaDescomptesUsoc {
  +mostrar()
}
class InscripcioCursAfiliat {
  +mostrarFormulari()
}
class EnviarInscripcioAfiliat {
  <<script PHP>>
  +reserveIdPag()
  +crearInscripcio()
  +TIPUS_DESC=4
  +VALID_DESC=pendent
  +comunicarFEUSOC()
}
class Intranet {
  +cnsAlumnDescNoValidat()
  +sendMsgValidatCurosDescomptes()
  +updValidDescByInsc()
  +updValidDescByInscPreu()
}
class LegacyDiscountValidationLookup {
  +isUsoc(idInsc) bool
}
class SifInternalUsocClient {
  +request(action,payload)
}
class LegacyUsocLifecycleGuard {
  +assertAllowed(idInsc,operation)
  +payerSnapshot()
}
PaginaDescomptesUsoc --> InscripcioCursAfiliat
InscripcioCursAfiliat --> EnviarInscripcioAfiliat
Intranet --> LegacyDiscountValidationLookup
Intranet --> SifInternalUsocClient : begin/complete validation
LegacyUsocLifecycleGuard --> SifInternalUsocClient : lifecycle_guard/lifecycle_plan
```

### Responsabilitats observades

- La web pública mostra la proposta USOC i el formulari només sol·licita el tractament; marcar USOC no valida l'afiliació.
- `enviarInscripcioAfiliat.php` crea la inscripció legacy amb `TIPUS_DESC=4`, `VALID_DESC` pendent i reserva `IDPAG` amb l'allocator compartit protegit per named lock.
- La validació manual passa per POST + CSRF + rol d'edició i un `requestId`.
- Per USOC, la decisió legacy queda coordinada amb el SIF mitjançant dues fases durables abans/després de la mutació.
- Els canvis de curs/baixes USOC ja no poden continuar cegament: el guard consulta el SIF i bloqueja quan existeix expedient fiscal/econòmic.

## 2. Classes ACTUAL — nucli SIF USOC

```mermaid
classDiagram
direction LR
class RedsysUsocInvoiceService {
  +sourceType() string
  +issueFromIntentSnapshot(db,dsOrder,snapshot) array
  +issueStudentFromValidatedNotification(...)
}
class LegacyUsocSnapshotRepository {
  +loadByIdpag(db,idpag,studentAmount,entityAmount,idInsc) array
}
class LegacyUsocInvoicePayloadBuilder {
  +buildStudentPayload(snapshot) array
  +buildEntityPayload(snapshot,input) array
}
class RedsysInvoicePayloadBuilder {
  +buildFromValidatedNotification(...)
}
class InvoiceService {
  +issueInvoice(payload) array
}
class UsocFinancingCaseRepository {
  +recordStudentInvoice(...)
  +requireForEntityInvoice(...)
  +recordEntityInvoice(...)
  +updateReconciliation(...)
}
class UsocStudentInvoiceLinkRepository {
  +assertMatches(db,studentInvoiceUuid,idInsc,idpag,studentAmount)
}
class UsocEntityInvoiceService {
  +issueEntityFromExplicitInput(sifDb,legacyDb,input) array
}
class UsocEntityPaymentService {
  +register(...)
}
class PaymentService {
  +registerPayment(...)
}
class UsocCaseReconciler {
  +reconcile(...)
}
class UsocValidationDecisionService {
  +begin(...)
  +complete(...)
  +reconcile(...)
}
class UsocValidationDecisionRepository
class UsocLifecycleGuardService {
  +guard(...)
}
class UsocLifecyclePlanService {
  +plan(...)
}

RedsysUsocInvoiceService --> LegacyUsocInvoicePayloadBuilder
RedsysUsocInvoiceService --> RedsysInvoicePayloadBuilder
RedsysUsocInvoiceService --> InvoiceService
RedsysUsocInvoiceService --> UsocFinancingCaseRepository : checkpoint alumne
UsocEntityInvoiceService --> UsocFinancingCaseRepository : exigeix checkpoint
UsocEntityInvoiceService --> LegacyUsocSnapshotRepository
UsocEntityInvoiceService --> UsocStudentInvoiceLinkRepository
UsocEntityInvoiceService --> LegacyUsocInvoicePayloadBuilder
UsocEntityInvoiceService --> InvoiceService
UsocEntityPaymentService --> PaymentService
UsocEntityPaymentService --> UsocCaseReconciler
UsocCaseReconciler --> UsocFinancingCaseRepository
UsocValidationDecisionService --> UsocValidationDecisionRepository
UsocLifecycleGuardService --> UsocFinancingCaseRepository
UsocLifecyclePlanService --> UsocLifecycleGuardService
```

### Responsabilitats verificades al main actual

- `RedsysUsocInvoiceService` factura només la part alumne i persisteix/reutilitza `usoc_financing_case`.
- `UsocEntityInvoiceService` exigeix `ID_INSC`, `IDPAG`, imports, UUID de factura alumne i billing explícit; comprova checkpoint abans de facturar.
- `UsocStudentInvoiceLinkRepository` valida que la factura alumne sigui realment Redsys/USOC, de la mateixa inscripció i del mateix import.
- `LegacyUsocInvoicePayloadBuilder` exigeix `TIPUS_DESC=4` i `VALID_DESC=1`, usa imports explícits i construeix dues factures fiscals separades.
- `UsocEntityPaymentService` registra cobrament real de l'entitat i força reconciliació posterior.
- `UsocValidationDecisionService` manté el protocol `REQUESTED / COMMITTED / REVIEW_REQUIRED`.
- `UsocLifecyclePlanService` planifica per pagador separat i limita el màxim retornable al net real cobrat.

## 3. Persistència ACTUAL

```mermaid
classDiagram
direction LR
class factura {
  UUID_FACTURA
  IDEMPOTENCY_KEY
  SOURCE_CHANNEL
  TOTAL
  ESTAT_COBRAMENT
}
class fact_rels {
  UUID_FACTURA
  SOURCE_TYPE
  SOURCE_ID
  IDPAG
  VISIBLE_ALUMNE
}
class payment_transaction
class payment_allocation
class usoc_financing_case {
  UUID_CASE
  ID_INSC
  IDPAG
  UUID_STUDENT_INVOICE
  UUID_ENTITY_INVOICE
  STUDENT_AMOUNT
  ENTITY_AMOUNT
  STUDENT_PAYMENT_STATUS
  ENTITY_PAYMENT_STATUS
  STATUS
}
class usoc_validation_decision {
  REQUEST_ID
  ID_INSC
  DECISION
  STATUS
  ACTOR
}
factura --> fact_rels
factura --> payment_allocation
payment_transaction --> payment_allocation
usoc_financing_case --> factura : factura alumne
usoc_financing_case --> factura : factura entitat
```

**Invariant:** el pagament Redsys de l'alumne no pot saldar la factura USOC; cada factura conserva el seu propi origen de diners i estat de cobrament.

## 4. Classes FINAL / objectiu residual

```mermaid
classDiagram
direction LR
class UsocFinancingCaseRepository {
  <<IMPLEMENTAT>>
}
class UsocValidationDecisionService {
  <<IMPLEMENTAT>>
}
class UsocEntityInvoiceService {
  <<IMPLEMENTAT>>
}
class UsocEntityPaymentService {
  <<IMPLEMENTAT>>
}
class UsocLifecyclePlanService {
  <<IMPLEMENTAT>>
}
class UsocCancellationExecutionService {
  <<IMPLEMENTAT>>
  +execute(db,idInsc,idpag,requestId,actor,roles,input)
}
class LegacyUsocCourseChangePricingResolver {
  <<IMPLEMENTAT>>
  +resolve(idInsc,target,changeNumber)
}
class UsocCourseChangePreviewService {
  <<IMPLEMENTAT>>
  +preview(db,idInsc,idpag,target)
}
class UsocCourseChangeTargetResolver {
  <<IMPLEMENTAT>>
  +resolve(input)
}
class UsocCourseChangeFundPlanService {
  <<IMPLEMENTAT>>
  +plan(lifecyclePlan,target)
}
class UsocCourseChangeExecutionPreparationService {
  <<IMPLEMENTAT>>
  +prepare(db,idInsc,idpag,requestId,actor,roles,target)
}
class UsocCourseChangeExecutionService {
  <<PENDENT EFECTES>>
  +executeCourseChange(plan)
  +rectifySourceInvoices()
  +issueTargetInvoices()
  +compensateFundsByPayer()
}
class EnrollmentFundMovementRepository {
  <<IMPLEMENTAT>>
  +insertOrReuseCompensationAllocation()
}
class CreditBalanceService {
  <<IMPLEMENTAT>>
  +createCredit()
  +applyCreditByUuid()
}
class UsocPreproductionEvidence {
  <<PENDENT ENTORN>>
  +runPreflight()
  +browserE2E()
}
class UsocFreeCoursePolicy {
  <<DECISIO PENDENT>>
  +studentAmountZeroPolicy()
}
class UsocFiscalClassificationPolicy {
  <<VALIDACIO PENDENT>>
  +resolveVatRegime()
}
UsocCancellationExecutionService --> UsocLifecyclePlanService
UsocCancellationExecutionService --> InvoiceService
UsocCancellationExecutionService --> PaymentService
UsocCourseChangePreviewService --> UsocLifecyclePlanService
UsocCourseChangePreviewService --> UsocCourseChangeTargetResolver
UsocCourseChangePreviewService --> UsocCourseChangeFundPlanService
LegacyUsocCourseChangePricingResolver --> UsocCourseChangePreviewService : via API signada
UsocCourseChangeTargetResolver --> UsocLifecyclePlanService
UsocCourseChangeFundPlanService --> UsocLifecyclePlanService
UsocCourseChangeFundPlanService --> UsocCourseChangeTargetResolver
UsocCourseChangeExecutionPreparationService --> UsocCourseChangePreviewService
UsocCourseChangeExecutionPreparationService --> UsocLifecycleExecutionRepository
UsocCourseChangeExecutionService --> UsocLifecyclePlanService
UsocCourseChangeExecutionService --> UsocCourseChangeTargetResolver
UsocCourseChangeExecutionService --> UsocCourseChangeFundPlanService
UsocCourseChangeExecutionService --> EnrollmentFundMovementRepository
UsocCourseChangeExecutionService --> CreditBalanceService
```

## 5. Diferències ACTUAL → FINAL

| Responsabilitat | ACTUAL | FINAL / pendent |
|---|---|---|
| Doble factura | Implementada | Mantenir contracte |
| Doble pagador | Implementat | Mantenir separació estricta |
| Checkpoint durable | Implementat | Mantenir i evidenciar runtime |
| Cobrament entitat | Implementat | Provar preproducció real |
| Validació legacy↔SIF | Implementada en dues fases | Provar configuració real |
| UI USOC | Implementada al repositori | Desplegament/rols/secrets/preflight real |
| Baixa | Guard + planner + executor implementats | Acreditar CI actual i preproducció |
| Canvi de curs | Guard + planner + pricing server-side + resolver + fund planner + preview + `prepare` + reserva destí + `bind` + executor + reemissió + compensacions + `COMPLETED` + handoff implementats | Resta preproducció/navegador, configuració real i resolució operativa d'excessos |
| Alumne=0 | Bloquejat fail-closed | Decisió funcional/fiscal |
| Regla 20/25 % | No hardcoded al SIF | Decisió comercial fora del nucli |
| IVA | EXEMPT/E1 al builder | Validació fiscal de totes les variants |

## 6. Estat

- **Documentat:** sí, ara també amb classes ACTUAL/FINAL separades.
- **Implementat:** nucli de doble facturació, cobrament, checkpoint, conciliació, validació durable, UI, lifecycle planner i flux complet de canvi de curs: pricing server-side, resolver, fund planner, preparation, reserva/binding de destí, executor, reemissió, compensacions, esdeveniments i handoff legacy.
- **Verificat:** inspecció estàtica sobre el PR #120 reconciliat amb `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`.
- **Provat:** existeixen proves d'integració/contracte específiques per preparation, binding, executor, idempotència, preview, reserva legacy i handoff. L'acceptació definitiva correspon al CI del PR reconciliat i a preproducció.
- **Pendent:** preproducció/navegador, configuració real, curs gratuït, resolució explícita d'excessos i validacions comercials/fiscals. Tant la baixa com el canvi de curs disposen d'executor SIF.


## 7. Contracte FINAL del canvi de curs

El detall d'imports, compensacions, idempotència i casos límit queda fixat a:

- [Contracte FINAL · canvi de curs USOC](uc-013-canvi-curs-usoc-contracte-final.md)

La diferència principal respecte del legacy és que el FINAL **no copia `PAGAMENT`**. Els fons reals s'han de compensar per pagador mitjançant moviments auditables.
