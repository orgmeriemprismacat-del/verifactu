# UC-015 · Auditoria detallada i traçabilitat — 2026-09-29

## 1. Resultat

**Estat global:** FLUX FISCAL/ECONÒMIC PRINCIPAL IMPLEMENTAT I VERIFICAT EN CI / DOCUMENTACIÓ COMPLETADA / E2E D'ENTORN PENDENT.

Aquest registre diferencia:
- **DOCUMENTAT:** existeix contracte o UML.
- **IMPLEMENTAT:** existeix codi.
- **VERIFICAT:** inspeccionat directament al repositori.
- **PENDENT:** manca implementació, execució de proves o evidència d'entorn.

## 2. Matriu

| Element | Documentat | Implementat | Verificat | Pendent |
|---|---:|---:|---:|---:|
| Catàleg i fitxa pack | sí | sí | sí | proves navegador |
| N inscripcions amb IDPAG comú | sí | sí | sí | substituir identitat concurrent |
| Preu pack | sí | **sí, backend autoritatiu (30/09)** | sí | proves runtime |
| Snapshot comercial | sí | **sí al checkout PACK** | sí | verificar origen canònic de l'ordinal |
| Intenció Redsys PACK | sí | **sí + checkout connectat (30/09)** | sí | evidència runtime |
| Callback SIF | sí | sí | sí | evidència de desplegament/runtime |
| Callback legacy | sí | **retirat per defecte (30/09)** | sí | eliminar codi mort quan acabi finestra rollback |
| Factura N línies | sí | sí | sí | prova end-to-end |
| Conciliació factura/import Redsys | sí | **sí (29/09)** | **sí, CI** | E2E/runtime |
| Idempotència factura/payment | sí | sí | sí | evidència runtime |
| Ledger ID_INSC | sí | **sí (30/09)** | **sí, CI** | E2E/runtime |
| Outbox correu | sí | **enqueue sí al flux PACK asíncron (30/09)** | **sí, CI enqueue/idempotència** | worker/transport UC-58 + runtime |
| Activitats ACTUAL/FINAL | **sí (29/09)** | n/a | sí | mantenir sincronitzat |
| Classes ACTUAL/FINAL | **sí (29/09)** | n/a | sí | mantenir sincronitzat |
| Seqüències ACTUAL/FINAL | **sí (29/09)** | n/a | sí | mantenir sincronitzat |

## 3. Codi web/llegat inspeccionat

- `codi-drive/web-actual/Pack.php`
- `codi-drive/web-actual/EdicioPack.php`
- `codi-drive/web-actual/InscripcioPack.php`
- `codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js`
- `codi-drive/web-actual/ajax/mostrar_inscripcio_packs.php`
- `codi-drive/web-actual/ajax/obtenirIdPack.php`
- `codi-drive/web-actual/ajax/obtenirIdPreuPack.php`
- `codi-drive/web-actual/ajax/obtenirPreusPack.php`
- `codi-drive/web-actual/ajax/enviarInscripcioPack.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentPackAutomatic.php`

## 4. Codi SIF inspeccionat

- `sif/src/Service/RedsysPaymentIntentService.php`
- `sif/public/api/redsys/intents/create.php`
- `sif/src/Service/RedsysCallbackService.php`
- `sif/src/Service/RedsysCallbackDispatcher.php`
- `sif/src/Service/RedsysCallbackWorker.php`
- `sif/src/Service/RedsysPackInvoiceService.php`
- `sif/src/Repository/LegacyPackSnapshotRepository.php`
- `sif/src/Service/LegacyPackInvoicePayloadBuilder.php`
- `sif/src/Service/RedsysInvoicePayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`
- `sif/src/Repository/InvoiceRepository.php`
- `sif/src/Repository/PaymentRepository.php`

## 5. Proves localitzades

- `sif/tests/Integration/LegacyPackInvoicePayloadBuilderTest.php`
- `sif/tests/Integration/RedsysPackInvoiceServiceTest.php`
- `sif/tests/Integration/RedsysPackPreflightScriptTest.php`
- `sif/tests/Integration/RedsysPackPreproductionScriptTest.php`

### Prova afegida 2026-09-29

`RedsysPackInvoiceServiceTest::testRejectsPackWhenValidatedRedsysAmountDiffersFromInvoiceLines()`

