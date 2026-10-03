# UC-018 · Seqüències ACTUAL/FINAL — Bescanviar regal

## 1. Tall revalidat — 2026-10-03

Aquest document mostra l'ACTUAL de la branca de revalidació. El canvi principal respecte del tall 02/10 és que també es governa la frontera **navegador→legacy**, no només la frontera interna legacy→SIF.

## 2. ACTUAL — entrada web, validació i alta

```mermaid
sequenceDiagram
autonumber
actor B as Beneficiari
participant P as pagina_bescanvia.php
participant JS as mostrarBescanvia.min.js
participant V as codiRegalValid.php
participant C as buscarCursRegalat.php
participant D as inscripcioDuplicada.php
participant W as enviarInscripcioBescanvia.php

B->>P: obrir /bescanvia
P-->>B: bundle rastrejable ver=6.0
B->>JS: introduir codi
JS->>V: POST codiRegal
V->>V: validar sense enumerar estat públic
V-->>JS: vàlid o resposta neutra
JS->>C: POST codiRegal
C->>C: revalidar bescanviabilitat
C-->>JS: curs/modalitat
B->>JS: dades + edició
JS->>D: POST DNI + curs/edició
D-->>JS: duplicada / disponible
JS->>W: POST dades personals + codi
W->>W: lock regal + FACT_REL > 0
W->>W: recuperar o crear una sola ID_INSC
```

Cap d'aquestes quatre peticions sensibles posa `codiRegal` ni DNI a la query string.

## 3. ACTUAL — SIF i consum nominal

```mermaid
sequenceDiagram
autonumber
participant W as Writer legacy
participant C as SifGiftRedemptionClient
participant API as redeem.php
participant O as GiftRedemptionOrchestrator
participant T as TrustedContextResolver
participant S as GiftEnrollmentStager
participant R as GiftRedemptionService
participant L as LegacyGiftUsageReconciler
participant N as NotificationBundleService
participant DB as SIF/legacy

W->>C: enrollment_id + gift_code
C->>API: POST/HMAC
API->>O: execute
O->>T: participant + snapshot autoritatiu
O->>S: claim holder + stage + RESERVE
O->>R: redeem
R->>DB: COMPENSATION_ALLOCATION
R->>DB: CONSUME
O->>L: compare-and-set regal.USAT
L->>DB: reconciliar legacy
O->>N: crear/reutilitzar 6 notificacions
N->>DB: notification_outbox
O-->>API: CONSUMED + bundle
API-->>C: resultat
C-->>W: resultat
```

## 4. ACTUAL — correus post-SIF

```mermaid
sequenceDiagram
participant W as Writer legacy
participant C as SifGiftRedemptionClient
participant A as notifications.php
participant O as Outbox
participant M as SMTP legacy
loop cadascun dels 6 correus
 W->>C: claim(uuid_notification)
 C->>A: POST/HMAC claim
 A->>O: PENDING -> SENDING
 O-->>W: should_send
 alt autoritzat
   W->>M: enviar
   W->>C: complete(SENT/FAILED)
 else SENT o estat no reclamable
   W-->>W: no reenviar
 end
end
```

## 5. Replay / resposta perduda

```mermaid
sequenceDiagram
participant W as Web/recovery
participant C as Client SIF
participant O as GiftRedemptionOrchestrator
participant DB as SIF + legacy
W->>DB: lock regal
DB-->>W: USAT = mateixa ID_INSC
W->>W: validar curs/DNI/codi
W->>C: redeemCommittedEnrollment(mateixa ID_INSC,codi)
C->>O: execute
O->>DB: rellegir operació/dret
DB-->>O: mateix destí + CONSUMED
O->>DB: reutilitzar allocation + reconciliació + outbox
O-->>W: REUSED + bundle
Note over W,DB: no hi ha early-return abans del SIF
```

## 6. Concurrència multiprocés

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

## 7. FINAL

Per al flux base, FINAL = ACTUAL de la branca un cop el CI nou sigui verd. Resten fora:

- [ENV] execució real de preproducció/SMTP;
- [POLICY] diferències de valor, romanent, cobrament complementari, devolució o consum parcial.

## 8. Verificació

El nucli/SIF conserva el tall verificat del PR #115: 858/0, 49 PASS GIFT/UC-018. La prova boundary ampliada del 03/10 és la que ha d'acreditar la nova frontera pública.
