# UC-006 · Diagrames de seqüència ACTUAL / FINAL

**Cas d'ús:** UC-006 — Registrar devolució, saldo o compensació  
**Data:** 2026-10-03  
**Principi:** ACTUAL és codi observat; FINAL és el contracte objectiu. Els serveis SIF poden existir sense que la pantalla els estigui usant.

## 1. ACTUAL — baixa d’una inscripció

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant B as Browser
participant J as alumnes-mostrar-alumne.min.js
participant M as mostraModalDonarBaixa.php
participant C as confirmacioBaixa_DonarBaixa.php
participant A as LegacyInvoiceMutationAuthorization
participant G as LegacyUsocLifecycleGuard
participant I as Intranet llegat
participant DB as BDs llegades

O->>B: Obre Consulta/Modifica alumne
B->>J: JS runtime
O->>J: Donar de baixa(ID_INSC)
J->>M: GET idInsc
M->>I: modalDonarBaixa_resultatCerca(idInsc)
I-->>J: HTML modal
O->>J: motiu + confirmar
J->>C: POST idinsc,motiu,enviarCoreu,csrfToken
C->>C: validar POST, sessió, CSRF
C->>A: same-origin + assertCanEdit()
C->>G: assertMayUseLegacyMutation(cancellation)
C->>I: confirmaBaixa_modalDonarBaixa(...)
I->>DB: mutacions llegades
I-->>C: resultat
C-->>J: HTML/text
J-->>O: baixa correcta/error
Note over C,I: No crida observada a ManualRefundService ni CreditBalanceService.
```

**Conclusió:** la baixa és un disparador potencial d’UC-006, no la seva implementació econòmica.

## 2. ACTUAL — canvi de curs amb preview SIF opcional

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant J as alumnes-mostrar-alumne.min.js
participant C as realitzarCanviCurs_CanviCurs.php
participant A as Authorization
participant P as SifInternalApiClient
participant I as Intranet llegat
participant DB as BDs llegades

O->>J: Escull nou curs i imports
J->>C: POST canvi + csrfToken
C->>C: validar sessió/CSRF
C->>A: same-origin + assertCanEdit()
alt SIF_COURSE_CHANGE_PREVIEW_ENFORCED = 1
  C->>DB: carregar font llegada i preu estàndard
  C->>P: previewCourseChange(...)
  P-->>C: fiscal_decision + economic_decision + paid_amount
  C->>C: comparar decisions esperades
end
C->>I: realitzarCanviCurs_modalCanviCurs(...)
I->>DB: mutació llegada
I-->>C: resultat
C-->>J: èxit/error
Note over C,I: economic_decision es valida al preview, però no es veu execució UC-28/29/29a en aquest endpoint.
```

## 2.1. Contracte ACTUAL de `economic_decision` al canvi de curs

```mermaid
flowchart LR
  P[CourseChangePreviewService] --> C[CourseChangeImpactClassifier]
  C --> N[NONE]
  C --> D[AMOUNT_DUE]
  C --> E[EXCESS_TO_RESOLVE]
  D --> Pay[UC-002 / cobrament pendent]
  E --> U6[UC-006 · decidir destinació de l'excés]
  U6 --> R[REFUND]
  U6 --> S[CREDIT_BALANCE]
  U6 --> V[REVIEW]
```

La part esquerra fins a `EXCESS_TO_RESOLVE` existeix i està provada. La derivació `EXCESS_TO_RESOLVE → UC-006` és el wiring que falta.

## 3. ACTUAL — “anul·lar factura” llegada amb A TORNAR

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant J as alumnes-factura.js
participant M as mostrarModalAnulaFactura_Factures.php
participant G as SifLegacyInvoiceMutationGuard
participant A as LegacyInvoiceMutationAuthorization
participant C as anularFactura_Factures.php
participant I as Intranet llegat

