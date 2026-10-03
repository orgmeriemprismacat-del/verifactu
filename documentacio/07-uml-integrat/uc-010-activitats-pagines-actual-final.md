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
F -->|Sí| H[Crear token HMAC path /sif/versions/]
H --> I[POST autoform cap a pay.prisma.cat]
I --> J[PanelLaunchAuthenticator anti-replay]
J --> K[Sessió SIF de versions]
```

## 3. Panell · Runtime observat

### FINAL

```mermaid
flowchart TD
A[Carregar pàgina] --> B[POST action=runtime]
B --> C[Comprovar rol read/manage]
C --> D[Verificar manifest contra bytes]
D --> E[Calcular config hash]
E --> F[MigrationRunner inspect]
F --> G{Tot complet?}
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
D -->|Sí| F[Servidor inspecciona runtime]
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
D --> E[Versió]
D --> F[Última declaració APPROVED]
D --> G[Journal activacions]
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
C -->|Sí| E[Validar candidata]
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
B --> C[runtime complete?]
C --> D[Git matches?]
D --> E[Artifact matches?]
E --> F[Config matches?]
F --> G[DB version matches?]
G --> H[Schema verified?]
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
H --> I[Lock ACTIVE rows]
I --> J{0 o 1 ACTIVE i pointer coherent?}
J -->|No| K[ROLLBACK + 409]
J -->|Sí| L[Revalidar runtime sota lock]
L --> M[SUPERSEDED anterior]
M --> N[ACTIVE candidata]
N --> O[Actualitzar singleton]
O --> P[INSERT activation journal]
P --> Q[Audit + operational event]
Q --> R[COMMIT]
```

## 9. Script · build-release-manifest.php

```mermaid
flowchart TD
A[CLI] --> B[Llegir SIF_RELEASE_MANIFEST_PATH]
B --> C{directori existeix i fora public?}
C -->|No| D[exit 1]
C -->|Sí| E[Recórrer roots release]
E --> F[SHA-256 de cada fitxer]
F --> G[Ordenar paths]
G --> H[artifact_hash mapa canònic]
H --> I[escriptura temporal]
I --> J[rename atòmic + chmod 0640]
J --> K[JSON resum]
```

## 10. Script · preflight-version-governance.php

```mermaid
flowchart TD
A[CLI read-only] --> B[Comprovar rols]
B --> C[Comprovar activation flag]
C --> D[Comprovar private declaration root]
D --> E[Comprovar taules UC-010]
E --> F[RuntimeVersionInspector]
F --> G{checks}
G -->|tots true| H[ok=true]
G -->|algun false| I[ok=false + failed]
H --> J[production_authorized=false]
I --> J
```

El camp `production_authorized=false` és intencional: un preflight tècnic no substitueix una decisió productiva.

## 11. Errors i recuperació per apartat

| Apartat | Error | Resposta |
| --- | --- | --- |
| Launch | target no allowlisted | 422 |
| Launch | replay/signatura/caducitat | 401/409 |
| Sessió | no autenticada | 401 |
| Mutació | CSRF | 403 |
| Lectura | rol no configurat/no permès | 403 |
| Candidata | runtime incomplet | 503 |
| Declaració | path traversal | 422 |
| Declaració | fitxer absent | 404 |
| Preflight | drift runtime | NO-GO |
| Activació | payload idempotent diferent | 409 |
| Activació | múltiples ACTIVE | 409 |
| Activació | pointer incoherent | 409 |
| Activació | canvi d'evidència sota lock | 409/rollback |
