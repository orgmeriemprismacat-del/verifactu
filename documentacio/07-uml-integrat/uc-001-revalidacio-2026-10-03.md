# UC-001 · Revalidació exhaustiva — 2026-10-03

**Repositori:** `orgmeriemprismacat-del/verifactu`  
**PR reconciliada:** #145 — `audit(UC-001): reconciliació neta sobre main actual`  
**Branca:** `audit/uc-001-reconciled-2026-10-03`  
**Main contrastat:** `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Head de codi després de la nova correcció:** `276fb390cd3f4ac7157f831bb544a60e6330d157`

## 1. Conclusió de cobertura

Sí: el UC-001 disposa de totes les peces documentals principals demanades i s’han contrastat amb codi real.

| Peça | Existeix | Contrastada amb codi | Estat |
| --- | --- | --- | --- |
| Fitxa funcional | Sí — `06-fitxes-funcionals/uc-001.md` | Sí | Actualitzada a v1.4 |
| Fitxa + UML integrat | Sí | Sí | Actualitzada |
| Classes ACTUAL/FINAL | Sí | Sí | Nucli + frontera intranet |
| Seqüències ACTUAL/FINAL | Sí | Sí | Nucli, Redsys, AEAT, intranet i UC-004 |
| Activitats ACTUAL/FINAL | Sí | Sí | Ampliada per pàgina/apartat |
| Inventari PHP/JS | Sí | Sí | Ampliat amb guards i main |
| Auditoria/traçabilitat | Sí | Sí | Revalidada 03/10 |
| Proves específiques UC-001 | Sí | Sí, GitHub Actions | PASS al run `88e5c922…` |
| Acceptació preproducció | No | No | Pendent |
| Builders AEAT complets | No | Parcial/fail-closed | Pendent |

## 2. Què és UC-001 i què és una superfície adjacent

UC-001 és el **nucli transaccional d’emetre o reutilitzar una factura**, no una única pantalla. Els canals hi arriben per adaptadors especialitzats.

### 2.1. Generic endpoint

`sif/public/api/factures/issue.php` és servidor-servidor. Exigeix POST, HMAC intern, anti-replay, rol d’escriptura i policy. No s’ha localitzat cap JS públic autoritzat a construir i enviar-hi lliurement el payload fiscal complet.

### 2.2. Intranet — consulta de factures

`codi-drive/intranet-actual/alumnes-factura.php` carrega el circuit SIF de consulta i el JS llegat. El comportament SIF és read-only: cerca, vista i documents. L’edició/anul·lació del JS general apunta a endpoints llegats, que comproven sessió, rol, same-origin/AJAX i `SifLegacyInvoiceMutationGuard`.

**Gate pendent:** el bloqueig de la mutació llegada sobre una factura governada pel SIF és feature-gated per `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS`; si el flag no està actiu, el guard no bloqueja. La protecció existeix al codi però s’ha de demostrar configurada en preproducció abans del cutover.

### 2.3. Intranet — factura abans de cobrar

`alumnes-genera-factura-abans-pagar.php` és UC-004. El JS:
1. selecciona inscripcions;
2. resol entitat i demana preview autoritatiu;
3. conserva fingerprint de servidor;
4. confirma amb IDs + entity + fingerprint + observacions;
5. rep UUID/número i mostra cobrament pendent.

No calcula ni congela al navegador el snapshot fiscal complet.

### 2.4. Redsys i serveis manuals

Els `Redsys*InvoiceService` parteixen de notificacions/intencions/snapshots validats i invoquen `InvoiceService`. `RedsysInvoicePayloadBuilder` incorpora `movement_date` des de `CREATED_AT`. Els serveis manuals també reutilitzen el nucli i, si creen un payment inicial, han d’aportar la data real del moviment.

## 3. Nucli PHP revisat

### Documentat i implementat

- idempotència per clau + fingerprint complet del payload de negoci;
- normalització canònica de clau/canal abans de persistència;
- reús fail-closed si manca/divergeix el payment inicial original;
- numeració fiscal transaccional;
- cadena fiscal i registre/control;
- `fiscal_queue`;
- `fact_rels` i materialització de línia quan l’origen és unívoc;
- `operation_line_invoice_link` quan arriba `uuid_operation_line`;
- `operational_event` + `sif_audit_event` append-only;
- resposta amb estats factura/cobrament/AEAT/cua/document;
- endpoint genèric autenticat/autoritzat i identitat emissor/SIF server-owned;
- fail-closed de `aeat_fields` a PREPRODUCTION/PRODUCTION.

### Implementat però transversalment incomplet

- `commercial_operation` existeix però no és obligatòria per tot caller;
- `uuid_operation_line` no es propaga des de tots els builders;
- el snapshot AEAT es congela/valida, però el desglossament complet depèn del builder;
- el year de curs/pack/grup encara prové d’`inscription.ANY`;
- la selecció exacta R1–R5 continua al flux específic;
- la cobertura comercial entre dues claus diferents no és un guard global.

## 4. Troballa corregida en aquesta revalidació

### F-101 — `movement_date` no determinista en payment inicial

**Abans:** `InvoiceService::buildInitialPaymentPayload()` feia fallback a `date('Y-m-d H:i:s')`.  
**Risc:** dos intents equivalents separats en el temps podien generar payloads econòmics materials diferents i un CONFLICT idempotent fals.  
**Correcció:** commit `7dceaf9cff43566d3eac42108486c2f3d9ddc937` exigeix `movement_date` explícita i no buida.  
**Prova:** commit `276fb390cd3f4ac7157f831bb544a60e6330d157`, `IssueInvoiceTest::testInvoiceInitialPaymentRequiresStableMovementDateBeforeMutation`, **PASS**; comprova 422 i zero factura/registre/payment/seqüència consumida. El job queda 961 pass / 6 fail, amb les sis fallades fora d’UC-001.

## 5. Verificació executada

Al run del head `88e5c922…` passen proves específiques de:

- endpoint HTTP signat i scope;
- preflight UC-001;
- alta factura/registre/cua;
- reús idempotent;
- payment inicial i allocation;
- causa d’exempció;
- origen ambigu sense link arbitrari;
- control de registre fiscal anterior;
- materialització `commercial_operation_line -> factura_linia`;
- reintents amb traça nova;
- payment original desaparegut/manipulat/assignat a altra factura;
- factura antiga sense fingerprint;
- policies de payload, scope, normalització i hash;
- UC-004 invoice-before-payment;
- callers Redsys curs/pack/grup/regal/USOC;
- `ManualInvoiceService`.

**Resultat global:** 960 pass / 6 fail. Les sis fallades observades són cinc de pack/UC-015 i una de `RedsysSignatureValidatorTest`; no són proves del nucli UC-001.

## 6. Divergència amb `main`

La branca original #114 havia quedat **150 ahead / 44 behind**. El paquet UC-001 s'ha recreat selectivament sobre `main=b0e8ff7…` a la PR #145; el compare de la branca reconciliada és **ahead / behind 0** pel que fa a `behind`, amb merge-base al mateix `main`.

Solapament directe entre canvis de main i diff UC-001:
- `sif/config/sif.php`;
- `documentacio/07-uml-integrat/README.md`.

També s’han inspeccionat canvis de main en callers adjacents (`LegacyPackInvoicePayloadBuilder`, `RedsysCourseInvoiceService` i proves). La reconciliació de branca ja s'ha completat a #145, preservant els canvis concurrents; resta executar i validar el gate CI del head reconciliat.

## 7. Estat final per dimensió

| Dimensió | Estat | Explicació |
| --- | --- | --- |
| Documentació | **DOCUMENTAT** | Paquet complet i actualitzat |
| Nucli PHP | **IMPLEMENTAT** | Emissió/reús, idempotència, numeració, cadena, cua, payment, events |
| JS emissor genèric | **NO APLICA** | Endpoint intern, no API pública de navegador |
| Intranet consulta | **IMPLEMENTAT / ADJACENT** | SIF read-only + fallback llegat guardat |
| UC-004 | **IMPLEMENTAT / VERIFICAT** | Preview/fingerprint/confirmació |
| Redsys callers | **IMPLEMENTAT / VERIFICAT específicament** | Proves de serveis passades |
| Tests UC-001 pre-F101 | **VERIFICAT** | PASS a `88e5c922…` |
| F-101 | **IMPLEMENTAT I VERIFICAT** | Prova PASS a `276fb390…`; suite 961/6, fallades alienes a UC-001 |
| CI global | **NO VERD** | 6 fallades alienes observades |
| Preproducció | **PENDENT** | Config, flags, HMAC, issuer/SIF, AEAT builders, evidència |
| Producció | **PENDENT / BLOQUEJADA** | No declarar llest fins resoldre gaps i acceptació |

## 8. Pendents que bloquegen el tancament operatiu

1. **RESOLT:** reconciliació amb `main` a la PR #145; `sif/config/sif.php` i `README.md` s'han fusionat preservant el treball concurrent.
2. Mantenir com a evidència el run `276fb390…` on HARD-017 passa; el CI global continua bloquejat per les sis fallades alienes i s’ha de resoldre/reclassificar abans del merge.
3. Fer obligatòria o equivalent la cobertura comercial entre claus diferents abans de numerar.
4. Propagar `uuid_operation_line`/identitat comercial des de tots els builders pertinents.
5. Completar assembler fiscal server-side/`aeat_fields` per tots els fluxos qualificats.
6. Decidir/aplicar la regla d’any fiscal quan `inscription.ANY` és any acadèmic.
7. Tancar la classificació exacta R1–R5 als fluxos rectificatius.
8. Activar/provar `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS` i `SIF_UC007_QUERY_ENABLED` en preproducció.
9. Tancar fencing/acceptació operativa del worker Redsys als UCs específics.
10. Executar proves de preproducció amb BD segregada i evidència de rollback/idempotència/configuració real.

## 9. Criteri de tancament

**Auditoria de cobertura:** revalidada.  
**Core UC-001:** substancialment implementat i endurit.  
**Verificació específica:** existent i actualitzada; F-101 passa al run del head de codi `276fb390…`. El global continua 961/6 per fallades alienes.  
**Acceptació operativa/producció:** pendent.

No s’ha de convertir “fitxa + UML + tests del core” en “llest per producció”: els gaps fiscals/comercials i la configuració/cutover continuen explícits.


## 10. Troballes post-reconciliació de la PR #145

- **F-106 · operació comercial:** `commercial_operation.UUID_FACTURA` existeix a BD, però `CommercialOperationRepository` no té cap `linkInvoice()` i `InvoiceService` no materialitza aquest vincle. Pendent.
- **F-107 · any fiscal:** UC-004 ja usa `fiscal_year` separat de l'any d'edició; curs/pack/grup/USOC continuen derivant `year` de `inscription.ANY`, i regal usa `ANY`/any actual. Pendent transversal.
- **F-108 · operation line:** el writer `operation_line_invoice_link` existeix, però els builders auditats no propaguen `uuid_operation_line`. Pendent de contracte comú de builder.
- **F-109 · AEAT:** el core falla tancat en PREPRODUCTION/PRODUCTION si manca `aeat_fields`. És una protecció correcta, però evidencia que els builders legacy encara necessiten un assembler AEAT server-side complet.

Aquestes troballes no reobren el hardening ja verificat; concreten els quatre deutes transversals que impedeixen declarar UC-001 preparat per producció.
