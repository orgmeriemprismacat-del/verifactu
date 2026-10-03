# UC-012 — Auditoria exhaustiva i matriu de traçabilitat

**Data:** 03/10/2026  
**Base inicial:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Branca:** `audit/uc-012-2026-10-03`

## 1. Resultat executiu

L’auditoria va començar amb un UC-012 parcial: pantalles i correus legacy reals, però només el cobrament posterior (`ClaimPaymentService`) estava cobert al SIF. Durant la mateixa auditoria s’ha implementat el nucli SIF de morositat i una frontera segura d’intranet.

**Estat actual:** `AUDIT_COMPLETE / CORE_IMPLEMENTED_ON_BRANCH / CI_QUEUED / CUTOVER_PENDING / PREPRODUCTION_PENDING`.

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa + inventari + UML | Sí | n/a | contrast amb codi real | merge final |
| P-MOR-01 saldo/receptor | Sí | Sí | tests escrits | CI; UC-096 per dates/pròrroga |
| P-MOR-02 recordatori final | Sí | Sí | tests escrits | CI/delivery/cutover |
| P-MOR-03 primera reclamació | Sí | Sí | tests escrits | CI/delivery/cutover |
| P-MOR-04 reclamació final | Sí | Sí | tests escrits | CI/delivery/cutover |
| P-MOR-05 regularització | Sí | Sí | ClaimPayment existent + tests nous | CI/preproducció |
| Expedient durable | Sí | Sí | tests escrits | CI/preproducció |
| Outbox idempotent | Sí | Sí | tests escrits | delivery UC-58 |
| API HMAC | Sí | Sí | contract test | CI/config entorn |
| Bridge intranet CSRF/rol | Sí | Sí, desactivat | boundary test | cutover |
| Legacy POST antics | Sí | Sí | inspecció | substitució progressiva |

## 2. Mapa P-MOR reconciliat

| ID | ACTUAL legacy | FINAL a la branca | Estat |
| --- | --- | --- | --- |
| P-MOR-01 | SQL sobre `inscripcions`, `A_PAGAR-PAGAMENT` | `DebtSnapshotRepository`: factura + allocations + refunds + receptor fiscal | IMPLEMENTAT; dates/pròrroga UC-096 pendents |
| P-MOR-02 | UPDATE + SMTP | `DebtClaimCoordinator` + event + outbox | IMPLEMENTAT A BRANCA |
| P-MOR-03 | UPDATE + URL `IDPAG` + SMTP | event versionat + outbox idempotent | IMPLEMENTAT A BRANCA |
| P-MOR-04 | reclamació/baixa acoblades | `FINAL_CLAIM`; baixa continua separada | IMPLEMENTAT A BRANCA |
| P-MOR-05 | camps legacy + cobrament separat | `ClaimPaymentService` + `reconcileAfterPayment()` | IMPLEMENTAT A BRANCA |

## 3. Codi legacy contrastat

- `facturacio-recordatori-pagament-final.php` + JS + `updDadesRecordatoriPagament.php`.
- `facturacio-primera-reclamacio-pagament.php` + JS + `updDadesPrimeraReclamacio.php`.
- `facturacio-reclamacio-final.php` + JS + `updLastClaimPay.php`.
- `facturacio-control-morosos.php` + JS + handlers d’entitat/alumne certificat/no certificat.
- `Intranet.php`: `cnsCursosRecordarPag`, `cnsAlumnesRecordarPag`, `cnsCursosClaimBaixes`, `cnsAlumnClaimPag`, `cnsAlumnClaimEntMoros`, `cnsAlumnClaimAlumnNoCertMoros`, `cnsAlumnClaimAlumnCertMoros`, `cnsEntMoros`, i mutacions relacionades.

Troballa: els POST legacy executen mutació + SMTP al mateix flux i treballen principalment amb `ID_INSC`/`A_PAGAR-PAGAMENT`. Continuen existint fins al cutover i no es consideren reescrits pel bridge nou.

## 4. Codi FINAL implementat durant l’auditoria

### Persistència i domini
- migració `2026_10_03_000033_add_debt_claim_case.sql`;
- `DebtSnapshotRepository`;
- `DebtClaimCaseRepository`;
- `DebtClaimCoordinator`;
- extensió de `NotificationOutboxRepository` i `NotificationOutboxDeliveryService`.

