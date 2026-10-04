# UC-007 · Auditoria final consolidada — 2026-10-04

**Cas d'ús:** Consultar factura, estat i document.  
**Repositori:** `orgmeriemprismacat-del/verifactu`.  
**PR d'auditoria:** #135.  
**Branca:** `audit/uc-007-revalidacio-2026-10-03`.  
**Referència funcional:** `documentacio/06-fitxes-funcionals/uc-007.md`.

## 1. Criteri d'auditoria

Aquesta auditoria separa explícitament quatre dimensions:

- **DOCUMENTAT**: existeix contracte funcional/UML/traçabilitat.
- **IMPLEMENTAT**: existeix codi executable al head del PR.
- **VERIFICAT**: existeix evidència estàtica o prova automatitzada executada.
- **PENDENT**: necessita CI del head final, navegador, BD/storage/configuració real o retirada del fallback.

No s'equipara “fitxer existent” amb “asset executat”, ni “test escrit” amb “test passat”.

## 2. Resultat executiu

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa UC-007 | sí | n/a | revalidada | manteniment |
| Query SIF UUID/criteris | sí | sí | PASS CI inicial | CI ampliacions + preprod |
| HMAC/anti-replay | sí | sí | PASS CI inicial | configuració real |
| FULL/MINIMAL | sí | sí | policy PASS inicial | resolver nou: CI final |
| `/alumnes/factura/` | sí | sí | boundary inicial PASS | E2E |
| fitxa alumne AL-16/17/18 | sí | sí | boundary inicial PASS | E2E |
| UC-080 bytes/hash/audit | sí | sí en codi | test nou escrit | CI final + storage real |
| fallback llegat F02–F07 | sí | endurit | estàtic + tests nous | CI final + regressió dades |
| UML classes | sí | n/a | reconciliat | — |
| UML seqüències | sí | n/a | F02–F07/AL desglossats | — |
| UML activitats | sí | n/a | F01–F07/AL desglossats | — |
| traçabilitat | sí | n/a | reconciliada | evidència runtime |

**Estat global:** **DOCUMENTAT + IMPLEMENTAT EN CODI + VERIFICACIÓ PARCIAL.** Encara no s'ha de marcar “IMPLEMENTAT I PROVAT” fins a CI del head final i preproducció.

## 3. Superfícies reals auditades

### 3.1. Intranet

- `alumnes-factura.php`
- `js/alumnes-factura.js`
- `alumnes-mostrar-alumne.php`
- `js/alumnes-mostrar-alumne.js`
- `ajax/alumnes/sifFactures.php`
- `ajax/alumnes/sifDocument.php`
- `SifInternalApiClient.php`
- `SifInternalDocumentClient.php`
- `SifAuthenticatedActor.php`
- `LegacyInvoiceReadContext.php`
- `LegacyInvoiceReadAuthorization.php`
- `LegacyInvoiceMutationAuthorization.php`
- `SifLegacyInvoiceMutationGuard.php`
- wrappers llegats F02–F07/AL-17
- blob complet `Intranet.php`

### 3.2. SIF

- `InvoiceQueryService`
- `InvoiceReadRepository`
- `InvoiceQueryCriteriaValidator`
- `InvoiceQueryGateway`
- `InternalInvoiceScopeResolver`
- `ResolvedInvoiceVisibilityPolicy`
- `InternalApiAuthenticator`
- `public/api/factures/query.php`
- `InvoiceDocumentAccessService`
- `DocumentAccessRepository`
- `ResolvedDocumentAuthorizationPolicy`
- `PrivateDocumentStore`
- `FiscalDocumentAccessRepository`
- `public/api/documents/download.php`

## 4. Troballes i resolució

### UC007-FIND-01 · Asset real de fitxa alumne obsolet — CORREGIT
La pàgina carregava `alumnes-mostrar-alumne.min.js?ver=1.6`, mentre les correccions existien només al font no minificat. El minificat executable conservava download GET, `resD` indefinit, cleanup GET i lògica anterior de modal.

**Correcció:** `alumnes-mostrar-alumne.php` carrega `alumnes-mostrar-alumne.js?ver=1.7`.

### UC007-FIND-02 · Dues implementacions SIF simultànies — CORREGIT
`/alumnes/factura/` carregava `alumnes-factura-sif.js` i `alumnes-factura.js` amb contractes diferents.

**Correcció:** una sola implementació executable, `alumnes-factura.js?ver=1.1`.

### UC007-FIND-03 · Deep link UUID inconsistent — CORREGIT
Format canònic: `#/uuid/<UUID_FACTURA>`. Es conserva compatibilitat amb `?uuid_factura=<UUID_FACTURA>`.

### UC007-FIND-04 · Paginació del modal llegat perdia handlers — CORREGIT
El modal substituïa el body després de registrar fletxes. S'elimina la segona substitució del DOM.

### UC007-FIND-05 · Metadata documental divergent — RECONCILIAT
El read model UC-007 exposa `ID, UUID_FACTURA, TIPUS, ESTAT, CREATED_AT` i no exposa `PATH_FITXER/HASH_FITXER`. El hash/path són responsabilitat interna d'UC-080.

