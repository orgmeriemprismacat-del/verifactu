# UC-010 · Activitats ACTUAL / FINAL per pàgina i apartat

## 1. Mapa de superfícies

| Superfície | ACTUAL baseline | FINAL branca |
| --- | --- | --- |
| Intranet `sif-verifactu.php` | Resum + incidències | + accés “Configuració i versions” |
| `ajax/sif/sifPanelLaunch.php` | Launch incidències | target allowlist incidents/versions |
| `/sif/versions/index.php` | No existia | Panell UC-010 |
| `/sif/versions/actions.php` | No existia | API sessió+CSRF |
| `/sif/versions/app.js` | No existia | UX read/manage |
| `build-release-manifest.php` | No existia | Build evidence |
| `preflight-version-governance.php` | No existia | Gate read-only |
| `verify-version-governance-evidence.php` | No existia | Evidència post-activació read-only |

## 2. Intranet · botó “Configuració i versions”

### ACTUAL

```mermaid
flowchart TD
A[Obrir resum VERI*FACTU] --> B[Consultar incidències]
B --> C[Obrir panell incidències]
C --> D[Fi]
```

No hi havia ruta UC-010.

### FINAL

```mermaid
flowchart TD
A[Obrir resum VERI*FACTU] --> B{Acció}
B -->|Incidències| C[panel=incidents]
B -->|Configuració i versions| D[panel=versions]
D --> E[POST sifPanelLaunch.php + CSRF]
E --> F{target allowlisted?}
F -->|No| G[422]
F -->|Sí| H{HTTPS + host prisma.cat + URL path = signed path?}
H -->|No| G
H -->|Sí| I[Crear token HMAC UUIDv4 path /sif/versions/]
I --> I2[POST autoform cap a pay*.prisma.cat]
I2 --> J[PanelLaunchAuthenticator signatura + UUIDv4 + anti-replay]
J --> K[Sessió SIF de versions amb TTL]
```

## 3. Panell · Runtime observat

### FINAL

```mermaid
flowchart TD
A[Carregar pàgina] --> B[POST action=runtime]
B --> C[Comprovar rol read/manage]
C --> D[Verificar inventari + bytes + artifact_hash manifest]
D --> E[Calcular config hash sense valors secrets]
E --> F[MigrationRunner ledger/taules/columnes]
F --> F2[Verificar índex únic + CHECKs + triggers UC-010]
F2 --> G{Tot complet?}
G -->|Sí| H[Mostrar Git / artifact / config / BD]
G -->|No| I[Mostrar evidència incompleta]
```

És read-only.

## 4. Panell · Registrar candidata

```mermaid
flowchart TD
A[Usuari introdueix VERSION_CODE] --> B[JS genera request/correlation/idempotency]
B --> C[POST register_current]
C --> D{rol manage?}
D -->|No| E[403]
D -->|Sí| D2{replay semàntic existent?}
D2 -->|Sí| K[Retornar DTO candidata reused=true]
D2 -->|No| F[Servidor inspecciona runtime]
F --> G{complete?}
G -->|No| H[503]
G -->|Sí| I[INSERT DRAFT]
I --> J[Audit + operational event]
J --> K[Resposta candidata]
```

### Camps que NO existeixen al formulari

- git revision;
- artifact hash;
- config hash;
- database version.

Aquests valors són server-authoritative.

## 5. Panell · Llistat i detall

```mermaid
flowchart TD
A[action=list] --> B[versions ordenades per CREATED_AT]
B --> C[Seleccionar fila]
C --> D[action=view UUID]
D --> E[DTO versió sense metadades internes]
D --> F[DTO última declaració APPROVED]
D --> G[DTO journal sense idempotency/evidence JSON]
E --> H[Render detall]
F --> H
G --> H
```

## 6. Panell · Vincular declaració

```mermaid
flowchart TD
A[declaration_version + storage_key] --> B[POST attach_declaration]
B --> C{manage?}
C -->|No| D[403]
C -->|Sí| C2{replay semàntic existent?}
C2 -->|Sí| K[Retornar DTO declaració reused=true]
C2 -->|No| E[Validar candidata DRAFT]
E --> F[Resoldre storage_key dins private root]
F --> G{path segur i fitxer existeix?}
G -->|No| H[404/422]
G -->|Sí| I[SHA-256 bytes]
I --> J[INSERT APPROVED idempotent]
J --> K[Audit]
```

No hi ha upload de fitxer en aquest cas d'ús.

## 7. Panell · Preflight

```mermaid
flowchart TD
A[Preflight UUID] --> B[activation_enabled?]
B --> B2[candidata DRAFT?]
B2 --> B3[singleton present + ACTIVE coherent?]
B3 --> C[runtime complete?]
C --> D[Git matches?]
D --> E[Artifact matches?]
E --> F[Config matches?]
F --> G[DB version matches?]
G --> H[Schema + guards UC-010 verified?]
H --> I[Declaració APPROVED?]
I --> J[Hash document físic matches?]
J --> K{Backup obligatori?}
K -->|Sí| L[Evidence same env + success + integrity]
K -->|No| M[No cal]
L --> N{Tots true?}
M --> N
N -->|Sí| O[GO tècnic]
N -->|No| P[NO-GO + failed checks]
```

