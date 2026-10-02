# UC-001 · Auditoria i traçabilitat — 2026-10-02

**Base de reconciliació:** `main` `549d7ef9280df3cd5249340e3785a4bf23a14b78`. Els 10 commits nous des de `f7fa0822f82be96e842d9f2d031e643ab07f617c` només afecten documentació/índex d’UC-008 i no canvien cap superfície d’UC-001.

## 1. Resultat executiu

**Estat de l’auditoria:** `TANCADA_AMB_EXCEPCIO_EXECUCIO_CI_BLOQUEJADA_EXTERNAMENT`. Totes les troballes de la revisió tenen resolució implementada o classificació explícita. Els elements marcats com a pendents a la taula són deutes d’implementació transversal/entorn i **no són zones no auditades**.

| Bloc | Documentat | Implementat després d'aquesta branca | Inspecció | Execució |
| --- | --- | --- | --- | --- |
| Idempotència mateixa clau/payload | Sí | Sí | Sí | Pendent CI |
| Clau idempotent no buida/compatible SQL | Implícit | Sí | Sí | Pendent CI |
| Autenticació/rol a `issue.php` | Sí | Sí | Sí | Pendent CI |
| `created_by` no suplantable al generic endpoint | Sí | Sí | Sí | Pendent CI |
| Evitar CHARGE Redsys fabricat al generic endpoint | Sí per arquitectura | Sí | Sí | Pendent CI |
| Coherència capçalera↔línies | Sí | Sí | Sí | Pendent CI |
| `fact_rels.ID_FACTURA_LINIA` | Esquema sí | Sí si origen unívoc | Sí | Pendent CI |
| Sèrie ordinària/rectificativa | Sí (`A` ordinària, `R` rectificativa) | Sí (`A+F1/F2`, `R+R1–R5`) | Sí | Pendent CI |
| Reús amb payment original desaparegut | Sí per postcondició econòmica | Fail-closed 409 | Sí | Pendent CI |
| Cobertura entre claus diferents | Sí | Parcial UC-004; no general | Sí | Pendent |
| `commercial_operation` obligatòria | Sí/esquema | No al nucli UC-001 | Sí | Pendent |
| Events d'auditoria funcionals | Sí | No complet | Sí | Pendent |
| Snapshot AEAT oficial automàtic | Sí | No a tots els canals | Sí | Pendent |
| Historial AEAT per intent | Sí | Sí (`aeat_submission_attempt`) | Sí | Cobert per suite CI existent; revalidació PR pendent |
| Resposta amb estats fiscal/econòmic/documental | Sí | No completa | Sí | Pendent |

## 2. Resoltes o endurides en aquesta branca

1. El generic endpoint reutilitza `InternalApiAuthenticator`, anti-replay i rol d'escriptura.
2. `created_by` es deriva de l'actor autenticat.
3. `method/source_channel=REDSYS` queda bloquejat al generic endpoint; Redsys conserva callback + worker.
4. `emesa_abans_cobrament` queda bloquejat al generic endpoint; UC-004 conserva el seu endpoint amb coverage repository.
5. Amb `aeat_fields`, `ObligadoEmision` es deriva de la configuració servidor.
6. Clau idempotent buida o >100 caràcters queda bloquejada abans de BD.
7. Es validen suma d'import base, base imposable, IVA i total entre capçalera i línies.
8. `fact_rels.ID_FACTURA_LINIA` s'emplena quan l'origen identifica una única línia.
9. El validador imposa coherència de família sèrie-tipus (`A` amb `F1/F2`; `R` amb `R1…R5`) sense pretendre decidir el tipus rectificatiu concret.
10. El payload AEAT oficial rebutja l'emissor placeholder `G00000000`; `preflight-invoice-issue.php` exposa només booleans de readiness i comprova auth/rol/emissor/anti-replay/taules abans del desplegament.

## 3. Deutes classificats després de l’auditoria — no són feina d’auditoria desconeguda

No s'han modificat sense contracte suficient:

- any fiscal de curs/pack/grup versus any acadèmic;
- combinacions exactes de sèrie/tipus per cada variant;
- generació completa d'`aeat_fields` pels builders comercials i transició de cadena interna a oficial;
- cobertura comercial general entre dues claus diferents;
- `commercial_operation` i `operation_line_invoice_link` obligatoris;
- `operational_event`, `sif_audit_event`, `factura_registre_control` i correlació;
- el fencing de la cua AEAT, `aeat_submission_attempt` i el tractament de resultat remot incert **ja existeixen** (`CLAIM_TOKEN`, estat `REVIEW`, intent `UNCERTAIN` i reconciliació sense reenviament); no són pendents d’UC-001;
- reparació/reconciliació operativa d'una dada inconsistent quan falta el `payment` original; el reús ja falla tancat i no la maquilla com a èxit;
- resposta enriquida amb estats AEAT/cobrament/document.