### UC007-FIND-06 · F02 usava estat no inicialitzat — CORREGIT
`buscarUsuaris_Factures()` llegia `$existeixCerca` abans d'inicialitzar-lo. Ara comença amb `$existeixCerca = false`.

### UC007-FIND-07 · Protocol `#|DNI` possible — CORREGIT
El separador depenia de l'índex del bucle. Ara depèn del nombre de candidats realment afegits.

### UC007-FIND-08 · Títol F04 no escapat — CORREGIT
`$cercaPer` arribava cru al HTML. Ara passa per `__escapeHtmlValue()`.

### UC007-FIND-09 · PII del fallback per GET — CORREGIT EN EL CAMÍ UC-007
DNI, correu i llistes de candidats viatjaven a query string.

**Correcció UC-007:** cerca, selector i llistat legacy usen POST; el POST exigeix same-origin/XHR. Els wrappers mantenen GET temporal per compatibilitat amb consumidors no inventariats.

**Deute:** retirar GET quan s'hagin inventariat tots els callers.

### UC007-FIND-10 · Volum servidor sense límit explícit — CORREGIT
S'han afegit longituds màximes, màxim 2.000 candidats i límit de mida del payload de DNIs.

La resolució DNI/correu → inscripcions també deixa de truncar silenciosament: consulta fins a 201 files i retorna 422 si hi ha més de 200 coincidències, obligant a acotar la cerca.

### UC007-FIND-11 · Guard de convivència podia quedar inactiu amb UC-007 actiu — CORREGIT
El guard només s'activava amb `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS`.

**Correcció:** s'activa si `mutationBlock || uc007ReadBoundary`. Amb UC-007 actiu, una factura ja governada pel SIF no pot entrar al camí llegat.

### UC007-FIND-12 · Descàrrega llegada mutava `generada` — CORREGIT
`generaFactura($factura,true)` actualitzava `factures.generada` la primera vegada.

**Correcció compatible:** `generaFactura($factura,$descarrega,$marcaGenerada=true)` i UC-007 usa `generaFactura(...,true,false)`.

### UC007-FIND-13 · Preview/PDF llegat renderitzava dades de BD sense escape — CORREGIT
`Text::get()` no escapa HTML. `generaFactura()` concatenava receptor/conceptes/import a HTML usat per preview i DOMPDF.

**Correcció:** s'escapen número, data, raó, CIF, adreça, població, conceptes i import.

### UC007-FIND-14 · Descàrrega llegada depenia de permís d'edició al client — CORREGIT
Descarregar és lectura. S'elimina la dependència client-side de `tePermisEdicio`; el backend continua validant `ROLS_VISUALITZAR`.

### UC007-FIND-15 · Logs i modal incorrecte en anul·lació llegada — CORREGIT A UI
S'han eliminat logs d'id/import/data/observacions i els errors de validació es mostren al modal d'anul·lació. La lògica econòmica d'anul·lació no es redefineix dins UC-007.

### UC007-FIND-16 · Flag UI buit — CORREGIT
S'elimina `SIF_INVOICE_QUERY_UI_ENABLED` buit. Els flags efectius són `SIF_UC007_QUERY_ENABLED` i `SIF_UC080_DOCUMENT_ENABLED`.

## 5. Estat F01–F07

| Ref | Funció | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- | --- |
| F01 | càrrega/rol | sí | sí | HMAC/policy inicial PASS; static | rol revocat real |
| F02 | cerca | sí | sí | query inicial PASS; hardening nou | CI final + dades reals |
| F03 | selector | sí | sí | static/boundary nou | E2E |
| F04 | llistat | sí | sí | static/boundary nou | regressió històrica |
| F05 | detall | sí | sí | query PASS + static legacy | E2E; retirar UPDATE llegat final |
| F06 | preview | sí | sí | escaping test nou | CI final + visual/PDF |
| F07 | download | sí | sí | UC-080 test nou + boundary | CI final + storage real |

## 6. Estat AL-16–18

| Ref | Funció | Estat |
| --- | --- | --- |
| AL-16 | camp factura → pantalla factura | implementat; deep link UUID corregit; E2E pendent |
| AL-17 | modal factura des d'inscripció | implementat; múltiples UUID; paginació fallback corregida; E2E pendent |
| AL-18 | descàrrega | SIF → UC-080; legacy → POST read-only; E2E pendent |

## 7. Autorització

### Intranet
1. `comprovarSessio.php` rellegeix credencial/rol de BD.
2. `replaceRols()` substitueix els rols de sessió.
3. `LegacyInvoiceReadAuthorization` valida ROLS_VISUALITZAR.
4. bridges deriven actor/roles amb `SifAuthenticatedActor`.
5. navegador no aporta `invoice_scope` ni secret HMAC.

### SIF
1. HMAC inclou method/path/timestamp/request-id/actor/roles/body hash.
2. anti-replay reclama `request_id`.
3. `InternalInvoiceScopeResolver` resol FULL/MINIMAL des de rols configurats.
4. `ResolvedInvoiceVisibilityPolicy` falla tancat.
5. document requereix FULL.

