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

## S6. F02–F04 · Fallback llegat de cerca, selector i llistat — ACTUAL

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as alumnes-factura.js
participant S as consultaUsuarisFacturaRelacionada.php
participant I as Intranet::buscarUsuaris_Factures
participant T as mostrarTaulaUsuaris2.php
participant L as mostrarTotesFacturesUsuari_Factures.php
O->>UI: criteris sense resultat SIF
UI->>S: POST DNI/email/factRel/factNum
S->>S: sessió + ROLS_VISUALITZAR + same-origin
S->>I: criteris acotats
I-->>S: #DNI|CIF...
S-->>UI: candidats
alt un candidat
 UI->>L: POST DNI/CIF + títol
 L->>L: sessió + same-origin
 L-->>UI: factures escapades/ordenades
else múltiples
 UI->>T: POST llista candidats
 T->>T: max 2000 + sessió + same-origin
 T-->>UI: selector escapat
 O->>UI: seleccionar
 UI->>L: POST candidat
 L-->>UI: llistat
end
```

### FINAL F02–F04

```mermaid
sequenceDiagram
actor O
participant UI
participant API as InvoiceQueryPort
participant P as InvoiceVisibilityPolicy
participant R as InvoiceReadRepository
O->>UI: criteris
UI->>API: search(criteria)
API->>R: query fiscal única
R-->>API: candidates
API->>P: project per actor
P-->>API: resultats autoritzats
API-->>UI: factures
Note over UI,R: sense transportar DNI list ni consultar factures llegades
```

## S7. F05 · Detall i edició llegada transitòria — ACTUAL

```mermaid
sequenceDiagram
autonumber
actor O
participant UI as alumnes-factura.js
participant D as mostraModalConsultaInformacio_Factures.php
participant G as SifLegacyInvoiceMutationGuard
participant I as Intranet.php
participant W as guardarDadesFactura_Factures.php
O->>UI: Informació legacy ID
UI->>D: GET id
D->>D: sessió + ROLS_VISUALITZAR
D->>G: verificar que no és factura SIF
G-->>D: allowed
D->>I: modalConsultaInformacio_Factures
I-->>UI: HTML amb valors escapats
alt operador edita
 O->>UI: guardar
 UI->>W: POST camps
 W->>W: same-origin + ROLS_EDITAR
 W->>G: assertLegacyMutationAllowed
 W->>W: reobtenir FACTURA_RELACIONADA de BD
 W->>I: UPDATE camps llegats
 I-->>UI: OK
end
```

### FINAL F05

```mermaid
sequenceDiagram
actor O
participant Q as InvoiceQueryService
participant C as Correction/Rectification UC
O->>Q: consultar UUID
Q-->>O: detall immutable
alt cal corregir informació fiscal
 O->>C: iniciar cas de correcció
 C-->>O: nova evidència/rectificació
end
```

## S8. F06–F07 · Preview i descàrrega llegada — ACTUAL

```mermaid
sequenceDiagram
autonumber
actor O
participant UI as alumnes-factura.js
participant P as mostraModalPrevFactura_Factures.php
participant G as SifLegacyInvoiceMutationGuard
participant I as Intranet::generaFactura
participant D as descarregaFactura.php
O->>UI: preview legacy
UI->>P: GET legacy invoice id
P->>G: bloquejar si ja és SIF
P->>I: generaFactura(factRel,false)
I->>I: escapar num/receptor/conceptes/import
I-->>UI: HTML preview
O->>UI: descarregar
UI->>D: POST factRel
D->>D: sessió + same-origin + ROLS_VISUALITZAR
D->>G: bloquejar si ja és SIF
D->>I: generaFactura(factRel,true,false)
I->>I: render PDF temporal
Note over I: no UPDATE de generada
I-->>D: filename segur
D-->>UI: nom temporal
UI->>UI: iniciar descàrrega
```

### FINAL F06–F07

```mermaid
sequenceDiagram
actor O
participant UI
participant D as UC-080 DocumentAccessPort
participant P as DocumentAuthorizationPolicy
participant S as PrivateDocumentStore
participant A as AccessAuditRepository
O->>UI: preview/download
UI->>D: document_id
D->>P: authorize
P-->>D: FULL/deny
D->>S: readVerified
S-->>D: bytes
D->>A: ALLOWED/DENIED/FAILED
D-->>UI: bytes immutables
```

## S9. AL-18 · Descàrrega des de fitxa alumne

```mermaid
sequenceDiagram
autonumber
actor O
participant ST as alumnes-mostrar-alumne.js
participant B as sifFactures.php
participant DOC as sifDocument.php
participant LEG as fallback llegat
O->>ST: obrir factura d'inscripció
ST->>B: view_by_enrollment
alt SIF
 B-->>ST: VIEW/MULTIPLE
 ST->>DOC: POST document_id
 DOC-->>ST: bytes UC-080
else NO_SIF
 B-->>ST: NO_SIF
 ST->>LEG: modal llegat
 O->>LEG: descarregar
 LEG->>LEG: F07 read-only
end
```

### FINAL AL-18

AL-18 reutilitza exclusivament S4 + S5: inscripció → UUID autoritzat → document_id → UC-080.

## S10. FINAL GLOBAL

El FINAL conserva la frontera de consulta SIF i UC-080, elimina S6/S7/S8 llegats, i manté factura i document en serveis separats. Qualsevol correcció fiscal surt d'UC-007 cap al cas d'ús de rectificació corresponent.
