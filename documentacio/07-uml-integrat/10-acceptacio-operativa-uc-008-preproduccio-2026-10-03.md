# UC-008 · Runbook d'acceptació operativa de preproducció · 03/10/2026

**Objectiu:** obtenir les quatre evidències reals necessàries per passar de `ENVIRONMENT_ACCEPTANCE_PENDING` a `ENVIRONMENT_CLOSED` sense tocar producció.

**Entorns de treball:**

- SIF/pay: `pay-pre.prisma.cat`;
- intranet: `intranet-pre.prisma.cat`;
- producció exclosa de les proves: `pay.prisma.cat` / `intranet.prisma.cat`.

## 0. Regles de seguretat

1. No executar aquests passos amb `SIF_ENV=production`.
2. No reutilitzar secrets de producció a preproducció.
3. No apuntar cap URL E2E de preproducció a `pay.prisma.cat`.
4. El menú s'ha de descobrir/configurar a la BD de **intranet-pre**.
5. La incidència gestora ha de ser sintètica `UC008_E2E_MANAGER / UC008_E2E`.
6. No usar factures, pagaments o registres fiscals reals per completar l'E2E gestor.
7. No editar manualment els JSON d'evidència.

## 1. Configurar SIF a pay-pre

Carregar al runtime de `pay-pre.prisma.cat`:

```bash
export SIF_ENV=preproduction

export SIF_INCIDENT_READ_ROLES="<ROLS_REALMENT_AUTORITZATS>"
export SIF_INCIDENT_MANAGE_ROLES="<ROLS_GESTORS_REALMENT_AUTORITZATS>"
export SIF_INCIDENT_QUERY_MAX_RESULTS=100

export SIF_INTERNAL_API_KEY_ID="<KEY_ID_PREPROD>"
export SIF_INTERNAL_API_SECRET="<SECRET_PREPROD_MIN_32>"
export SIF_INTERNAL_INCIDENT_SIGNED_PATH="/api/incidents/manage.php"

export SIF_PANEL_LAUNCH_KEY_ID="<PANEL_KEY_ID_PREPROD>"
export SIF_PANEL_LAUNCH_SECRET="<PANEL_SECRET_PREPROD_MIN_32>"
export SIF_PANEL_INCIDENTS_PATH="/sif/incidencies/"
export SIF_PANEL_LAUNCH_MAX_SKEW=120
export SIF_PANEL_SESSION_NAME="SIFPANELSESSID"

export SIF_E2E_INCIDENT_PANEL_URL="https://pay-pre.prisma.cat/sif/incidencies/"
export SIF_E2E_INCIDENT_EXPECTED_HOST="pay-pre.prisma.cat"
export SIF_PRODUCTION_HOST="pay.prisma.cat"
```

**No guardar els valors secrets a Git ni als JSON d'evidència.**

## 2. Preflight SIF read-only

A `pay-pre`:

```bash
php sif/scripts/preflight-incidents-panel.php   | tee uc-008-preflight.json
```

Criteri:

```text
ok=true
environment=preproduction
production_authorized=false
```

Han de quedar verds, entre d'altres:

- rols read/manage;
- manage ⊆ read;
- API key/secret;
- panel key/secret;
- paths signats;
- extensions PHP;
- esquema/migracions;
- `errors_verifactu`;
- `sif_incident_action`;
- `internal_api_request`.

Si falla qualsevol check: **STOP**.

## 3. Configurar intranet-pre

A `intranet-pre.prisma.cat`, el client intern ha d'apuntar exclusivament a pay-pre:

```bash
export SIF_ENV=preproduction

export SIF_INTERNAL_INCIDENTS_URL="https://pay-pre.prisma.cat/api/incidents/manage.php"
export SIF_INTERNAL_INCIDENT_SIGNED_PATH="/api/incidents/manage.php"
export SIF_INTERNAL_API_KEY_ID="<MATEIX_KEY_ID_PREPROD>"
export SIF_INTERNAL_API_SECRET="<MATEIX_SECRET_PREPROD>"

export SIF_PANEL_INCIDENTS_URL="https://pay-pre.prisma.cat/sif/incidencies/"
export SIF_PANEL_INCIDENTS_PATH="/sif/incidencies/"
export SIF_PANEL_LAUNCH_KEY_ID="<MATEIX_PANEL_KEY_ID_PREPROD>"
export SIF_PANEL_LAUNCH_SECRET="<MATEIX_PANEL_SECRET_PREPROD>"
```

## 4. Evidència del menú a intranet-pre

Des de l'arrel del codi de la intranet-pre:

```bash
cd codi-drive/intranet-actual
export SIF_ENV=preproduction

php preflight-sif-verifactu-menu.php   | tee uc-008-menu-evidence.json
```

### Si retorna ALREADY_PRESENT

Continuar només si:

```text
ok=true
environment=preproduction
scope=uc-008-intranet-menu-discovery
read_only=true
target_url=/sif-verifactu.php
existing_target_count=1
status=ALREADY_PRESENT
```

### Si retorna CONFIRM_PARENT_ROLES_ORDER_BEFORE_INSERT

No és un error de codi. Cal:

1. revisar `SHOW CREATE TABLE apartats`;
2. confirmar pare/nivell/rols/ordre/icona;
3. executar la plantilla transaccional documentada a `07-alta-menu-intranet-uc-008.md`;
4. validar la fila;
5. fer `COMMIT`;
6. repetir el preflight;
7. substituir l'evidència anterior pel JSON que retorni `ALREADY_PRESENT`.

### Si retorna DUPLICATE_TARGET_URL

**STOP.** No modificar ni eliminar files fins investigar el duplicat.

## 5. E2E read-only real

A `pay-pre`:

```bash
export SIF_ENV=preproduction
export SIF_E2E_INCIDENT_PANEL_URL="https://pay-pre.prisma.cat/sif/incidencies/"
export SIF_E2E_INCIDENT_EXPECTED_HOST="pay-pre.prisma.cat"
export SIF_PRODUCTION_HOST="pay.prisma.cat"
export SIF_E2E_INCIDENT_ACTOR_ID="uc008-e2e-reader"
export SIF_E2E_INCIDENT_READ_ROLE="<ROL_REAL_READ_ONLY>"

php sif/scripts/e2e-incidents-panel.php   | tee uc-008-e2e-read-only.json
```

El script falla abans de fer HTTP si:

- l'entorn és producció;
- la URL no és HTTPS;
- falta `SIF_E2E_INCIDENT_EXPECTED_HOST`;
- el host no coincideix exactament amb `pay-pre.prisma.cat`;
- el host coincideix amb `pay.prisma.cat`.

## 6. Evidència agregada de preproducció

A `pay-pre`:

```bash
php sif/scripts/verify-incidents-panel-preproduction.php   | tee uc-008-preproduction-evidence.json
```

Criteri:

```text
ok=true
scope=uc-008-preproduction-verification
environment=preproduction
checks.preflight_ok=true
checks.e2e_ok=true
production_authorized=false
```

## 7. Preparar la incidència sintètica del gestor

Triar el rol gestor real que també tingui lectura:

```bash
export SIF_ENV=preproduction
export SIF_E2E_INCIDENT_MANAGER_ROLE="<ROL_GESTOR_REAL>"
export SIF_UC008_MANAGER_E2E_PREPARE=YES

php sif/scripts/prepare-incident-manager-e2e.php 2026-10-03-A   | tee uc-008-manager-e2e-prepare.json
```

Conservar l'`incident_id` retornat.

## 8. Executar el lifecycle des del panell

Amb un usuari gestor real a **intranet-pre**:

1. entrar a `/sif-verifactu.php`;
2. prémer «Obrir incidències SIF»;
3. comprovar que s'obre **pay-pre**, no pay de producció;
4. obrir l'incident sintètic;
5. `ASSIGN`;
6. `ADD_EVIDENCE`;
7. `RESOLVE` amb criteri de tancament, notes i evidència;
8. comprovar el timeline i l'estat final `RESOLVED`.

No usar `DISMISS` per aquesta evidència perquè el gate exigeix el flux de resolució.

## 9. Verificar el gestor read-only

A `pay-pre`:

```bash
php sif/scripts/verify-incident-manager-evidence.php <INCIDENT_ID>   | tee uc-008-manager-e2e-evidence.json
```

Ha de retornar:

```text
ok=true
scope=uc-008-manager-e2e-evidence
environment=preproduction
read_only=true
```

I tots els checks de:

- `ASSIGN`;
- `ADD_EVIDENCE`;
- `RESOLVE`;
- actor;
- rol gestor;
- correlació;
- evidència;
- notes/criteri de tancament;
- query read-only.

## 10. Gate final

Col·locar en un mateix directori:

- `uc-008-preproduction-evidence.json`;
- `uc-008-menu-evidence.json`;
- `uc-008-manager-e2e-evidence.json`.

Executar:

```bash
php sif/scripts/validate-uc008-evidence.php   uc-008-preproduction-evidence.json   uc-008-menu-evidence.json   uc-008-manager-e2e-evidence.json   | tee uc-008-closure-validation.json
```

Criteri final:

```text
ok=true
scope=uc-008-evidence-validation
```

El validador exigeix explícitament:

- preproduction evidence de `environment=preproduction`;
- menu evidence de `environment=preproduction`;
- manager evidence de `environment=preproduction`;
- menú únic i `ALREADY_PRESENT`;
- flux gestor complet;
- absència de secrets;
- SHA-256 dels tres inputs.

## 11. Evidències a conservar

| Fitxer | Obligatori | Origen |
| --- | --- | --- |
| `uc-008-preflight.json` | recomanat | pay-pre |
| `uc-008-e2e-read-only.json` | recomanat | pay-pre |
| `uc-008-preproduction-evidence.json` | **sí** | pay-pre |
| `uc-008-menu-evidence.json` | **sí** | intranet-pre |
| `uc-008-manager-e2e-prepare.json` | recomanat | pay-pre |
| `uc-008-manager-e2e-evidence.json` | **sí** | pay-pre |
| `uc-008-closure-validation.json` | **sí** | pay-pre |

## 12. Criteri de tancament

Només després d'obtenir `uc-008-closure-validation.json` amb `ok=true` es pot actualitzar l'estat a:

`AUDIT_CLOSED + CODE_COMPLETE + UC008_REGRESSION_PASS + ENVIRONMENT_CLOSED`

Fins llavors:

`ENVIRONMENT_ACCEPTANCE_PENDING`.
