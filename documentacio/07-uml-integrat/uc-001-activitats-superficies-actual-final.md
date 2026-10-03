# UC-001 · Activitats i superfícies ACTUAL / FINAL

## 1. Superfícies

| ID | Superfície | ACTUAL | FINAL / límit |
| --- | --- | --- | --- |
| P-UC001-01 | `/api/factures/issue.php` | POST intern signat, rol, actor servidor, no Redsys/no UC-004; emissor/SistemaInformatico server-owned, resposta d’estats i traça append-only. | Operació comercial transversal i desglossament fiscal complet generat pels builders. |
| P-UC001-02 | `/api/factures/before-payment.php` | UC-004 amb auth, rol, selecció servidor i coverage. | No saltar-lo via P-UC001-01. |
| P-UC001-03 | Worker Redsys | Handlers especialitzats després de callback/intenció validats. | Coherència intent↔snapshot i fencing. |
| P-UC001-04 | Serveis manuals | Builders + idempotència del nucli. | Identificador d'operació comercial independent. |
| P-UC001-05 | Cua/worker AEAT | Procés postcommit separat, `aeat_submission_attempt`, fencing `CLAIM_TOKEN`, `REVIEW` i reconciliació sense reenviament cec. | Resolució operativa dels casos realment `UNCERTAIN`. |
| P-UC001-06 | `alumnes-factura.php` | Consulta SIF read-only + fallback llegat; mutacions llegades amb guards. | Activar/provar flags de cutover; correccions SIF per flux dedicat. |
| P-UC001-07 | `alumnes-genera-factura-abans-pagar.php` | UC-004: selecció, preview/fingerprint, confirmació i resultat. | UC-004 prepara l’ordre; UC-001 emet/reutilitza. |

## 2. Activitat ACTUAL del generic endpoint

```mermaid
flowchart TD
A[POST intern] --> B{HMAC timestamp REQUEST_ID vàlids?}
B -- No --> X[401/409]
B -- Sí --> C{Rol escriptura?}
C -- No --> Y[403]
C -- Sí --> D[Decodificar JSON]
D --> E{REDSYS o invoice-before-payment?}
E -- Sí --> Z[422: flux dedicat]
E -- No --> F[Actor autenticat + emissor servidor si AEAT]
F --> G[Validar clau estructura sumes i traça]
G --> G2{PREPROD/PROD amb aeat_fields?}
G2 -- No --> Z2[422 fail-closed]
G2 -- Sí / entorn no qualificat --> H{Clau existent?}
H -- Sí --> I[Comparar fingerprint i reutilitzar]
H -- No --> J[Numeració + cadena]
J --> K[Factura línies registre cua relacions]
K --> L[Enllaçar relació amb línia si unívoca]
L --> M{Payment inicial?}
M -- No --> N[COMMIT]
M -- Sí --> M1{movement_date estable?}
M1 -- No --> X3[422 + ROLLBACK]
M1 -- Sí --> M2[Validar payment + transaction + allocation]
M2 --> N
```

## 3. Activitat FINAL comuna

```mermaid
flowchart TD
A[Ordre facturació] --> B[Actor/procés + correlació]
B --> C[Lock operació comercial]
C --> D{Obligació coberta?}
D -- Equivalent --> E[Reutilitzar]
D -- Conflictiva --> X[Incidència/correcció]
D -- No --> F[Receptor línies descomptes totals]
F --> G[Snapshot fiscal servidor]
G --> H[Emissió transaccional]
H --> I[Links operació↔línies↔factura]
I --> J[Auditoria append-only]
J --> K[AEAT document sync correus]
K --> L[Resposta amb estats independents]
```

El FINAL continua parcial: audit writer i projecció d’estats ja són ACTUAL; resten cobertura comercial transversal, links d’operació↔línia i assembler fiscal servidor complet.

## 4. Activitats ACTUAL per pàgina i apartat real

### 4.1. `alumnes-factura.php`

| Apartat | Codi real | ACTUAL | FINAL / criteri |
| --- | --- | --- | --- |
| Cerca | `alumnes-factura.js` → `sifFactures.php` | POST SIF read-only; fallback llegat. | SIF autoritatiu per factura migrada. |
| Resultats/modal SIF | `search/view` | Estats i dades només lectura. | Cap edició fiscal directa. |
| Documents | `sifDocument.php` | POST i descàrrega. | Custòdia UC-036/78. |
| Edició llegada | `guardarDadesFactura_Factures.php` | Sessió, rol, same-origin i guard SIF. | Bloquejada si governada pel SIF. |
| Anul·lació llegada | `anularFactura_Factures.php` | Mateixos guards; no és anul·lació fiscal SIF. | Derivar al flux fiscal. |

**Càrrega JS:** `alumnes-factura-sif.js` es carrega abans de `alumnes-factura.js`; tots dos assignen `window.uc007SifSearch`, i la definició global final és la del segon.

### 4.2. `alumnes-genera-factura-abans-pagar.php` — UC-004

```mermaid
flowchart TD
A[Pas 1 cercar/seleccionar inscripcions] --> B[Pas 2 resoldre entitat]
B --> C[POST preview + CSRF]
C --> D[Servidor reconstrueix receptor/imports]
D --> E[Fingerprint autoritatiu]
E --> F{Confirmar mateix preview?}
F -- No/canvi --> C
F -- Sí --> G[POST confirm + expected_fingerprint]
G --> H[UC-004 CommandService]
H --> I[InvoiceService UC-001]
I --> J[Pas 3 UUID + número + cobrament PENDING]
```

El browser envia identificadors, entitat, observacions i fingerprint; no decideix lliurement `series/year/type/totals/lines/aeat_fields`.

### 4.3. Generic endpoint, Redsys i manual

- `/api/factures/issue.php`: frontera interna signada, sense JS públic emissor.
- Redsys: notificació/snapshot validats → handler → `InvoiceService`; la data del payment ve de la notificació.
- Manual: builder/servei intern → `InvoiceService`; si hi ha cobrament inicial ha d’aportar `movement_date` real/estable.

## 5. Estat verificat / pendent

- **Inspecció:** superfícies i guards revisats.
- **Tests:** core, endpoint, UC-004 i callers Redsys/manuals PASS a `88e5c922…`.
- **Corregit 03/10:** `movement_date` obligatòria; prova afegida a `276fb390…`.
- **Pendent preprod:** flags llegats, HMAC/rol/emissor/SIF, builders AEAT, reconciliació amb `main`.