## 4. Proves incorporades

- `InvoicePayloadValidatorTest`: clau buida, clau massa llarga, canal buit i incoherències d'import base/base imposable/IVA/total.
- `InternalInvoiceIssueScopeResolverTest`: rol permès, denegat i fail-closed.
- `InternalInvoiceIssuePayloadPolicyTest`: actor/emissor servidor, bloqueig Redsys i UC-004.
- `InvoiceIssueHttpEndpointTest`: body cru signat i frontera interna.
- `InvoiceIssuePreflightScriptTest`: preflight CLI, HMAC/rol/emissor real, taules necessàries, cap mutació ni exposició del secret.
- `IssueInvoiceTest`: `fact_rels.ID_FACTURA_LINIA` coincideix amb la línia fiscal d'origen i queda `NULL` quan l'origen és ambigu.
- `PayloadIdempotencyFlowTest`: reús amb `payment` original desaparegut falla tancat amb CONFLICT.

## 5. Criteri de tancament

L'auditoria UC-001 es considera tancada quan el head de codi d'aquesta revisió passa CI. El tancament significa que l'abast ha estat inspeccionat, les troballes pròpies s'han corregit quan hi havia contracte suficient i la resta ha quedat classificada. **No significa** desplegament, homologació AEAT ni finalització dels UCs/transversals relacionats.

### 5.1. Bloquejadors de l'auditoria

- [x] Fitxa funcional reconciliada.
- [x] Inventari PHP/JS.
- [x] Classes ACTUAL/FINAL.
- [x] Seqüències ACTUAL/FINAL.
- [x] Activitats/superfícies ACTUAL/FINAL.
- [x] Idempotència/payload contrastada.
- [x] Frontera HTTP autenticada i autoritzada.
- [x] Coherència monetària i persistència d'exempció revisades.
- [x] Troballes restants classificades per frontera.
- [!] Gate CI del head de codi — **BLOQUEJAT EXTERNAMENT**: 1.027 runs del repositori constaven `queued` en la comprovació final del 02/10/2026; els quatre workflows del head UC-001 no havien començat.

### 5.2. No bloqueja el tancament de l'auditoria, però sí altres fases

La integració obligatòria de `commercial_operation`, els events funcionals propis de l’emissió, l’assembler AEAT complet, el resultat enriquit i la configuració/preproducció continuen oberts com a **deute implementatiu o operatiu explícit**. El fencing i la gestió d’incertesa/reconciliació AEAT ja estan implementats i no es mantenen com a fals pendent. No s'han silenciat ni declarat implementats.


### 5.3. Excepció de tancament — execució CI

A data 02/10/2026, GitHub Actions acumula **1.027 runs en estat `queued`** al repositori. Els workflows del head UC-001 (`SIF checks`, `SIF PHP MySQL tests`, `UC-004 SIF secure flow checks` i `UC-111 integration verification`) estan en cua i no han produït cap conclusió. Per tant:

- l’auditoria de codi/documentació es tanca perquè l’abast és complet i les troballes estan resoltes o classificades;
- **no** es declara la suite executada ni verda;
- el PR queda obert i mergeable a nivell Git, però la decisió de merge queda condicionada a evidència d’execució posterior;
- qualsevol fallada futura del CI reobre només la troballa/prova afectada, no converteix retroactivament l’evidència pendent en evidència verda.

## 6. Matriu detallada de troballes 51–92