## 8. Read model

UC-007 separa factura, línies, relacions, rectificatives, pagaments, darrer registre fiscal i metadata documental. No barreja `ESTAT_FACTURA`, `ESTAT_COBRAMENT`, `ESTAT_AEAT` i estat documental.

## 9. UC-080

Flux: `document_id` → HMAC → scope → policy → metadata → root privat → `realpath` → mida → SHA-256 → audit `ALLOWED/DENIED/FAILED` → stream no-store.

Test nou cobreix bytes correctes, MINIMAL denegat, hash mismatch, fitxer absent i path fora del root.

**Pendent:** CI del head final i storage real de preproducció.

## 10. Fallback llegat

Controls actuals:
- sessió/rol;
- prepared statements i escape LIKE;
- POST en el camí UC-007 per PII;
- same-origin POST;
- inputs acotats;
- HTML escapat;
- guard SIF;
- download read-only;
- PDF temporal sense mutació `generada`.

Limitacions:
- alguns wrappers conserven GET compatible;
- model per `FACTURA_RELACIONADA`;
- reconstrucció PDF viva, no document immutable;
- camps/queries històriques amb semàntica legacy;
- edició/anul·lació directa només mentre la factura no sigui SIF.

## 11. UML i traçabilitat

- [classes ACTUAL/FINAL](uc-007-classes-actual-final.md)
- [seqüències ACTUAL/FINAL](uc-007-sequencies-actual-final.md)
- [activitats per pàgina/apartat](uc-007-activitats-pagines-actual-final.md)
- [inventari PHP/JS](uc-007-inventari-codi-php-js-actual-final.md)
- [matriu de traçabilitat](uc-007-tracabilitat-estats-2026-10-03.md)
- [proves pendents/runtime](03-proves-pendents-uc-007-implementacio.md)
- [evidència CI](05-evidencia-ci-uc-007-pr135-2026-10-03.md)

## 12. Proves

### PASS al primer head auditat
- `Uc007IntranetBoundaryTest` inicial;
- `InvoiceQueryServiceTest`;
- `ResolvedInvoiceVisibilityPolicyTest`;
- `InternalApiAuthenticatorTest`;
- `InvoiceQueryScriptTest`;
- registre immutable de document.

### Afegides després — CI head final pendent
- `InvoiceDocumentAccessServiceTest`;
- `InternalInvoiceScopeResolverTest`;
- ampliació query per `source_ids`/múltiples UUID;
- boundary d'asset canònic, POST PII, same-origin, guard anti-bypass, F02 state/delimiter/escape, F06 escaping, F07 zero mutació `generada` i permís de lectura.

## 13. CI global

El primer run auditat va acabar amb **921 PASS / 6 FAIL**. Les sis fallades eren en tests PACK/Redsys, fora del diff UC-007 d'aquell head. El `main` base ja tenia workflows SIF en failure; no s'afirma que fossin exactament les mateixes assertions.

El head final necessita una nova evidència CI abans del tancament.

## 14. Pendent de preproducció

- rols FULL/MINIMAL reals i revocació durant sessió;
- HMAC/secrets;
- AL-16/17/18 navegador;
- una i múltiples factures per inscripció;
- cache-busting assets 1.1/1.7;
- document real READY/CREATED;
- fitxer absent/hash incorrecte/path/permissions reals;
- audit `fiscal_document_access`;
- factura legacy `generada IS NULL` abans/després download;
- dades històriques empresa/grup/rectificatives;
- feature flags/rollback;
- retirada final del fallback.

## 15. Criteri de tancament

### Es pot afirmar ara
**UC-007 està documentat i implementat en codi, amb una part significativa verificada automatitzadament.**

### Encara no
**UC-007 no s'ha de marcar “IMPLEMENTAT I PROVAT / TANCAT EN PRODUCCIÓ”.**

Per fer-ho cal:
1. CI del head final sense regressions atribuïbles a UC-007;
2. matriu E2E/preproducció;
3. evidència de storage/document;
4. validació de rols/flags;
5. decisió explícita sobre retirada del fallback.


## 16. Reconciliació amb main — 2026-10-04

La branca s'ha reconciliat contra el `main` vigent mitjançant comparació de tres vies des del merge-base `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`.

Resultat:
- `main` havia avançat 92 commits;
- la branca UC-007 contenia 105 commits respecte del merge-base;
- **0 fitxers solapats** entre els canvis nous de `main` i els fitxers modificats per UC-007;
- s'ha creat un merge commit net incorporant el `main` actual;
- estat posterior: **behind = 0**, PR mergeable.

Això permet atribuir el diff restant exclusivament al paquet UC-007/080 sense sobreescriure treball nou de `main`.

### CI del head reconciliat

El head reconciliat ha activat els workflows SIF/UC/intranet corresponents. En el moment d'aquesta evidència continuen en estat `queued`; per tant no es promou encara cap test afegit després del primer run a estat PASS final.
