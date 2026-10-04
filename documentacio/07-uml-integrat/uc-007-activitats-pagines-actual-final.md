# UC-007 · Activitats ACTUAL / FINAL per pàgina i apartat

## P-07-01 · `/alumnes/factura/`

### ACTUAL
```mermaid
flowchart TD
A[Carregar pantalla] --> B{hash/query UUID?}
B -->|sí| C[Cerca SIF per UUID]
B -->|no| D[Operador introdueix criteris]
D --> E[Cerca SIF]
E --> F{resultats?}
F -->|sí| G[Llistat SIF]
F -->|0 / flag explícit| H[Fallback llegat]
G --> I[Detall read-only]
I --> J{document?}
J -->|sí| K[UC-080]
J -->|no| L[Fi]
H --> M[selector/llistat llegat]
```

### FINAL
```mermaid
flowchart TD
A[Actor autenticat] --> B[Validar criteris]
B --> C[Resoldre scope server-side]
C --> D[Cercar factures]
D --> E[Projectar camps autoritzats]
E --> F[Detall read-only]
F --> G{demana bytes?}
G -->|sí| H[UC-080 reautoritza]
G -->|no| I[Fi]
```

## P-07-02 · Fitxa alumne — AL-16

### ACTUAL
```mermaid
flowchart TD
A[Clic camp factura] --> B[view_by_enrollment]
B --> C{resolució}
C -->|VIEW| D[#/uuid/UUID]
C -->|MULTIPLE| E[selector]
C -->|NO_SIF/flag| F[#/factRel/legacy]
C -->|error| G[No fallback silenciós]
```

### FINAL
```mermaid
flowchart TD
A[Clic factura] --> B[Relació autoritzada enrollment→invoice]
B --> C{1 o N UUID}
C -->|1| D[Obrir UC-007 UUID]
C -->|N| E[Selector de factures independents]
D --> F[Revalidar recurs]
E --> F
```

## P-07-02 · Fitxa alumne — AL-17 modal

### ACTUAL
```mermaid
flowchart TD
A[Clic icona factura] --> B[view_by_enrollment]
B --> C{SIF?}
C -->|sí, una| D[Render read-only]
C -->|sí, diverses| E[Selector]
C -->|no| F[Fallback llegat]
D --> G[Documents]
E --> D
G --> H[UC-080]
```

### FINAL
```mermaid
flowchart TD
A[Consultar factura de la inscripció] --> B[Resoldre factures autoritzades]
B --> C[Mostrar cada UUID independent]
C --> D[Detall immutable]
D --> E[Document autoritzat via UC-080]
```

## Fallback llegat F02–F07

```mermaid
flowchart TD
A[NO_SIF o rollout explícit] --> B[LegacyInvoiceReadContext]
B --> C[ROLS_VISUALITZAR]
C --> D[Cerca/llistat/modal]
D --> E{download?}
E -->|sí| F[POST descarregaFactura]
F --> G[SifLegacyInvoiceMutationGuard]
G --> H[generaFactura temporal]
E -->|no| I[consulta]
```

**FINAL:** aquest bloc desapareix quan totes les factures consultables siguin SIF/històriques classificades i la matriu de regressió estigui tancada.

## UC-080 · Activitat documental

```mermaid
flowchart TD
A[documentId] --> B[Autenticar actor]
B --> C[Resoldre scope]
C --> D[Carregar metadata document]
D --> E[Autoritzar factura+document]
E -->|deny| F[Audit DENIED]
E -->|allow| G[Validar estat]
G --> H[readVerified root/hash]
H -->|OK| I[Audit ALLOWED + stream]
H -->|error| J[Audit FAILED]
```


# Desglossament per apartat funcional

## F01 · Carregar pantalla i autorització

### ACTUAL
```mermaid
flowchart LR
A[/alumnes/factura/] --> B[comprovarSessio.php]
B --> C[Rellegir password/ROLS de BD]
C --> D{ROLS_VISUALITZAR?}
D -->|no| E[403 / no contingut]
D -->|sí| F[Carregar JS canònic]
F --> G[Bridge SIF deriva actor/rol de sessió]
```

### FINAL
```mermaid
flowchart LR
A[Request] --> B[Identitat vigent]
B --> C[Policy UC-007]
C -->|deny| D[403]
C -->|allow| E[Scope FULL o MINIMAL server-side]
E --> F[UI només lectura]
```

## F02 · Cercar factures

