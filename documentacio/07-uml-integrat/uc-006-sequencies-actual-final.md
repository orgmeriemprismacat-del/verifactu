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

## 4. ACTUAL — registre SIF de devolució disponible però no cablejat a la UI

```mermaid
sequenceDiagram
autonumber
participant Caller as Script/adaptador tècnic
participant R as ManualRefundService
participant IR as ManualPaymentInvoiceRepository
participant B as ManualRefundPayloadBuilder
participant PS as PaymentService
participant PR as PaymentRepository
participant DB as BD SIF

Caller->>R: registerByUuid/NumVisible(invoice,input)
R->>IR: find invoice
IR->>DB: SELECT factura
alt factura absent
  R--xCaller: 422
else factura existent
  R->>B: forExistingInvoice(...)
  B-->>R: REFUND + K + allocation
  R->>PS: registerPayment(payload)
  PS->>PR: findByIdempotencyKey(K, FOR UPDATE)
  alt existent
    PS->>PS: assertSamePayload(hash)
    PS-->>R: reused
  else nou
    PS->>PR: createPayment()
    PR->>DB: INSERT payment_transaction REFUND
    PR->>DB: INSERT payment_allocation
    PR->>DB: recalcular ESTAT_COBRAMENT
    PS-->>R: uuid_payment
  end
  R-->>Caller: resultat
end
Note over Caller,R: El caller ha d'acreditar permisos, límit retornable i retorn extern; el servei no ho resol completament.
```

## 5. ACTUAL — crear saldo

```mermaid
sequenceDiagram
autonumber
participant Caller as Script/adaptador tècnic
participant CS as CreditBalanceService
participant B as CreditBalancePayloadBuilder
participant CR as CreditBalanceRepository
participant TR as TransactionRunner
participant DB as BD SIF

Caller->>CS: createCredit(input)
CS->>B: forCreditBalance(input)
B-->>CS: titular + import + origen
CS->>TR: run()
TR->>DB: BEGIN
CS->>CR: createCredit(db,payload)
CR->>DB: INSERT credit_balance ACTIVE
TR->>DB: COMMIT
CS-->>Caller: uuid_credit + disponible + ACTIVE
Note over CS,CR: No clau idempotent/origin uniqueness observada en aquest contracte.
```

## 6. ACTUAL — aplicar compensació

```mermaid
sequenceDiagram
autonumber
participant Caller as Script/adaptador tècnic
participant CS as CreditBalanceService
participant CR as CreditBalanceRepository
participant IR as ManualPaymentInvoiceRepository
participant B as CreditBalancePayloadBuilder
participant PR as PaymentRepository
participant DB as BD SIF

Caller->>CS: applyCredit(uuidCredit,invoice,amount,date)
CS->>DB: BEGIN
CS->>CR: findByUuid(uuidCredit, FOR UPDATE)
CS->>IR: find invoice FOR UPDATE
CS->>B: forCompensation(...)
B-->>CS: COMPENSATION + K
CS->>PR: findByIdempotencyKey(K, FOR UPDATE)
alt moviment existent
  PR-->>CS: payment existent
  CS-->>Caller: reused=true
else nou
  CS->>CR: assert credit ACTIVE
  CS->>CR: invoiceOutstandingAmount(invoice)
  CS->>CS: amount <= available && amount <= outstanding
  CS->>PR: createPayment(COMPENSATION)
  PR->>DB: INSERT transaction + allocation
  CS->>CR: updateAvailableAmount()
  DB-->>CS: saldo ACTIVE/USED
  CS->>DB: COMMIT
  CS-->>Caller: uuid_payment + saldo restant
end
Note over CS,DB: El lock/consum és transaccional.
Note over CS,IR: No es veu comprovació de titular del saldo contra factura.
```

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