Exigeix:
- notificació Redsys `VALIDATED`;
- import notificat diferent de la suma de línies;
- resposta 409;
- zero factures;
- zero `payment_transaction`.

**Execució en el punt inicial de l'auditoria:** pendent. Aquesta mancança queda resolta posteriorment pel run `36720150263` amb **706 passed / 0 failed**.

## 6. Troballes P0

### UC15-P0-01 · Preu enviat pel navegador — CORREGIT AL CANAL D'ALTA 2026-09-30
El servidor recalcula el preu i grava metadata comercial per component. `PackPaymentGate` rellegeix aquesta informació, valida import/ordinal/receptor i crea un snapshot que `SifPaymentIntentClient` envia a la intenció SIF abans del TPV.

### UC15-P0-02 · IDPAG concurrent — MITIGAT 2026-09-30
L'allocator continua basant-se en `MAX(IDPAG)+1`, però `ConnexioBBDDSTMT::reserveIdPag()` serialitza la reserva amb `GET_LOCK()` i manté el lock fins a `releaseIdPag()`. Continua sent deute tècnic davant d'una seqüència pròpia, però ja no és el patró concurrent sense lock de l'auditoria inicial.

### UC15-P0-03 · Callback legacy fiscal — RETIRAT PER DEFECTE 2026-09-30
`realitzaPagamentPackAutomatic.php` conserva codi històric per rollback, però abans de qualsevol mutació comprova `SIF_PACK_LEGACY_CALLBACK_ENABLED`. Per defecte és `0` i respon HTTP 410. Les compres PACK noves ja envien Redsys a `SIF_REDSYS_CALLBACK_URL`, de manera que el SIF és l'únic camí autoritatiu per defecte.

### UC15-P0-04 · Signatura — CORREGIT 2026-09-30
Els dos callbacks legacy de pack comparen ara de forma bloquejant la signatura calculada amb `Ds_Signature` mitjançant `hash_equals()`. També es bloqueja si `Ds_Order` o `Ds_Amount` signats no coincideixen amb els valors legacy utilitzats pel procés.

### UC15-P0-05 · Ordinal comercial — ORDRE OPERATIU ESTABILITZAT 2026-09-30
L'alta grava `PACK_ORDINAL`; `LegacyPackSnapshotRepository` el recupera i `LegacyPackInvoicePayloadBuilder` ordena per aquest ordinal i exigeix seqüència contigua. Alta, `Pack.php` i `InfoPack.php` utilitzen ara el mateix ordre determinista `ORDER BY c.DATAI, p.ID_CURS`. Això blinda l'ordre operatiu/presentat actual; resta una decisió funcional sobre si cal una posició comercial explícita independent de les dates.

### UC15-P0-06 · Receptor — CORREGIT FAIL-CLOSED 2026-09-30
`PackPaymentGate` construeix billing des de BD i el builder aplica `consistentBilling()` a totes les inscripcions. Si qualsevol component divergeix en dades fiscals, l'emissió es bloqueja amb conflicte.

### UC15-P0-07 · Conciliació import
**Corregit al SIF el 2026-09-29:** `RedsysPackInvoiceService` bloqueja si total factura i import Redsys no coincideixen.

### UC15-P0-08 · Checkout PACK → intenció SIF — CORREGIT 2026-09-30
La ruta `efectPagGrupsAuto` tracta explícitament `TIPUS_INSC='P'`. `PackPaymentGate` reconstrueix el checkout des de BD, `SifPaymentIntentClient` crea la intenció autenticada `SOURCE_TYPE=PACK` i el merchant URL de Redsys passa a `SIF_REDSYS_CALLBACK_URL`.

### UC15-P0-09 · DS_ORDER del PACK — CORREGIT AL CANAL PACK 2026-09-30
El canal PACK ja no usa `time()`: genera un DS_ORDER de 12 dígits, l'envia a la intenció SIF i exigeix que el SIF retorni el mateix valor abans de construir el formulari Redsys.

## 7. Troballes P1

- eliminar físicament el codi mort del callback legacy quan finalitzi la finestra de rollback;
- decidir si l'ordre estable `DATAI, ID_CURS` és suficient o cal una posició comercial explícita;
- executar i evidenciar ledger/outbox en runtime/preproducció;
- la sincronització legacy post-SIF ja està implementada; resta evidència d'entorn;
- packs N i combinacions de descompte;
- component indisponible (UC-122);
- factura prèvia al cobrament (RM-016).

