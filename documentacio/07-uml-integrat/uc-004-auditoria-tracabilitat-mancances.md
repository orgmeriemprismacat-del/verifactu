# UC-004 · Auditoria detallada, traçabilitat i mancances

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Data de tall actualitzada:** 2026-10-02  
**Branca de continuació:** `feat/uc-004-adaptador-servidor-2026-10-02`  
**Tipus de verificació:** revisió estàtica del codi i documentació versionats. No s'han executat proves ni s'ha verificat preproducció/producció.

## 1. Conclusió de l'auditoria

UC-004 té **dos circuits diferents** que no s'han de fusionar documentalment:

1. **ACTUAL llegat, implementat:** la pantalla `/alumnes/genera-factura-abans-pagar/` treballa amb JS + endpoints AJAX + `Intranet.php` + BD llegada. Genera la factura directament al model antic, assigna un número amb “últim + 1”, actualitza inscripcions i construeix/descarrega el PDF des del circuit llegat.
2. **FINAL SIF, implementació avançada:** a més del nucli fiscal, el `main` actual ja conté `InvoiceBeforePaymentCommandService`, preparació autoritativa des de les dues BDs llegades, endpoint `public/api/factures/before-payment.php`, autenticació HMAC, anti-replay de `request_id`, resolució de rol d'escriptura, preview/confirmació amb fingerprint i cobertura UC-004. Aquesta branca hi afegeix `operational_event` atòmic. **La pantalla real encara no està connectada a aquest endpoint i continua emetent pel circuit llegat.**

Per tant, l'estat correcte del cas és:

- **Documentat:** SÍ, ara amb ACTUAL/FINAL i activitats separades.
- **Implementat ACTUAL:** SÍ, circuit llegat.
- **Implementat backend FINAL SIF:** SÍ per command intern, autenticació, preview/confirmació, emissió, cobertura i auditoria operacional en aquesta branca.
- **Integrat pantalla → FINAL:** NO; el bridge de la intranet llegada continua pendent.
- **Verificat estàticament:** SÍ.
- **Proves executades en aquesta auditoria:** NO.
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
6. `operational_event` queda **implementat en aquesta branca** dins de la mateixa transacció de l'emissió UC-004. `sif_audit_event`, si es manté com a requisit separat, s'ha de justificar contra el model real perquè no s'ha localitzat com a taula/writer específic d'aquest flux.
7. La fitxa funcional 2.0 d'aquesta branca ja incorpora els scripts UC-004 de preview/preflight/process, les proves de flow/preproducció i els nous guards de cobertura.
8. L'endpoint genèric `issue.php` continua sense ser UC-004, però el `main` ja disposa de l'endpoint específic `sif/public/api/factures/before-payment.php`, que sí passa per `InvoiceBeforePaymentCommandService` i `InvoiceBeforePaymentService`.

**Decisió documental d'aquesta branca:** no s'ha sobreescrit la fitxa existent; les correccions queden recollides en aquest dossier perquè es puguin revisar abans d'una consolidació posterior.

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
- En continuar, reinicia `cursos`, `edicions` i `preuTotal`, però `idsInsc` és global i no s'ha observat un reset equivalent en aquest punt.
- El total es calcula sumant `parseFloat($('#apagar-'+idInsc).html())`.
- El text de convocatòria depèn de crides AJAX a `calcularTextData.php`.

### 3.3 Emissió llegada

`generaFacturaElectronica_Factures.php` rep el POST i invoca:

`Intranet::generarFacturaElectronica_Alumnes($empresa, $concepte1, $concepte2, $preu, $cursos, $edicions, $inscripcions, $observacions)`.

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
| `InvoiceBeforePaymentServiceTest` | emissió sense cobrament; reintent; flags; rebuig de `payment`; relations obligatòries; origen INSCRIPCIO; deduplicació; conflicte de cobertura entre claus | NO |
| `InvoiceBeforePaymentFlowTest` | factura PENDING; cobrament posterior marca PAID sense nou registre fiscal | NO |
| `InvoiceBeforePaymentPreviewScriptTest` | preview CLI no emet | NO |
| `InvoiceBeforePaymentPreflightScriptTest` | preflight SIF no muta ni depèn de llegat/Redsys | NO |
| `InvoiceBeforePaymentPreproductionScriptTest` | processador CLI usa servei UC-004 i no registra payment | NO |
| `PayloadIdempotencyFlowTest` | flux de fingerprint/idempotència transversal | NO |