### Frontera SIF/intranet
- `sif/public/api/debt-claims/manage.php` amb `InternalApiAuthenticator`;
- `SifInternalDebtClaimClient.php`;
- `LegacyDebtClaimContext.php`;
- `ajax/facturacio/sifDebtClaim.php`;
- `js/sif-debt-claim-bridge.js`;
- les quatre pàgines legacy creen CSRF i carreguen el bridge, sense commutar encara les mutacions antigues.

### Operació no productiva
- `preflight-debt-claim.php`;
- `preview-debt-claim.php`;
- `process-debt-claim.php`.

## 5. Matriu de traçabilitat

| Requisit | Codi | Prova/evidència | Estat |
| --- | --- | --- | --- |
| Saldo des del ledger SIF | `DebtSnapshotRepository` | `DebtClaimCoordinatorSmokeTest`/reconciliació | IMPLEMENTAT; CI EN CUA |
| Receptor fiscal, no alumne implícit | `BILLING_EMAIL` de `factura` | smoke + boundary | IMPLEMENTAT; CI EN CUA |
| Expedient únic per factura | `debt_claim_case` | smoke | IMPLEMENTAT; CI EN CUA |
| Events append-only | `debt_claim_event` | smoke/guards | IMPLEMENTAT; CI EN CUA |
| Retry equivalent | payload hash + idempotency key | smoke | IMPLEMENTAT; CI EN CUA |
| Payload contradictori | `PayloadIdempotencyValidator` | guards | IMPLEMENTAT; CI EN CUA |
| No regressió d’etapa | rank FINAL_REMINDER/FIRST/FINAL | guards | IMPLEMENTAT; CI EN CUA |
| Outbox post-commit | `NotificationOutboxRepository` | smoke | IMPLEMENTAT; CI EN CUA |
| Cancel·lació d’avisos en saldo zero | `cancelPendingForInvoice()` | reconciliation + delivery test | IMPLEMENTAT; CI EN CUA |
| Cobrament sense nova factura | `ClaimPaymentService` | tests existents | VERIFICAT AL REPOSITORI PREVI; revalidació CI actual pendent |
| CSRF/mateix origen | bridge intranet | `DebtClaimIntranetBoundaryTest` | IMPLEMENTAT; CI EN CUA |
| Rol/permís servidor | context + `assertCanEdit` + API roles | boundary/guards | IMPLEMENTAT; CI EN CUA |
| HMAC/replay intern | client + `InternalApiAuthenticator` | contract test | IMPLEMENTAT; CI EN CUA |
| Venciment/pròrroga | UC-096 | no hi ha model SIF complet | PENDENT / BLOQUEJA SCHEDULER |
| Delivery real | UC-58 | no acreditat | PENDENT |

## 6. Proves afegides

- `DebtClaimCoordinatorSmokeTest`.
- `DebtClaimCoordinatorReconciliationTest`.
- `DebtClaimCoordinatorGuardsTest`.
- `DebtClaimInternalApiContractTest`.
- `DebtClaimIntranetBoundaryTest`.
- `DebtClaimScriptsContractTest`.
- `NotificationOutboxDeliveryServiceTest` ampliat per `CANCELLED`.

GitHub Actions continua `queued` en la darrera comprovació; per tant, aquestes proves són **existents però no encara acreditades com a verdes**.

## 7. Decisions de seguretat

1. El bridge és `SIF_DEBT_CLAIM_UI_ENABLED=0` per defecte.
2. No es converteix automàticament `ID_INSC` en factura.
3. Mutacions requereixen factura SIF explícita, CSRF, same-origin, rol i HMAC.
4. Una reclamació té `fiscal_impact=NONE` i `economic_impact=NONE`.
5. La baixa acadèmica continua en UC-72/95/96.
6. No s’activa cap scheduler de morositat fins que UC-096 tingui venciment/pròrroga autoritatius.

## 8. Pendent per acceptació operativa

- CI MySQL/SIF verda;
- preflight i proves sobre `sif_test*`/preproducció;
- configurar URL, key/secret i rols de l’API interna;
- plantilles/delivery UC-58;
- pilot de cutover d’una pantalla i després les restants;
- evidència de pagament parcial/complet, retry, rol denegat i CSRF invàlid;
- UC-096 abans de qualsevol automatització temporal.

## 9. Estat de tancament

**Auditoria tècnica/documental: TANCADA.**  
**Implementació del nucli: COMPLETA A LA BRANCA, no encara verificada per CI.**  
**Legacy cutover: PENDENT.**  
**Acceptació de preproducció/producció: PENDENT.**

Vegeu també `uc-012-implementacio-sif-2026-10-03.md` i `uc-012-tancament-auditoria-2026-10-03.md`.