## 8. Fitxers UML associats

- [Fitxa UC-015](../06-fitxes-funcionals/uc-015.md)
- [UML integrat](uc-015-comprar-pack.md)
- [Classes ACTUAL/FINAL](uc-015-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-015-sequencies-actual-final.md)
- [Activitats ACTUAL/FINAL](uc-015-activitats-pagines-pack-actual-final.md)

## 9. Criteri de tancament

UC-015 no pot passar a **VERIFICAT/TANCAT** fins que:
- el checkout ecommerce → intenció SIF → callback/worker continuï passant CI i es validi en preproducció;
- el callback fiscal legacy continuï desactivat per defecte i s'elimini després de la finestra de rollback;
- es mantingui l'ordre estable `DATAI, ID_CURS` o es defineixi una posició comercial explícita;
- ledger/outbox es verifiquin en runtime amb el mateix snapshot congelat;
- proves PK-01..PK-11 i callback duplicat s'executin en entorn controlat.


## 10. Canvis addicionals 2026-09-30

### Endpoint d'intenció
Creat `sif/public/api/redsys/intents/create.php`:
- només POST;
- autenticació HMAC interna;
- protecció anti-replay per `REQUEST_ID`;
- rols autoritzats configurables;
- `created_by` deriva de l'actor signat i no del JSON;
- fail-closed si no hi ha rols configurats.

Configuració:
- `SIF_INTERNAL_REDSYS_INTENT_SIGNED_PATH`
- `SIF_REDSYS_INTENT_CREATE_ROLES`

### Preu servidor
`enviarInscripcioPack.php` ja no consumeix imports del client com a font de veritat. El JS manté la consulta de preus només per visualització però no envia els totals a l'alta.

### Prova d'intent PACK
Afegida `RedsysPaymentIntentTest::testCreatesPackIntentWithFrozenCommercialSnapshot()`, amb `SOURCE_TYPE=PACK`, ordinal de components i receptor dins del snapshot.

**Execució de les proves:** CI acreditat posteriorment amb **706 passed / 0 failed**; continua pendent només l'evidència runtime/preproducció.


## 11. Enduriment temporal del callback legacy — 2026-09-30

Mentre el callback fiscal legacy encara no s'ha retirat, s'han aplicat mesures de contenció als dos copies:
- validació bloquejant de signatura Redsys;
- conciliació de `Ds_Order` amb l'ordre legacy;
- conciliació de `Ds_Amount` amb l'import legacy;
- eliminació de correus i sortides de depuració;
- eliminació de la clau Redsys del codi font;
- lectura de la clau des de `SIF_REDSYS_MERCHANT_KEY`;
- fallada tancada si la clau no està configurada.

**Desplegament:** abans de desplegar aquests callbacks cal configurar `SIF_REDSYS_MERCHANT_KEY` al runtime corresponent. La retirada del secret del codi no elimina la necessitat de **rotar la clau**, perquè el secret havia estat versionat històricament.

Aquest enduriment és transitori i **no substitueix UC-68**: el callback legacy continua contenint numeració/INSERT de factura i UPDATE d'inscripcions fins que el worker SIF sigui l'únic emissor.


## 10. Revalidació d'implementació — 2026-09-30

### Flux principal PACK acreditat per inspecció

`PagamentGrupAutomatic (TIPUS_INSC=P)`
→ `pagina_efectuar_pagament_grup_automatic.php`
→ `PackPaymentGate::assertCanPrepare()`
→ `SifPaymentIntentClient::create()`
→ `/api/redsys/intents/create.php`
→ Redsys
→ callback SIF
→ cua
→ `RedsysPackInvoiceService::issueFromIntentSnapshot()`
→ `InvoiceService`
→ `PackEnrollmentFundAllocationService`
→ `PackPaymentNotificationService`
→ sincronització legacy posterior.

### Ledger

La migració `2026_09_30_000030_add_enrollment_fund_movement.sql` i el repositori `EnrollmentFundMovementRepository` implementen una atribució monetària immutable per `ID_INSC` sense crear CHARGE addicionals.

### Prova de regressió ja escrita

