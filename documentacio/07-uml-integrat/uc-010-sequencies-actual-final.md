# UC-010 · Diagrames de seqüència ACTUAL / FINAL

## 1. ACTUAL — abans de l'auditoria

No existia una seqüència executable UC-010. El màxim acreditable era persistència SQL i documentació de disseny.

```mermaid
sequenceDiagram
autonumber
actor T as Responsable tècnica
participant Git as Repositori Git
participant Env as Servidor
participant DB as sif_version / sif_declaration

T->>Git: Preparar canvi
T->>Env: Desplegament fora d'UC-010
Note over Env,DB: no servei que compari bytes/config/esquema
opt Registre manual/futur
  T->>DB: metadades de versió
end
Note over DB: STATUS no garanteix exclusivitat ni runtime real
```

## 2. FINAL A — registrar candidata des del runtime observat

```mermaid
sequenceDiagram
autonumber
actor U as Responsable tècnica
participant JS as versions/app.js
participant API as actions.php
participant S as SifVersionService
participant I as RuntimeVersionInspector
participant M as ReleaseManifestVerifier
participant MR as MigrationRunner
participant VR as SifVersionRepository
participant A as Audit repositories
participant DB as MySQL SIF

U->>JS: Registrar codi de versió
JS->>API: register_current + CSRF + ids
API->>S: registerCurrentRuntime(actor,input)
S->>VR: lookup idempotency key
alt replay semàntic existent
  VR-->>S: candidata persistent
  S-->>API: reused=true
  API-->>JS: DTO candidata
else comanda nova
S->>I: inspect(DB,config)
I->>M: verificar manifest vs bytes
M-->>I: artifact_hash + mismatches
I->>MR: inspect(DB)
MR-->>I: ledger + taules/columnes
I->>DB: validar índex únic/CHECKs/triggers UC-010
DB-->>I: hardening schema checks
I-->>S: git/artifact/config/db version
alt evidència incompleta
  S-->>API: 503 fail-closed
else runtime complet
  S->>DB: BEGIN
  S->>VR: registerCandidate(observed values)
  VR->>DB: INSERT DRAFT idempotent
  S->>A: audit + operational event
  S->>DB: COMMIT
  S-->>API: candidata
  API-->>JS: DTO candidata sense idempotency intern
end
end
```

**Invariante:** el navegador no pot proposar els quatre hashes/versions de runtime.

## 3. FINAL B — vincular declaració real

```mermaid
sequenceDiagram
autonumber
actor U as Responsable autoritzada
participant JS as versions/app.js
participant API as actions.php
participant S as SifVersionService
participant FS as SIF_DECLARATION_ROOT
participant DR as SifDeclarationRepository
participant DB as MySQL SIF

U->>JS: storage_key + versió documental
JS->>API: attach_declaration
API->>S: attachDeclaration(actor,uuid,input)
S->>DB: cercar idempotency key
Note over S,DB: request_id/correlation_id/actor_role són traça; reason_code és semàntic
alt replay semàntic existent
  DB-->>S: declaració persistent
  S-->>API: reused=true
else comanda nova
  S->>DB: comprovar candidata DRAFT
  S->>FS: realpath dins root privat
alt path traversal o absent
  FS-->>S: error
  S-->>API: 404/422
else fitxer vàlid
  S->>FS: SHA-256(bytes)
  FS-->>S: document_hash
  S->>DB: BEGIN
  S->>DR: appendApproved(...)
  DR->>DB: INSERT APPROVED idempotent
  S->>DB: audit + operational_event
  S->>DB: COMMIT
  S-->>API: declaració vinculada
end
end
```

## 4. FINAL C — preflight i activació serialitzada

