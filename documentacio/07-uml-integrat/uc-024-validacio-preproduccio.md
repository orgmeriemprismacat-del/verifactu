# UC-024 — Pla de validació de preproducció

**Data:** 04/10/2026  
**Branca:** `audit/uc-024-2026-10-03`  
**Objectiu:** validar el flux navegador → intranet → API SIF → ledger → projecció legacy abans d'activar UC-024 a producció.

## 1. Principi d'activació

La UI de cobrament **ha de romandre desactivada** fins que:

1. el HEAD objectiu hagi passat les proves UC-024;
2. `preflight-claim-payment.php` retorni `ok=true`;
3. `intranet-pre` i `pay-pre` tinguin secrets/rols/orígens coherents;
4. la BD SIF sigui `sif_pre` o una `sif_test*` controlada;
5. la BD legacy sigui una còpia de preproducció, mai producció.

La feature flag és:

```text
SIF_CLAIM_PAYMENT_UI_ENABLED=1
```

En absència del valor `1`, les quatre pantalles de reclamació continuen sense mostrar el control «Registrar cobrament».

## 2. Configuració — intranet-pre

Configurar al runtime de `intranet-pre.prisma.cat` sense versionar valors secrets:

```text
SIF_CLAIM_PAYMENT_UI_ENABLED=0
SIF_INTERNAL_CLAIM_PAYMENT_URL=https://pay-pre.prisma.cat/api/claim-payments/register.php
SIF_INTERNAL_CLAIM_PAYMENT_SIGNED_PATH=/api/claim-payments/register.php
SIF_INTERNAL_API_KEY_ID=<key-id-preproduccio>
SIF_INTERNAL_API_SECRET=<secret-preproduccio>
INTRANET_ALLOWED_ORIGINS=https://intranet-pre.prisma.cat
```

Primer desplegar amb `SIF_CLAIM_PAYMENT_UI_ENABLED=0`. Activar-la només després del preflight i de les proves server-side.

## 3. Configuració — pay-pre

Configurar al runtime de `pay-pre.prisma.cat`:

```text
SIF_ENV=preproduction
SIF_DB_DSN=<dsn sif_pre>
SIF_DB_USER=<usuari sif_pre>
SIF_DB_PASSWORD=<secret>

SIF_LEGACY_DB_DSN=<dsn legacy preproduccio>
SIF_LEGACY_DB_USER=<usuari legacy preproduccio>
SIF_LEGACY_DB_PASSWORD=<secret>

SIF_INTERNAL_API_KEY_ID=<mateix key-id que intranet-pre>
SIF_INTERNAL_API_SECRET=<mateix secret que intranet-pre>
SIF_INTERNAL_CLAIM_PAYMENT_SIGNED_PATH=/api/claim-payments/register.php
SIF_CLAIM_PAYMENT_MANAGE_ROLES=<rols reals autoritzats>
```

No activar `SIF_INTERNAL_API_ALLOW_HTTP` en preproducció.

## 4. Preflight obligatori

Des del codi desplegat a `pay-pre`:

```bash
php sif/scripts/preflight-claim-payment.php
```

Ha de retornar `ok: true`.

Comprova, entre altres:

- entorn no production;
- key/secret/path de l'API interna;
- rols UC-024;
- `factura`, `fact_rels`, `payment_transaction`, `payment_allocation`;
- `payment_action_event` i `internal_api_request`;
- `PAYLOAD_HASH_VERSION`;
- connectivitat legacy;
- taula `inscripcions`;
- camps `ID`, `IDPAG`, `A_PAGAR`, `PAGAMENT`, `DATA PAG`, `INSC CURS`, `OBSERVACIONS`.

No continuar si qualsevol check és fals.

## 5. Preparació de dades

Triar inscripcions de prova que compleixin:

- una sola factura SIF d'origen a `fact_rels`;
- `SOURCE_TYPE='INSCRIPCIO'`;
- `SOURCE_ID=<idInsc>`;
- `IDPAG>0`;
- mateixa línia base econòmica entre `inscripcions.PAGAMENT` i el net del ledger SIF;
- factura amb saldo pendent.

No corregir una inconsistència real manualment només per fer passar el cas: una divergència ha de validar el bloqueig fail-closed.

## 5B. Límit: factura multi-inscripció

UC-024 individual només és elegible si la factura té **una sola** relació `INSCRIPCIO/ORIGIN`.

Si una factura conté diverses inscripcions d’origen:

- el flux ha de retornar 409;
- no s’ha de crear cap CHARGE nou;
- no s’ha de projectar `PAGAMENT` a una sola inscripció;
- cal un cas d’ús específic de repartiment/conciliació agregada.

## 6. Casos E2E mínims

### E2E-01 — feature flag OFF

1. `SIF_CLAIM_PAYMENT_UI_ENABLED=0`.
2. Obrir cadascuna de les quatre superfícies.
3. Verificar que no apareix «Registrar cobrament».
4. Confirmar que els fluxos de reclamació llegats continuen igual.

### E2E-02 — cobrament parcial bancari

Exemple controlat:

- expedient derivat: `LEGACY-INSC:<idInsc>:<fase>`;
- `external_receipt_type=BANK_REFERENCE`;
- rebut E1 únic;
- import 40,00 €;
- factura pendent 120,00 €.

Esperat:

- un nou `payment_transaction`;
- `IDEMPOTENCY_KEY=CLAIM|RECEIPT:BANK_REFERENCE:<E1>`;
- `IDPAG` resolt des de `fact_rels`;
- una assignació `CLAIM_PAYMENT`;
- factura `PARTIAL`;
- events `LINK_CLAIM_PAYMENT REQUESTED/SUCCEEDED`;
- events `SYNC_LEGACY REQUESTED/SUCCEEDED`;
- `inscripcions.PAGAMENT=40.00`.

### E2E-03 — reintent mateix rebut

Repetir E1 amb mateix tipus/id/import.

Esperat:

- mateix `UUID_PAYMENT`;
- cap segon `payment_transaction`;
- resultat `REUSED`;
- projecció legacy idempotent.

### E2E-04 — segon ingrés del mateix expedient

Registrar E2 amb identificador extern diferent i, per exemple, 30,00 €.

Esperat:

- segon `UUID_PAYMENT`;
- dues assignacions econòmiques;
- mateix `claim_case_id` auditat;
- factura encara `PARTIAL`;
- legacy projectat al total net acumulat.

### E2E-05 — cobrament final

Registrar el saldo restant.

Esperat:

- factura `PAID`;
- `inscripcions.PAGAMENT=A_PAGAR`;
- `DATA PAG` informada si era buida;
- `INSC CURS` passa de `M` a `1` quan correspongui.

### E2E-06 — import superior al pendent

Intentar un import > saldo.

Esperat:

- HTTP 409;
- cap nou `payment_transaction`;
- cap canvi legacy;
- cap `OVERPAID` creat per UC-024.

### E2E-07 — baseline legacy divergent

Preparar una inscripció de prova amb `PAGAMENT legacy != net SIF` abans del cobrament nou.

Esperat:

- HTTP 409;
- `Legacy payment baseline does not match SIF invoice ledger`;
- cap nou CHARGE.

### E2E-08 — rebut existent a un altre canal

Usar un rebut ja present al ledger amb mateixa factura, mateix IDPAG i mateix import.

Esperat:

- reutilització del `UUID_PAYMENT`;
- cap CHARGE nou;
- projecció legacy segons el ledger.

### E2E-09 — rebut ja assignat a una altra factura

Esperat:

- HTTP 409;
- cap reallocació implícita;
- cap mutació legacy.

### E2E-10 — DS_ORDER/PROVIDER_REF no existent

Enviar un `DS_ORDER` o `PROVIDER_REF` que el canal autoritatiu encara no hagi registrat.

Esperat:

- HTTP 409;
- UC-024 **no** crea un cobrament manual substitutiu.

### E2E-11 — seguretat negativa

Provar separadament:

- sense sessió;
- GET en lloc de POST;
- CSRF absent/incorrecte;
- Origin/Referer no autoritzat;
- sense permís d'edició;
- rol SIF no inclòs a `SIF_CLAIM_PAYMENT_MANAGE_ROLES`;
- signatura interna incorrecta/replay.

Esperat: 401/403/405/409 segons el cas i cap moviment econòmic.

## 7. Evidència SQL — SIF

### Relació autoritativa inscripció → factura

```sql
SELECT
    r.SOURCE_TYPE,
    r.SOURCE_ID,
    r.IDPAG,
    r.UUID_FACTURA,
    f.NUM_VISIBLE,
    f.TOTAL,
    f.ESTAT_COBRAMENT
FROM fact_rels r
JOIN factura f ON f.UUID_FACTURA = r.UUID_FACTURA
WHERE r.SOURCE_TYPE = 'INSCRIPCIO'
  AND r.SOURCE_ID = :id_insc
  AND r.RELATION_TYPE = 'ORIGIN';
```