`RedsysPackInvoiceServiceTest::testIntentSnapshotCreatesOneDurableNotificationAcrossRetry()` verifica:
- una factura i un únic cobrament extern;
- dues atribucions `enrollment_fund_movement`;
- imports 120 € + 90 € = 210 €;
- mateix UUID_PAYMENT;
- outbox únic;
- reintent idempotent.

Aquesta auditoria **no declara l'execució** d'aquesta prova si no hi ha evidència runtime/CI específica del commit.


## 11. Enduriments addicionals — 2026-09-30

### Checkout fail-closed
S'han afegit controls abans de crear la intenció:
- totes les dades de receptor han de coincidir entre components;
- `PACK_BASE - PACK_DISCOUNT = PACK_TOTAL`;
- `PACK_DISCOUNT_PCT` ha de reproduir l'import del descompte amb tolerància d'1 cèntim;
- PACK i ordinals han de ser coherents;
- import sol·licitat = pendent complet.

### Configuració Redsys
Per PACK, `pagina_efectuar_pagament_grup_automatic.php` utilitza:
- `SIF_REDSYS_MERCHANT_KEY` per signar;
- `REDSYS_MERCHANT_CODE` per codi de comerç;
- `REDSYS_TERMINAL` per terminal.

El mateix terminal es passa a la intenció SIF i al formulari Redsys. Si manca configuració del PACK, el flux falla tancat abans de preparar el TPV.

### Evidència de test escrita
`PackPaymentGateTest` cobreix pagament complet, rebuig parcial, pack ja pagat parcialment, ordinal absent, PACK inconsistent, ordinal duplicat/no contigu, receptor divergent, adreça divergent i descompte percentual inconsistent.

**Execució CI acreditada:** run GitHub Actions `36720150263`, commit `c961f193...`, resultat **706 passed / 0 failed**. La validació end-to-end/preproducció continua pendent.


## 12. Evidència CI positiva — 2026-09-30

Run: `36658248618` · workflow `SIF PHP MySQL tests` · commit `d02bc5406099b2417196fb107d799b35fba291aa`.

**Resultat final d'aquell run:** **619 passed / 0 failed**.

S'han observat PASS explícits per:
- tots els `LegacyPackInvoicePayloadBuilderTest`;
- tots els `RedsysPackInvoiceServiceTest`;
- tots els `LegacyPackSnapshotRepositoryTest`;
- tots els `PackPaymentGateTest`, inclòs el nou control percentatge/import.

Un run immediatament anterior havia quedat vermell per `InvoiceQueryServiceTest::testViewReturnsNotFoundForUnknownInvoice` (UC-007, esperava 404 i rebia 422); no era una fallada UC-015. El run posterior ja és completament verd.


### Evidència CI amb lint del checkout PACK
Run `36658376996` · commit `7dcad412...` · **SUCCESS**.

- `php -l` correcte a les dues còpies de `PackPaymentGate.php`.
- `php -l` correcte a les dues còpies de `pagina_efectuar_pagament_grup_automatic.php`.
- Suite SIF d'aquell run: **619 passed / 0 failed**.


## 13. Enduriment addicional — 2026-09-30

### UC15-P0-09 · Builder fiscal sense heurística — CORREGIT

`LegacyPackInvoicePayloadBuilder` ja no reconstrueix automàticament una base o un descompte del 25 % a partir d'`A_PAGAR`.

Ara cada línia del pack ha de portar explícitament:

- `TOTAL`;
- `IMPORT_BASE`;
- `DESC_IMPORT`;
- `DESC_PCT`.

El builder comprova que:

`IMPORT_BASE - DESC_IMPORT = TOTAL`

i rebutja amb conflicte qualsevol línia incompleta o inconsistent.

També s'ha endurit `LegacyPackSnapshotRepository`: el fallback legacy exigeix snapshot comercial complet amb `PACK_ORDINAL`, `PACK_BASE`, `PACK_DISCOUNT`, `PACK_DISCOUNT_PCT` i `PACK_TOTAL`. Una inscripció antiga sense aquests marcadors no pot generar una factura SIF per reconstrucció.

### Proves afegides

- `RedsysPackInvoiceServiceTest::testRejectsLegacyPackWithoutCompleteCommercialSnapshot()`.
- `LegacyPackInvoicePayloadBuilderTest::testRejectsPackLineWithoutExplicitCommercialAmounts()`.

