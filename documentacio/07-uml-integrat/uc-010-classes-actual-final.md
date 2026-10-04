# UC-010 · Diagrama de classes ACTUAL / FINAL

**ACTUAL** = baseline de `main` abans de l'auditoria del 2026-10-04.  
**FINAL** = arquitectura implementada a la branca `audit/uc-010-reconciliacio-2026-10-04`.

## 1. ACTUAL — persistència sense servei UC-010

```mermaid
classDiagram
direction LR

class MigrationRunner {
  +migrate(PDO) array
  +inspect(PDO) array
  +expectedSchema() array
}
class SifConfig {
  <<PHP config>>
  env
  db
  internal_api
  redsys
  aeat
}
class sif_version {
  <<SQL>>
  UUID_VERSION
  VERSION_CODE
  GIT_REVISION
  ARTIFACT_HASH
  CONFIG_HASH
  DATABASE_VERSION
  STATUS
  ACTIVATED_AT
}
class sif_declaration {
  <<SQL>>
  UUID_DECLARATION
  UUID_VERSION
  DOCUMENT_HASH
  STORAGE_KEY
  APPROVED_BY
  STATUS
}
class backup_restore_evidence {
  <<SQL>>
  UUID_EVIDENCE
  ENVIRONMENT
  BACKUP_HASH
  STATUS
  INTEGRITY_RESULT
}
class PanelLaunchAuthenticator {
  +authenticate(input,path) actor
}
class SifVersionManager {
  <<NO EXISTIA>>
}
class VersionPanel {
  <<NO EXISTIA>>
}

MigrationRunner ..> sif_version : schema only
MigrationRunner ..> sif_declaration : schema only
MigrationRunner ..> backup_restore_evidence : schema only
SifConfig ..> MigrationRunner
PanelLaunchAuthenticator ..> VersionPanel : infraestructura reutilitzable
SifVersionManager ..> sif_version : absent
```

### Lectura

El baseline tenia dades estructurals, però no un writer ni un gate UC-010. `STATUS` no era una exclusió transaccional.

## 2. FINAL — domini de governança executable

```mermaid
classDiagram
direction LR

class SifVersionService {
  +runtime(actor) array
  +list(actor,limit) array
  +view(actor,uuid) array
  +registerCurrentRuntime(actor,input) array
  +attachDeclaration(actor,uuid,input) array
  +preflight(actor,uuid,backupUuid) array
  +activate(actor,uuid,input) array
}
class RuntimeVersionInspector {
  +inspect(PDO,config) array
  -uc010DatabaseHardeningChecks(PDO) array
}
class ReleaseManifestVerifier {
  +verify(baseDir,manifestPath) array
}
class RuntimeConfigFingerprint {
  +hash(config) string
  -isSensitiveKey(key) bool
  -secretPresence(value) string
}
class MigrationRunner {
  +inspect(PDO) array
}
class SifVersionRepository {
  +registerCandidate(PDO,input) array
  +assertReplay(existing,input)
  +findByUuid(PDO,uuid,forUpdate) array
  +state(PDO) array
  +activeRows(PDO) array
  +lockState(PDO) array
  +activeRowsForUpdate(PDO) array
  +activate(PDO,uuid)
}
class SifDeclarationRepository {
  +appendApproved(PDO,input) array
  +assertReplay(existing,input)
  +findLatestApprovedByVersion(PDO,uuid) array
}
class BackupRestoreEvidenceRepository {
  +findByUuid(PDO,uuid) array
  +isAcceptable(evidence,env) bool
}
class SifVersionActivationRepository {
  +append(PDO,input) array
  +assertReplay(existing,input)
  +findByIdempotencyKey(PDO,key) array
  +listByVersion(PDO,uuid) array
}
class SifVersionEvidenceVerifier {
  +verify(PDO,uuid) array
}
class SifAuditEventRepository {
  +append(PDO,event) string
}
class OperationalEventRepository {
  +append(PDO,event) string
}
class VersionPanelSession {
  +start()
  +establish(actor)
  +actor() array
  +csrfToken() string
  +assertCsrf(token)
}
class PanelLaunchAuthenticator {
  +authenticate(input,path) actor
}
class VersionActions {
  <<HTTP POST>>
}
class VersionAppJS {
  <<Browser>>
}
class sif_version
class sif_declaration
class sif_version_state
class sif_version_activation
class backup_restore_evidence
class sif_audit_event
class operational_event

SifVersionService --> RuntimeVersionInspector
RuntimeVersionInspector --> ReleaseManifestVerifier
RuntimeVersionInspector --> RuntimeConfigFingerprint
RuntimeVersionInspector --> MigrationRunner
SifVersionService --> SifVersionRepository
SifVersionService --> SifDeclarationRepository
SifVersionService --> BackupRestoreEvidenceRepository
SifVersionService --> SifVersionActivationRepository
SifVersionService --> SifAuditEventRepository
SifVersionEvidenceVerifier --> RuntimeVersionInspector
SifVersionEvidenceVerifier --> BackupRestoreEvidenceRepository
SifVersionService --> OperationalEventRepository

SifVersionRepository --> sif_version
SifVersionRepository --> sif_version_state
SifDeclarationRepository --> sif_declaration
BackupRestoreEvidenceRepository --> backup_restore_evidence
SifVersionActivationRepository --> sif_version_activation
SifAuditEventRepository --> sif_audit_event
OperationalEventRepository --> operational_event

VersionAppJS --> VersionActions : JSON + CSRF
VersionActions --> VersionPanelSession
VersionActions --> SifVersionService
PanelLaunchAuthenticator --> VersionPanelSession : launch HMAC
```

