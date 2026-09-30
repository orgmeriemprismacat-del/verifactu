# UC-007 / UC-080 · Desplegament de consulta i document SIF

**Estat:** implementació funcional en curs; proves ajornades per una fase posterior.  
**Objectiu:** activar consulta read-only de factures SIF des de la intranet i streaming privat de documents sense regeneració llegada.

## 1. Peces implementades

### SIF
- `InvoiceReadRepository`
- `InvoiceQueryService`
- `InvoiceQueryCriteriaValidator`
- `InvoiceVisibilityPolicyInterface`
- `ResolvedInvoiceVisibilityPolicy`
- `InternalInvoiceScopeResolver`
- `InvoiceQueryGateway`
- `InternalApiAuthenticator`
- `InternalApiRequestRepository`
- `InvoiceDocumentAccessService`
- `ResolvedDocumentAuthorizationPolicy`
- `PrivateDocumentStore`
- `FiscalDocumentAccessRepository`
- `POST /api/factures/query.php`
- `POST /api/documents/download.php`

### Intranet
- `LegacyInvoiceReadContext`
- `LegacyInvoiceReadAuthorization`
- `SifAuthenticatedActor`
- `SifInternalApiClient`
- `SifInternalDocumentClient`
- `SifLegacyInvoiceMutationGuard`
- `ajax/alumnes/sifFactures.php`
- `ajax/alumnes/sifDocument.php`
- integració UI a `alumnes-factura.js`
- integració AL-16/17/18 a `alumnes-mostrar-alumne.js`

## 2. Migració necessària abans d'activar

Aplicar la migració:

`2026_09_29_000009_add_internal_api_replay_guard.sql`

Crea `internal_api_request`, que impedeix reutilitzar el mateix `REQUEST_ID` signat.

No activar `SIF_UC007_QUERY_ENABLED` ni `SIF_UC080_DOCUMENT_ENABLED` abans que aquesta migració estigui aplicada al mateix entorn del SIF.

## 3. Variables d'entorn del SIF

Configurar al servidor SIF, sense guardar secrets al repositori:

```text
SIF_INTERNAL_API_KEY_ID=<identificador no secret>
SIF_INTERNAL_API_SECRET=<secret compartit fort>
SIF_INTERNAL_API_MAX_SKEW=300

SIF_INTERNAL_API_SIGNED_PATH=/api/factures/query.php
SIF_INTERNAL_DOCUMENT_SIGNED_PATH=/api/documents/download.php

SIF_INVOICE_FULL_READ_ROLES=<rol1,rol2,...>
SIF_INVOICE_MINIMAL_READ_ROLES=<rols de consulta parcial si s'utilitzen>
SIF_INVOICE_QUERY_MAX_RESULTS=50

SIF_DOCUMENT_ROOT=<directori privat fora del webroot>
SIF_DOCUMENT_MAX_BYTES=20971520
```

`SIF_DOCUMENT_ROOT` ha d'existir físicament i no ha de ser servible directament pel servidor web.

## 4. Variables d'entorn de la intranet

```text
SIF_INTERNAL_API_URL=https://pay.prisma.cat/api/factures/query.php
SIF_INTERNAL_DOCUMENT_API_URL=https://pay.prisma.cat/api/documents/download.php

SIF_INTERNAL_API_KEY_ID=<mateix key id del SIF>
SIF_INTERNAL_API_SECRET=<mateix secret compartit>
SIF_INTERNAL_API_SIGNED_PATH=/api/factures/query.php
SIF_INTERNAL_DOCUMENT_SIGNED_PATH=/api/documents/download.php

SIF_UC007_QUERY_ENABLED=0
SIF_UC080_DOCUMENT_ENABLED=0
SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=0

INTRANET_ALLOWED_ORIGINS=https://intranet.prisma.cat
```

El secret només viu al servidor de la intranet i al servidor SIF. No s'envia al navegador.

## 5. Ordre d'activació recomanat

1. Aplicar migracions.
2. Configurar secret HMAC i rols al SIF.
3. Configurar URLs/secrets a la intranet.
4. Configurar storage privat i documents existents.
5. Activar `SIF_UC007_QUERY_ENABLED=1`.
6. Verificar manualment que les factures SIF es mostren com **SIF · només lectura**.
7. Activar `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1`.
8. Quan storage/hash estiguin preparats, activar `SIF_UC080_DOCUMENT_ENABLED=1`.
9. Mantenir el fallback llegat només per factures no migrades.

## 6. Comportament de fallback

### Factura trobada al SIF
- consulta per UUID;
- no s'edita al llegat;
- no es previsualitza amb `generaFactura()`;
- documents es serveixen via UC-080;
- original i rectificativa es mostren separats.

### Cap factura SIF trobada
- es manté temporalment la consulta llegada;
- la mutació llegada només és possible si el guard comprova que la relació no està governada pel SIF.

### SIF indisponible
- no s'ha de suposar automàticament que la factura és llegada;
- les mutacions protegides fallen tancat quan `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1`.

## 7. Seguretat

- API interna només per POST.
- HMAC SHA-256 sobre mètode, path, timestamp, request id, actor, rols i hash del cos.
- finestra temporal configurable;
- `REQUEST_ID` únic anti-replay;
- rols del navegador no són autoritat: la intranet signa els rols de la sessió revalidada;
- `InternalInvoiceScopeResolver` torna a filtrar segons rols configurats al SIF;
- consultes sensibles amb `no-store`;
- document: path privat, `realpath`, root fix, mida màxima i SHA-256;
- document: denegacions i lliuraments a `fiscal_document_access`;
- proxy intranet exigeix same-origin/AJAX.

## 8. Proves ajornades per més endavant

La llista canònica de proves queda a:

**[UC-007 · Proves pendents d'implementació](../07-uml-integrat/03-proves-pendents-uc-007-implementacio.md)**

No duplicar checklists en aquesta guia. Cap prova es considera executada fins disposar d'evidència posterior de local/preproducció.

## 9. Criteri per retirar el fallback llegat

No retirar-lo fins que:
- totes les factures actives consultables estiguin al SIF o classificades com històriques;
- documents originals/reconstruïts estiguin diferenciats;
- UC-080 tingui storage privat estable;
- la matriu de proves ajornada estigui executada;
- no hi hagi rutes operatives que depenguin de `generaFactura()` per una factura SIF.
