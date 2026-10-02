# UC-001 · Activitats i superfícies ACTUAL / FINAL

## 1. Superfícies

| ID | Superfície | ACTUAL | FINAL / límit |
| --- | --- | --- | --- |
| P-UC001-01 | `/api/factures/issue.php` | POST intern signat, rol, actor servidor, no Redsys/no UC-004; resposta d’estats i traça append-only. | Operació comercial transversal i snapshot fiscal servidor complet. |
| P-UC001-02 | `/api/factures/before-payment.php` | UC-004 amb auth, rol, selecció servidor i coverage. | No saltar-lo via P-UC001-01. |
| P-UC001-03 | Worker Redsys | Handlers especialitzats després de callback/intenció validats. | Coherència intent↔snapshot i fencing. |
| P-UC001-04 | Serveis manuals | Builders + idempotència del nucli. | Identificador d'operació comercial independent. |
| P-UC001-05 | Cua/worker AEAT | Procés postcommit separat, `aeat_submission_attempt`, fencing `CLAIM_TOKEN`, `REVIEW` i reconciliació sense reenviament cec. | Resolució operativa dels casos realment `UNCERTAIN`. |

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
F --> G[Validar clau estructura i sumes]
G --> H{Clau existent?}
H -- Sí --> I[Comparar fingerprint i reutilitzar]
H -- No --> J[Numeració + cadena]
J --> K[Factura línies registre cua relacions]
K --> L[Enllaçar relació amb línia si unívoca]
L --> M[Payment inicial opcional no Redsys]
M --> N[COMMIT]
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
