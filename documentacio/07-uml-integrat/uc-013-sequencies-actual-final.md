# UC-013 · Seqüències ACTUAL / FINAL — Orquestrar la doble facturació USOC

**Data d'auditoria:** 2026-10-02  
**Main contrastat:** `f7fa0822f82be96e842d9f2d031e643ab07f617c`

## 1. ACTUAL — sol·licitud USOC

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant JS as mostrarInscripcionsAfiliats.min.js
participant Alta as enviarInscripcioAfiliat.php
participant L as BD legacy
participant F as FEUSOC

A->>JS: marca USOC i envia formulari
JS->>Alta: dades inscripció + opció USOC
Alta->>Alta: reserveIdPag() sota named lock
Alta->>L: INSERT inscripció TIPUS_DESC=4 / VALID_DESC pendent
Alta->>F: comunicació de confirmació afiliació
Alta-->>A: inscripció pendent de validació
```

**ACTUAL:** marcar l'opció USOC no valida l'afiliació ni crea encara la segona factura.

## 2. ACTUAL — validació positiva durable legacy ↔ SIF

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as alumnes-validar-descomptes.js
participant EP as sendMsgValidatCurosDescomptes.php
participant C as SifInternalUsocClient
participant API as /api/usoc/manage.php
participant V as UsocValidationDecisionService
participant L as BD legacy
participant I as Intranet.php

G->>UI: aprovar USOC
UI->>EP: POST + CSRF + requestId
EP->>C: begin_validation_decision
C->>API: HMAC intern
API->>V: begin(requestId, APPROVE)
V-->>EP: REQUESTED / COMMITTED / REVIEW_REQUIRED
alt REQUESTED
 EP->>I: aplicar decisió legacy
 I->>L: VALID_DESC=1 + comunicació
 EP->>C: complete_validation_decision
 C->>API: HMAC intern
 API->>V: complete()
 V->>L: contrastar estat real
 V-->>EP: COMMITTED o REVIEW_REQUIRED
else COMMITTED
 EP-->>G: no repetir mutació/correu
else REVIEW_REQUIRED
 EP-->>G: conflicte, revisió manual
end
```

## 3. ACTUAL — factura i cobrament de la part alumne

```mermaid
sequenceDiagram
autonumber
participant R as Redsys
participant W as callback/worker SIF
participant U as RedsysUsocInvoiceService
participant B as LegacyUsocInvoicePayloadBuilder
participant I as InvoiceService
participant C as UsocFinancingCaseRepository

R->>W: callback validat
W->>U: issueFromIntentSnapshot(dsOrder,snapshot)
U->>U: exigir entity_amount > 0
U->>B: buildStudentPayload()
B->>B: exigir TIPUS_DESC=4 i VALID_DESC=1
U->>I: issueInvoice(payload + payment Redsys)
I-->>U: UUID factura alumne + payment
U->>C: recordStudentInvoice()
C-->>U: PENDING_ENTITY_INVOICE
U-->>W: entity_invoice_pending + id_insc + idpag
```

## 4. ACTUAL — reintent alumne

```mermaid
sequenceDiagram
autonumber
participant W as Worker
participant U as RedsysUsocInvoiceService
participant I as InvoiceService
participant C as UsocFinancingCaseRepository

W->>U: mateix intent/callback
U->>I: mateix payload/idempotency key
I-->>U: reutilitza factura/payment si hash coincideix
U->>C: recordStudentInvoice()
C->>C: validar mateixa factura/imports
C-->>U: mateix expedient
```

**Invariant:** mateix idempotency key amb payload divergent no es reutilitza silenciosament.

## 5. ACTUAL — emissió factura entitat

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as UI USOC intranet
participant API as API interna signada
participant E as UsocEntityInvoiceService
participant C as UsocFinancingCaseRepository
participant S as UsocStudentInvoiceLinkRepository
participant L as LegacyUsocSnapshotRepository
participant B as LegacyUsocInvoicePayloadBuilder
participant I as InvoiceService

G->>UI: emetre factura entitat
UI->>API: id_insc,idpag,student_invoice_uuid,imports,billing
API->>E: issueEntityFromExplicitInput()
E->>C: requireForEntityInvoice()
C-->>E: checkpoint coherent
E->>L: snapshot IDPAG + ID_INSC
E->>S: assertMatches(factura alumne)
S-->>E: vincle correcte
E->>B: buildEntityPayload()
E->>I: issueInvoice()
I-->>E: UUID factura entitat
E->>C: recordEntityInvoice()
C-->>E: ENTITY_INVOICED
E-->>API: payment_registered=false
```

## 6. ACTUAL — cobrament parcial/complet de l'entitat

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as UI USOC
participant P as UsocEntityPaymentService
participant Pay as PaymentService
participant R as UsocCaseReconciler
participant C as UsocFinancingCaseRepository

G->>UI: registrar ingrés real USOC
UI->>P: factura entitat + import
P->>Pay: registerPayment()
Pay-->>P: payment/allocation
P->>R: reconcile()
R->>R: llegir estat cobrament de les dues factures
R->>C: updateReconciliation()
alt entitat parcial
 C-->>P: ENTITY_PARTIAL
else entitat completament cobrada
 C-->>P: FINANCING_RECONCILED
end
```

## 7. ACTUAL — canvi de curs / baixa

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant L as endpoint legacy
participant Guard as LegacyUsocLifecycleGuard
participant API as API USOC signada
participant GS as UsocLifecycleGuardService
participant Plan as UsocLifecyclePlanService

