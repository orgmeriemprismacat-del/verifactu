# UC-015 · Auditoria detallada i traçabilitat — 2026-09-29

## 1. Resultat

**Estat global:** IMPLEMENTACIÓ PARCIAL / DOCUMENTACIÓ COMPLETADA EN AQUEST LOT / PROVES D'ENTORN PENDENTS.

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
| Callback legacy | sí | sí | sí | retirada |
| Factura N línies | sí | sí | sí | prova end-to-end |
| Conciliació factura/import Redsys | sí | **sí (29/09)** | sí | executar test |
| Idempotència factura/payment | sí | sí | sí | evidència runtime |
| Ledger ID_INSC | sí | **sí (30/09)** | sí | executar proves/runtime |
| Outbox correu | sí | **sí al flux PACK asíncron (30/09)** | sí | executar worker/runtime |
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

**Execució:** pendent d'evidència; no hi ha workflow associat al commit inspeccionat.

## 6. Troballes P0

### UC15-P0-01 · Preu enviat pel navegador — CORREGIT AL CANAL D'ALTA 2026-09-30
El servidor recalcula el preu i grava metadata comercial per component. `PackPaymentGate` rellegeix aquesta informació, valida import/ordinal/receptor i crea un snapshot que `SifPaymentIntentClient` envia a la intenció SIF abans del TPV.

### UC15-P0-02 · IDPAG concurrent — MITIGAT 2026-09-30
L'allocator continua basant-se en `MAX(IDPAG)+1`, però `ConnexioBBDDSTMT::reserveIdPag()` serialitza la reserva amb `GET_LOCK()` i manté el lock fins a `releaseIdPag()`. Continua sent deute tècnic davant d'una seqüència pròpia, però ja no és el patró concurrent sense lock de l'auditoria inicial.

### UC15-P0-03 · Callback legacy fiscal
`realitzaPagamentPackAutomatic.php` encara calcula numeració i insereix `factures` directament.

### UC15-P0-04 · Signatura — CORREGIT 2026-09-30
Els dos callbacks legacy de pack comparen ara de forma bloquejant la signatura calculada amb `Ds_Signature` mitjançant `hash_equals()`. També es bloqueja si `Ds_Order` o `Ds_Amount` signats no coincideixen amb els valors legacy utilitzats pel procés.

### UC15-P0-05 · Ordinal comercial — PARCIALMENT CORREGIT 2026-09-30
L'alta grava `PACK_ORDINAL`; `LegacyPackSnapshotRepository` el recupera i `LegacyPackInvoicePayloadBuilder` ordena per aquest ordinal i exigeix seqüència contigua. Resta verificar que el valor gravat prové de l'ordre comercial canònic del pack i no només de l'ordre per data de les edicions.

### UC15-P0-06 · Receptor — CORREGIT FAIL-CLOSED 2026-09-30
`PackPaymentGate` construeix billing des de BD i el builder aplica `consistentBilling()` a totes les inscripcions. Si qualsevol component divergeix en dades fiscals, l'emissió es bloqueja amb conflicte.

### UC15-P0-07 · Conciliació import
**Corregit al SIF el 2026-09-29:** `RedsysPackInvoiceService` bloqueja si total factura i import Redsys no coincideixen.

### UC15-P0-08 · Checkout PACK → intenció SIF — CORREGIT 2026-09-30
La ruta `efectPagGrupsAuto` tracta explícitament `TIPUS_INSC='P'`. `PackPaymentGate` reconstrueix el checkout des de BD, `SifPaymentIntentClient` crea la intenció autenticada `SOURCE_TYPE=PACK` i el merchant URL de Redsys passa a `SIF_REDSYS_CALLBACK_URL`.

### UC15-P0-09 · DS_ORDER del PACK — CORREGIT AL CANAL PACK 2026-09-30
El canal PACK ja no usa `time()`: genera un DS_ORDER de 12 dígits, l'envia a la intenció SIF i exigeix que el SIF retorni el mateix valor abans de construir el formulari Redsys.

## 7. Troballes P1

- retirar definitivament el callback fiscal legacy;
- acreditar l'origen canònic de `PACK_ORDINAL`;
- executar i evidenciar ledger/outbox en runtime;
- sincronització acadèmica postcommit;
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
- ecommerce creï la intenció SIF amb snapshot comercial;
- el callback fiscal legacy deixi de ser autoritatiu;
- ordinal, imports i receptor siguin congelats abans del TPV;
- ledger per inscripció estigui resolt;
- proves PK-01..PK-11 i de callback duplicat s'executin en entorn controlat.


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

**Execució de les proves:** continua pendent d'evidència runtime/CI.


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

**No s'ha acreditat execució CI d'aquests nous tests en aquesta auditoria.**
