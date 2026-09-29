# UC-008 — Diagrames d'activitat ACTUAL/FINAL per pàgina i apartat

**Data:** 29/09/2026  
**Objectiu:** aplicar el criteri RM-037 al UC-008. Quan la UI encara no existeix, el diagrama ACTUAL ho diu explícitament i representa només el backend real; no s'inventa una pantalla.

Vegeu [classes](uc-008-classes-actual-final.md), [seqüències](uc-008-sequencies-actual-final.md) i [fitxa integrada](uc-008-gestionar-incidencia-sif.md).

## 0. Inventari de pàgines/apartats

| ID | Superfície | Apartats | ACTUAL | FINAL |
| --- | --- | --- | --- | --- |
| P-INC-01 | `pay.prisma.cat/sif/incidencies` · llistat | filtres, prioritat/estat, obrir detall | UI no acreditada; API list sí | panell autenticat |
| P-INC-02 | detall d'incidència | capçalera, recurs, timeline, evidències | API view sí; UI no | expedient complet |
| P-INC-03 | accions de lifecycle | assignar, evidència, resoldre, dismiss, reobrir | API/service sí; UI no | controls per rol |
| P-INC-04 | Intranet · VERI*FACTU | indicador, resum, enllaç | no acreditat | read-only + fallback indisponibilitat |
| A-INC-05 | obertura automàtica | Redsys, AEAT integritat/dead-letter | implementat parcial | tots els detectors rellevants |
| A-INC-06 | reparació | derivació a UC específic | manual/orquestrada per cas | derivació explícita correlacionada |

## 1. P-INC-01 · Llistat

### 1.1 ACTUAL

```mermaid
flowchart TD
A[No hi ha pàgina UI acreditada] --> B[POST API action=list]
B --> C{rol read/manage?}
C -->|no| D[403]
C -->|sí| E[IncidentRepository list]
E --> F[Filtrar estat severitat tipus responsable]
F --> G[JSON ordenat per data/ID]
```

### 1.2 FINAL

```mermaid
flowchart TD
A[Entrar al panell incidències] --> B[Autenticar sessió SIF]
B --> C[Carregar filtres]
C --> D[Consultar API]
D --> E{resposta?}
E -->|error| F[Mostrar indisponibilitat/error sense estat fals]
E -->|ok| G[Taula: severitat estat tipus recurs responsable data]
G --> H{acció}
H -->|filtrar| C
H -->|obrir| I[P-INC-02 detall]
```

## 2. P-INC-02 · Detall

### 2.1 ACTUAL

```mermaid
flowchart TD
A[POST action=view + incident_id] --> B{rol lectura?}
B -->|no| C[403]
B -->|sí| D[findById]
D --> E{existeix?}
E -->|no| F[404]
E -->|sí| G[listForIncident]
G --> H[JSON capçalera + timeline]
```

### 2.2 FINAL

```mermaid
flowchart TD
A[Obrir expedient] --> B[Mostrar UUID tipus severitat estat]
B --> C[Mostrar factura/pagament/recurs/origen]
C --> D[Mostrar correlació i responsable]
D --> E[Mostrar timeline immutable]
E --> F[Mostrar evidències autoritzades]
F --> G{rol manage?}
G -->|no| H[Només lectura]
G -->|sí| I[Habilitar P-INC-03]
```

## 3. P-INC-03A · Assignar / triage

### ACTUAL

```mermaid
flowchart TD
A[action=assign] --> B[Validar role manage]
B --> C[SELECT incident FOR UPDATE]
C --> D[append ASSIGN amb payload hash]
D --> E{key ja usada?}
E -->|mateix payload| F[REUSED sense nova transició]
E -->|payload diferent| G[409]
E -->|nova| H{incident actiu?}
H -->|no| I[ROLLBACK + 409]
H -->|sí| J[IN_PROGRESS + assignee]
```

### FINAL

```mermaid
flowchart TD
A[Operador tria responsable/severitat] --> B[Motiu obligatori]
B --> C[Confirmar]
C --> D[assign idempotent]
D --> E[Refrescar detall/timeline]
```

## 4. P-INC-03B · Afegir evidència

### ACTUAL

```mermaid
flowchart TD
A[action=evidence] --> B[evidence JSON no buit]
B --> C[append ADD_EVIDENCE idempotent]
C --> D{reused?}
D -->|sí| E[No duplicar]
D -->|no| F{incident actiu?}
F -->|no| G[ROLLBACK]
F -->|sí| H[Conservar timeline sense canviar estat]
```

### FINAL

```mermaid
flowchart TD
A[Seleccionar evidència] --> B[Comprovar que no conté secrets/PAN/tokens]
B --> C[Guardar referència o evidència privada]
C --> D[Afegir motiu/context]
D --> E[Timeline]
```

