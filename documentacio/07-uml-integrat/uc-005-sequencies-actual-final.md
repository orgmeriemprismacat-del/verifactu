# UC-005 · Diagrames de seqüència ACTUAL / FINAL

**Tall:** 2026-10-03 · branca `audit/uc-005-2026-10-03`

## 1. ACTUAL — consulta SIF intranet

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant B as alumnes-factura-sif.js
participant P as Proxy consulta intranet
participant Q as SIF query read-only
O->>B: Cercar / obrir factura
B->>P: POST search/view
P->>Q: petició interna signada
Q-->>P: factura + línies + pagaments + rectificacions
P-->>B: JSON
B-->>O: modal SIF només lectura
```

## 2. ACTUAL — backend UC-005 preview

```mermaid
sequenceDiagram
autonumber
actor C as Caller intern
participant API as POST /api/factures/rectify.php
participant H as InternalApiAuthenticator
participant R as InternalRectificationScopeResolver
participant CMD as RectificationCommandService
participant G as FiscalCorrectionDecisionGuard
participant B as ManualRectificationPayloadBuilder
participant A as SifAuditEventRepository
participant DB as SIF DB
C->>API: action=preview + correction + classification
API->>H: HMAC + timestamp + request_id + replay
H->>DB: claim internal_api_request
H-->>API: actor autenticat
API->>R: resolve(actor)
R-->>API: scope preview/issue + rol autoritzat
API->>CMD: preview(...)
CMD->>G: exigir source_uc=UC-74 + RECTIFICATION + mode coherent
G-->>CMD: decisió normalitzada
CMD->>B: construir payload R fail-closed
B-->>CMD: billing + totals + lines
CMD->>CMD: fingerprint snapshot fiscal immutable
CMD->>A: RECTIFICATION_PREVIEW/SUCCEEDED
A->>DB: INSERT sif_audit_event
CMD-->>C: preview + fingerprint
```

## 3. ACTUAL — confirmació UC-005 atòmica

```mermaid
sequenceDiagram
autonumber
actor C as Caller intern
participant CMD as RectificationCommandService
participant M as ManualRectificationService
participant I as InvoiceService
participant R as ManualPaymentInvoiceRepository
participant RR as RectificationRepository
participant OA as OperationalEventRepository
participant SA as SifAuditEventRepository
participant DB as SIF DB
C->>CMD: confirm(expected_fingerprint)
CMD->>CMD: recomputar fingerprint previ
CMD->>SA: RECTIFICATION_CONFIRM/REQUESTED
SA->>DB: INSERT audit requested
CMD->>M: issueByUuid(..., beforeCommit)
M->>I: issueInvoice(payload, beforeCommit)
I->>DB: BEGIN
I->>DB: crear/reutilitzar R + línies + registre + cua + rels
M->>R: findByUuid(original, FOR UPDATE)
R->>DB: SELECT original FOR UPDATE
M->>M: revalidar snapshot original
M->>RR: linkRectification()
RR->>DB: INSERT/REUSE factura_rectificacio
M->>RR: markOriginalRectified()
RR->>DB: UPDATE original=RECTIFIED
M->>CMD: callback abans del COMMIT
CMD->>CMD: reconstruir payload i fingerprint sobre original locked
CMD->>OA: COMMITTED o REUSED
OA->>DB: INSERT operational_event
CMD->>SA: SUCCEEDED o REUSED
SA->>DB: INSERT sif_audit_event
I->>DB: COMMIT
CMD-->>C: UUID R + estat + audit ids
Note over I,SA: Qualsevol error en vincle, lock, fingerprint o audit terminal provoca ROLLBACK de la R.
```

## 4. ACTUAL — reintent idempotent

```mermaid
sequenceDiagram
autonumber
actor C as Caller intern
participant CMD as RectificationCommandService
participant I as InvoiceService
participant DB as SIF DB
C->>CMD: nou preview equivalent
CMD->>CMD: fingerprint sobre camps fiscals immutables
CMD-->>C: mateix fingerprint encara que original sigui RECTIFIED
C->>CMD: confirm
CMD->>I: issueInvoice mateixa idempotency key
I->>DB: lock factura R existent + assert payload hash
I-->>CMD: idempotency_reused=true
CMD->>DB: revalidar/vincular idempotentment + audit REUSED
CMD-->>C: mateix UUID R
```

## 5. FINAL — intranet segura

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as alumnes-factura-sif.js
participant PX as Proxy intranet UC-005
participant AUTH as Sessió + permís + CSRF
participant CL as UC-74 Classifier
participant API as SIF rectify.php
participant CMD as RectificationCommandService
participant DOC as Document/AEAT
O->>UI: proposar correcció
UI->>PX: dades proposades + CSRF
PX->>AUTH: validar sessió, rol i same-origin
AUTH-->>PX: OK
PX->>CL: classificar amb estat fiscal real
CL-->>PX: RECTIFICATION / SUBSANATION / ANNULMENT / NONE
alt RECTIFICATION
  PX->>API: POST intern signat preview
  API->>CMD: preview
  CMD-->>PX: before/after + fingerprint
  PX-->>UI: mostrar preview
  O->>UI: confirmar
  UI->>PX: confirm + CSRF + fingerprint
  PX->>API: POST intern signat confirm
  API->>CMD: confirm
  CMD-->>PX: CREATED/REUSED
  PX->>DOC: document + cua/estat AEAT
  DOC-->>UI: resultat final
else altra decisió
  PX-->>UI: derivar al UC corresponent
end
```

## 6. AEAT rectificativa — frontera pendent

```mermaid
sequenceDiagram
autonumber
participant U74 as UC-74 fiscal
participant MAP as AeatRectificationMapper [PENDENT]
participant OR as factura_registres original
participant RF as RecordFactory
participant X as XmlCodec
U74->>MAP: R1-R5 + S/I + desglose corregit
MAP->>OR: carregar PAYLOAD_JSON.aeat original congelat
OR-->>MAP: identitat i snapshot fiscal original
MAP->>MAP: FacturasRectificadas
alt TipoRectificativa=S
 MAP->>MAP: ImporteRectificacion obligatori
else TipoRectificativa=I
 MAP->>MAP: sense ImporteRectificacion
end
MAP->>RF: freeze RegistroAlta rectificatiu
RF->>RF: validar semàntica R1-R5 / S-I
RF->>X: validar XSD local oficial
X-->>MAP: snapshot AEAT immutable
```

**Estat:** `RecordFactory` ja valida S/I i la presència/absència d'`ImporteRectificacion`; falta el mapper que construeixi els camps des de snapshot original i classificació fiscal fiable.

## 7. Garanties i pendents

- **Implementat:** HMAC/replay, rol server-side, preview/confirm, fingerprint doble, `FOR UPDATE`, idempotència, audit terminal dins del COMMIT.
- **No confiar en el navegador:** el client no pot declarar unilateralment `source_uc=UC-74`; la classificació FINAL ha de néixer al servidor.
- **Implementat al protocol AEAT:** validació local de `TipoRectificativa=S|I`; S exigeix `ImporteRectificacion`, I el rebutja.
- **Pendent:** classificador UC-74 executable, mapper AEAT rectificatiu complet, proxy sessió/CSRF, document E2E, concurrència real i preproducció.