## 8. Panell · Activació

```mermaid
flowchart TD
A[Confirmar activació] --> B[Idempotency replay?]
B -->|Sí, mateix payload| C[Retornar activació existent]
B -->|Sí, diferent| D[409]
B -->|No| E[Preflight]
E --> F{GO?}
F -->|No| D
F -->|Sí| G[BEGIN]
G --> H[Lock sif_version_state ID=1]
H --> I[Recheck idempotency FOR UPDATE]
I --> J[Lock candidata + exigir DRAFT]
J --> K[Lock ACTIVE rows]
K --> L{0 o 1 ACTIVE i pointer coherent?}
L -->|No| M[ROLLBACK + 409]
L -->|Sí| N[Revalidar runtime sota lock]
N --> O[SUPERSEDED anterior]
O --> P[ACTIVE candidata]
P --> Q[Actualitzar singleton]
Q --> R[INSERT activation journal]
R --> S[Audit + operational event]
S --> T[COMMIT]
```

## 9. Script · build-release-manifest.php

```mermaid
flowchart TD
A[CLI] --> B[Llegir SIF_RELEASE_MANIFEST_PATH]
B --> C{directori existeix i manifest fora del release?}
C -->|No| D[exit 1]
C -->|Sí i fora de tot l'arbre sif| E[Recórrer roots release]
E --> F[SHA-256 de cada fitxer]
F --> G[Ordenar paths]
G --> H[artifact_hash mapa canònic]
H --> H2[Persistir files + artifact_hash autoconsistent]
H2 --> I[escriptura temporal]
I --> J[rename atòmic + chmod 0640]
J --> K[JSON resum]
```

## 10. Script · preflight-version-governance.php

```mermaid
flowchart TD
A[CLI read-only] --> B[Comprovar rols]
B --> C[Comprovar activation flag]
C --> D[Comprovar private declaration root]
D --> D2[Comprovar manifest fora del release]
D2 --> E[Comprovar taules UC-010]
E --> F[RuntimeVersionInspector]
F --> G{checks}
G -->|tots true| H[ok=true]
G -->|algun false| I[ok=false + failed]
H --> J[production_authorized=false]
I --> J
```

El camp `production_authorized=false` és intencional: un preflight tècnic no substitueix una decisió productiva.

## 11. Script · verify-version-governance-evidence.php

```mermaid
flowchart TD
A[CLI read-only UUID_VERSION] --> B{production?}
B -->|Sí i no opt-in| C[exit 1]
B -->|No / opt-in explícit| D[Consultar ACTIVE + singleton + journal]
D --> E[Revalidar declaració física]
E --> F[RuntimeVersionInspector]
F --> G[Backup gate si requerit]
G --> H[Audit + operational trace]
H --> I{tots checks true?}
I -->|Sí| J[JSON ok=true]
I -->|No| K[JSON ok=false + failed]
J --> L[production_authorized=false]
K --> L
```

## 12. Errors i recuperació per apartat

| Apartat | Error | Resposta |
| --- | --- | --- |
| Launch | target/host/path no allowlisted | 422/launch rebutjat |
| Launch | replay/signatura/caducitat/UUID invàlid | 401/409 |
| Sessió | no autenticada o TTL expirat | 401 |
| Mutació | CSRF | 403 |
| Lectura | rol no configurat/no permès | 403 |
| Candidata | runtime incomplet | 503 |
| Declaració | path traversal | 422 |
| Declaració | fitxer absent | 404 |
| Preflight | drift runtime / guard BD absent / pointer incoherent | NO-GO |
| Activació | payload semàntic diferent | 409 |
| Activació | múltiples ACTIVE | 409 |
| Activació | pointer incoherent | 409 |
| Activació | canvi d'evidència sota lock | 409/rollback |
| Evidència | ACTIVE/journal/declaració/runtime/trace incoherent | JSON ok=false |

## 13. Estat per superfície després de la reconciliació

| Superfície | Documentat | Implementat en branca | Verificat automàtic | Verificat entorn |
| --- | --- | --- | --- | --- |
| Launch intranet/HMAC | Sí | Sí | tests nous preparats; CI final queued | No |
| Runtime/list/detail DTO | Sí | Sí | tests d'integració preparats | No |
| Registrar candidata | Sí | Sí | replay/trace/reason coberts | No |
| Declaració | Sí | Sí | replay/DRAFT/traversal coberts | No |
| Preflight | Sí | Sí | state/schema guards coberts | No |
| Activació | Sí | Sí, lock + guards BD | tests preparats; CI queued | No |
| Manifest CLI | Sí | Sí, inventari + artifact hash | unit tests preparats | No |
| Evidència CLI | Sí | Sí read-only | contracte/integració preparats | No |
| Backup gate UC-85 | Sí com a dependència | reader sí | parcial | No; UC-85 no està tancat |
