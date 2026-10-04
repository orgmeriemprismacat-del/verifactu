# UC-012 — Implementació SIF de morositat — 03/10/2026

**Branca:** `audit/uc-012-2026-10-03`  
**PR:** #140  
**Estat:** `IMPLEMENTED_ON_BRANCH / CI_PENDING / LEGACY_CUTOVER_PENDING`

## 1. Components implementats

### Persistència
- `debt_claim_case`: expedient únic per factura, estat, etapa, versió, saldo i destinatari resolt.
- `debt_claim_event`: historial append-only amb `IDEMPOTENCY_KEY`, `PAYLOAD_HASH`, versió, actor, request/correlation IDs i saldo abans/després.

Migració:
- `sif/database/migrations/2026_10_03_000033_add_debt_claim_case.sql`.

### Lectura de deute
`DebtSnapshotRepository` calcula el saldo a partir de:
- total de factura;
- assignacions de `CHARGE` i `COMPENSATION`;
- assignacions de `REFUND`;
- només moviments `CONFIRMED`.

No usa `web.inscripcions.A_PAGAR-PAGAMENT` com a autoritat final.

### Coordinació
`DebtClaimCoordinator` implementa:
- `preview()`;
- `recordNotice()`;
- `reconcileAfterPayment()`.

Etapes:
`DETECTED → FINAL_REMINDER → FIRST_CLAIM → FINAL_CLAIM → RESOLVED`.

Controls:
- rols de lectura/gestió;
- request/correlation IDs;
- idempotència amb comparació de payload;
- bloqueig de regressions/repeticions d'etapa amb clau nova;
- destinatari derivat de `factura.BILLING_EMAIL`;
- cap canvi fiscal o moviment monetari per una reclamació;
- `operational_event` per cada canvi efectiu.

### Comunicacions
Cada avís crea una fila idempotent de `notification_outbox`:
- `DEBT_CLAIM_FINAL_REMINDER`;
- `DEBT_CLAIM_FIRST_CLAIM`;
- `DEBT_CLAIM_FINAL_CLAIM`.

Quan el saldo arriba a zero, les notificacions `PENDING` del cas es marquen `CANCELLED`.

### Frontera interna
Nou endpoint:
- `sif/public/api/debt-claims/manage.php`.

Accions:
- `preview`;
- `record_notice`;
- `reconcile_after_payment`.

Seguretat:
- `InternalApiAuthenticator`;
- HMAC de petició;
- replay guard existent;
- rols `SIF_DEBT_CLAIM_READ_ROLES` i `SIF_DEBT_CLAIM_MANAGE_ROLES`;
- configuració absent → cap rol autoritzat.

### Client intranet
Nou:
- `codi-drive/intranet-actual/SifInternalDebtClaimClient.php`.

Métodes:
- `preview()`;
- `recordNotice()`;
- `reconcileAfterPayment()`.

El client signa la petició amb el mateix contracte HMAC de les altres APIs internes.

## 2. Cobertura de proves afegida

- `DebtClaimCoordinatorSmokeTest`: preview, alta, outbox, auditoria i retry idempotent.
- `DebtClaimCoordinatorReconciliationTest`: pagament parcial → 80,00 pendent; pagament complet → expedient tancat + notificacions pendents cancel·lades.
- `DebtClaimCoordinatorGuardsTest`: payload contradictori, regressió d'etapa, factura ja pagada i rol denegat.
- `DebtClaimInternalApiContractTest`: endpoint signat + client intranet + parseig real del client.

## 3. Traçabilitat P-MOR després de la implementació

| ID | Estat anterior | Estat en branca |
| --- | --- | --- |
| P-MOR-01 Detectar deute/pagador | legacy/parcial | **SIF implementat** per factura + ledger; venciment/pròrroga encara no modelats |
| P-MOR-02 Recordatori final | legacy | **SIF implementat** com event + outbox |
| P-MOR-03 Primera reclamació | legacy | **SIF implementat** com event + outbox |
| P-MOR-04 Reclamació final | legacy | **SIF implementat** com event + outbox; baixa continua separada |
| P-MOR-05 Regularització | ClaimPaymentService parcial | **SIF implementat** per cobrament existent + reconciliació/tancament |

## 4. Buits que continuen oberts

### P0 abans de migrar pantalles
1. CI MySQL verda sobre el head de la branca.
2. Afegir model explícit de venciment/pròrroga o adaptador autoritatiu UC-96 abans d'automatitzar selecció temporal.
3. Definir transport/plantilles reals de les tres notificacions a UC-58.
4. Configurar secrets/URL/rols de l'API interna a dev/test/pre.

### P1 de cutover
1. Substituir les mutacions directes dels AJAX legacy per `SifInternalDebtClaimClient`.
2. Mantindre la pantalla legacy inicialment com a UI/adaptador, no com a autoritat.
3. Projectar al llegat `reclamat/data_reclamacio/pag_observacions` només després del commit SIF si encara és necessari.
4. Retirar generació d'URL de pagament legacy basada només en `IDPAG`.
5. Proves E2E de les quatre pantalles en preproducció.

## 5. Criteri actual

La mancança principal ja no és «no existeix coordinador». Ara és:

**coordinador SIF implementat a branca; verificació CI, model de venciment/pròrroga i cutover dels handlers encara pendents.**


## 6. Frontera segura d'intranet incorporada

S'ha afegit una frontera nova, desactivada per defecte amb `SIF_DEBT_CLAIM_UI_ENABLED=0`:

- `LegacyDebtClaimContext.php`: valida sessió i permís de lectura sobre `/facturacio/morosos/`;
- `ajax/facturacio/sifDebtClaim.php`: només POST, mateix origen, AJAX obligatori, CSRF, actor/rol autenticat i permís d'edició;
- `SifInternalDebtClaimClient.php`: HMAC cap a `/api/debt-claims/manage.php`;
- `js/sif-debt-claim-bridge.js`: helper de navegador amb `operation_id` estable per retries;
- les quatre pantalles de morositat creen `csrf_debt_claim` i exposen el meta `csrf-token-debt-claim`.

El pont admet exactament un selector entre `UUID_FACTURA`, `NUM_VISIBLE` o `ID_INSC`. Quan arriba `ID_INSC`, la resolució es fa **al servidor** mitjançant les relacions SIF i només continua si hi ha una factura aplicable inequívoca; si hi ha més d'una factura pendent candidata, retorna conflicte `409`. Per tant, `ID_INSC` no és autoritat econòmica per si mateix ni es transforma de manera cega.

Els endpoints antics `updDadesRecordatoriPagament.php`, `updDadesPrimeraReclamacio.php`, `updLastClaimPay.php` i variants de morosos continuen sense ser substituïts. Això és deliberat fins al cutover.

## 7. Límit UC-096 — venciment i pròrroga

La revisió de `uc-096.md` confirma `NOT_COMPLETE / DISSENY`. El llegat usa dates i camps administratius, però no s'ha localitzat una pròrroga SIF persistent i autoritativa.

Conseqüència:

- UC-012 pot calcular saldo, identificar el receptor fiscal i registrar/escalar una reclamació **per acció explícita d'un operador autoritzat**;
- UC-012 **no** ha d'auto-seleccionar ni auto-enviar reclamacions per calendari mentre UC-096 no tingui persistència de venciment/pròrroga;
- qualsevol scheduler de P-MOR queda bloquejat fins resoldre UC-096.

## 8. Proves i controls nous

A més dels tests del coordinador s'han afegit:

- `DebtClaimIntranetBoundaryTest`: CSRF, same-origin, permís, HMAC i presència del bridge a les quatre pantalles;
- `DebtClaimScriptsContractTest`: preflight/preview/process no productius;
- extensió de `NotificationOutboxDeliveryServiceTest`: una notificació `CANCELLED` no pot ser reclamada ni generar intent de lliurament;
- CI `SIF checks`: `php -l` explícit del pont PHP i `node --check` del JS.

## 9. Estat després d'aquesta passada

`CORE_IMPLEMENTED_ON_BRANCH / SAFE_BRIDGE_IMPLEMENTED_DISABLED / CI_QUEUED / LEGACY_CUTOVER_PENDING / UC096_BLOCKS_AUTOMATION / PREPRODUCTION_PENDING`.

No s'ha activat cap canvi productiu ni s'ha redirigit cap POST legacy.

### Reconciliació d'avisos després de pagament

Un cobrament confirmat, encara que sigui **parcial**, invalida l'import incorporat als avisos `PENDING`. Per això `reconcileAfterPayment()` cancel·la els avisos pendents de la factura en qualsevol reconciliació de pagament. Els missatges ja `SENT` no es modifiquen. Si encara queda saldo, l'expedient continua obert amb el saldo recalculat i qualsevol avís posterior es generarà amb un snapshot nou.


## Bloqueig de cutover confirmat per codi legacy

La simple inclusió de `sif-debt-claim-bridge.js` **no significa que les pantalles hagin fet cutover**. Els quatre JavaScript actuals continuen cridant els endpoints legacy:

- recordatori final → `updDadesRecordatoriPagament.php`;
- primera reclamació → `updDadesPrimeraReclamacio.php`;
- reclamació final → `updLastClaimPay.php`;
- control de morosos → handlers `upd*ClaimPayDefaulter.php`.

A més, `updateSendMsg_LastClaimPay()` no és només una reclamació: segons el cas deriva a `updateSendMsg_LastClaimPay_noApprove()/approve()`, pot executar `__donarBaixaMoodleNou()` i `updCampInscripcioBaixaMorosBD()`. Per això **P-MOR-04 no es pot commutar cegament** a `FINAL_CLAIM`; primer s'ha de separar la reclamació de la baixa acadèmica (UC-72/95/96) i definir la projecció legacy posterior al commit SIF.

També s'ha corregit una incidència existent del recordatori legacy: `updateSendMsg_Facturacio_Recordatori_Pagament()` passava `$reclamatM` a `updClaimRecPag` sense inicialitzar-lo a `Intranet.php`, mentre `IntranetProva.php` sí contenia la lògica correcta. La branca ara preserva el valor anterior de `reclamat` i hi afegeix «Reclamat fi de curs», amb prova de regressió.


### Reclamació final recurrent i període de 30 dies

El control legacy de morosos permet una nova reclamació quan han passat **30 dies** des de la darrera. El model SIF preserva aquesta regla sense relaxar la idempotència:

- `FINAL_REMINDER` i `FIRST_CLAIM` continuen sense poder repetir-se amb una clau nova ni retrocedir d'etapa;
- `FINAL_CLAIM` es pot repetir només si l'expedient continua obert, el saldo continua pendent i han transcorregut almenys 30 dies des de l'últim event `FINAL_CLAIM`;
- un retry amb la mateixa clau idempotent continua reutilitzant el resultat;
- un intent de seguiment final abans de 30 dies retorna conflicte `409`;
- aquesta regla no activa cap scheduler: l'automatització temporal general continua bloquejada fins que UC-096 tingui venciment/pròrroga autoritatius.
