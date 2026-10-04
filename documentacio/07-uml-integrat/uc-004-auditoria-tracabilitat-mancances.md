# UC-004 · Auditoria detallada, traçabilitat i mancances

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Data de tall actualitzada:** 2026-10-04  
**Branca reconciliada:** `audit/uc-004-revalidacio-v2-2026-10-04`  
**Base:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`  
**Tipus de verificació:** auditoria estàtica exhaustiva sobre `main` + hardening en branca nova. Evidència CI històrica separada de la verificació del HEAD nou i de preproducció/producció.

## 1. Conclusió de l'auditoria

UC-004 té **dos circuits diferents** que no s'han de fusionar documentalment:

1. **ACTUAL històric llegat:** el codi antic emetia via JS + `generaFacturaElectronica_Factures.php` + `Intranet.php`, amb numeració “últim + 1”, escriptura a `factures` llegades i PDF temporal.
2. **FINAL SIF al codi versionat actual:** la pantalla ja usa `sifFacturaAbansPagar.php` amb sessió/permís/CSRF; `SifInternalApiClient` signa HMAC servidor-servidor; el SIF rellegeix selecció/receptor, fa preview/confirmació amb fingerprint, idempotència, cobertura UC-004 i `operational_event`. En aquesta branca, l'endpoint fiscal llegat retorna `410 Gone` i ja no pot emetre.

Per tant, l'estat correcte del cas és:

- **Documentat:** SÍ, ara amb ACTUAL/FINAL i activitats separades.
- **Circuit ACTUAL llegat:** LOCALITZAT però **RETIRAT COM A ENTRADA FISCAL** en aquesta branca.
- **Implementat backend FINAL SIF:** SÍ per command intern, autenticació, preview/confirmació, emissió, cobertura i auditoria operacional.
- **Integrat pantalla → FINAL:** **SÍ al codi versionat**: JS → `sifFacturaAbansPagar.php` → HMAC → endpoint SIF. **No verificat encara en preproducció/producció.**
- **Verificat estàticament:** SÍ.
- **Proves automatitzades:** PR #70 va quedar verd històricament; PR #134 va tenir `UC-004 SIF secure flow checks` verd però tres checks globals SIF/UC-111 vermells. La branca 04/10 encara necessita resultat propi.
- **Preproducció/producció:** NO verificada.
- **Tancable com a UC complet:** ENCARA NO.

## 2. Fitxa funcional existent — revisió

Fitxer existent: `documentacio/06-fitxes-funcionals/uc-004.md`.

### 2.1 Punts correctes

- La factura abans de cobrar es tracta com a emissió fiscal real, no com una proforma.
- El cobrament és independent i posterior.
- El SIF és l'autoritat objectiu per factura, línies, registre encadenat i cua AEAT.
- Es requereixen idempotència, concurrència, auditoria i immutabilitat.
- Es considera imprescindible congelar dades fiscals de receptor, línies i imports.

### 2.2 Punts que la fitxa encara formula de manera massa genèrica

1. No descriu amb prou concreció la pantalla llegada real de tres passos.
2. No identifica que el POST actual rep `empresa`, `preu`, conceptes, cursos, edicions i IDs des del navegador.
3. No explicita la numeració llegada “últim + 1”.
4. No explicita la manca de transacció observada entre INSERT de factura i UPDATE de totes les inscripcions.
5. No separa prou “permís de visualització servidor” de “permís d'edició calculat al client”.
6. `InvoiceService` del `main` escriu **`operational_event` i `sif_audit_event`** dins la mateixa transacció d'emissió. Per UC-004 l'operació és la genèrica `ISSUE_INVOICE`; un reús idempotent deixa un event/auditoria `REUSED` sense recrear el graf fiscal.
7. La fitxa funcional 2.0 d'aquesta branca ja incorpora els scripts UC-004 de preview/preflight/process, les proves de flow/preproducció i els nous guards de cobertura.
8. L'endpoint genèric `issue.php` continua sense ser UC-004, però el `main` ja disposa de l'endpoint específic `sif/public/api/factures/before-payment.php`, que sí passa per `InvoiceBeforePaymentCommandService` i `InvoiceBeforePaymentService`.

**Decisió documental d'aquesta branca:** la fitxa funcional s'ha reconciliat i actualitzat conjuntament amb aquest dossier perquè el conjunt documental sigui coherent amb el codi real del tall 04/10/2026.

## 3. Codi ACTUAL — evidència funcional

### 3.1 Pantalla i permisos

- `alumnes-genera-factura-abans-pagar.php`: shell de la pàgina, sessió/configuració i càrrega de JS.
- `ajax/mostrarMain.php`: resol l'URL, consulta `ROLS_VISUALITZAR` i aplica `Usuari::tePermisVisualitzacio()`.
- `Intranet::mostrarPage()`: mapeja exactament `/alumnes/genera-factura-abans-pagar/` a `__mostrarPage_Alumnes_GeneraFacturaAbansPagar()`.
- `general.js`: consulta rols d'edició/usuari via AJAX i calcula `tePermisEdicio` al navegador.
- `__mostrarPage_Alumnes_GeneraFacturaAbansPagar()`: crea PAS 1 (cerca/selecció), PAS 2 (entitat/concepte/preu/observacions) i PAS 3 (dades factura).

### 3.2 Cerca i selecció

- `mostrarInformacioInscripcio_generaFactura.php` delega a `mostrarInformacioInscripcio_generaFactura_Alumnes($dni)`.
- El mètode retorna HTML amb IDs i valors del domini (`any-`, `mes-`, `curs-`, `titol-`, `hores-`, `apagar-`).
- El JS copia/gestiona aquestes dades al DOM.
- El JS actual recalcula `idsInsc` amb `selectedInscriptionIds()` a partir del DOM, deduplica i ordena; l'acumulació global observada al llegat ha quedat corregida.
- El total es calcula sumant `parseFloat($('#apagar-'+idInsc).html())`.
- El text de convocatòria depèn de crides AJAX a `calcularTextData.php`.

### 3.3 Emissió llegada

**Traça històrica llegada:** `generaFacturaElectronica_Factures.php` rebia el POST i invocava `Intranet::generarFacturaElectronica_Alumnes(...)`. **Tall actual d'aquesta branca:** aquest endpoint retorna `410 Gone` i no carrega dependències; el JS actual ja no el crida.

El mètode:

1. cerca entitat per `RAO LIKE ?`;
2. usa el primer resultat recuperat;
3. calcula `ordreFact = lastOrdreFact + 1`;
4. calcula `factura = lastFactRel + 1`;
5. inserta la factura a la BD llegada;
6. recorre IDs rebuts del client i actualitza cada inscripció;
7. retorna HTML.

No s'ha observat en aquest mètode una transacció comuna, lock de seqüència, clau idempotent, hash de payload o registre SIF.

### 3.4 Consulta i document

- `mostraDadesFacturaElectronica_Alumnes()` i `mostraInscripcionsFacturaElectronica_Alumnes()` consulten la BD llegada.
- `modalConsultaFactura_Factures()` reutilitza `generaFactura(..., false)`.
- `generaFactura(..., true)` marca `GENERAT`, renderitza Dompdf i escriu un fitxer temporal.
- El document inclou un text d'exempció d'IVA codificat al generador llegat; el FINAL no ha de derivar el règim fiscal d'una frase fixa.
- `eliminarArxiu.php` fa `unlink($filename)` amb `filename` rebut per GET; aquesta interfície no és acceptable com a patró FINAL de custòdia documental.

## 4. Nucli FINAL SIF — evidència implementada

### 4.1 Servei específic

`InvoiceBeforePaymentService::issueBeforePayment()`:

1. passa l'entrada per `InvoiceBeforePaymentPayloadBuilder`;
2. delega l'emissió a `InvoiceService::issueInvoice()`.

### 4.2 Builder UC-004

`InvoiceBeforePaymentPayloadBuilder`:

- rebutja un bloc `payment` no nul;
- usa clau explícita o la deriva d'una referència;
- força `source_channel=INTRANET`;
- força `emesa_abans_cobrament=1`;
- elimina `payment`.

### 4.3 Idempotència

`PayloadIdempotencyValidator`:

- canonicalitza mapes ordenant claus;
- calcula SHA-256 del payload canònic;
- rebutja hash històric absent/invàlid;
- rebutja mateixa clau amb payload diferent.

`InvoiceService`:

- consulta la clau `FOR UPDATE`;
- en duplicat SQL `23000`, fa una segona transacció i rellegeix la factura;
- en reús comprova `IDEMPOTENCY_PAYLOAD_HASH`.

### 4.4 Persistència SIF

`InvoiceRepository::createInvoiceGraph()` persisteix:

- `factura`;
- `factura_linia`;
- `factura_registres`;
- actualització de `fiscal_chain_state`;
- `fiscal_queue`;
- `fact_rels`.

La migració base té `IDEMPOTENCY_KEY` i `NUM_VISIBLE` únics i una única seqüència per sèrie/any. En aquesta branca, `2026_09_29_000009_guard_uc004_inscription_coverage.sql` crea `invoice_before_payment_coverage`. `InvoiceBeforePaymentCoverageRepository` reclama els `SOURCE_ID` dins la mateixa transacció fiscal i la UNIQUE `uq_invoice_before_payment_source` impedeix dues claus **UC-004** diferents sobre el mateix origen. La migració també fa backfill de factures SIF existents `EMESA_ABANS_COBRAMENT=1` a partir de `fact_rels INSCRIPCIO/ORIGIN`; qualsevol doble reclamació històrica fa fallar el backfill i s'ha de reconciliar. No s'imposa una UNIQUE global a `fact_rels`, perquè la cobertura entre altres canals/pagadors pot tenir variants legítimes i continua pendent de classificació funcional.

### 4.5 Scripts específics UC-004

Existeixen:

- `sif/scripts/preview-invoice-before-payment.php`: dry-run del builder; no emet factura; refusa `SIF_ENV=production`.
- `sif/scripts/preflight-invoice-before-payment.php`: comprova entorn no productiu, connexió i taules/seed necessaris; no muta dades.
- `sif/scripts/process-invoice-before-payment.php`
- `sif/scripts/preflight-invoice-before-payment-from-legacy.php`
- `sif/scripts/preview-invoice-before-payment-from-legacy.php`
- `sif/scripts/process-invoice-before-payment-from-legacy.php`
- `sif/tests/Integration/InvoiceBeforePaymentLegacyPreparationServiceTest.php`
- `sif/tests/Integration/InvoiceBeforePaymentLegacyScriptsTest.php`: construeix `InvoiceBeforePaymentService` i emet via CLI en entorn no productiu; no registra pagament ni sincronitza llegat.

Això augmenta la maduresa del nucli, però no acredita el flux de la pantalla productiva.

## 5. Proves localitzades

| Prova | Cobertura observada al codi de test | Executada aquí? |
| --- | --- | --- |
| `InvoiceBeforePaymentServiceTest` | emissió sense cobrament; reintent; flags; rebuig de `payment`; relations obligatòries; origen INSCRIPCIO; deduplicació; conflicte de cobertura entre claus | **PASS històric PR #70 · rerun 04/10 pendent** |
| `InvoiceBeforePaymentFlowTest` | factura PENDING; cobrament posterior marca PAID sense nou registre fiscal | **PASS històric PR #70 · rerun 04/10 pendent** |
| `InvoiceBeforePaymentPreviewScriptTest` | preview CLI no emet | **PASS històric PR #70 · rerun 04/10 pendent** |
| `InvoiceBeforePaymentPreflightScriptTest` | preflight SIF no muta ni depèn de llegat/Redsys | **PASS històric PR #70 · rerun 04/10 pendent** |
| `InvoiceBeforePaymentPreproductionScriptTest` | processador CLI usa servei UC-004 i no registra payment | **PASS històric PR #70 · rerun 04/10 pendent** |
| `PayloadIdempotencyFlowTest` | flux de fingerprint/idempotència transversal | **PASS històric PR #70 · rerun 04/10 pendent** |

**Interpretació:** “test existent” ≠ “test executat” ≠ “integració productiva verificada”.

## 6. Matriu de traçabilitat UC-004

| Requisit / responsabilitat | Documentat | Implementat | Verificat estàtic | Prova executada | Pendent |
| --- | --- | --- | --- | --- | --- |
| Pantalla real UC-004 | Sí | **Sí, bridge SIF** | Sí | workflow específic | E2E/preproducció |
| Permís de visualització | Sí | Sí | Sí | No | prova endpoint |
| Autorització d'emissió al servidor | Sí | **Sí end-to-end al codi** · sessió/permís intranet + CSRF + HMAC + anti-replay + rol SIF | Sí | No | executar E2E/preproducció |
| Cerca inscripcions | Sí | **UI + loader servidor per IDs** | Sí | workflow/tests | E2E/preproducció |
| Deduplicació selecció | Sí FINAL | **Implementada al bridge + loader servidor** | Sí | tests definits | evidència E2E |
| Validació curs/edició al servidor | Sí FINAL | **Implementada a l'assembler servidor** | Sí | tests definits | evidència E2E |
| Receptor per ID intern | Sí FINAL | **Implementat i consumit per la UI** | Sí | tests definits | evidència E2E |
| Recalcular línies/total al servidor | Sí FINAL | **Implementat amb `A_PAGAR` autoritatiu** | Sí | tests definits | classificador fiscal/comercial transversal |
| Emissió sense payment | Sí | **Sí, SIF + pantalla integrada** | Sí | tests definits | E2E/preproducció |
| Idempotència mateixa clau/payload | Sí | Sí, SIF | Sí | No | executar proves |
| Conflicte mateixa clau/payload diferent | Sí | Sí, SIF | Sí | No | executar proves |
| Cobertura entre claus UC-004 diferents | Sí FINAL | **Implementada en aquesta branca amb claim transaccional específic** | Sí | No | executar migració/proves; cobertura transversal entre canals continua pendent |
| Seqüència fiscal segura | Sí | **Sí, SIF usat des de la UI** | Sí | suite | preproducció |
| Cadena / registre / cua | Sí | **Sí, SIF usat des de la UI** | Sí | suite | preproducció |
| `fact_rels` | Sí | **Sí, `INSCRIPCIO/ORIGIN` obligatori** | Sí | tests definits | preproducció/backfill |
| `operational_event` / `sif_audit_event` | Sí | **IMPLEMENTATS AL MAIN dins la mateixa transacció** com `ISSUE_INVOICE` | Sí | suite global històrica | inspeccionar evidència real d'entorn |
| Cobrament posterior separat | Sí | Sí, serveis SIF | Sí | No | integrar canal |
| Preview segur abans d'emetre | Sí FINAL | **Implementat en CLI + HTTP + pantalla** amb fingerprint, relectura i comprovació prèvia de coverage UC-004 | Sí | No | executar E2E |
| Document per UUID | Sí FINAL | **NO IMPLEMENTAT EN EL MAIN UC-004** | Sí com a disseny | No | job/worker/snapshot/storage + renderer PDF/QR/XML + E2E |
| Sincronització llegada post-commit | Sí FINAL | processador UC-004 diu que no la fa | Sí | No | decidir/implementar |
| Preproducció | Sí | scripts disponibles, però `InvoiceService` exigeix `aeat_fields` en PREPROD/PROD | estàtic | No | **construir snapshot AEAT oficial + executar i evidenciar** |

## 7. Mancances prioritzades

### P0 — bloquegen integració segura/fiscal

| ID | Mancança | Evidència / impacte |
| --- | --- | --- |
| UC004-GAP-001 | **TANCAT AL CODI:** pantalla real connectada al SIF | `alumnes-genera-factura-abans-pagar.js` usa `sifFacturaAbansPagar.php`; el mutador llegat queda 410 |
| UC004-GAP-002 | **TANCAT AL CODI:** autorització d'acció fiscal | `SifInvoiceBeforePaymentAccess` refresca rols vigents i exigeix permís d'edició; `InternalInvoiceBeforePaymentScopeResolver` torna a exigir rol SIF |
| UC004-GAP-003 | **TANCAT AL CODI:** CSRF + contracte intern signat | la UI envia `X-CSRF-Token` al bridge; el bridge signa HMAC amb secret només servidor i el SIF aplica anti-replay |
| UC004-GAP-004 | **TANCAT AL CODI:** receptor per `entityId` | la UI carrega entitats amb ID intern i el backend resol el snapshot fiscal autoritatiu |
| UC004-GAP-005 | **TANCAT AL CODI:** total reconstruït des del servidor | el JS mostra el total del preview SIF; `concepte1` i `preu` són només lectura i no són autoritat fiscal |
| UC004-GAP-006 | **TANCAT AL CODI:** reconstrucció autoritativa | la UI només envia IDs/entityId/observacions; selecció, curs, receptor, línies i total es rellegeixen/recalculen al servidor |
| UC004-GAP-007 | **TANCAT AL JS ACTUAL:** selecció recalculada | `selectedInscriptionIds()` llegeix el DOM, deduplica i ordena abans de preview/confirm |
| UC004-GAP-008 | **TANCAT AL BACKEND:** deduplicació d'IDs | `InvoiceBeforePaymentSelectionRepository` rebutja IDs duplicats abans de consultar |
| UC004-GAP-009 | **TANCAT AL BACKEND + UI SEGURA:** mateix curs/edició revalidat | l'assembler rebutja seleccions mixtes i la UI consumeix el preview autoritatiu |
| UC004-GAP-010 | **TANCAT PER DOBLE EMISSIÓ UC-004:** preview + guard transaccional | `preview` consulta `invoice_before_payment_coverage` i falla 409 si ja hi ha claim; `confirm` manté UNIQUE `uq_invoice_before_payment_source` dins la transacció. Un retry de la mateixa K reutilitza UUID; la cobertura transversal contra altres canals continua oberta |
| UC004-GAP-011 | Numeració llegada amb “últim + 1” | risc concurrent; s'ha de substituir per seqüència SIF |
| UC004-GAP-012 | ID de factura llegada amb “últim + 1” | risc concurrent independent del número fiscal |
| UC004-GAP-013 | INSERT factura + UPDATEs d'inscripcions no formen una transacció observada | possible estat parcial |
| UC004-GAP-014 | Cap idempotency key al circuit llegat | doble clic/reintent pot crear una altra factura |
| UC004-GAP-015 | El camí llegat no crea registre fiscal encadenat/cua SIF | bloqueja adopció FINAL |
| UC004-GAP-016 | L'endpoint genèric SIF `issue.php` no força semàntica UC-004 | usa `InvoiceService` directament, no el builder UC-004 |
| UC004-GAP-017 | **TANCAT PER AL SERVEI UC-004:** `InvoiceBeforePaymentPayloadBuilder` exigeix relations `INSCRIPCIO/ORIGIN` úniques | l'endpoint genèric `issue.php` continua sent genèric i no substitueix l'adaptador UC-004 |
| UC004-GAP-018 | **TANCAT AL MAIN:** auditoria transaccional | `InvoiceService` escriu `operational_event` + `sif_audit_event` com `ISSUE_INVOICE`; el reús idempotent queda auditat com `INVOICE_IDEMPOTENCY_REUSED` |
| UC004-GAP-019 | Validació fiscal definitiva no està tota a `InvoicePayloadValidator` | adreça/país/règims/causes/consistència de línies necessiten contracte final |
| UC004-GAP-020 | Cobrament posterior encara no està connectat des de la pantalla UC-004 al UUID SIF | servei/prova existeixen, integració UI no acreditada |

### P1 — robustesa, documents i operació

| ID | Mancança | Evidència / impacte |
| --- | --- | --- |
| UC004-GAP-021 | **TANCAT AL CODI:** concepte de convocatòria determinista | el preview SIF omple els camps només lectura; ja no usa `calcularTextData.php` |
| UC004-GAP-022 | **TANCAT AL CODI:** entitat resolta per ID | `sifFacturaAbansPagarEntitats.php` carrega IDs interns i el repositori SIF valida entitat/responsable |
| UC004-GAP-023 | **TANCAT AL CODI:** resposta JSON tipificada | la pantalla consumeix JSON del bridge i renderitza el resultat SIF amb UUID/número/PENDING |
| UC004-GAP-024 | **PARCIAL:** request, correlació i versió | `InternalApiAuthenticator` registra `request_id`; aquesta branca fixa `contract_version=UC004-V1`. El `request_id` encara no es propaga al payload fiscal UC-004, així que `InvoiceService` usa la clau idempotent com a fallback de request/correlation |
| UC004-GAP-025 | **DECISIÓ TANCADA:** no crear factura shadow llegada ni falsificar `FACTURA_RELACIONADA` | `FACTURA_RELACIONADA` és un ID enter de `factures` llegades; representar-hi un UUID/sentinel reintroduiria doble autoritat. Les lectures que encara ho necessitin s'han d'adaptar a SIF/read-model; només projeccions no fiscals i idempotents són admissibles |
| UC004-GAP-026 | PDF llegat es regenera des de dades vives | no és custòdia immutable per snapshot/UUID |
| UC004-GAP-027 | Descàrrega marca `GENERAT` com a efecte lateral | lectura/descàrrega no hauria de redefinir estat fiscal |
| UC004-GAP-028 | Eliminació de fitxer per `unlink(filename)` rebut per GET | cal eliminar aquesta superfície o restringir-la estrictament |
| UC004-GAP-029 | Text d'exempció IVA codificat al PDF llegat | el document FINAL ha de sortir del snapshot fiscal |
| UC004-GAP-030 | `generaFactura()` reinicialitza `$mostrar` després de preparar l'obertura HTML de descàrrega | revisar generació documental llegada abans de donar-la per estable |
| UC004-GAP-031 | E_FACT llegat es posa a 1 mentre `InvoiceRepository` SIF insereix E_FACT=0 | cal documentar la semàntica/mapeig, no copiar flags a cegues |
| UC004-GAP-032 | **OBERT:** estat documental post-COMMIT | el `main` UC-004 no té `document_job`/worker/snapshot/storage; el codi del PR #134 no es considera implementació vigent |

### P2 — evidència i tancament

| ID | Mancança | Evidència / impacte |
| --- | --- | --- |
| UC004-GAP-033 | Evidència CI del tall 04/10 | PR #70 és evidència històrica verda; PR #134 no és green global; falta resultat del HEAD d'aquesta branca |
| UC004-GAP-034 | Preflight/migració no s'han executat contra entorn objectiu | falta evidència BD, backfill `invoice_before_payment_coverage` i detecció/reconciliació de possibles duplicats UC-004 històrics |
| UC004-GAP-035 | Processador CLI refusa production | és útil per preproducció, no acredita desplegament productiu |
| UC004-GAP-036 | No hi ha prova end-to-end pantalla → adaptador → SIF → document | principal criteri de tancament |
| UC004-GAP-037 | No hi ha prova end-to-end UC-004 → cobrament posterior real de canal | test de servei no substitueix canal |
| UC004-GAP-038 | No hi ha evidència de dos operadors concurrents a la pantalla | cal provar numeració/idempotència real |
| UC004-GAP-039 | **TANCAT AL CODI UI + SIF:** optimistic concurrency per fingerprint | la UI guarda el fingerprint del preview i la confirmació rellegeix servidor i exigeix coincidència |

### P0 addicional detectat el 04/10

| ID | Mancança | Evidència / impacte |
| --- | --- | --- |
| UC004-GAP-040 | **`aeat_fields` oficials no construïts per UC-004** | `InvoiceService::issueInvoice()` falla tancat en PREPROD/PROD quan falta `aeat_fields`; l'assembler UC-004 actual no els afegeix. Bloqueja desplegament qualificat fins implementar un assembler AEAT server-side amb emissor/SistemaInformatico i desglossament fiscal oficial. |

## 8. Fitxers UML revisats/creats

- `documentacio/07-uml-integrat/uc-004-cas-us-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-classes-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-sequencies-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-activitats-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-inventari-artefactes.md`
- aquest fitxer de traçabilitat i mancances.

El fitxer existent `uc-004-emetre-factura-abans-cobrar.md` continua sent una bona síntesi integrada, però ja no ha d'assumir la càrrega d'explicar per si sol totes les diferències ACTUAL/FINAL.

## 9. Proves mínimes per passar de PARCIAL a VERIFICAT

1. Invocació directa del mutador actual sense permís: ha de quedar bloquejada.
2. Doble clic i retry exacte: mateix UUID/número.
3. Mateixa clau amb receptor/import/línies diferents: CONFLICT.
4. Clau diferent sobre una inscripció ja coberta: CONFLICT/NEEDS_REVIEW.
5. Tornar enrere i seleccionar de nou: IDs únics.
6. DOM manipulat amb preu diferent: el servidor recalcula i no l'accepta.
7. Entitat visible manipulada: el servidor resol per ID i snapshot.
8. Dos operadors concurrents: una sola emissió vàlida per operació.
9. Error després del COMMIT però abans del PDF: factura conserva UUID/número i document es reprèn.
10. AEAT/doc fallits: no reemissió.
11. Cobrament posterior: `PaymentService` sobre UUID existent, cap segon registre ALTA.
12. Reconciliació llegada: repetible i recuperable després del COMMIT SIF.
13. Auditoria: actor, request/correlation, snapshots i resultat reconstruïbles.
14. Endpoint/document: sense eliminació arbitrària de paths.
15. Preflight + suite UC-004 + E2E en preproducció amb evidència conservada.

## 10. Ordre recomanat d'implementació

1. **FET AL MAIN:** bridge intranet + endpoint UC-004 amb sessió/rol, CSRF, HMAC, anti-replay, request ID i resposta JSON.
2. **FET AL MAIN:** Selection/Billing/Pricing preflight amb `InvoiceBeforePaymentSelectionRepository`, `InvoiceBeforePaymentBillingPartyRepository`, `InvoiceBeforePaymentServerPayloadAssembler` i `InvoiceBeforePaymentLegacyPreparationService`.
3. **FET EN AQUESTA BRANCA:** el preview consulta coverage UC-004 abans de retornar una confirmació; el confirm manté el guard UNIQUE transaccional i els retries idempotents reutilitzen UUID.
4. **FET AL CODI VERSIONAT:** pantalla connectada al command intern i mutador fiscal llegat retirat amb `410 Gone`.
5. **FET AL MAIN:** auditoria operacional + `sif_audit_event` atòmics de l'emissió.
6. **DECIDIT:** no crear factura shadow ni sentinel a `FACTURA_RELACIONADA`; adaptar read-models llegats a SIF quan calgui.
7. **P0 PENDENT:** construir `aeat_fields` oficials server-side per UC-004 abans de PREPROD/PROD.
8. **PENDENT:** circuit documental SIF per UUID + renderer PDF/QR/XML.
9. Connectar cobrament posterior al UUID, sense reemetre.
10. Executar proves i preflight/preproducció; conservar evidències.
11. La fitxa funcional està consolidada; marcar UC-004 com verificat només després de l'E2E.

## 11. Artefactes relacionats

- `documentacio/06-fitxes-funcionals/uc-004.md`
- `documentacio/07-uml-integrat/uc-004-emetre-factura-abans-cobrar.md`
- `documentacio/07-uml-integrat/uc-004-cas-us-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-classes-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-sequencies-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-activitats-actual-final.md`
- `documentacio/07-uml-integrat/uc-004-inventari-artefactes.md`
- `sif/src/Service/InvoiceBeforePaymentService.php`
- `sif/src/Service/InvoiceBeforePaymentPayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`
- `sif/src/Service/PayloadIdempotencyValidator.php`
- `sif/src/Repository/InvoiceRepository.php`
- `sif/src/Repository/InvoiceBeforePaymentCoverageRepository.php`
- `sif/src/Repository/InvoiceBeforePaymentSelectionRepository.php`
- `sif/src/Repository/InvoiceBeforePaymentBillingPartyRepository.php`
- `sif/src/Service/InvoiceBeforePaymentServerPayloadAssembler.php`
- `sif/src/Service/InvoiceBeforePaymentLegacyPreparationService.php`
- `sif/src/Service/InvoiceBeforePaymentCommandService.php`
- `sif/src/Service/InternalApiAuthenticator.php`
- `sif/src/Service/InternalInvoiceBeforePaymentScopeResolver.php`
- `sif/src/Repository/InternalApiRequestRepository.php`
- `sif/src/Repository/OperationalEventRepository.php`
- `sif/public/api/factures/before-payment.php`
- `sif/database/migrations/2026_09_29_000009_guard_uc004_inscription_coverage.sql`
- `sif/scripts/preview-invoice-before-payment.php`
- `sif/scripts/preflight-invoice-before-payment.php`
- `sif/scripts/process-invoice-before-payment.php`
- `sif/tests/Integration/InvoiceBeforePaymentServiceTest.php`
- `sif/tests/Integration/InvoiceBeforePaymentFlowTest.php`
- `sif/tests/Integration/InvoiceBeforePaymentPreviewScriptTest.php`
- `sif/tests/Integration/InvoiceBeforePaymentPreflightScriptTest.php`
- `sif/tests/Integration/InvoiceBeforePaymentPreproductionScriptTest.php`

**Criteri final d'auditoria:** la pantalla ja usa la ruta SIF segura i, en aquesta branca, l'endpoint vell queda 410. UC-004 queda **documentalment auditat però no operativament verificat**: falta CI del HEAD 04/10, `aeat_fields` oficials per PREPROD/PROD, document SIF per UUID, E2E/concurrència i cobertura transversal/sync que correspongui.