O->>J: Anul·lar factura
J->>M: GET id factura
M->>G: assertLegacyMutationAllowed(user,id)
M->>I: modalAnularFactura_Factures(id)
I-->>J: modal amb A TORNAR + DATA DEVOLUCIÓ + obs
O->>J: omple import/data i confirma
J->>J: validar número/data
J->>C: POST id,tornar,dataAnulacio,obs
C->>A: same-origin + assertCanEdit()
C->>G: assertLegacyMutationAllowed(user,id)
C->>I: anularFactura(id,tornar,dataDevol,obs)
I-->>C: resultat
C-->>J: "Factura anul·lada"
Note over J,C: Introduir A TORNAR no acredita que el banc/Redsys hagi retornat diners.
Note over C,I: No s'ha localitzat ManualRefundService en aquest camí.
```

## 4. ACTUAL — registre SIF de devolució amb ledger opcional

```mermaid
sequenceDiagram
autonumber
participant Caller as Script/adaptador tècnic
participant R as ManualRefundService
participant IR as ManualPaymentInvoiceRepository
participant B as ManualRefundPayloadBuilder
participant PS as PaymentService
participant F as EnrollmentFundMovementRepository
participant DB as BD SIF

Caller->>R: registerByUuid/NumVisible(invoice,input)
R->>IR: find invoice
IR->>DB: SELECT factura
alt factura absent
  R--xCaller: 422
else factura existent
  R->>B: forExistingInvoice(...)
  B-->>R: REFUND + K + allocation + source_enrollment_id?
  alt sense source_enrollment_id
    R->>PS: registerPayment(payload)
    PS->>DB: create/reuse REFUND + allocation
    R-->>Caller: resultat
  else amb source_enrollment_id
    R->>DB: BEGIN
    R->>PS: registerPaymentInTransaction(db,payload)
    PS->>DB: create/reuse REFUND + allocation
    R->>F: insertOrReuseRefundExit(...)
    F->>DB: lock REFUND + ledger origen
    alt excedeix import REFUND o dret disponible
      F--xR: 409
      R->>DB: ROLLBACK
    else vàlid
      F->>DB: INSERT REFUND_EXIT
      R->>DB: COMMIT
      R-->>Caller: uuid_payment / reused
    end
  end
end
Note over Caller,R: El límit quantitatiu per inscripció ja existeix; titularitat i evidència externa del retorn continuen fora d'aquest servei.
```

## 5. ACTUAL — crear saldo amb idempotència i consum opcional del dret

```mermaid
sequenceDiagram
autonumber
participant Caller as Script/adaptador tècnic
participant CS as CreditBalanceService
participant B as CreditBalancePayloadBuilder
participant CR as CreditBalanceRepository
participant F as EnrollmentFundMovementRepository
participant DB as BD SIF

Caller->>CS: createCredit(input)
CS->>B: forCreditBalance(input)
B-->>CS: titular + import + origen + K? + source_enrollment_id?
alt source_enrollment_id sense K
  CS--xCaller: 422
else alta/reintent
  CS->>DB: BEGIN
  CS->>CR: findByIdempotencyKey(K, FOR UPDATE) si K
  alt K existent
    CS->>CS: assertSameCreditPayload()
  else nou
    CS->>CR: createCredit()
    CR->>DB: INSERT credit_balance ACTIVE
  end
  alt source_enrollment_id informat
    CS->>F: insertOrReuseCreditCreate(...)
    F->>DB: lock credit + ledger origen
    alt dret insuficient
      F--xCS: 409
      CS->>DB: ROLLBACK
    else dret disponible
      F->>DB: INSERT CREDIT_CREATE
    end
  end
  CS->>DB: COMMIT
  CS-->>Caller: uuid_credit + disponible + reused
end
```

**Límit ACTUAL:** la K tècnica i el consum quantitatiu ja existeixen, però el futur orquestrador encara ha de construir la identitat estable del dret i validar-ne titularitat/origen de negoci.

## 6. ACTUAL — aplicar compensació amb destí d'inscripció opcional

```mermaid
sequenceDiagram
autonumber
participant Caller as Script/adaptador tècnic
participant CS as CreditBalanceService
participant CR as CreditBalanceRepository
participant IR as ManualPaymentInvoiceRepository
participant B as CreditBalancePayloadBuilder
participant PR as PaymentRepository
participant F as EnrollmentFundMovementRepository
participant DB as BD SIF

Caller->>CS: applyCredit(uuidCredit,invoice,amount,date,target_enrollment_id?)
CS->>DB: BEGIN
CS->>CR: findByUuid(uuidCredit, FOR UPDATE)
CS->>IR: find invoice FOR UPDATE
CS->>B: forCompensation(...)
B-->>CS: COMPENSATION + K + target?
CS->>PR: findByIdempotencyKey(K, FOR UPDATE)
alt moviment existent
  PR-->>CS: payment existent
  CS->>CS: assertSamePaymentPayload()
