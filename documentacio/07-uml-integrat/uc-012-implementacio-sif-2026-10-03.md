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