**Interpretació:** “test existent” ≠ “test executat” ≠ “integració productiva verificada”.

## 6. Matriu de traçabilitat UC-004

| Requisit / responsabilitat | Documentat | Implementat | Verificat estàtic | Prova executada | Pendent |
| --- | --- | --- | --- | --- | --- |
| Pantalla real UC-004 | Sí | Llegat | Sí | No | migrar adaptador |
| Permís de visualització | Sí | Sí | Sí | No | prova endpoint |
| Autorització d'emissió al servidor | Sí | **Sí al SIF intern** · HMAC + anti-replay + rol | Sí | No | bridge intranet + execució de proves |
| Cerca inscripcions | Sí | Llegat + **loader servidor nou a la branca** | Sí | No | connectar UI |
| Deduplicació selecció | Sí FINAL | **Implementada al loader servidor** | Sí | No | connectar UI / executar tests |
| Validació curs/edició al servidor | Sí FINAL | **Implementada a l'assembler servidor** | Sí | No | connectar UI / executar tests |
| Receptor per ID intern | Sí FINAL | **Implementat a la branca** | Sí | No | connectar UI / executar tests |
| Recalcular línies/total al servidor | Sí FINAL | **Implementat parcialment amb A_PAGAR autoritatiu** | Sí | No | classificador fiscal/comercial transversal + UI |
| Emissió sense payment | Sí | Sí, SIF | Sí | No | integrar pantalla |
| Idempotència mateixa clau/payload | Sí | Sí, SIF | Sí | No | executar proves |
| Conflicte mateixa clau/payload diferent | Sí | Sí, SIF | Sí | No | executar proves |
| Cobertura entre claus UC-004 diferents | Sí FINAL | **Implementada en aquesta branca amb claim transaccional específic** | Sí | No | executar migració/proves; cobertura transversal entre canals continua pendent |
| Seqüència fiscal segura | Sí | Sí, SIF | Sí | No | usar SIF des UI |
| Cadena / registre / cua | Sí | Sí, SIF | Sí | No | usar SIF des UI |
| `fact_rels` | Sí | Sí | Sí | No | **UC-004 ja exigeix relations INSCRIPCIO/ORIGIN; falta executar proves** |
| `operational_event` UC-004 | Sí | **IMPLEMENTAT EN AQUESTA BRANCA dins la mateixa transacció** | Sí | No | executar suite i inspeccionar event |
| Cobrament posterior separat | Sí | Sí, serveis SIF | Sí | No | integrar canal |
| Preview segur abans d'emetre | Sí FINAL | **Implementat en CLI i endpoint HTTP intern** amb fingerprint + relectura | Sí | No | connectar pantalla / executar E2E |
| Document per UUID | Sí FINAL | infraestructura SIF a revisar | parcial | No | integrar UC-004 |
| Sincronització llegada post-commit | Sí FINAL | processador UC-004 diu que no la fa | Sí | No | decidir/implementar |
| Preproducció | Sí | scripts disponibles | estàtic | No | **executar i evidenciar** |

## 7. Mancances prioritzades

### P0 — bloquegen integració segura/fiscal