### ACTUAL
```mermaid
flowchart TD
A[Criteris UUID/número/DNI/email/factRel] --> B[POST sifFactures.php]
B --> C[Criteria whitelist + HMAC]
C --> D{Resultats SIF}
D -->|1..N| E[Render SIF]
D -->|0| F[Fallback llegat]
D -->|FEATURE_DISABLED| F
D -->|error auth/5xx| G[Error: no fallback silenciós]
F --> H[POST criteris amb PII]
H --> I[buscarUsuaris_Factures]
I --> J[DNIs/CIFs únics]
J --> K{1 o N}
K -->|1| L[Carregar factures]
K -->|N| M[Selector]
```

### FINAL
```mermaid
flowchart TD
A[Criteris validats] --> B[Read model fiscal únic]
B --> C[Query exacta/normalitzada]
C --> D[Policy per recurs]
D --> E[Resultats + indicador truncament si escau]
E --> F[Sense cerca paral·lela llegada]
```

## F03 · Selector de coincidències

### ACTUAL
```mermaid
flowchart LR
A[Múltiples DNIs/CIFs llegats] --> B[POST mostrarTaulaUsuaris2]
B --> C[Max 2000 candidats]
C --> D[Valors HTML escapats]
D --> E[Operador selecciona]
E --> F[POST llistat factures]
```

### FINAL
```mermaid
flowchart LR
A[Múltiples factures autoritzades] --> B[Selector per UUID/num_visible]
B --> C[Sense transportar llista de DNIs]
C --> D[Seleccionar factura]
D --> E[Revalidar UUID]
```

## F04 · Llistat de factures

### ACTUAL
```mermaid
flowchart TD
A[Identitat seleccionada] --> B[Factures llegades]
B --> C[Ordenar any/sèrie/seqüència]
C --> D[Escapar cel·les i títol cerca]
D --> E[Accions info / preview / anul·lar]
E --> F{Factura ja SIF?}
F -->|sí i UC007 actiu| G[Guard bloqueja camí llegat]
F -->|no| H[Permetre fallback]
```

### FINAL
```mermaid
flowchart LR
A[InvoiceQueryService] --> B[Projection FULL/MINIMAL]
B --> C[Llistat read-only]
C --> D[Info]
C --> E[Document UC-080]
```

## F05 · Consultar informació / edició llegada transitòria

### ACTUAL
```mermaid
flowchart TD
A[Info factura] --> B[LegacyInvoiceReadContext]
B --> C[Guard SIF]
C --> D[Valors escapats]
D --> E{Rol edició i factura no SIF?}
E -->|no| F[Només consulta]
E -->|sí| G[POST guardarDadesFactura]
G --> H[Same-origin + rol edició]
H --> I[FACTURA_RELACIONADA immutable]
I --> J[UPDATE camps llegats]
```

### FINAL
```mermaid
flowchart TD
A[Detall fiscal] --> B[Només lectura]
B --> C{Cal corregir dades fiscals?}
C -->|no| D[Fi]
C -->|sí| E[Cas d'ús de rectificació/correcció]
E --> F[Mai UPDATE directe de factura emesa]
```

## F06 · Previsualitzar document

### ACTUAL
```mermaid
flowchart TD
A[Preview] --> B{SIF?}
B -->|sí| C[Metadata document]
C --> D[UC-080]
B -->|no| E[generaFactura des de BD llegada]
E --> F[Escapar tots els valors]
F --> G[HTML preview]
```

### FINAL
```mermaid
flowchart LR
A[document_id] --> B[UC-080]
B --> C[Storage privat]
C --> D[Hash verificat]
D --> E[Preview/stream autoritzat]
```

## F07 · Descarregar factura

### ACTUAL
```mermaid
flowchart TD
A[Clic descarregar] --> B{SIF?}
B -->|sí| C[POST sifDocument.php]
C --> D[HMAC + scope FULL]
D --> E[Hash/root/audit]
B -->|no| F[POST descarregaFactura.php]
F --> G[Same-origin + ROLS_VISUALITZAR]
G --> H[Guard: si ja és SIF, 409]
H --> I[generaFactura true,false]
I --> J[PDF temporal]
J --> K[Sense UPDATE generada]
```

### FINAL
```mermaid
flowchart LR
A[Descarregar] --> B[UC-080]
B --> C[Reautoritzar document]
C --> D[Audit ALLOWED/DENIED/FAILED]
D --> E[Bytes immutables]
```

## AL-18 · Descàrrega des de la fitxa alumne

### ACTUAL
```mermaid
flowchart TD
A[Modal factura alumne] --> B{Resolució SIF}
B -->|VIEW| C[document_id]
C --> D[UC-080]
B -->|NO_SIF| E[Fallback modal llegat]
E --> F[POST descarregaFactura]
F --> G[Mateix contracte F07-L]
```

### FINAL
```mermaid
flowchart LR
A[Fitxa alumne] --> B[UUID factura autoritzada]
B --> C[document_id]
C --> D[UC-080]
```
