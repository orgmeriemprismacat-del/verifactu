# UC-004 · Tancament de l'auditoria de codi i documentació · 2026-10-03

## 1. Identificació del tall

- **Cas d'ús:** UC-004 — Emetre factura abans de cobrar.
- **Branca reconciliada:** `audit/uc-004-reconciliacio-2026-10-03`.
- **Pull request:** #134.
- **Base de reconciliació:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`.
- **Origen recuperat:** PR #111, descartat com a candidat directe perquè el seu HEAD havia quedat 54 commits per darrere de `main`.
- **Mètode:** comparació SHA per fitxer contra base/main/HEAD del #111, trasllat només de canvis sense divergència i fusió manual dels fitxers globals concurrents.

Aquest document tanca **l'auditoria de repositori**. No certifica desplegament, preproducció, producció ni acceptació fiscal externa.

## 2. Resposta a la pregunta “tenim totes les fitxes, diagrames i codi?”

### Documentació exigida

| Peça | Fitxer | Estat de l'auditoria |
| --- | --- | --- |
| Fitxa funcional | `../06-fitxes-funcionals/uc-004.md` | **COMPLETA · RECONCILIADA** |
| Cas d'ús ACTUAL/FINAL | `uc-004-cas-us-actual-final.md` | **COMPLET** · ACTUAL històric, ACTUAL versionat i FINAL operatiu |
| Classes ACTUAL/FINAL | `uc-004-classes-actual-final.md` | **COMPLET** |
| Seqüències ACTUAL/FINAL | `uc-004-sequencies-actual-final.md` | **COMPLET** |
| Activitats per pàgina/apartat | `uc-004-activitats-actual-final.md` | **COMPLET · 14 DIAGRAMES** |
| Auditoria/traçabilitat/mancances | `uc-004-auditoria-tracabilitat-mancances.md` | **COMPLET** |
| Inventari d'artefactes | `uc-004-inventari-artefactes.md` | **COMPLET** |
| Síntesi integrada | `uc-004-emetre-factura-abans-cobrar.md` | **RECONCILIADA 03/10** |
| Acta de tancament | aquest fitxer | **CREADA** |

### Activitats cobertes

El document d'activitats conté ACTUAL i FINAL per:

- A004-P00 — pàgina completa;
- A004-P01 — accés, càrrega i permisos;
- A004-P02 — cerca i selecció d'inscripcions;
- A004-P03 — selecció, imports i conceptes;
- A004-P04 — receptor i dades de factura;
- A004-P05 — emissió abans de cobrar;
- A004-P06 — resultat, previsualització i document.

No falta cap tipus de diagrama dels sol·licitats.

## 3. Codi real revisat

### Intranet / PHP / JS

S'han contrastat, entre altres:

- `alumnes-genera-factura-abans-pagar.php`;
- `js/alumnes-genera-factura-abans-pagar.js`;
- `SifInvoiceBeforePaymentAccess.php`;
- `SifInternalApiClient.php`;
- `ajax/alumnes/sifFacturaAbansPagar.php`;
- `ajax/alumnes/sifFacturaAbansPagarToken.php`;
- `ajax/alumnes/sifFacturaAbansPagarEntitats.php`;
- l'antic `ajax/alumnes/generaFacturaElectronica_Factures.php`;
- les superfícies llegades de dades/preview/descàrrega i `Intranet.php`.

### SIF

S'han contrastat i/o modificat:

- endpoint intern `public/api/factures/before-payment.php`;
- `InvoiceBeforePaymentCommandService`;
- `InvoiceBeforePaymentLegacyPreparationService`;
- `InvoiceBeforePaymentSelectionRepository`;
- `InvoiceBeforePaymentBillingPartyRepository`;
- `InvoiceBeforePaymentServerPayloadAssembler`;
- `InvoiceBeforePaymentPayloadBuilder`;
- `InvoiceBeforePaymentService`;
- `InvoiceService`;
- `InvoiceBeforePaymentCoverageRepository`;
- `OperationalEventRepository`;
- repositoris de factura/document;
- scripts de preview/preflight/process;
- tests d'integració específics UC-004.

## 4. Estat funcional per capa

| Capacitat | Documentat | Implementat al codi versionat | Verificable automàticament | Pendent operatiu |
| --- | --- | --- | --- | --- |
| Sessió i permís d'edició intranet | Sí | **Sí** | workflow estàtic | E2E |
| CSRF | Sí | **Sí** | workflow estàtic | E2E |
| HMAC servidor-servidor | Sí | **Sí** | tests/contract checks | configuració real |
| Anti-replay `request_id` | Sí | **Sí** | tests | BD/entorn |
| Rol d'escriptura SIF | Sí | **Sí** | tests | configuració real |
| Receptor per `entity_id` | Sí | **Sí** | tests | casuística real |
| Rellegir IDs/curs/imports | Sí | **Sí** | tests | classificador transversal |
| Preview + fingerprint | Sí | **Sí** | tests | E2E |
| Confirmació amb rellectura | Sí | **Sí** | tests | E2E |
| Emissió sense payment | Sí | **Sí** | tests | preproducció |
| Idempotència de payload | Sí | **Sí** | tests | concurrència d'entorn |
| Cobertura UC-004 per inscripció | Sí | **Sí** | tests | backfill/preproducció + cobertura entre canals |
| Número/cadena/registre/cua fiscal | Sí | **Sí** | suite SIF | preproducció |
| `operational_event` atòmic | Sí | **Sí** | tests | inspecció de traça real |
| Mutador fiscal llegat | Sí | **RETIRAT · 410 Gone** | static check | desplegament |
| `document_job` UUID+versió | Sí | **Sí** | tests | desplegament |
| Worker/lease/retry/stale recovery | Sí | **Sí** | tests | worker real |
| Snapshot fiscal immutable | Sí | **Sí** | tests | validació normativa |
| Storage privat + SHA-256 | Sí | **Sí** | tests | configuració filesystem |
| Metadata `READY/ERROR` | Sí | **Sí** | tests | E2E |
| Renderer fiscal concret PDF/QR/XML | Sí com a contracte | **NO** | no | **PENDENT** |
| Cobrament posterior sobre mateix UUID | Sí | servei SIF existent | test de servei | **E2E DE CANAL PENDENT** |
| E2E pantalla → document → cobrament | Sí | peces parcials | no substituïble per unit tests | **PENDENT** |

## 5. Canvis de seguretat i integritat aplicats

1. **Retirada fail-closed del mutador llegat.** `generaFacturaElectronica_Factures.php` retorna 410 abans de carregar dependències i ja no pot invocar `generarFacturaElectronica_Alumnes()`.
2. **Contracte UC004-V1.** El client intern versiona el command per evitar evolució implícita.
3. **Preview autoritatiu.** La selecció, el receptor i els imports es rellegeixen al servidor; el DOM no és autoritat fiscal.
4. **Optimistic concurrency.** Confirmació només si el fingerprint de la rellectura coincideix amb el preview.
5. **Cobertura doble.** Preview detecta claims existents i el confirm conserva el guard UNIQUE dins la transacció.
6. **Auditoria atòmica.** `ISSUE_INVOICE_BEFORE_PAYMENT` es desa a la mateixa transacció que la factura; una fallada d'auditoria desfà el graf fiscal.
7. **Document desacoblat de l'emissió.** El job es crea/reutilitza per UUID+versió; un error documental no reemet factura.
8. **Storage immutable.** El writer privat verifica bytes/hash i rebutja sobreescriptures divergents.
9. **No shadow invoice llegada.** No es crea una segona autoritat fiscal ni es posa un UUID/sentinel dins `FACTURA_RELACIONADA`.

## 6. Verificació automatitzada

El HEAD històric del PR #111 havia superat els workflows següents abans de quedar desfasat respecte de `main`:

- SIF PHP MySQL tests — success;
- SIF checks — success;
- UC-004 SIF secure flow checks — success;
- Intranet AO batch checks — success;
- UC-111 integration verification — success.

Aquesta evidència **no es reutilitza com a certificació del HEAD reconciliat**. La font autoritativa per al tall final són els checks del PR #134 sobre el seu HEAD vigent. En el moment de redactar aquesta acta, els runs nous estan llançats i poden quedar temporalment en cua per concurrència de GitHub Actions.

## 7. Mancances reals que queden

### P0 abans d'acceptació operativa

- implementar un renderer fiscal concret i versionat per PDF/QR/XML;
- validar-lo visualment i normativament;
- executar migracions/preflight sobre `sif_test` i preproducció;
- validar backfill de `invoice_before_payment_coverage`;
- provar cobertura transversal entre canals/pagadors;
- E2E pantalla → bridge → SIF → job → document;
- E2E de cobrament posterior sobre el mateix UUID, sense segon `ALTA`;
- prova de dos operadors/concurrència real.

### P1 de neteja/operació

- retirar dependències llegades de PDF temporal quan s'acrediti que no tenen altres consumidors;
- completar read-models/projeccions no fiscals si alguna pantalla llegada encara necessita estat SIF;
- documentar configuració de secrets, root documental i worker;
- conservar evidències d'operació i runbook.

## 8. Criteri final

**DOCUMENTAT:** sí, complet per l'abast sol·licitat.  
**IMPLEMENTAT:** sí per la ruta segura d'emissió, idempotència, cobertura específica UC-004, auditoria i infraestructura documental; no pel renderer fiscal concret.  
**VERIFICAT:** estàticament sí; els tests del codi font recuperat havien estat verds al #111, però la verificació del **HEAD final del #134** s'ha de llegir als checks del PR.  
**PENDENT:** renderer, classificador transversal, migració/preproducció, concurrència real i E2E complet.

Per tant, UC-004 queda **tancat com a auditoria de repositori i documentació**, però **no tancat com a acceptació operativa/producció**.
