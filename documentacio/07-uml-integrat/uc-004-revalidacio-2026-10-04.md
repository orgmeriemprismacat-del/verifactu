# UC-004 · Revalidació exhaustiva de repositori · 2026-10-04

## 1. Tall auditat

- **Cas d'ús:** UC-004 — Emetre factura abans de cobrar.
- **Base real revisada:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`.
- **Branca de correcció:** `audit/uc-004-revalidacio-v2-2026-10-04`.
- **PR històric integrat de referència:** #70.
- **PR històric NO integrat:** #134, divergit respecte del main i amb checks globals fallits.
- **Criteri:** el que no és al main o no queda reaplicat explícitament en aquesta branca no es considera implementat.

Aquesta acta tanca l'auditoria de repositori i documentació del tall indicat. No certifica desplegament, preproducció, producció ni acceptació AEAT.

## 2. Resposta: tenim totes les fitxes, diagrames i codi?

### Documentació requerida

| Peça | Fitxer | Estat |
| --- | --- | --- |
| Fitxa funcional | `../06-fitxes-funcionals/uc-004.md` | **COMPLETA · REVALIDADA** |
| Cas d'ús ACTUAL/FINAL | `uc-004-cas-us-actual-final.md` | **COMPLET** |
| Classes ACTUAL/FINAL | `uc-004-classes-actual-final.md` | **COMPLET** |
| Seqüències ACTUAL/FINAL | `uc-004-sequencies-actual-final.md` | **COMPLET** |
| Activitats per pàgina/apartat | `uc-004-activitats-actual-final.md` | **COMPLET · 14 DIAGRAMES** |
| Auditoria/traçabilitat/mancances | `uc-004-auditoria-tracabilitat-mancances.md` | **COMPLET · ACTUALITZAT** |
| Inventari PHP/JS | `uc-004-inventari-artefactes.md` | **COMPLET · ACTUALITZAT** |
| Síntesi integrada | `uc-004-emetre-factura-abans-cobrar.md` | **RECONCILIADA** |
| Acta de revalidació | aquest fitxer | **CREADA** |

### Activitats cobertes

Hi ha ACTUAL/FINAL per:

- A004-P00 — pàgina completa;
- A004-P01 — accés, càrrega i permisos;
- A004-P02 — cerca i selecció d'inscripcions;
- A004-P03 — selecció, imports i conceptes;
- A004-P04 — receptor i dades de factura;
- A004-P05 — emissió abans de cobrar;
- A004-P06 — resultat, previsualització i document.

No falta cap tipus de diagrama demanat.

## 3. Codi PHP/JS real contrastat

### Intranet

- `alumnes-genera-factura-abans-pagar.php`;
- `js/alumnes-genera-factura-abans-pagar.js`;
- `SifInvoiceBeforePaymentAccess.php`;
- `SifInternalApiClient.php`;
- `ajax/alumnes/sifFacturaAbansPagar.php`;
- `ajax/alumnes/sifFacturaAbansPagarToken.php`;
- `ajax/alumnes/sifFacturaAbansPagarEntitats.php`;
- antic `ajax/alumnes/generaFacturaElectronica_Factures.php`;
- superfícies llegades de preview/descàrrega i `Intranet.php`.

### SIF

- `public/api/factures/before-payment.php`;
- `InvoiceBeforePaymentCommandService`;
- `InvoiceBeforePaymentLegacyPreparationService`;
- `InvoiceBeforePaymentSelectionRepository`;
- `InvoiceBeforePaymentBillingPartyRepository`;
- `InvoiceBeforePaymentServerPayloadAssembler`;
- `InvoiceBeforePaymentPayloadBuilder`;
- `InvoiceBeforePaymentService`;
- `InvoiceService`;
- `InvoiceBeforePaymentCoverageRepository`;
- `InternalApiAuthenticator`;
- `InternalInvoiceBeforePaymentScopeResolver`;
- `InternalApiRequestRepository`;
- `OperationalEventRepository` i `SifAuditEventRepository`;
- migració de coverage i suites UC-004.

## 4. Flux executable real després del hardening d'aquesta branca

```text
browser UC-004
  -> sessió/permís intranet
  -> CSRF
  -> sifFacturaAbansPagar.php
  -> SifInternalApiClient
       contract_version=UC004-V1
       HMAC + timestamp + request_id + actor + roles
  -> /api/factures/before-payment.php
  -> anti-replay + rol SIF
  -> InvoiceBeforePaymentCommandService
  -> rellegir inscripcions + receptor + imports
  -> preview + fingerprint + pre-check coverage
  -> confirm + nova rellectura
  -> InvoiceBeforePaymentService
  -> InvoiceService
  -> idempotència + seqüència + factura + línies
     + registre/hash/cadena + fiscal_queue + fact_rels
     + invoice_before_payment_coverage
     + operational_event + sif_audit_event
  -> COMMIT
  -> factura ISSUED / cobrament PENDING