**Execució CI en aquell punt històric:** encara pendent per als dos canvis acabats d'afegir; la darrera suite acreditada aleshores era 619/619. Aquesta mancança queda resolta posteriorment pel run `36720150263` (706/0).


## 14. Retirada operativa del callback fiscal legacy — 2026-09-30

El callback `codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentPackAutomatic.php` ha deixat de ser autoritatiu per defecte.

Comportament actual:
- `SIF_PACK_LEGACY_CALLBACK_ENABLED` no configurat o fals → HTTP 410 abans de carregar el flux fiscal;
- només `SIF_PACK_LEGACY_CALLBACK_ENABLED=1` permet executar el codi històric de rollback;
- les compres PACK noves utilitzen `SIF_REDSYS_CALLBACK_URL` i entren pel callback/cua/worker SIF.

Prova afegida:
- `LegacyPackCallbackBoundaryTest::testLegacyPackCallbackIsDisabledByDefaultBeforeLegacyMutationCode()`.

El script `sif/scripts/process-redsys-pack.php` continua limitat a CLI i rebutja `SIF_ENV=production`; es considera eina de diagnòstic/reconciliació no productiva, no un segon callback.


## 15. Revalidació exhaustiva contra main — 2026-09-30 (segona passada)

### Estat real

- **DOCUMENTAT:** fitxa funcional + UML integrat + classes + seqüències + activitats per pàgina existeixen.
- **IMPLEMENTAT:** checkout PACK autoritatiu al servidor, intenció SIF, callback/cua/worker, factura N línies, reconciliació import, ledger `enrollment_fund_movement`, outbox `notification_outbox`, sincronització legacy post-SIF i guard HTTP 410 del callback fiscal legacy.
- **VERIFICAT PER INSPECCIÓ:** cablejat de `PackPaymentGate`, `SifPaymentIntentClient`, `RedsysPackInvoiceService`, `PackEnrollmentFundAllocationService`, `PackPaymentNotificationService`, `RedsysLegacySyncingProcessor` i `LegacySyncService`.
- **PENDENT D'EVIDÈNCIA D'ENTORN:** pagament real Redsys/preproducció, worker real amb secrets/URLs definitius i verificació navegador.

### Correccions d'aquesta passada

1. Els UML FINAL deixen d'inventar `AcademicEnrollmentSyncService`: el flux real usa `RedsysLegacySyncingProcessor` + `LegacySyncService` després de l'èxit SIF.
2. El runner local UC-015 comprova explícitament les taules `enrollment_fund_movement` i `notification_outbox`.
3. El runner local enumera els tests de regressió afegits després de la suite històrica 619/0 i fa lint dels quatre PHP crítics del checkout PACK.
4. El run `36720150263` sobre `c961f193...` acredita també els tests nous del UC-015; qualsevol canvi posterior haurà de tornar a executar CI abans de donar-lo per verificat.

### Mancances residuals prioritzades

- **P0 entorn:** executar E2E real/preproducció amb Redsys i conservar evidència de callback, cua, factura, payment, ledger, outbox i sincronització legacy.
- **P1 comercial:** decidir formalment si l'ordre estabilitzat `DATAI, ID_CURS` és el contracte comercial definitiu o si cal una posició explícita/versionada.
- **P1 retirada:** eliminar físicament `realitzaPagamentPackAutomatic.php` com a callback fiscal quan acabi la finestra de rollback.
- **P2 llegat:** substituir si es decideix l'allocator `MAX(IDPAG)+1` sota lock per una seqüència pròpia.


## 16. Evidència CI posterior al PR #53 — 2026-09-30

GitHub Actions `SIF PHP MySQL tests`, run `36720150263`, commit `c961f1931687a1363a9a080a6715317645f9686e`: **706 passed / 0 failed**.

PASS explícits rellevants per UC-015:
- `LegacyPackCallbackBoundaryTest::testLegacyPackCallbackIsDisabledByDefaultBeforeLegacyMutationCode`;
- `LegacyPackInvoicePayloadBuilderTest::testUsesCommercialOrdinalWhenSnapshotItemsArriveOutOfOrder`;
- `LegacyPackInvoicePayloadBuilderTest::testRejectsPackLineWithoutExplicitCommercialAmounts`;
- `RedsysPackInvoiceServiceTest::testIntentSnapshotCreatesOneDurableNotificationAcrossRetry`;
- `RedsysPackInvoiceServiceTest::testRejectsPackWhenValidatedRedsysAmountDiffersFromInvoiceLines`;
- `RedsysPackInvoiceServiceTest::testRejectsLegacyPackWithoutCompleteCommercialSnapshot`;
- tots els controls actuals de `PackPaymentGateTest`.