else nou
  CS->>CR: assert credit ACTIVE
  CS->>CR: invoiceOutstandingAmount(invoice)
  CS->>CS: amount <= available && amount <= outstanding
  CS->>PR: createPayment(COMPENSATION)
  PR->>DB: INSERT transaction + allocation
end
alt target_enrollment_id informat
  CS->>F: findInvoiceLineForInscription()
  F->>F: assertMoneyBackedCredit()
  CS->>F: insertOrReuseCompensationAllocation()
  alt target invàlid / credit no respaldat
    F--xCS: 409
    CS->>DB: ROLLBACK
  else vàlid
    F->>DB: INSERT COMPENSATION_ALLOCATION
  end
end
CS->>CR: updateAvailableAmount() si moviment nou
DB-->>CS: saldo ACTIVE/USED
CS->>DB: COMMIT
CS-->>Caller: uuid_payment + saldo restant/reused
Note over CS,IR: La titularitat compatible entre saldo i factura continua pendent.
```

## 6.1. ACTUAL — atribució de fons per inscripció ja implementada en curs/pack

```mermaid
sequenceDiagram
autonumber
participant R as Redsys invoice flow
participant A as Course/PackEnrollmentFundAllocationService
participant M as EnrollmentFundMovementRepository
participant DB as BD SIF
R->>A: allocate(db, dsOrder, snapshot, invoiceResult)
A->>M: lockPayment(uuid_payment)
M->>DB: SELECT payment_transaction FOR UPDATE
A->>M: findInvoiceLineForInscription(...)
M->>DB: SELECT factura_linia FOR UPDATE
A->>M: insertOrReuseExternalAllocation(...)
M->>DB: INSERT enrollment_fund_movement EXTERNAL_ALLOCATION
DB-->>M: UUID_MOVEMENT / reús
M-->>A: moviment atribuït a ID_INSC_DESTI
A-->>R: count + amount + movements
```

**Lectura d'auditoria actualitzada:** l'atribució inicial `EXTERNAL_ALLOCATION` continua sent la base; les seccions 6.2–6.4 documenten ara els recorreguts executables afegits per treure fons via refund, convertir-los en saldo i tornar a atribuir un saldo a una inscripció.

## 6.2. ACTUAL ampliat — crear saldo consumint dret d'inscripció

```mermaid
sequenceDiagram
autonumber
participant C as Caller
participant CS as CreditBalanceService
participant CR as CreditBalanceRepository
participant F as EnrollmentFundMovementRepository
participant DB as BD SIF
C->>CS: createCredit(K, source_enrollment_id, amount)
CS->>DB: BEGIN
CS->>CR: findByIdempotencyKey(K, FOR UPDATE)
alt saldo existent
  CS->>CS: assertSameCreditPayload
else saldo nou
  CS->>CR: createCredit(K,hash)
  CR->>DB: INSERT credit_balance
end
CS->>F: insertOrReuseCreditCreate(...)
F->>DB: lock credit + lock/reconstruir ledger origen
alt dret insuficient
  F--xCS: 409
  CS->>DB: ROLLBACK
else dret disponible
  F->>DB: INSERT CREDIT_CREATE
  CS->>DB: COMMIT
  CS-->>C: UUID_CREDIT created/reused
end
```

## 6.3. ACTUAL ampliat — refund consumint el mateix dret

```mermaid
sequenceDiagram
autonumber
participant C as Caller
participant R as ManualRefundService
participant P as PaymentService
participant F as EnrollmentFundMovementRepository
participant DB as BD SIF
C->>R: register(... source_enrollment_id ...)
R->>DB: BEGIN
R->>P: registerPaymentInTransaction(REFUND)
P->>DB: create/reuse REFUND + allocation
R->>F: insertOrReuseRefundExit(uuidPayment,idInsc,amount)
F->>DB: lock REFUND + ledger inscripció
alt excedeix REFUND o dret disponible
  F--xR: 409
  R->>DB: ROLLBACK payment/allocation/status
else vàlid
  F->>DB: INSERT REFUND_EXIT
  R->>DB: COMMIT
  R-->>C: UUID_PAYMENT