## 3. Responsabilitats i invariants

| Component | Responsabilitat | No ha de fer |
| --- | --- | --- |
| `RuntimeVersionInspector` | observar runtime | activar ni desplegar |
| `SifVersionRepository` | candidata + estat actiu | verificar bytes |
| `SifDeclarationRepository` | persistir aprovació/hash | crear/signar el document |
| `SifVersionActivationRepository` | journal immutable | editar activacions antigues |
| `SifVersionService` | orquestrar gate | executar FTP/deploy |
| `VersionActions` | frontera HTTP | confiar en rols/valors del navegador |
| `VersionAppJS` | UX | decidir hashes o permisos |
| UC-85 | generar evidència backup | activar UC-010 |

## 4. Persistència FINAL

```mermaid
erDiagram
  sif_version ||--o{ sif_declaration : has
  sif_version ||--o{ sif_version_activation : activates
  sif_version ||--o| sif_version_state : active_pointer
  sif_declaration ||--o{ sif_version_activation : proves
  backup_restore_evidence ||--o{ sif_version_activation : gates

  sif_version {
    bigint ID PK
    char UUID_VERSION UK
    varchar VERSION_CODE UK
    char GIT_REVISION
    char ARTIFACT_HASH
    char CONFIG_HASH
    varchar DATABASE_VERSION
    varchar STATUS
    varchar IDEMPOTENCY_KEY UK
    char IDEMPOTENCY_PAYLOAD_HASH
    datetime ACTIVATED_AT
  }
  sif_version_state {
    tinyint ID PK
    char ACTIVE_UUID_VERSION FK
    bigint LOCK_VERSION
  }
  sif_declaration {
    bigint ID PK
    char UUID_DECLARATION UK
    char UUID_VERSION FK
    char DOCUMENT_HASH
    varchar STORAGE_KEY
    varchar APPROVED_BY
    varchar STATUS
    varchar IDEMPOTENCY_KEY UK
  }
  sif_version_activation {
    bigint ID PK
    char UUID_ACTIVATION UK
    varchar IDEMPOTENCY_KEY UK
    char UUID_VERSION FK
    char PREVIOUS_UUID_VERSION FK
    char UUID_DECLARATION FK
    char UUID_BACKUP_EVIDENCE FK
    char RUNTIME_ARTIFACT_HASH
    char RUNTIME_CONFIG_HASH
    varchar RUNTIME_DATABASE_VERSION
    json RUNTIME_EVIDENCE_JSON
  }
```


## 5. Diferències auditades entre el FINAL antic i el FINAL reconciliat

- `SifAuditEventRepository` és el component compartit ja existent a `main`; UC-010 l'injecta amb `UuidGenerator` i no en manté una còpia divergent.
- `SifVersionRepository::activate()` comprova `STATUS=DRAFT` abans de supersedir cap versió.
- `ReleaseManifestVerifier` considera invàlid un manifest ubicat dins del mateix arbre del release.
- `SifVersionService::activate()` serialitza primer amb `sif_version_state FOR UPDATE`; després fa la comprovació idempotent autoritativa i bloqueja candidata/ACTIVE rows.
- `MigrationRunner::inspect()` acredita ledger, hashes de migració i presència de taules/columnes declarades. `RuntimeVersionInspector` hi afegeix verificació explícita dels invariants físics crítics UC-010 (ACTIVE únic, CHECKs i triggers); continua sense pretendre auditar tots els índexs/constraints/tipus de tot el SIF.
- `BackupRestoreEvidenceRepository` és només un reader del contracte persistent UC-85. UC-85 continua sense servei executable complet, per tant aquesta relació és una dependència pendent d'entorn/governança.


## 6. Guards físics de persistència afegits

```mermaid
classDiagram
class sif_version {
  ACTIVE_UNIQUE_GUARD
  CHECK STATUS
  UNIQUE ACTIVE_UNIQUE_GUARD
}
class sif_version_state {
  ID = 1
  ACTIVE_UUID_VERSION
  CHECK ID=1
  trigger no-delete
}
class sif_version_activation {
  STATUS=ACTIVATED
  CHECK STATUS
  trigger no-update
  trigger no-delete
}
```

Aquests guards complementen, però no substitueixen, els locks i validacions del servei.