### Moviment i rebut extern

```sql
SELECT
    UUID_PAYMENT,
    IDEMPOTENCY_KEY,
    TIPUS_MOVIMENT,
    METODE,
    SOURCE_CHANNEL,
    IMPORT,
    IDPAG,
    REFERENCIA_BANCARIA,
    DS_ORDER,
    PROVIDER_REF,
    ESTAT,
    CREATED_AT
FROM payment_transaction
WHERE UUID_PAYMENT = :uuid_payment;
```

### Assignació

```sql
SELECT
    UUID_PAYMENT,
    UUID_FACTURA,
    IMPORT_ASSIGNAT,
    TIPUS_ASSIGNACIO,
    CREATED_AT
FROM payment_allocation
WHERE UUID_PAYMENT = :uuid_payment;
```

### Auditoria

```sql
SELECT
    ACTION,
    RESULT,
    IS_TERMINAL,
    UUID_PAYMENT,
    PAYMENT_IDEMPOTENCY_KEY,
    REQUEST_ID,
    CORRELATION_ID,
    SOURCE_CHANNEL,
    ACTOR_ID,
    ACTOR_ROLE,
    REASON_CODE,
    CHANGESET_JSON,
    ERROR_CODE,
    OCCURRED_AT
FROM payment_action_event
WHERE UUID_PAYMENT = :uuid_payment
ORDER BY ID;
```

Ha d'incloure `LINK_CLAIM_PAYMENT` i `SYNC_LEGACY`.

### Absència d'efecte fiscal nou

Guardar abans/després:

```sql
SELECT COUNT(*) FROM factura_registres WHERE UUID_FACTURA = :uuid_factura;
SELECT COUNT(*) FROM fiscal_queue WHERE UUID_FACTURA = :uuid_factura;
```

Un cobrament posterior no ha de crear un registre fiscal nou.

## 8. Evidència SQL — legacy

```sql
SELECT
    ID,
    IDPAG,
    A_PAGAR,
    PAGAMENT,
    `DATA PAG`,
    `INSC CURS`,
    OBSERVACIONS
FROM inscripcions
WHERE ID = :id_insc
  AND IDPAG = :idpag;
```

Conservar el resultat abans i després de cada cas nominal.

## 9. Reconciliació pendent després del commit SIF

Si l'API retorna:

```json
{
  "ok": false,
  "payment_persisted": true,
  "requires_reconciliation": true
}
```

no registrar manualment un segon cobrament.

Procediment:

1. conservar el `UUID_PAYMENT`;
2. corregir la causa de la projecció legacy;
3. repetir la mateixa operació amb el mateix rebut extern i import;
4. comprovar que el SIF retorna/reutilitza el mateix `UUID_PAYMENT`;
5. comprovar `SYNC_LEGACY SUCCEEDED`.

No esborra ni s'edita el moviment econòmic ja registrat.

## 10. Activació gradual

Ordre recomanat:

1. desplegar codi amb flag OFF;
2. executar preflight;
3. executar casos server-side;
4. activar flag només a `intranet-pre`;
5. executar E2E-01..E2E-11;
6. conservar captures/JSON/SQL i SHA;
7. desactivar de nou el flag si queda qualsevol diferència;
8. només després preparar activació de producció.

## 11. Rollback operatiu

Si apareix una incidència:

```text
SIF_CLAIM_PAYMENT_UI_ENABLED=0
```

Això retira immediatament la UI nova sense tocar els moviments ja persistits.

No:

- esborrar `payment_transaction`;
- modificar imports per SQL;
- eliminar `payment_action_event`;
- «compensar» un error amb un segon CHARGE manual.

Qualsevol correcció econòmica s'ha de fer amb el flux tipificat corresponent.

## 12. Criteri de tancament de preproducció

UC-024 pot passar de `PREPROD_PENDING` a `PREPROD_VERIFIED` quan:

- preflight = OK;
- proves UC-024 del commit = OK;
- E2E-01..E2E-11 = OK o justificades;
- cap CHARGE duplicat;
- cap OVERPAID creat per UC-024;
- SIF i legacy queden conciliats;
- auditoria completa;
- sense efecte fiscal nou;
- evidència guardada amb SHA del commit.
