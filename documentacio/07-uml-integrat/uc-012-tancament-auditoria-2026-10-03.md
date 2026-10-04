# UC-012 — Tancament de l'auditoria tècnica — 03/10/2026

**Branca:** `audit/uc-012-2026-10-03`  
**PR:** #140  
**Base inicial auditada:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`

## 1. Resultat

L'auditoria de UC-012 queda **tècnicament completada**. La revisió inicial va demostrar que el cicle de morositat existia només de forma fragmentada al llegat i que el SIF únicament cobria el cobrament posterior amb `ClaimPaymentService`.

Durant aquesta auditoria s'ha implementat el nucli SIF que faltava i una frontera segura d'intranet. L'estat actual és:

`AUDIT_COMPLETE / CORE_IMPLEMENTED_ON_BRANCH / CI_QUEUED / CUTOVER_PENDING / PREPRODUCTION_PENDING`.

## 2. Documentat / implementat / verificat / pendent

| Àrea | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa funcional | Sí | n/a | contrastada amb codi | reconciliació final amb merge |
| Inventari PHP/JS | Sí | n/a | inspecció real | — |
| Classes ACTUAL/FINAL | Sí | FINAL implementat a branca | tests escrits | CI |
| Seqüències ACTUAL/FINAL | Sí | FINAL implementat a branca | tests escrits | CI/preproducció |
| P-MOR-01 saldo/pagador | Sí | Sí | tests escrits | UC-096 per venciment/pròrroga |
| P-MOR-02 recordatori | Sí | Sí: event + outbox | tests escrits | delivery/cutover |
| P-MOR-03 primera reclamació | Sí | Sí: event + outbox | tests escrits | delivery/cutover |
| P-MOR-04 reclamació final | Sí | Sí: event + outbox | tests escrits | baixa acadèmica separada |
| P-MOR-05 regularització | Sí | Sí | ClaimPayment existent + tests nous | CI/preproducció |
| Idempotència | Sí | Sí, payload hash | tests escrits | CI |
| Auditoria | Sí | Sí, `operational_event` | tests escrits | CI |
| API interna HMAC | Sí | Sí | contract test | CI/config entorn |
| CSRF/mateix origen/rol | Sí | Sí al bridge nou | boundary test | activar cutover |
| Legacy handlers | Sí | segueixen actius | inspecció estàtica | substituir després de proves |
| Automatització temporal | Sí com a objectiu | No | No | UC-096 |

## 3. Fitxers de documentació principals

- `documentacio/06-fitxes-funcionals/uc-012.md`
- `documentacio/07-uml-integrat/uc-012-morositat-reclamacio.md`
- `documentacio/07-uml-integrat/uc-012-inventari-codi-php-js-actual-final-2026-10-03.md`
- `documentacio/07-uml-integrat/uc-012-classes-actual-final.md`
- `documentacio/07-uml-integrat/uc-012-sequencies-activitats-actual-final.md`
- `documentacio/07-uml-integrat/uc-012-auditoria-tracabilitat-2026-10-03.md`
- `documentacio/07-uml-integrat/uc-012-implementacio-sif-2026-10-03.md`
- aquest document de tancament.

## 4. Fitxers executables nous o modificats

### SIF
- `sif/database/migrations/2026_10_03_000033_add_debt_claim_case.sql`
- `sif/src/Repository/DebtSnapshotRepository.php`
- `sif/src/Repository/DebtClaimCaseRepository.php`
- `sif/src/Service/DebtClaimCoordinator.php`
- `sif/src/Repository/NotificationOutboxRepository.php`
- `sif/src/Service/NotificationOutboxDeliveryService.php`
- `sif/public/api/debt-claims/manage.php`
- `sif/scripts/preflight-debt-claim.php`
- `sif/scripts/preview-debt-claim.php`
- `sif/scripts/process-debt-claim.php`

### Intranet
- `SifInternalDebtClaimClient.php`
- `LegacyDebtClaimContext.php`
- `ajax/facturacio/sifDebtClaim.php`
- `js/sif-debt-claim-bridge.js`
- quatre pàgines legacy preparades amb CSRF/meta/bridge.

### Proves
- `DebtClaimCoordinatorSmokeTest.php`
- `DebtClaimCoordinatorReconciliationTest.php`
- `DebtClaimCoordinatorGuardsTest.php`
- `DebtClaimInternalApiContractTest.php`
- `DebtClaimIntranetBoundaryTest.php`
- `DebtClaimScriptsContractTest.php`
- `NotificationOutboxDeliveryServiceTest.php` ampliat.

## 5. Regles tancades

1. Una reclamació no crea factura, cobrament ni registre AEAT.
2. El saldo es calcula des de factura + ledger SIF, no des de `A_PAGAR-PAGAMENT`.
3. El pagador de l'avís és el receptor de la factura SIF; no s'infereix automàticament des de l'alumne.
4. Un retry equivalent reutilitza l'event; mateixa clau amb payload contradictori falla amb conflicte.
5. Les etapes no poden retrocedir amb una clau nova.
6. El pagament parcial recalcula el saldo; el complet tanca l'expedient.
7. En tancar-se, els avisos pendents es cancel·len i el worker no els envia.
8. La baixa acadèmica és una decisió separada i no anul·la fiscalment la factura.
9. El bridge de la intranet és fail-closed: feature flag, POST, mateix origen, CSRF, permisos i HMAC.
10. `ID_INSC` només es resol server-side si identifica inequívocament una factura SIF aplicable; l'ambigüitat falla amb `409`.

## 6. Bloquejos que no permeten declarar producció

### CI
GitHub Actions del PR continua en cua en la darrera comprovació. Els tests existeixen, però no s'etiqueten com a `VERIFIED` fins tenir resultat verd.

### UC-096
No existeix encara un venciment/pròrroga SIF persistent. Per això no s'activa scheduler automàtic de recordatoris.

### Delivery UC-58
L'outbox és durable, però cal confirmar les plantilles i el transport real de:
- `DEBT_CLAIM_FINAL_REMINDER`;
- `DEBT_CLAIM_FIRST_CLAIM`;
- `DEBT_CLAIM_FINAL_CLAIM`.

### Cutover
Els POST legacy continuen executant la lògica antiga. El nou bridge està preparat però no activat.

### Preproducció
Cal executar, conservar i identificar evidència sobre `sif_test*`/preproducció.

## 7. Via alternativa d'evidència

Amb `SIF_ENV` no productiu:

```bash
php sif/scripts/preflight-debt-claim.php
php sif/scripts/preview-debt-claim.php --uuid-factura=<UUID>
php sif/scripts/process-debt-claim.php --uuid-factura=<UUID> --action=FINAL_REMINDER --idempotency-key=<KEY>
php sif/scripts/process-debt-claim.php --uuid-factura=<UUID> --action=RECONCILE_AFTER_PAYMENT --idempotency-key=<KEY>
```

Els scripts rebutgen `SIF_ENV=production`.

## 8. Criteri per passar a IMPLEMENTATION_CLOSED

- CI MySQL/SIF verda;
- preflight verd en entorn identificat;
- prova P-MOR-02 → P-MOR-03 → P-MOR-04 controlada;
- pagament parcial i complet amb tancament i cancel·lació d'outbox;
- rol denegat, CSRF invàlid i retry contradictori sense efectes;
- delivery o decisió explícita de no activar correus;
- cutover d'una pantalla pilot i després de les restants;
- cap automatització per dates fins tancar UC-096.

Fins aleshores l'auditoria està tancada, però l'acceptació operativa no.

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