end
```

## 6.4. ACTUAL ampliat — aplicar saldo a una inscripció de la factura

```mermaid
sequenceDiagram
autonumber
participant C as Caller
participant CS as CreditBalanceService
participant F as EnrollmentFundMovementRepository
participant PR as PaymentRepository
participant DB as BD SIF
C->>CS: applyCredit(... target_enrollment_id ...)
CS->>DB: BEGIN + lock credit/factura
CS->>PR: create/reuse COMPENSATION
PR->>DB: payment_transaction + payment_allocation
CS->>F: findInvoiceLineForInscription(factura,target)
alt target no pertany a factura
  F--xCS: 409
  CS->>DB: ROLLBACK COMPENSATION
else target vàlid
  CS->>F: insertOrReuseCompensationAllocation
  F->>DB: INSERT ledger amb UUID_CREDIT/payment/factura/línia/destí
  CS->>DB: UPDATE credit_balance disponible
  CS->>DB: COMMIT
end
```

**Nota:** aquests tres recorreguts són ACTUALS a la branca, però encara no estan invocats per la UI llegada ni acreditats per CI/preproducció.
## 6.5. ACTUAL ampliat — traspàs intern A → B sense nou cobrament

```mermaid
sequenceDiagram
autonumber
participant C as Caller/Coordinator
participant S as EnrollmentFundTransferService
participant B as EnrollmentFundTransferPayloadBuilder
participant F as EnrollmentFundMovementRepository
participant DB as BD SIF
C->>S: transfer(K, source_enrollment_id=A, target_enrollment_id=B, amount)
S->>B: build(input)
B-->>S: payload normalitzat
S->>DB: BEGIN
S->>F: insertOrReuseInternalTransfer(...)
F->>DB: buscar K FOR UPDATE
alt K existent
  F->>F: comparar payload econòmic
  F-->>S: moviment reutilitzat
else K nova
  F->>DB: lock/reconstruir disponible d'A
  alt A no té prou dret
    F--xS: 409
    S->>DB: ROLLBACK
  else disponible suficient
    F->>DB: INSERT INTERNAL_TRANSFER A→B
    S->>DB: COMMIT
  end
end
S-->>C: UUID_MOVEMENT + reused
Note over S,DB: No crea payment_transaction ni CHARGE. La disponibilitat baixa a A i puja a B pel mateix import.
```

**Implementat a la branca:** builder, servei transaccional, repositori, preview/process CLI i proves A→B/A→B→C. **Pendent:** que UC-071/UC-006 decideixi i autoritzi quan/quanta quantitat traspassar.

## 6.6. ACTUAL ampliat — reversió segura d'un traspàs A → B

```mermaid
sequenceDiagram
autonumber
participant C as Caller/Coordinator
participant S as EnrollmentFundTransferService
participant B as EnrollmentFundTransferPayloadBuilder
participant F as EnrollmentFundMovementRepository
participant DB as BD SIF
C->>S: reverseTransfer(movement_uuid)
S->>B: buildReversal(...)
B-->>S: K derivada del UUID original
S->>DB: BEGIN
S->>F: lock moviment original
alt no és INTERNAL_TRANSFER
  F--xS: 409
  S->>DB: ROLLBACK
else transfer vàlid
  F->>DB: comprovar REVERSAL existent
  F->>DB: lock/reconstruir disponible de B
  alt B ja no conserva l'import transferit
    F--xS: 409
    S->>DB: ROLLBACK
  else B conserva prou saldo
    F->>DB: INSERT REVERSAL(reverses_uuid_movement)
    S->>DB: COMMIT
    S-->>C: UUID_REVERSAL / reused
  end
end
Note over F,DB: availableAmountForInscription() ignora el moviment original quan existeix REVERSAL: A recupera l'import i B el perd.
```

**Límit deliberat:** aquesta reversió no desfà `REFUND_EXIT`, `CREDIT_CREATE` ni `COMPENSATION_ALLOCATION`. Si el canvi ja ha generat altres efectes, cal regularització específica i no una reversió genèrica.

## 7. FINAL — preview de decisió UC-006

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant C as Uc006Controller [PROPOSAT]
participant A as Uc006Authorization [PROPOSAT]
participant D as Uc006DecisionService [PROPOSAT]
participant R as EconomicRightsRepository [PROPOSAT]
participant F as Fiscal classifier UC-05/74
participant DB as BD SIF + lectura llegat

O->>C: preview(originId, affectedItems)
C->>A: assertCanDecide(actor,origin)
A-->>C: OK
C->>D: classify(snapshot)
D->>DB: llegir factura, pagaments, saldos, inscripcions
D->>R: computeAvailableRights(origin/items)
R-->>D: available + holder + consumed
D->>F: classifyFiscalImpact(snapshot)
F-->>D: decisió fiscal independent
D-->>C: economic_decision + amount partitions + fingerprint
C-->>O: REFUND/CREDIT/COMPENSATION/NO_CHANGE/REVIEW
Note over C,D: Preview no muta diners ni fiscalitat.
```

