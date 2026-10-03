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
