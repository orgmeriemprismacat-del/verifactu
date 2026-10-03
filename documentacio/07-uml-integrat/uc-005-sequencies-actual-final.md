# UC-005 · Diagrames de seqüència ACTUAL / FINAL

## 1. ACTUAL — consulta SIF

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant B as alumnes-factura.js
participant A as sifFactures.php
participant S as SIF read-only
O->>B: Cerca factura
B->>A: POST search/view
A->>S: consulta
S-->>A: factura + línies + pagaments + rectificacions
A-->>B: JSON
B-->>O: modal només lectura
```

## 2. ACTUAL — mutació llegada protegida

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant B as Browser
participant E as guardarDadesFactura_Factures.php / anularFactura_Factures.php
participant A as LegacyInvoiceMutationAuthorization
participant G as SifLegacyInvoiceMutationGuard
participant I as Intranet
participant L as BD llegada
O->>B: editar/anul·lar
B->>E: POST
E->>A: same-origin + permís
A-->>E: OK
E->>G: assertLegacyMutationAllowed()
G-->>E: OK o bloqueig
E->>I: mutació llegada
I->>L: UPDATE/INSERT llegat
L-->>O: resultat
```

## 3. SIF existent — rectificació manual

```mermaid
sequenceDiagram
autonumber
actor C as CLI/Caller
participant M as ManualRectificationService
participant R as ManualPaymentInvoiceRepository
participant B as ManualRectificationPayloadBuilder
participant I as InvoiceService
participant RR as RectificationRepository
participant DB as SIF DB
C->>M: issueByUuid/NumVisible(input)
M->>R: find original
R->>DB: SELECT factura
DB-->>M: original
M->>M: normalizeInput()
M->>B: forOriginalInvoice()
B-->>M: payload R
M->>I: issueInvoice(payload)
I->>DB: BEGIN + factura/línies/registre/cua/rels
I->>DB: COMMIT
I-->>M: rectificativa creada/reutilitzada
M->>RR: linkRectification()
RR->>DB: INSERT factura_rectificacio
M->>RR: markOriginalRectified()
RR->>DB: UPDATE original=RECTIFIED
M-->>C: resultat
Note over I,RR: No és una única transacció atòmica.
```

## 4. FINAL — preview + classificació + commit atòmic

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Intranet UC-005
participant A as Authorization
participant C as UC-74 Classifier
participant P as Snapshot/Preview
participant T as RectificationTransactionService
participant DB as SIF DB
participant D as DocumentService
O->>UI: proposa correcció
UI->>A: actor + scope + CSRF
A-->>UI: OK
UI->>C: original + canvi sol·licitat
C-->>UI: RECTIFICATIVA / altre cas
UI->>P: construir abans/després
P-->>O: preview, mode, imports i motiu
O->>UI: confirma
UI->>T: command amb fingerprint
T->>DB: BEGIN
T->>DB: lock original + revalidar fingerprint
T->>DB: crear/reutilitzar factura R + registre/cua
T->>DB: vincular factura_rectificacio
T->>DB: marcar original segons política
T->>DB: append audit
T->>DB: COMMIT
T->>D: assegurar document
D-->>O: CREATED/REUSED/PENDING_DOCUMENT
```