| ID | Troballa | Tractament a la branca | Estat |
| --- | --- | --- | --- |
| F-051 | `fact_rels.ID_FACTURA_LINIA` no s'emplenava. | Enllaç per `SOURCE_TYPE/SOURCE_ID` només quan la línia és unívoca. | **Corregit per codi; pendent CI** |
| F-052 | USOC pot tenir obligacions alumne/entitat sobre la mateixa inscripció. | No s'aplica una unicitat «1 inscripció = 1 factura». | **Pendent guard comercial específic** |
| F-053 | `student_invoice_uuid` pot canviar la clau de la part entitat USOC. | Fora del nucli UC-001. | **Pendent UC-019b** |
| F-054 | Worker Redsys sense fencing per propietari del lock. | No modificat des d'UC-001. | **Pendent transversal** |
| F-055 | Falta integrar tota la traça `operational_event/sif_audit_event/factura_registre_control`. | Reflectit al FINAL, sense writer nou inventat. | **Pendent** |
| F-056 | Rectificativa pot heretar any de l'original. | No es modifica sense decisió fiscal. | **Pendent decisió/prova** |
| F-057 | Reús amb `payment` original i moviment desaparegut podia retornar sense `uuid_payment`. | `InvoiceService` ara falla tancat amb CONFLICT; test específic afegit. | **Corregit per codi; reparació de dades continua operativa** |
| F-058 | Cal decisió comuna de cobertura/cobrament abans d'emetre. | Incorporada a activitats/seqüència FINAL. | **Documentat; no implementat complet** |
| F-059 | Cal distingir documentat/implementat/inspeccionat/executat. | Aquesta fitxa ho centralitza. | **Corregit documentalment** |
| F-060 | Registre intern no congela explícitament tots els camps fora del bloc AEAT. | No es duplica estructura sense model aprovat. | **Pendent snapshot final** |
| F-061 | Head AEAT revisable pot bloquejar posteriors. | Ordre global fail-closed + estat `REVIEW`; `AeatReviewReconciliationService` reconcilia resultats terminals persistits sense segon enviament i rebutja intents `UNCERTAIN`. | **Implementat; revalidació CI del PR pendent** |
| F-062 | Signe/import no classificat globalment per tipus F/R/refund. | No s'endureix sense regla per variant. | **Pendent classificació** |
| F-063 | Numeració llegada depèn de `TIPUS='A'` amb insercions antigues potencialment implícites. | No es toca llegat des d'UC-001. | **Pendent retirada/esquema llegat** |
| F-064 | Columnes fiscals noves poden existir sense writer individual complet. | Documentat als límits. | **Pendent model final** |
| F-065 | Verificació de payload de cua no equival a auditoria completa de tota la cadena. | Responsabilitats separades als UML. | **Pendent auditoria de cadena** |
| F-066 | Cobrament inicial UC-001 crea assignació a la factura emesa, no repartiment multi-factura arbitrari. | Es conserva com a frontera del cas. | **Acceptat; validar adaptadors** |
| F-067 | Error intern podia exposar missatge d'excepció. | Error no funcional -> missatge 500 genèric. | **Corregit per codi; pendent CI** |
| F-068 | Faltaven peces ACTUAL/FINAL específiques. | Classes, seqüències, activitats i inventari creats. | **Corregit documentalment** |
| F-069 | Builders comercials no construeixen tots el snapshot AEAT oficial complet. | `ServerFiscalSnapshotAssembler` queda com a responsabilitat FINAL pendent. | **Pendent** |
| F-070 | Cadena interna i oficial no es poden barrejar. | Guarda existent reflectida als diagrames. | **Implementat; pendent prova d'entorn** |
| F-071 | Intent Redsys i snapshot comercial necessiten coherència d'identitat. | Generic endpoint separat; no resol handler específic. | **Pendent Redsys** |
| F-072 | Emissor AEAT al generic endpoint podia venir del payload. | Policy força emissor de configuració servidor. | **Corregit per codi; pendent CI** |
| F-073 | `emesa_abans_cobrament` es podia saltar fora del builder UC-004. | Generic endpoint rebutja bypass UC-004. | **Corregit al generic; pendent CI** |
| F-074 | Resposta d'`InvoiceService` no inclou tots els estats de la fitxa. | No s'inventa projector parcial. | **Pendent contracte de resposta** |
| F-075 | Codi utilitzable de regal pot formar part del detall fiscal. | Es manté com a risc UC-017/018. | **Pendent custòdia/presentació** |
| F-076 | Idempotència manual derivada de contingut pot col·lapsar dues vendes legítimes equivalents. | Es vincula al pendent de `UUID_OPERATION`. | **Pendent operació comercial** |
| F-077 | `commercial_operation*` existeix a esquema però no és obligatori a UC-001. | Incorporat al model FINAL. | **Pendent writer/coverage guard** |
| F-078 | Classes no mostraven branca AEAT completa. | Nou diagrama ACTUAL/FINAL. | **Corregit documentalment** |
| F-079 | Seqüència no mostrava branca AEAT/rollback. | Nou diagrama ACTUAL/FINAL. | **Corregit documentalment** |
| F-080 | Faltava paquet de proves de hardening. | Tests de policy, scope, endpoint, validador i relació-línia. | **Implementat com a proves; pendent CI** |
| F-081 | `issue.php` no acreditava auth/rol d'aplicació. | HMAC + anti-replay + rol configurat. | **Corregit per codi; pendent CI/desplegament** |
| F-082 | Generic endpoint podia fabricar `CHARGE REDSYS`. | Policy rebutja Redsys factura/pagament. | **Corregit per codi; pendent CI** |
| F-083 | `created_by` podia ser aportat pel client. | Substituït per actor autenticat. | **Corregit per codi; pendent CI** |
| F-084 | Clau idempotent podia ser buida o massa llarga. | Rebuig abans de BD. | **Corregit per codi; pendent CI** |
| F-085 | Capçalera podia no quadrar amb línies. | Sumes en cèntims d'import base/descompte/base imposable/IVA/total. | **Corregit per codi; pendent CI** |
| F-086 | Curs/pack/grup acoblen `inscription.ANY` a l'any fiscal. | No es canvia sense decisió funcional/fiscal. | **Pendent decisió + prova any creuat** |
| F-087 | Un reintent podia trobar la factura però no el cobrament inicial esperat. | El reús falla tancat amb 409 si falta el `payment`; no recrea ni retorna èxit econòmic parcial. | **Corregit per codi; pendent CI** |
| F-088 | Sèrie i tipus de factura podien arribar en una família incompatible. | `A` exigeix `F1/F2`; `R` exigeix `R1…R5`. | **Corregit per codi; pendent CI** |
| F-089 | El descompte de capçalera no es contrastava amb les línies. | Suma en cèntims de `discount_amount` contra `totals.discount`. | **Corregit per codi; pendent CI** |
| F-090 | Clau idempotent/canal amb espais perifèrics podien passar validació però persistir amb identitat diferent. | Es rebutgen valors no canònics amb whitespace perifèric. | **Corregit per codi; pendent CI** |
| F-091 | La configuració per defecte podia conservar l’emissor placeholder `G00000000` en un payload AEAT oficial. | Policy i preflight exigeixen NIF no-placeholder; la policy normalitza caixa abans del guard. | **Corregit per codi; pendent CI/configuració real** |
| F-092 | Faltava un gate operatiu de readiness del generic `invoice_issue`, i el primer preflight no comprovava explícitament la seqüència fiscal. | `preflight-invoice-issue.php` valida HMAC/rol/path/emissor, BD, taules de factura/pagament, `fiscal_sequence`, `fiscal_chain_state` i seed de cadena, sense mutació. | **Corregit per codi; pendent execució a entorn** |