G->>L: POST canvi/baixa + CSRF
L->>Guard: comprovar TIPUS_DESC=4
Guard->>API: lifecycle_guard
API->>GS: guard(ID_INSC)
GS-->>API: payer_snapshot / allowed
alt expedient USOC fiscalitzat
 API-->>L: 409 requires orchestration
 L->>API: lifecycle_plan
 API->>Plan: plan(snapshot,operation)
 Plan-->>L: accions separades per pagador + max_refundable
 L-->>G: preview; no mutació legacy
else sense evidència USOC fiscal
 L-->>G: flux legacy permès
end
```

## 8. ACTUAL — baixa USOC executiva amb dos pagadors

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant Plan as UsocLifecyclePlanService
participant Exec as UsocCancellationExecutionService
participant I as InvoiceService
participant P as PaymentService
participant C as UsocCaseReconciler

G->>Plan: preparar canvi/baixa
Plan-->>G: pla alumne + pla entitat
G->>Exec: autoritzar baixa amb decisions per pagador
loop student / entity
 Exec->>I: RECTIFY si s'ha decidit
 Exec->>P: REFUND només fins max_refundable
 Exec->>Exec: o DEFER_FISCAL / DEFER_REFUND
end
Exec-->>G: COMPLETED + requires_follow_up si hi ha diferits
```

**ACTUAL:** la baixa ja té `UsocCancellationExecutionService`, amb idempotència per `requestId`, rectificatives/refunds separats i persistència d'esdeveniments. **PENDENT:** executor equivalent per al canvi de curs i evidència de preproducció.

## 9. FINAL — variant curs gratuït

```mermaid
sequenceDiagram
autonumber
participant U as Flux USOC
participant Policy as UsocFreeCoursePolicy
participant I as InvoiceService

U->>Policy: student_amount=0
alt política funcional/fiscal no definida
 Policy-->>U: bloqueig fail-closed
else política aprovada
 Policy-->>U: circuit explícit
 U->>I: operació segons regla aprovada
end
```

Actualment el builder rebutja `student_amount=0`; no s'inventa una factura o cobrament fictici.

## 10. Estat

- **Documentat:** seqüències principals ACTUAL/FINAL separades.
- **Implementat:** sol·licitud, validació durable, factura/cobrament alumne, checkpoint, factura/cobrament entitat, conciliació, guard, planner, resolver d'imports destí i pla econòmic pur per pagador.
- **Verificat:** inspecció estàtica sobre el main indicat.
- **Provat:** evidència CI prèvia específica del UC-013 cobreix E2E de servei, idempotència, parcial/complet, validació durable, UI contracts i lifecycle planner.
- **Pendent:** E2E navegador/preproducció sobre configuració real i executor d'efectes del canvi de curs. El preview USOC server-side ja està implementat; la baixa ja està implementada al repositori.


## 11. FINAL — canvi de curs USOC executable

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as Intranet canvi curs
participant Guard as LegacyUsocLifecycleGuard
participant API as API USOC
participant Plan as UsocLifecyclePlanService
participant Target as UsocCourseChangeTargetResolver
participant Exec as UsocCourseChangeExecutionService
participant Rect as ManualRectificationService
participant Inv as InvoiceService
participant Funds as EnrollmentFundMovementRepository
participant Credit as CreditBalanceService
participant Legacy as Legacy canvi curs

G->>UI: confirmar curs destí
UI->>Guard: inspect(course_change)
Guard->>API: lifecycle_guard / lifecycle_plan
API->>Plan: snapshot separat student/entity
Plan-->>UI: origen congelat + max_refundable
UI->>Target: preu estàndard + preu USOC destí + fee
Target->>Target: entity = standard - student
Target->>Target: validar invariants i regla USOC destí
alt incoherent o regla absent
 Target-->>UI: REVIEW_REQUIRED
else coherent
 UI->>Exec: execute(requestId, origen, destí)
 loop alumne / entitat
  Exec->>Rect: rectificar factura origen si existeix
 end
 Exec->>Inv: emetre factura alumne destí
 Exec->>Inv: emetre factura entitat destí si import > 0
 loop alumne / entitat
  Exec->>Funds: compensar només fons reals del mateix pagador
  opt excés
   Exec->>Credit: crear saldo o aplicar decisió explícita
  end
 end
 Exec-->>UI: COMPLETED + handoff token/checkpoint
 UI->>Legacy: crear/mutar inscripció destí
 Legacy-->>UI: OK
end
```

**Invariant:** mai usar fons de l'alumne per saldar la part USOC ni a l'inrevés. El detall complet és a [contracte FINAL de canvi de curs](uc-013-canvi-curs-usoc-contracte-final.md).


## 12. ACTUAL — preview server-side del canvi de curs USOC

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as alumnes-usoc-lifecycle-preview.js
participant EP as sifUsocCourseChangePreview.php
participant Price as LegacyUsocCourseChangePricingResolver
participant API as API USOC signada
participant Prev as UsocCourseChangePreviewService
participant Life as UsocLifecyclePlanService
participant Target as UsocCourseChangeTargetResolver
participant Funds as UsocCourseChangeFundPlanService

G->>UI: clic Guardar canvi USOC
UI->>EP: POST + CSRF + id/edició/número canvi
EP->>Price: resolve()
Price->>Price: validar USOC origen
Price->>Price: preu base + preu USOC destí
Price->>Price: fee=origen si canvi 4, altrament 0
Price-->>EP: pricing snapshot server-side
EP->>API: course_change_preview HMAC
API->>Prev: preview()
Prev->>Life: lifecycle plan course_change
Prev->>Target: split student/entity/fee
Prev->>Funds: compensable/pendent/excés per pagador
Funds-->>Prev: fund plan
Prev-->>EP: preview sense efectes
EP-->>UI: pricing + target + fund plan
UI-->>G: mostrar preview; no continuar al legacy
```

**Invariant:** el preview no confia en `apagar`, `pagat` ni `despeses` editables del navegador per determinar el contracte USOC.
