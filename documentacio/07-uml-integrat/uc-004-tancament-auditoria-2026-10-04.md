# UC-004 · Tancament d'auditoria de repositori · 2026-10-04

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Base auditada:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`  
**Branca de revalidació:** `audit/uc-004-revalidacio-v2-2026-10-04`  
**PR:** #166  
**Abast:** fitxa funcional, PHP/JS executable, UML ACTUAL/FINAL, traçabilitat, proves versionades i mancances.

## 1. Veredicte executiu

L'auditoria de repositori del UC-004 queda **tancada documentalment**, però el cas d'ús **no queda acceptat operativament per PREPROD/PROD**.

- **Documentat:** SÍ, complet per l'abast auditat.
- **Implementat:** PARCIAL AVANÇAT.
- **Verificat:** PARCIAL.
- **Pendent:** `aeat_fields` oficials, document fiscal SIF per UUID, cobertura transversal, concurrència/E2E, cobrament posterior real i evidència de preproducció.

## 2. Artefactes obligatoris localitzats

| Artefacte | Estat |
| --- | --- |
| Fitxa funcional | COMPLETA · REVALIDADA |
| Cas d'ús ACTUAL/FINAL | COMPLET |
| Classes ACTUAL/FINAL | COMPLET |
| Seqüències ACTUAL/FINAL | COMPLET |
| Activitats ACTUAL/FINAL per pàgina/apartat | COMPLET · 14 DIAGRAMES |
| Auditoria/traçabilitat/mancances | COMPLET |
| Inventari PHP/JS | COMPLET |
| Síntesi integrada | COMPLETA |
| Acta de revalidació | COMPLETA |

## 3. Flux executable acreditat al codi versionat

```text
Browser UC-004
  → sessió + permís d'edició + CSRF
  → sifFacturaAbansPagar.php
  → SifInternalApiClient
  → HMAC + request_id + actor + rols
  → /api/factures/before-payment.php
  → anti-replay + rol SIF
  → InvoiceBeforePaymentCommandService
  → rellegir selecció/receptor/imports
  → preview + fingerprint
  → confirm + nova rellectura
  → InvoiceBeforePaymentService
  → InvoiceService
  → idempotència + seqüència + graf fiscal + coverage + auditoria
  → COMMIT
```

El mutador fiscal llegat `generaFacturaElectronica_Factures.php` queda retirat amb **410 Gone** en aquesta branca.

## 4. Implementat

- sessió i autorització del bridge intranet;
- CSRF;
- HMAC servidor-servidor;
- anti-replay i rol SIF;
- contracte `UC004-V1`;
- receptor per `entity_id`;
- reconstrucció autoritativa d'inscripcions i imports;
- preview + fingerprint;
- confirmació amb relectura;
- idempotència per clau + hash de payload;
- cobertura UC-004 amb pre-check de preview i guard UNIQUE transaccional;
- emissió sense `payment` inicial;
- seqüència, hash chain, `factura_registres`, `fiscal_queue` i `fact_rels`;
- `operational_event` + `sif_audit_event` dins el mateix tall transaccional;
- prova d'integració específica de `InvoiceBeforePaymentCommandService`.

## 5. Verificat

Queda acreditat per inspecció de codi i proves versionades que el contracte anterior existeix i que hi ha cobertura automatitzada de les regles principals.

No es considera encara verificació operativa completa perquè el HEAD del PR #166 necessita CI propi verd i falta evidència d'entorn real de preproducció.

## 6. Bloquejos i pendents

### P0 — `aeat_fields` en PREPROD/PROD

`InvoiceService::issueInvoice()` exigeix snapshot oficial AEAT en entorns qualificats. El payload UC-004 actual encara no el construeix server-side. El endpoint, a més, rebutja explícitament qualsevol `aeat_fields` o `aeat_header` aportat pel caller. Per tant el flux està dissenyat per **fallar tancat** abans d'emetre mentre no existeixin el perfil fiscal UC-004 versionat i el builder server-side. Vegeu [contracte AEAT pendent](uc-004-contracte-aeat-pendent-2026-10-04.md).

### P0/P1 — document fiscal immutable per UUID

El `main` ja disposa de l'esquema `document_job`, metadades `factura_documents`, auditoria d'accés i descàrrega privada signada. Vegeu [pla de recuperació selectiva](uc-004-recuperacio-pipeline-documental-2026-10-04.md). El que falta és completar el **pipeline productor UC-004**: queue post-COMMIT, snapshot fiscal verificat, repository/worker amb lease i retry, storage writer immutable i renderer PDF/QR/XML. El PR #134 conté una implementació candidata d'aquestes peces; el check específic UC-004 va passar i els 6 errors globals eren aliens al UC-004, però no es recuperarà el PR sencer a cegues.

### P1 — cobertura transversal

El guard actual impedeix dues operacions UC-004 sobre la mateixa inscripció, però no resol encara totes les combinacions de canal/pagador/rectificació.

### P1 — E2E i cobrament posterior

Cal demostrar en entorn controlat:

1. preview i confirmació;
2. carrera concurrent;
3. manipulació de DOM/receptor/import;
4. generació documental sobre el mateix UUID;
5. cobrament posterior sobre aquell UUID;
6. absència d'un segon registre fiscal ALTA.

## 7. Criteri de tancament operatiu

UC-004 només podrà passar de **PARCIAL AVANÇAT** a **VERIFICAT** quan:

- CI del HEAD del PR #166 sigui verd;
- migracions i backfill s'hagin executat en `sif_test`/preproducció;
- `aeat_fields` oficials es construeixin al servidor;
- el document PDF/QR/XML quedi custodiat per UUID;
- la concurrència no dupliqui factura/cobertura;
- el cobrament posterior s'assigni al mateix UUID;
- es conservi evidència E2E de preproducció.

## 8. Referències

- [Fitxa funcional](../06-fitxes-funcionals/uc-004.md)
- [Cas d'ús ACTUAL/FINAL](uc-004-cas-us-actual-final.md)
- [Classes ACTUAL/FINAL](uc-004-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-004-sequencies-actual-final.md)
- [Activitats ACTUAL/FINAL](uc-004-activitats-actual-final.md)
- [Auditoria i mancances](uc-004-auditoria-tracabilitat-mancances.md)
- [Inventari](uc-004-inventari-artefactes.md)
- [Síntesi integrada](uc-004-emetre-factura-abans-cobrar.md)
- [Revalidació 2026-10-04](uc-004-revalidacio-2026-10-04.md)

**Conclusió:** el dossier documental i la traçabilitat de repositori queden coherents i complets per UC-004. L'acceptació operativa continua condicionada pels bloquejos anteriors.
