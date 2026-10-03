# UC-005 · Activitats per pàgina/apartat ACTUAL / FINAL

**Tall:** 2026-10-03 · branca `audit/uc-005-2026-10-03`

## P01 — Cerca i consulta de factura

### ACTUAL
```mermaid
flowchart TD
A[Obrir alumnes/factura] --> B[Introduir criteri]
B --> C{SIF query habilitada?}
C -- sí --> D[POST sifFactures search/view]
D --> E{resultats?}
E -- sí --> F[Mostrar factura SIF read-only]
E -- no --> G[Fallback llegat]
C -- no --> G
```

### FINAL
```mermaid
flowchart TD
A[Consultar factura] --> B[Carregar snapshot SIF]
B --> C[Mostrar estat fiscal/econòmic/documental]
C --> D{rol pot proposar correcció?}
D -- no --> E[Només lectura]
D -- sí --> F[Acció Rectificar]
F --> G[Proxy intranet segur]
```

## P02 — Proposar canvi sobre factura emesa

### ACTUAL
```mermaid
flowchart TD
A[Llapis edició llegada] --> B[Modificar camps]
B --> C[POST guardarDadesFactura_Factures]
C --> D[Auth + same-origin + guard]
D --> E[UPDATE llegat si permès]
```

### FINAL
```mermaid
flowchart TD
A[Sol·licitar canvi] --> B[Capturar motiu i dades proposades]
B --> C[Servidor rellegeix factura immutable]
C --> D[UC-74 classifica]
D --> E{decisió}
E -- RECTIFICATION --> F[UC-005 preview]
E -- SUBSANATION --> G[UC-31]
E -- ANNULMENT --> H[UC-30]
E -- NONE --> I[No mutació fiscal]
```

**Regla:** el navegador no pot autoassignar `source_uc=UC-74`; la decisió fiscal ha de provenir del servidor.

## P03 — Preview UC-005

### BACKEND IMPLEMENTAT
```mermaid
flowchart TD
A[POST intern signat action=preview] --> B[HMAC + replay guard]
B --> C[Resoldre rol de rectificació]
C --> D[Guard UC-74]
D --> E[Builder fail-closed]
E --> F[Fingerprint fiscal immutable]
F --> G[Audit PREVIEW/SUCCEEDED]
G --> H[Retornar billing/totals/lines + fingerprint]
```

### INTRANET PENDENT
```mermaid
flowchart TD
A[Modal de correcció] --> B[POST proxy amb sessió + CSRF]
B --> C[Classificador server-side]
C --> D[Cridar API SIF signada]
D --> E[Mostrar abans/després]
E --> F[Confirmació explícita]
```

## P04 — Rectificació per diferències

```mermaid
flowchart TD
A[UC-74 decideix DIFERENCIES] --> B{Original subjecte a IVA?}
B -- no/exempt --> C[Preservar règim + causa exempció]
B -- sí --> D[Exigir bloc fiscal complet]
C --> E[Preview]
D --> E
E --> F[Confirmar fingerprint]
F --> G[Emetre R atòmicament]
G --> H[TipoRectificativa AEAT = I quan mapper estigui actiu]
```

**Implementat:** el builder no torna a assumir que `amount = base = total` per factures amb IVA.  
**Pendent:** mapper AEAT complet i classificador R1-R5.

## P05 — Rectificació per substitució

```mermaid
flowchart TD
A[UC-74 decideix SUBSTITUCIO] --> B[Construir snapshot corregit]
B --> C[Permetre billing nou]
C --> D[Validar fiscalitat]
D --> E[Preview + fingerprint]
E --> F[Confirmar]
F --> G[Emetre R sense modificar original]
G --> H[TipoRectificativa AEAT = S]
H --> I[ImporteRectificacion obligatori]
```

**Implementat:** la nova R pot congelar nom/CIF/adreça corregits; `DIFERENCIES` rebutja mutacions de receptor.  
**Pendent:** obtenir `ImporteRectificacion` i identitat original des del snapshot AEAT congelat, no de dades vives.

## P06 — Confirmació i commit

```mermaid
flowchart TD
A[Confirm expected_fingerprint] --> B[Recalcular fingerprint]
B --> C{coincideix?}
C -- no --> D[409 + nou preview]
C -- sí --> E[BEGIN]
E --> F[Crear/reutilitzar factura R]
F --> G[FOR UPDATE original]
G --> H[Revalidar snapshot]
H --> I[INSERT/REUSE factura_rectificacio]
I --> J[Original -> RECTIFIED]
J --> K[Audit + operational_event]
K --> L[COMMIT]
L --> M[CREATED o REUSED]
```

**Garantia:** si falla vincle, lock, revalidació o auditoria terminal, la factura R nova no es confirma.

## P07 — Reintent idempotent

```mermaid
flowchart TD
A[Repetir la mateixa correcció] --> B[Nou preview]
B --> C[Fingerprint immutable igual]
C --> D[Confirm]
D --> E[InvoiceService troba idempotency key]
E --> F[Validar payload hash]
F --> G[Reutilitzar mateix UUID R]
G --> H[Audit REUSED]
```

El canvi esperat de l'original `ISSUED -> RECTIFIED` no forma part del fingerprint immutable i no força una segona factura R.

## P08 — “Anul·lar” factura llegada

### ACTUAL
```mermaid
flowchart TD
A[Botó anul·lar llegat] --> B[Modal import/data/obs]
B --> C[POST anularFactura_Factures]
C --> D[Auth + guard]
D --> E[Intranet::anularFactura]
```

### FINAL
```mermaid
flowchart TD
A[Acció de correcció] --> B[UC-74 server-side]
B --> C{què correspon?}
C -- Rectificativa --> D[UC-005]
C -- Registre improcedent --> E[UC-30]
C -- Subsanació --> F[UC-31]
C -- Baixa --> G[UC-27/72]
C -- Devolució --> H[UC-28]
```

El text històric del botó no determina la figura fiscal.

## P09 — Resultat, document i AEAT

```mermaid
flowchart TD
A[Commit UC-005] --> B[Mostrar factura R + original]
B --> C[Mostrar relació + motiu + audit]
C --> D[Generar/assegurar document]
D --> E{AEAT rectificativa preparada?}
E -- no --> F[No declarar flux productiu VERI*FACTU]
E -- sí --> G[Validar XSD + cua AEAT]
G --> H[Mostrar estat AEAT/document]
```

## P10 — Estat per capa

| Capa | Estat |
|---|---|
| Consulta SIF intranet | **IMPLEMENTADA / read-only** |
| Mutació llegada protegida | **IMPLEMENTADA**, però no és UC-005 |
| Builder rectificatiu local | **IMPLEMENTAT / fail-closed** |
| SUBSTITUCIO amb receptor corregit | **IMPLEMENTADA al backend** |
| Atomicitat R + vincle + original | **IMPLEMENTADA** |
| Fingerprint + reintent | **IMPLEMENTAT** |
| HMAC/replay + rol backend | **IMPLEMENTAT** |
| Audit SIF/operacional | **IMPLEMENTAT** |
| Guard de decisió UC-74 | **IMPLEMENTAT** |
| Classificador UC-74 genèric | **PENDENT / BLOQUEJANT** |
| Proxy intranet sessió+CSRF UC-005 | **PENDENT** |
| `RecordFactory` AEAT S/I | **IMPLEMENTAT** |
| Mapper AEAT rectificatiu R1-R5 | **PENDENT / BLOQUEJANT** |
| Suite MySQL UC-005 | **DEFINIDA; CI EN CUA** |
| E2E/preproducció | **PENDENT** |