```

El navegador no és autoritat fiscal dels imports ni del receptor.

## 5. Canvis aplicats en aquesta revalidació

1. **Retirat el mutador fiscal llegat.** `generaFacturaElectronica_Factures.php` retorna `410 Gone` abans de carregar dependències.
2. **Contracte intern versionat.** Preview i confirm envien `contract_version=UC004-V1`; l'endpoint rebutja versions absents/desconegudes.
3. **Coverage al preview.** El command consulta `invoice_before_payment_coverage` abans de mostrar una confirmació; el guard UNIQUE transaccional continua sent l'autoritat davant curses.
4. **Prova d'integració nova.** `InvoiceBeforePaymentCommandServiceTest` cobreix preview bloquejat després del claim i reintent idempotent del confirm.
5. **Static regression check reforçat.** Verifica 410, absència de writer llegat, contracte V1 i preview coverage.
6. **Documentació ACTUAL/FINAL reconciliada** amb el codi que existeix realment al tall 04/10.

## 6. Estat per responsabilitat

| Capacitat | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Sessió/permís intranet | Sí | **Sí** | estàtic/històric CI | E2E entorn |
| CSRF | Sí | **Sí** | estàtic/històric CI | E2E |
| HMAC + anti-replay | Sí | **Sí** | tests/contracte | secrets/config real |
| Contracte `UC004-V1` | Sí | **Sí en aquesta branca** | static test nou | CI HEAD |
| Receptor per `entity_id` | Sí | **Sí** | tests | casuística real |
| Rellegir selecció/imports | Sí | **Sí** | tests | classificació transversal |
| Preview + fingerprint | Sí | **Sí** | tests | E2E |
| Preview coverage | Sí | **Sí en aquesta branca** | test nou definit | CI HEAD |
| Confirm + guard coverage | Sí | **Sí** | tests | concurrència real |
| Emissió sense payment | Sí | **Sí** | tests | preproducció |
| Idempotència payload | Sí | **Sí** | tests | concurrència entorn |
| Seqüència/hash/registre/cua | Sí | **Sí** | suite | preproducció |
| Auditoria transaccional | Sí | **Sí al main** | codi/suite | inspecció evidència |
| Mutador llegat | Sí | **RETIRAT 410 en aquesta branca** | static test nou | desplegament |
| `aeat_fields` oficials UC-004 | Sí com a requisit | **NO** | fail-closed detectat | **P0** |
| Document SIF per UUID | Sí FINAL | **PARCIAL**: schema/metadata/download sí; generació UC-004 no | parcial | **P0/P1** producer/worker/renderer/E2E |
| Cobrament posterior mateix UUID | Sí | servei existeix | test de servei | E2E canal |
| E2E pantalla→SIF→document→cobrament | Sí | parcial | no | **PENDENT** |

## 7. Bloqueig P0 detectat el 04/10: `aeat_fields`

`InvoiceService::issueInvoice()` exigeix `aeat_fields` quan l'entorn és `PREPROD/PREPRODUCTION/PROD/PRODUCTION`.

El payload que construeix avui:

`InvoiceBeforePaymentSelectionRepository -> InvoiceBeforePaymentServerPayloadAssembler -> InvoiceBeforePaymentPayloadBuilder`

no incorpora `aeat_fields`.

Conseqüència: **UC-004 pot ser funcional en LOCAL/DEV/TEST i fallar tancat en PREPROD/PROD abans d'emetre**.

Abans d'acceptació operativa cal un assembler server-side que construeixi l'snapshot AEAT oficial a partir del snapshot fiscal UC-004 i de la identitat SIF configurada; no s'ha d'acceptar aquest bloc des del navegador.

## 8. Auditoria real

El `InvoiceService` compartit ja persisteix dins la transacció:

- `operational_event` amb `operation_type=ISSUE_INVOICE`;
- `sif_audit_event` amb `action=ISSUE_INVOICE`.

Emissió nova: `reason_code=INVOICE_ISSUED`.

Reús idempotent: `reason_code=INVOICE_IDEMPOTENCY_REUSED`.

A diferència del PR #134, el main actual no usa `ISSUE_INVOICE_BEFORE_PAYMENT` com a nom específic i no evita crear un event d'auditoria de reús; això no duplica la factura ni el registre fiscal, però sí deixa traça de cada reintent.

`InternalApiAuthenticator` conserva el `request_id` anti-replay. Encara falta propagar aquest request id fins al payload fiscal UC-004 si es vol correlació 1:1 entre request HTTP i audit event; avui `InvoiceService` pot usar la clau idempotent com a fallback.

## 9. Document fiscal

El `main` actual ja conté l'**esquema** `document_job`, `factura_documents`, `fiscal_document_access` i la lectura/descàrrega privada signada de UC-080. El PR #134 contenia, a més, una implementació candidata del repository de jobs, queue post-COMMIT, snapshot verificat, worker/lease/retry i storage writer. Aquests PHP de producció **no són al runtime UC-004 actual**. El workflow específic `UC-004 SIF secure flow checks` del #134 va ser verd; els 6 errors de la suite global van ser 5 regressions de packs/privacitat i 1 de `RedsysSignatureValidator`, no errors UC-004. Per tant #134 és recuperable per peces, però no és un tall globalment verificat ni s'ha de fusionar a cegues.

Per tant l'estat autoritatiu és:

- schema `document_job` / `factura_documents` / `fiscal_document_access`: **IMPLEMENTAT AL MAIN**;
- lectura i descàrrega privada signada: **IMPLEMENTADA AL MAIN via UC-080**;
- producer/queue UC-004 post-COMMIT: **PENDENT al runtime vigent**;
- repository PHP + lease/retry/stale recovery: **PENDENT al runtime vigent**;
- snapshot documental immutable verificat: **PENDENT al runtime vigent**;
- storage writer privat/hash: **PENDENT al runtime vigent**;
- renderer fiscal PDF/QR/XML: **PENDENT**.

No s'ha recuperat aquest subsistema a cegues dins d'aquesta auditoria.

## 10. Evidència de proves

### Històrica vàlida però no suficient

El HEAD del PR #70 (`35946cca69e298960cea14a896075fb75887621e`) va tenir:

- SIF checks — success;
- Intranet AO batch checks — success;
- UC-004 SIF secure flow checks — success;
- SIF PHP MySQL tests — success;
- UC-111 integration verification — success.

### PR #134

El HEAD `3d792a4db6cb655f23fe55d6c61b2ada4c300d66` va tenir:

- Intranet AO batch checks — success;
- UC-004 SIF secure flow checks — success;
- UC-111 integration verification — failure;
- SIF checks — failure;
- SIF PHP MySQL tests — failure.

Per això #134 **no** es considera un tall verificat global ni s'ha fusionat/reutilitzat cegament.

### Branca 04/10

Les proves i checks de regressió estan actualitzats. El seu resultat s'ha de prendre del HEAD del PR nou; fins llavors l'estat és **PENDENT DE CI**, no `VERIFICAT`.

## 11. Criteris de tancament operatiu

UC-004 només pot passar a **VERIFICAT** quan:

1. CI del HEAD 04/10 és verd;
2. migracions i backfill de coverage s'han executat a `sif_test`/preproducció;
3. `aeat_fields` oficials es construeixen server-side i passen validació;
4. dos operadors concurrents no dupliquen factura/cobertura;
5. manipulació del DOM/receptor/import provoca nou preview o conflicte;
6. document PDF/QR/XML es genera/custodia pel mateix UUID sense reemetre;
7. cobrament posterior s'assigna al mateix UUID i no crea segon ALTA;
8. E2E pantalla → bridge → SIF → document → cobrament queda guardat com a evidència;
9. configuració real de secrets, rols i workers queda documentada.

## 12. Veredicte

- **DOCUMENTAT:** **SÍ · COMPLET per l'abast demanat.**
- **IMPLEMENTAT:** **PARCIAL AVANÇAT.** Ruta segura d'emissió, idempotència, coverage UC-004 i auditoria sí; snapshot AEAT qualificat i document UUID no.
- **VERIFICAT:** **PARCIAL.** Inspecció estàtica + evidència històrica; no hi ha encara CI del nou HEAD ni E2E/preproducció.
- **PENDENT:** `aeat_fields`, document per UUID, cobertura transversal, E2E/concurrència, cobrament real i evidència d'entorn.

Per tant UC-004 queda **tancat com a auditoria documental/de repositori**, però **NO tancat com a acceptació operativa o de producció**.
