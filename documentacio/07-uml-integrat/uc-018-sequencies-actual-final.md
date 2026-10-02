# UC-018 · Seqüències ACTUAL/FINAL — Bescanviar regal

## 1. ACTUAL — compra i emissió del dret

```mermaid
sequenceDiagram
autonumber
actor C as Comprador
participant R as Redsys
participant G as RedsysGiftInvoiceService
participant I as GiftEntitlementIssuerService
participant DB as SIF
C->>R: compra regal
R->>G: pagament confirmat
G->>DB: factura + CHARGE
G->>I: emetre/reutilitzar dret GIFT
I->>DB: GIFT_PURCHASE + entitlement + ISSUE
```

## 2. ACTUAL — bescanvi nominal

```mermaid
sequenceDiagram
autonumber
actor B as Beneficiari
participant W as Writer web legacy
participant C as SifGiftRedemptionClient
participant API as redeem.php
participant O as GiftRedemptionOrchestrator
participant T as TrustedContextResolver
participant S as GiftEnrollmentStager
participant R as GiftRedemptionService
participant L as LegacyGiftUsageReconciler
participant N as NotificationBundleService
participant DB as SIF/legacy
B->>W: confirmar inscripció amb regal
W->>DB: lock regal + crear/reutilitzar ID_INSC
W->>C: POST/HMAC enrollment_id + gift_code
C->>API: redeem
API->>O: execute
O->>T: resoldre participant/preu autoritatiu
O->>S: claim holder + stage + RESERVE
O->>R: redeem
R->>DB: COMPENSATION_ALLOCATION
R->>DB: CONSUME
O->>L: compare-and-set regal.USAT
L->>DB: reconciliar legacy
O->>N: enqueue 6 notificacions idempotents
N->>DB: 6 notification_outbox
O-->>API: CONSUMED + bundle
API-->>C: resultat
C-->>W: resultat
W->>C: claim notificació
C-->>W: autorització at-most-once
W->>W: SMTP legacy
W->>C: complete SENT/FAILED
```

## 3. Replay / resposta perduda

```mermaid
sequenceDiagram
participant W as Web/recovery
participant O as GiftRedemptionOrchestrator
participant DB as SIF + legacy
W->>O: execute(ID_INSC, gift_code)
O->>DB: rellegir operació/dret
DB-->>O: mateix destí + mateix moviment + CONSUMED
O->>DB: reconciliació ja aplicada
O-->>W: REUSED
Note over W,DB: cap alta nova, cap CHARGE, cap factura i cap segon CONSUME
```

## 4. Concurrència multiprocés

```mermaid
sequenceDiagram
participant A as Procés A
participant B as Procés B
participant E as Entitlement row
participant DB as SIF
A->>E: SELECT ... FOR UPDATE
B->>E: SELECT ... FOR UPDATE
A->>DB: CLAIM/RESERVE/ALLOCATION/CONSUME
A-->>B: commit allibera lock
B->>E: rellegir estat
alt mateix ID_INSC
B-->>B: REUSED
else ID_INSC diferent
B-->>B: CONFLICT 409
end
```

## 5. Notificació i fallada SMTP

- Cap `MailSMTPComvive` s'executa abans d'un redeem/reconciliació SIF correcte.
- Cada un dels sis correus té outbox i claim propis.
- `PENDING → SENDING` és el punt d'autorització d'enviament.
- `SENDING` ambigu no es reclama automàticament una segona vegada.
- `SENT` és idempotent.
- `FAILED` queda per revisió; no hi ha retry automàtic que pugui duplicar un enviament ja acceptat pel proveïdor.

## 6. Diferències de preu

El flux executable auditat exigeix valor exacte. Un curs de valor diferent **no** genera automàticament cobrament, saldo, devolució ni consum parcial. Es rebutja/falla tancat fins a una decisió funcional específica.