| ID | Mancança | Evidència / impacte |
| --- | --- | --- |
| UC004-GAP-001 | Pantalla real no connectada a `InvoiceBeforePaymentService` | el POST actual entra a `Intranet::generarFacturaElectronica_Alumnes` |
| UC004-GAP-002 | **TANCAT A LA FRONTERA SIF / PENDENT BRIDGE UI:** autorització d'acció fiscal | `InternalApiAuthenticator` autentica actor i `InternalInvoiceBeforePaymentScopeResolver` exigeix rol d'escriptura; el POST llegat encara no usa aquesta frontera |
| UC004-GAP-003 | **TANCAT COM A CONTRACTE INTERN SIF / PENDENT INTRANET:** el command no és browser-direct | HMAC sobre cos exacte + timestamp + request UUID + anti-replay substitueixen CSRF a la frontera servidor-servidor; cal que la pantalla passi per un bridge autenticat de la intranet |
| UC004-GAP-004 | **TANCAT AL BACKEND / PENDENT UI:** receptor per `entityId` | `InvoiceBeforePaymentBillingPartyRepository::loadByEntityId()` resol entitat + responsable actiu; la pantalla actual encara envia text |
| UC004-GAP-005 | **TANCAT AL BACKEND / PENDENT UI:** total reconstruït des del servidor | `InvoiceBeforePaymentServerPayloadAssembler` suma `inscripcions.A_PAGAR`; la pantalla llegada encara calcula `preuTotal` al DOM |
| UC004-GAP-006 | **TANCAT AL BACKEND / PENDENT UI:** reconstrucció autoritativa | `InvoiceBeforePaymentSelectionRepository` rellegeix IDs + curs; assembler genera conceptes/línies al servidor |
| UC004-GAP-007 | `idsInsc` pot conservar valors entre recorreguts | global; no s'ha observat reset al pas de recomputació |
| UC004-GAP-008 | **TANCAT AL BACKEND:** deduplicació d'IDs | `InvoiceBeforePaymentSelectionRepository` rebutja IDs duplicats abans de consultar |
| UC004-GAP-009 | **TANCAT AL BACKEND:** mateix curs/edició revalidat | `InvoiceBeforePaymentServerPayloadAssembler` rebutja seleccions mixtes; pendent integrar UI |
| UC004-GAP-010 | **TANCAT PER DOBLE EMISSIÓ UC-004:** guard entre claus idempotents diferents | `invoice_before_payment_coverage` + `InvoiceBeforePaymentCoverageRepository` + UNIQUE `uq_invoice_before_payment_source`; la cobertura transversal contra factures d'altres canals continua oberta |
| UC004-GAP-011 | Numeració llegada amb “últim + 1” | risc concurrent; s'ha de substituir per seqüència SIF |
| UC004-GAP-012 | ID de factura llegada amb “últim + 1” | risc concurrent independent del número fiscal |
| UC004-GAP-013 | INSERT factura + UPDATEs d'inscripcions no formen una transacció observada | possible estat parcial |
| UC004-GAP-014 | Cap idempotency key al circuit llegat | doble clic/reintent pot crear una altra factura |
| UC004-GAP-015 | El camí llegat no crea registre fiscal encadenat/cua SIF | bloqueja adopció FINAL |
| UC004-GAP-016 | L'endpoint genèric SIF `issue.php` no força semàntica UC-004 | usa `InvoiceService` directament, no el builder UC-004 |
| UC004-GAP-017 | **TANCAT PER AL SERVEI UC-004:** `InvoiceBeforePaymentPayloadBuilder` exigeix relations `INSCRIPCIO/ORIGIN` úniques | l'endpoint genèric `issue.php` continua sent genèric i no substitueix l'adaptador UC-004 |
| UC004-GAP-018 | **TANCAT EN AQUESTA BRANCA PER `operational_event`** | `InvoiceService` escriu `ISSUE_INVOICE_BEFORE_PAYMENT` dins la mateixa transacció; falta només executar proves/evidència i decidir si cal algun segon artefacte `sif_audit_event` separat |
| UC004-GAP-019 | Validació fiscal definitiva no està tota a `InvoicePayloadValidator` | adreça/país/règims/causes/consistència de línies necessiten contracte final |
| UC004-GAP-020 | Cobrament posterior encara no està connectat des de la pantalla UC-004 al UUID SIF | servei/prova existeixen, integració UI no acreditada |

### P1 — robustesa, documents i operació