```mermaid
sequenceDiagram
autonumber
actor U as Responsable tècnica
participant S as SifVersionService
participant I as RuntimeVersionInspector
participant DR as SifDeclarationRepository
participant BR as BackupRestoreEvidenceRepository
participant VR as SifVersionRepository
participant AR as SifVersionActivationRepository
participant DB as MySQL SIF
participant Env as Runtime físic

U->>S: activate(uuid, backup?, ids)
S->>AR: fast replay semàntic
S->>VR: llegir singleton + ACTIVE
S->>I: inspect
I->>Env: hashes bytes/config + schema
Env-->>I: evidència actual + hardening BD
S->>DR: latest APPROVED
S->>Env: recalcular hash declaració
opt backup obligatori
  S->>BR: find evidence
  BR-->>S: status/integrity/environment
end
alt singleton/ACTIVE incoherent o qualsevol check falla
  S-->>U: 409 NO-GO
else preflight GO i candidata DRAFT
  S->>DB: BEGIN
  S->>VR: lockState() FOR UPDATE
  VR->>DB: lock singleton
  S->>AR: find idempotency key FOR UPDATE
  alt replay mateix payload
    AR-->>S: activation existent
    S-->>DB: COMMIT
    S-->>U: reused=true
  else key nova
    S->>VR: lock candidata
    S->>VR: lock ACTIVE rows
    alt candidata no DRAFT o estat inconsistent o >1 ACTIVE
      S-->>DB: ROLLBACK
      S-->>U: 409
    else estat consistent
      S->>I: tornar a inspeccionar sota lock
      S->>VR: SUPERSEDE old + ACTIVE candidate
      S->>AR: append immutable activation
      AR->>DB: runtime evidence + declaració/backup snapshot + actor
      S->>DB: audit + operational_event
      S->>DB: COMMIT
      S-->>U: ACTIVATED
    end
  end
end
```

## 5. FINAL D — replay idempotent

```mermaid
sequenceDiagram
autonumber
actor C as Client/panell
participant S as SifVersionService
participant AR as ActivationRepository
participant DB as MySQL

C->>S: activate(key K, payload P, trace T)
Note over C,S: canviar T=request/correlation/actor_role no canvia el payload; reason_code sí
S->>AR: fast lookup K
alt K ja existeix i P concorda
  AR-->>S: activation existent
  S-->>C: reused=true
else K no existeix
  S->>S: preflight
  S->>DB: BEGIN + lock singleton
  S->>AR: authoritative lookup K FOR UPDATE
  alt una altra transacció ja ha creat K
    AR-->>S: activation existent
    S-->>C: reused=true
  else K continua absent
    S->>S: validar DRAFT + locks + preflight sota lock
    S->>AR: append(K,P)
    AR->>DB: INSERT unique K
    S-->>C: activació nova
  end
else K existeix però payload és diferent
  S-->>C: 409 conflict
end
```

## 6. FINAL E — frontera de desplegament

```mermaid
sequenceDiagram
autonumber
actor O as Operador/deploy
participant Deploy as Mecanisme extern de desplegament
participant Env as Runtime SIF
participant Build as build-release-manifest.php
participant UC10 as UC-010

O->>Deploy: publicar release
Deploy->>Env: bytes + config + migracions
O->>Build: generar manifest exhaustiu sobre bytes desplegats
Build-->>O: files map + artifact_hash autoconsistent
O->>UC10: registrar candidata observada
UC10->>Env: verificar runtime
Note over Deploy,UC10: UC-010 NO executa FTP, checkout, rsync ni rollback físic
```

Aquesta separació evita marcar ACTIVE una versió que només estava “prevista” però no realment servida.


## 7. Nota de verificació 2026-10-04

La seqüència FINAL representa el codi reconciliat, no el PR #139 original. El canvi d'ordre del lock/idempotència i el gate `DRAFT` són correccions derivades de l'auditoria de concurrència i traçabilitat.


## 7. FINAL F — launch HMAC de la intranet

```mermaid
sequenceDiagram
autonumber
actor U as Usuari intranet
participant I as sif-verifactu.js
participant L as sifPanelLaunch.php
participant T as SifPanelLaunchToken
participant P as /sif/versions/
participant A as PanelLaunchAuthenticator
participant DB as internal_api_request

U->>I: Configuració i versions
I->>L: POST panel=versions + CSRF
L->>T: create(actor,roles)
T->>T: HTTPS + host *.prisma.cat + URL path = signed path
T-->>I: URL + fields HMAC + UUIDv4
I->>P: auto-POST fields
P->>A: authenticate(fields,/sif/versions/)
A->>A: key/timestamp/UUIDv4/roles/signature
A->>DB: claim request_id
alt replay
  DB-->>A: duplicate
  A-->>P: 409
else nou
  A-->>P: actor + roles
  P-->>U: sessió amb TTL + CSRF
end
```

## 8. Nota de verificació 2026-10-04

La seqüència FINAL representa el codi reconciliat actual. Els DTOs públics no retornen `IDEMPOTENCY_KEY`, `IDEMPOTENCY_PAYLOAD_HASH`, guard columns ni `RUNTIME_EVIDENCE_JSON`; aquestes dades romanen a persistència/evidència interna.