## 7. Proves de tancament i deutes posteriors

| ID | Escenari | Resultat esperat |
| --- | --- | --- |
| UC001-VAL-04 | `import_base` de capçalera diferent de la suma de línies | **Implementat en test**: 422 abans de numerar |
| UC001-VAL-05 | `iva_import` de capçalera diferent de la suma de línies | **Implementat en test**: 422 abans de numerar |
| UC001-VAL-06 | `source_channel` buit | **Implementat en test**: 422 abans de numerar |
| UC001-VAL-07 | `totals.discount` diferent de la suma de `line.discount_amount` | **Implementat en test**: 422 abans de numerar |
| UC001-VAL-08 | clau idempotent o canal amb whitespace perifèric | **Implementat en test**: 422; no es persisteix una identitat no canònica |
| UC001-REL-02 | dues línies comparteixen el mateix origen d'una única `fact_rel` | **Implementat en test**: `ID_FACTURA_LINIA=NULL`, mai assignació arbitrària |
| UC001-YEAR-01 | edició d'any anterior emesa l'any actual | expected pendent de decisió funcional/fiscal |
| UC001-COV-01 | mateixa obligació comercial amb dues claus diferents | no duplicar factura; guard pendent |
| UC001-TRACE-01 | reconstruir actor, request, correlació, comanda i resultat | pendent audit writer |
| UC001-AEAT-02 | emissor del payload diferent de la configuració servidor al generic endpoint | configuració servidor preval o petició rebutjada segons policy |

## 8. Criteri de tancament funcional/productiu posterior

L’**auditoria** queda tancada segons §5, amb l’excepció d’execució CI documentada a §5.3. El **cas d’ús com a capacitat productiva final** no s’ha de marcar com a complet només perquè el hardening d’aquesta branca sigui mergeable. Aquest segon tancament exigeix, com a mínim:

1. CI/suite MySQL verda per les correccions incorporades;
2. adaptadors reals connectats als endpoints dedicats;
3. cobertura comercial entre claus diferents;
4. traçabilitat `commercial_operation -> operation_line -> factura_linia`;
5. audit writer persistent amb actor/request/correlació/resultat;
6. snapshot AEAT oficial server-side en l'entorn qualificat;
7. decisió i prova de l'any fiscal;
8. resposta amb estats d'emissió local, cobrament, document i AEAT diferenciats.

Fins aleshores, l’estat d’implementació continua **CORE_HARDENED_CROSSCUTTING_AND_ENVIRONMENT_PENDING**. Això és compatible amb tenir l’**AUDITORIA TANCADA AMB EXCEPCIÓ D’EXECUCIÓ** mentre la cua global impedeixi obtenir el gate; només passarà a **VERIFICADA EN EXECUCIÓ** quan existeixi un run efectiu satisfactori.
