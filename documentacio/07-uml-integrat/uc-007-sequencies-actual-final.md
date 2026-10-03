# UC-007 · Diagrames de seqüència ACTUAL / FINAL

## S1. Cerca a `/alumnes/factura/` — ACTUAL revalidat

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as alumnes-factura.js
participant B as sifFactures.php
participant C as SifInternalApiClient
participant API as /api/factures/query.php
participant A as InternalApiAuthenticator
participant G as InvoiceQueryGateway
participant Q as InvoiceQueryService
participant R as InvoiceReadRepository
O->>UI: criteris
UI->>B: POST action=search
B->>B: sessió + rol vigent + same-origin
B->>C: searchInvoices(actor,roles,criteria)
C->>API: POST signat HMAC
API->>A: validar timestamp/request/body/actor/roles
A-->>API: actor autenticat
API->>G: search(actor,criteria)
G->>G: resolve role → FULL/MINIMAL
G->>Q: search()
Q->>R: SELECT exactes
R-->>Q: candidates
Q-->>G: projeccions autoritzades
G-->>API: results
API-->>C: JSON no-store
C-->>B: JSON
B-->>UI: resultats
alt zero resultats o FEATURE_DISABLED explícit
 UI->>UI: fallback llegat
end
```

## S2. Detall factura SIF

```mermaid
sequenceDiagram
autonumber
actor O
participant UI
participant B as sifFactures.php
participant API as query.php
participant Q as InvoiceQueryService
participant P as VisibilityPolicy
participant R as InvoiceReadRepository
O->>UI: Informació(UUID)
UI->>B: POST view UUID
B->>API: POST HMAC
API->>Q: view(actor,UUID)
Q->>R: factura + relacions
Q->>P: canView/project
P-->>Q: FULL/MINIMAL o deny
Q->>R: línies/pagaments/rectificacions/fiscal/doc metadata
Q-->>UI: read model
Note over Q,R: cap UPDATE/INSERT fiscal o econòmic
```

## S3. AL-16 — camp factura de fitxa alumne

```mermaid
sequenceDiagram
autonumber
actor O
participant ST as alumnes-mostrar-alumne.js
participant B as sifFactures.php
participant INV as /alumnes/factura/
O->>ST: clic #factura-insc
ST->>B: POST view_by_enrollment(id_insc)
alt una factura SIF
 B-->>ST: VIEW + UUID
 ST->>INV: #/uuid/UUID
 INV->>INV: search UUID i mostrar detall
else múltiples
 B-->>ST: MULTIPLE
 ST->>ST: selector/modal
else NO_SIF o flag
 B-->>ST: NO_SIF/FEATURE_DISABLED
 ST->>INV: #/factRel/legacy
end
```

## S4. AL-17 — modal des d'inscripció

```mermaid
sequenceDiagram
autonumber
actor O
participant ST as alumnes-mostrar-alumne.js
participant B as sifFactures.php
participant UI as modalConsultaFactura
O->>ST: clic .cns-factura
ST->>B: POST view_by_enrollment
alt VIEW
 B-->>ST: factura SIF
 ST->>UI: render read-only
else MULTIPLE
 B-->>ST: candidates
 ST->>UI: selector
else NO_SIF/flag
 ST->>ST: mostrarModalConsultaFacturaLlegat
end
```

## S5. UC-080 — document

```mermaid
sequenceDiagram
autonumber
actor O
participant UI
participant IB as sifDocument.php
participant IC as SifInternalDocumentClient
participant API as documents/download.php
participant D as InvoiceDocumentAccessService
participant P as DocumentAuthorizationPolicy
participant FS as PrivateDocumentStore
participant AU as fiscal_document_access
O->>UI: Descarregar documentId
UI->>IB: POST JSON + same-origin
IB->>IC: download(actor,roles,documentId)
IC->>API: POST HMAC
API->>D: download(actor,id)
D->>P: canDownload()
alt denied
 D->>AU: DENIED
 D-->>UI: 403
else allowed
 D->>FS: readVerified(path,hash)
 alt íntegre
  FS-->>D: bytes
  D->>AU: ALLOWED
  D-->>UI: bytes + headers no-store
 else absent/hash/path
  D->>AU: FAILED
  D-->>UI: 403/409/503
 end
end
```

## S6. FINAL

El FINAL conserva S1–S5 eliminant el fallback llegat i substituint els adaptadors interns/externs segons actor. La factura i el document continuen en fronteres separades.