## 5. P-INC-03C · Resoldre / dismiss

### ACTUAL

```mermaid
flowchart TD
A[action=resolve o dismiss] --> B[Validar role manage]
B --> C[Exigir criteria + notes]
C --> D{RESOLVE?}
D -->|sí| E[Exigir evidence]
D -->|no| F[Justificació dismissal]
E --> G[append acció idempotent]
F --> G
G --> H{reused?}
H -->|sí| I[No segona transició]
H -->|no| J{incident actiu?}
J -->|no| K[ROLLBACK]
J -->|sí| L[RESOLVED/DISMISSED + timestamp]
```

### FINAL

```mermaid
flowchart TD
A[Sol·licitar tancament] --> B[Repetir prova o conciliar font]
B --> C{resultat acreditat?}
C -->|no| D[Mantenir obert + FAIL/BLOCKED]
C -->|sí| E[Adjuntar prova i criteri]
E --> F{impacte material resolt?}
F -->|no| D
F -->|sí| G[RESOLVED]
```

## 6. P-INC-03D · Reobrir

### ACTUAL

```mermaid
flowchart TD
A[action=reopen] --> B[append REOPEN idempotent]
B --> C{reused?}
C -->|sí| D[Retornar OPEN sense duplicat]
C -->|no| E{estat RESOLVED/DISMISSED?}
E -->|no| F[ROLLBACK + 409]
E -->|sí| G[OPEN + netejar dades de tancament]
```

### FINAL

```mermaid
flowchart TD
A[Nova evidència o recurrència] --> B[Motiu de reobertura]
B --> C[Reobrir expedient existent si és el mateix incident lògic]
C --> D[No crear reparació fiscal/econòmica automàtica]
D --> E[Nou cicle de triage]
```

## 7. A-INC-05 · Obertura automàtica

### ACTUAL

```mermaid
flowchart TD
A[Worker detecta anomalia] --> B{origen}
B -->|Redsys| C[BEGIN: queue INCIDENT + openDetailed]
B -->|AEAT integritat| D[BEGIN: rejectIntegrity + FISCAL_PAYLOAD_CONFLICT]
B -->|AEAT retries esgotats| E[BEGIN: DEAD_LETTER + AEAT_DEAD_LETTER]
C --> F[COMMIT o ROLLBACK conjunt]
D --> F
E --> F
B -->|altres| G[Integració encara pendent]
```

### FINAL

```mermaid
flowchart TD
A[Detector SIF] --> B[Identificar recurs + causa + evidència]
B --> C[Construir key idempotent]
C --> D[open/reuse incident]
D --> E[Classificar severitat]
E --> F[Notificar/resumir segons política]
F --> G[Mai repetir factura/càrrec/registre només per obrir incidència]
```

## 8. A-INC-06 · Reparació

### ACTUAL

```mermaid
flowchart TD
A[Incident diagnosticat] --> B[UC-008 conserva expedient]
B --> C[Operador/procés identifica UC corrector]
C --> D[Executar UC-02/28/55/74/77/82/124/etc.]
D --> E[Recollir resultat]
E --> F[Afegir evidència i decidir tancament]
```

### FINAL

```mermaid
flowchart TD
A[Diagnosi] --> B[Catàleg de reparacions autoritzades]
B --> C{reparació suportada?}
C -->|no| D[Escalar a responsable]
C -->|sí| E[Cridar UC específic amb correlation]
E --> F[Verificar destinació real]
F --> G{PASS?}
G -->|no| D
G -->|sí| H[RESOLVE]
```

## 9. P-INC-04 · Intranet VERI*FACTU

### ACTUAL

```mermaid
flowchart TD
A[Sidebar/intranet actual] --> B[No hi ha resum UC-008 acreditat]
B --> C[Consulta de factures SIF sí existeix però és un altre UC]
```

### FINAL

```mermaid
flowchart TD
A[Obrir VERI*FACTU] --> B[Consulta SIF read-only]
B --> C{SIF disponible?}
C -->|sí| D[Mostrar obertes crítiques i última actualització]
D --> E[Enllaç al panell SIF]
C -->|no| F[Mostrar indisponibilitat + últim resum vàlid]
E --> G[Resolució només a pay.prisma.cat/sif]
```

## 10. Cobertura

**Pàgines/superfícies identificades:** 4.  
**Accions transversals amb activitat pròpia:** 2.  
**Parells ACTUAL/FINAL:** 10.  
**UI implementada:** 0 de 4 superfícies; backend API/lifecycle sí.  
**No s'ha inventat cap pantalla com a implementada.**