Això mou aquests blocs de **prova escrita** a **verificats en CI**. Continua pendent únicament l'evidència real de navegador/Redsys/preproducció i la decisió/contracte de l'ordre comercial de components.

### Origen real actual de `PACK_ORDINAL`

La revalidació del codi d'alta confirma que `PACK_ORDINAL = $i + 1` i que `$i` prové de la consulta de components ordenada amb `ORDER BY c.DATAI`. El mateix criteri cronològic s'utilitza a `Pack.php` i `InfoPack.php` per presentar els components.

Per tant, avui l'ordinal és **coherent amb l'ordre de presentació cronològic del web**, però no existeix al repositori un camp explícit de posició comercial versionada a `packs`. Això és suficient per reproduir el comportament actual, però no per afirmar que existeix una ordre comercial independent de les dates. La decisió residual és una de dues:
1. declarar formalment `DATAI + tie-break estable` com a contracte d'ordre comercial; o
2. afegir una posició explícita/versionada a la definició del pack i usar-la a visualització, snapshot i factura.

No s'introdueix ara una nova columna legacy sense evidència de l'esquema productiu i una decisió funcional explícita.


## 17. Estabilització de l'ordre de components — 2026-09-30

S'ha eliminat la indeterminació en empats de `DATAI` fent que els tres punts que defineixen/presenten el pack comparteixin `ORDER BY c.DATAI, p.ID_CURS`:
- `codi-drive/web-actual/ajax/enviarInscripcioPack.php`;
- `codi-drive/web-actual/Pack.php`;
- `codi-drive/web-actual/InfoPack.php`.

S'ha afegit `PackCommercialOrderBoundaryTest` per impedir que presentació i alta divergeixin i per comprovar que `PACK_ORDINAL` es congela després de la consulta ordenada.

Això **no inventa** una nova columna de negoci: documenta i estabilitza el contracte actual. Si PrisMa necessita un ordre comercial independent de la cronologia, caldrà afegir-lo explícitament al model legacy i migrar els packs existents.


## 18. Alineació dels scripts de preproducció — 2026-09-30

S'ha detectat i corregit una divergència: `process-redsys-pack.php` podia emetre des de `issueFromValidatedNotification()` sense `PackEnrollmentFundAllocationService` ni `PackPaymentNotificationService`, i `--sync-legacy` no executava `syncPackFullPayment()`.

Ara:
- `preview-redsys-pack.php` llegeix `RedsysPaymentIntentRepository`, exigeix `SOURCE_TYPE=PACK` i construeix el payload des de `SNAPSHOT_JSON`;
- `process-redsys-pack.php` usa `issueFromIntentSnapshot()`;
- injecta `NotificationOutboxRepository` + `PackPaymentNotificationService`;
- injecta `EnrollmentFundMovementRepository` + `PackEnrollmentFundAllocationService`;
- `--sync-legacy` executa `syncAfterSifSuccess()` i `syncPackFullPayment()` quan el mode és `PACK_FULL_PAYMENT`;
- els tests de scripts exigeixen explícitament aquestes dependències i impedeixen tornar a reconstruir el preview des de legacy.

Aquesta correcció redueix el pendent E2E a **execució i evidència d'entorn**, no a divergència del codi de preproducció.


## 19. Verificador d'evidència E2E — 2026-10-01

Nou servei `RedsysPackEvidenceVerifier` i CLI `verify-redsys-pack-evidence.php`. Donat un `DS_ORDER`, comprova de forma read-only:
- intenció `SOURCE_TYPE=PACK`;
- notificació `VALIDATED` i signatura validada;
- identitat IDPAG/import coherent;
- job callback `PROCESSED`;
- factura `ISSUED/PAID`;
- un únic `CHARGE` Redsys;
- N línies i relacions PACK/INSCRIPCIO;
- una assignació payment→factura;
- N moviments `EXTERNAL_ALLOCATION` i suma exacta;
- una outbox `PACK_PAYMENT_CONFIRMED`;
- registre i cua fiscal;
- `legacy_sync_executed=true`;
- `PAGAMENT=A_PAGAR`, `DATA PAG` i marcador UUID de factura a les inscripcions legacy.

