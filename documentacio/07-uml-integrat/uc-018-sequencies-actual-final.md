# UC-018 · Seqüències ACTUAL/FINAL — Bescanviar regal

## 1. Tall revalidat — 2026-10-04

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
participant H as buscarSiHaRealitzatElCurs.php
participant M as enviamentPubli.php
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
JS->>M: POST correu
M-->>JS: ja subscrit / demanar consentiment
JS->>H: POST DNI + curs
H-->>JS: curs previ / disponible
Note over JS,H: si hi ha curs derivat, el lookup es pot repetir també per POST
JS->>D: POST DNI + curs/edició
D-->>JS: duplicada / disponible
JS->>W: POST dades personals + codi
W->>W: lock regal + FACT_REL > 0 + CCURS
W->>W: validar curs/edició i compatibilitat d'hores
alt curs incompatible o hores ambigües
 W-->>JS: CONFLICT 409 sense commit d'ID_INSC
else elegible
 W->>W: recuperar o crear una sola ID_INSC
end
```

Cap petició UC-018 que transporta `codiRegal`, DNI o correu els posa a la query string.

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
T->>DB: validar snapshot immutable + CCURS genèric o swap concret per mateixes hores
O->>S: claim holder + stage + RESERVE
S->>DB: revalidar CCURS/hores abans de stage
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

## 7. ACTUAL — confirmació segura

```mermaid
sequenceDiagram
autonumber
participant W as Writer legacy
participant T as GiftRedemptionConfirmationToken
participant B as Browser
participant P as pagina_confirmacio_bescanvia.php
participant J as mostrarConfirmacioBescanvia.min.js
participant A as mostrar_confirmacio_bescanvia.php
participant DB as legacy DB

W->>T: issue(ID_INSC,key)
T-->>W: v2.base64url(AES-256-GCM)
W-->>B: token
B->>B: redirect /bescanvia/confirmacio/v2#token
Note over B,P: el fragment no arriba al servidor ni al Referer
B->>P: GET /bescanvia/confirmacio/v2
P-->>B: no-store + no-referrer + JS
J->>A: POST token
A->>T: parse(token,key)
T-->>A: ID_INSC autenticada
A->>DB: verificar regal.USAT + inscripció
A-->>J: confirmació sense codi regal + correu emmascarat
```

Els tokens antics CBC no són acceptats pel nou parser: es prioritza fail-closed davant mantenir un format amb IV no autenticat.

## 8. FINAL

Per al flux base, FINAL = ACTUAL de la branca un cop el CI nou sigui verd. El FINAL admet regal genèric de N hores i regal d'un curs concret. El concret pot usar el curs original o un altre de les mateixes hores; si la història del curs original no permet obtenir una única durada, el canvi falla tancat. Resten fora:

- [ENV] execució real de preproducció/SMTP;
- [POLICY/DATA] equivalència entre preu de catàleg del curs i `FACE_VALUE`; cal definir snapshot/data/descomptes abans de calcular diferencials;
- [POLICY] romanent, cobrament complementari, devolució o consum parcial.

## 9. Verificació

El nucli/SIF conserva el tall verificat del PR #115: 858/0, 49 PASS GIFT/UC-018. El 03–04/10 s'han ampliat la prova boundary pública i les proves de `GiftRedemptionTrustedContextResolver`/`GiftEnrollmentStager` per a snapshot immutable, `CCURS` numèric, swap concret per mateixes hores i rebuig d'incompatibilitats. `GiftRedemptionConfirmationBoundaryTest` afegeix token AEAD, tampering, routing, fragment i POST de confirmació. El CI del nou head ha d'acreditar aquestes regressions.