| ID | Mancança | Evidència / impacte |
| --- | --- | --- |
| UC004-GAP-021 | **TANCAT AL BACKEND / PENDENT UI:** concepte de convocatòria determinista | assembler genera `Convocatòria <mes> <any>` sense AJAX |
| UC004-GAP-022 | **TANCAT AL BACKEND / PENDENT UI:** entitat resolta per ID | repositori nou no usa `RAO LIKE`; exigeix responsable actiu |
| UC004-GAP-023 | **TANCAT A LA FRONTERA SIF / PENDENT UI:** resposta JSON tipificada | `before-payment.php` retorna JSON; la pantalla llegada continua esperant HTML i encara no s'ha migrat |
| UC004-GAP-024 | **PARCIALMENT TANCAT AL SIF:** request i correlació | `InternalApiAuthenticator` registra `request_id`; l'event operacional usa `UC004:sha256(idempotency_key)` com a correlació estable i conserva la clau original dins l'snapshot. Continua pendent versionar explícitament el command/bridge si es considera necessari |
| UC004-GAP-025 | Sincronització llegada posterior al COMMIT SIF no implementada al processador UC-004 | el script ho evita explícitament |
| UC004-GAP-026 | PDF llegat es regenera des de dades vives | no és custòdia immutable per snapshot/UUID |
| UC004-GAP-027 | Descàrrega marca `GENERAT` com a efecte lateral | lectura/descàrrega no hauria de redefinir estat fiscal |
| UC004-GAP-028 | Eliminació de fitxer per `unlink(filename)` rebut per GET | cal eliminar aquesta superfície o restringir-la estrictament |
| UC004-GAP-029 | Text d'exempció IVA codificat al PDF llegat | el document FINAL ha de sortir del snapshot fiscal |
| UC004-GAP-030 | `generaFactura()` reinicialitza `$mostrar` després de preparar l'obertura HTML de descàrrega | revisar generació documental llegada abans de donar-la per estable |
| UC004-GAP-031 | E_FACT llegat es posa a 1 mentre `InvoiceRepository` SIF insereix E_FACT=0 | cal documentar la semàntica/mapeig, no copiar flags a cegues |
| UC004-GAP-032 | Estat documental després de COMMIT no està integrat a la pantalla | cal READY/PENDING/ERROR sense reemetre |

### P2 — evidència i tancament

| ID | Mancança | Evidència / impacte |
| --- | --- | --- |
| UC004-GAP-033 | Proves UC-004 existents no executades en aquesta auditoria | falta evidència real PASS/FAIL |
| UC004-GAP-034 | Preflight/migració no s'han executat contra entorn objectiu | falta evidència BD, backfill `invoice_before_payment_coverage` i detecció/reconciliació de possibles duplicats UC-004 històrics |
| UC004-GAP-035 | Processador CLI refusa production | és útil per preproducció, no acredita desplegament productiu |
| UC004-GAP-036 | No hi ha prova end-to-end pantalla → adaptador → SIF → document | principal criteri de tancament |
| UC004-GAP-037 | No hi ha prova end-to-end UC-004 → cobrament posterior real de canal | test de servei no substitueix canal |
| UC004-GAP-038 | No hi ha evidència de dos operadors concurrents a la pantalla | cal provar numeració/idempotència real |
| UC004-GAP-039 | **TANCAT EN EL FLUX CLI / PENDENT UI:** optimistic concurrency per fingerprint | preview calcula SHA-256 del payload; confirmació rellegeix ambdues BDs i exigeix `hash_equals(expected,current)` abans d'emetre |

## 8. Fitxers UML que faltaven i s'han creat en aquesta branca

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

1. **FET AL MAIN A LA FRONTERA SIF:** endpoint UC-004 amb HMAC, anti-replay, rol, request ID i resposta JSON. **Pendent:** bridge servidor de la intranet real.
2. **FET AL MAIN:** Selection/Billing/Pricing preflight amb `InvoiceBeforePaymentSelectionRepository`, `InvoiceBeforePaymentBillingPartyRepository`, `InvoiceBeforePaymentServerPayloadAssembler` i `InvoiceBeforePaymentLegacyPreparationService`; pendent substituir el circuit llegat de pantalla.
3. **Guard de cobertura UC-004:** implementat amb validació del builder + taula/UNIQUE específica; aplicar-lo en test/preproducció. Afegir separadament el classificador de cobertura transversal entre canals/pagadors.
4. Connectar la pantalla al **command intern ja existent** i eliminar la numeració/inserció fiscal llegada del camí d'escriptura.
5. **FET EN AQUESTA BRANCA:** auditoria operacional atòmica de l'emissió.
6. Sincronització llegada post-commit, si encara és necessària, idempotent i observable.
7. Document per UUID/snapshot, sense `unlink(filename)` exposat.
7. Connectar cobrament posterior al UUID, sense reemetre.
8. Executar proves i preflight/preproducció; conservar evidències.
9. La fitxa funcional ja està consolidada en versió 2.0; marcar UC-004 com verificat només després de l'E2E.

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

**Criteri final d'auditoria:** UC-004 està prou definit documentalment per implementar la integració, però no s'ha de considerar tancat fins que la pantalla abandoni l'escriptura fiscal llegada i el camí complet fins al SIF sigui provat amb evidència.