La sortida no inclou email, DNI/NIF, adreces ni `SNAPSHOT_JSON`. Si falta qualsevol baula, `ok=false` i el procés retorna codi 2. En producció queda bloquejat per defecte i requereix `SIF_UC015_EVIDENCE_ALLOW_PRODUCTION=1`.


## 20. Hardening del checkout Redsys — 2026-10-01

S'ha detectat que el flux PACK ja era autoritatiu per import/snapshot però encara reutilitzava `dni` i `nom-titular` del navegador per al formulari Redsys. S'ha corregit perquè el PACK substitueixi DNI/NIF, nom i email pels valors de `snapshot.billing` validats al servidor i falli tancat si falten. També s'han escapat els valors POST mostrats als camps ocults per eliminar la superfície XSS. La regressió queda coberta per `PackCheckoutBoundaryTest` sobre les dues còpies de `pagina_efectuar_pagament_grup_automatic.php`.


## 21. Transport HTTP i privacitat de l'alta/TPV — 2026-10-01

Troballes corregides:
- `enviarInscripcioPack.php` era una mutació amb dades personals per GET; ara només admet POST i respon 405 a altres mètodes;
- `pagFrac` ja no és una decisió enviada pel navegador: ecommerce fixa `No` i persisteix `FRACCIONAT=0`;
- l'ajax concret d'alta PACK és POST, mentre les consultes de catàleg/preu continuen read-only;
- `DS_MERCHANT_TITULAR` usa nom/cognoms del snapshot servidor;
- `DS_MERCHANT_PRODUCTDESCRIPTION` de PACK ja no inclou DNI;
- URL OK/KO del PACK ja no inclou email;
- les pàgines de retorn validen/escapen qualsevol email legacy opcional;
- `SIF_REDSYS_PAYMENT_URL` només accepta les dues URLs oficials exactes de Redsys: real i sandbox.

Cobertura: `PackEnrollmentTransportBoundaryTest`, `PackCheckoutBoundaryTest`, `PackPaymentPrivacyBoundaryTest` i `RedsysPackPreflightScriptTest`.

**Residual de seguretat del formulari públic:** POST evita PII a URL i mutacions GET, però no equival a una protecció anti-abús/CSRF. Abans del desplegament definitiu convé decidir un control compatible amb el formulari públic (token de formulari o comprovació d'origen + rate limiting) sense confondre'l amb l'autenticació HMAC del SIF.


## 22. E2E worker, privacitat Redsys i frontera pública — 2026-10-01

Cobertura nova preparada:
- `RedsysPackWorkerEndToEndTest`: processa un PACK real pel worker, força un replay del mateix job i exigeix exactament 1 factura, 1 payment, N moviments de ledger i 1 outbox;
- `PackEnrollmentTransportBoundaryTest`: blinda POST-only de l'alta i impedeix reintroduir `$_GET`/fraccionament controlat pel client;
- `PackPaymentPrivacyBoundaryTest`: blinda que PACK no posi DNI a `DS_MERCHANT_PRODUCTDESCRIPTION`, que `DS_MERCHANT_TITULAR` provingui del nom servidor i que les URL OK/KO PACK no duguin email;
- `PackCheckoutBoundaryTest`: manté titular/valors ocults derivats del snapshot servidor i escapats;
- `RedsysPackPreflightScriptTest`: exigeix endpoint Redsys HTTPS amb allowlist d'host/path.

Segons el contracte públic de Redsys, `DS_MERCHANT_TITULAR` representa nom i cognoms del titular i `DS_MERCHANT_PRODUCTDESCRIPTION` és una descripció visible del producte; per tant, retirar el DNI d'aquests camps és coherent amb la semàntica del TPV.

**Residual no resolt en aquesta passada:** l'alta és un formulari públic no autenticat. POST evita PII en URL i mutacions via GET, però no substitueix un control anti-abús/origen. Cal tractar-ho com a hardening del formulari públic (token, comprovació d'origen i/o rate limiting) sense barrejar-lo amb la seguretat HMAC server-to-server del SIF.