## 8. FINAL — confirmar devolució real

```mermaid
sequenceDiagram
autonumber
actor O as Responsable autoritzat
participant C as Uc006Controller
participant A as Authorization
participant D as DecisionService
participant E as RefundEvidenceGuard
participant R as EconomicRightsRepository
participant G as AuditGateway
participant RS as ManualRefundService
participant DB as BD SIF
participant L as LegacySync

O->>C: confirm(requestId,fingerprint,REFUND)
C->>A: assertCanRefund()
C->>D: rellegir + validar fingerprint
D->>R: lock rights / import disponible
D->>E: assertConfirmedExternalRefund(externalId,holder,amount)
alt retorn no executat o ambigu
  E-->>C: PENDING/REVIEW
  C-->>O: cap REFUND
else retorn real confirmat
  D->>G: run(audit context)
  G->>RS: registerByUuid(...)
  RS->>DB: REFUND idempotent
  RS-->>G: uuid_payment/reused
  G->>R: consume right exactly once
  G->>DB: terminal audit event
  DB-->>G: COMMIT
  G-->>C: resultat
  C->>L: syncAfterSifCommit()
  C-->>O: retorn registrat
end
```

## 9. FINAL — crear saldo idempotent

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant C as Uc006Controller
participant D as DecisionService
participant R as EconomicRightsRepository
participant G as AuditGateway
participant CS as CreditBalanceService
participant DB as BD SIF

O->>C: confirm(requestId,fingerprint,CREDIT_BALANCE)
C->>D: rellegir decisió
D->>R: lock origin right
R->>DB: buscar credit existent per origin/right key
alt dret ja convertit en saldo
  DB-->>R: UUID_CREDIT existent
  R-->>C: REUSED
else dret disponible
  D->>G: run(...)
  G->>CS: createCredit(input + origin identity)
  CS->>DB: INSERT credit_balance
  G->>R: consume/reserve origin right
  G->>DB: terminal audit event
  DB-->>G: COMMIT
  G-->>C: UUID_CREDIT
end
C-->>O: saldo creat/reutilitzat
```

## 10. FINAL — compensar saldo

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant C as Uc006Controller
participant A as Authorization
participant P as CreditOwnershipPolicy
participant CS as CreditBalanceService
participant G as AuditGateway
participant DB as BD SIF

O->>C: aplicar saldo a factura destí
C->>A: assertCanCompensate()
C->>DB: carregar credit + factura + titulars
C->>P: assertCompatible(credit,invoice)
alt titular incompatible
  P--xC: 409/REVIEW
  C-->>O: cap compensació
else compatible
  C->>G: run(audit)
  G->>CS: applyCreditByUuid/NumVisible(...)
  CS->>DB: locks + validar disponible/deute
  CS->>DB: INSERT COMPENSATION + consumir saldo
  DB-->>CS: COMMIT
  CS-->>G: created/reused
  G-->>C: resultat
  C-->>O: saldo aplicat
end
```

## 11. Errors i recuperació FINAL

- **preview caducat:** 409 i nou preview;
- **dret ja consumit per devolució/saldo:** 409/REVIEW, cap segon efecte;
- **retorn extern confirmat però SIF falla:** incidència i reintent de registre, **mai segon retorn bancari**;
- **SIF confirma però sync llegat falla:** conservar SIF i reintentar només sync;
- **mateixa clau amb payload diferent:** CONFLICT;
- **titular ambigu:** REVIEW;
- **fiscalitat amb dubte:** decisió UC-05/74 pendent, sense inventar efecte econòmic;
- **conciliació Redsys/manual duplicada:** reutilitzar el mateix fet extern.
