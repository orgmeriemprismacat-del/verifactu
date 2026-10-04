# UC-006 · Verificació controlada en `sif_test` i preproducció — 2026-10-04

## 1. Objectiu

Validar amb evidència executable les primitives UC-006 afegides a la branca:

- atribució inicial `EXTERNAL_ALLOCATION`;
- càlcul de dret disponible per inscripció;
- `CREDIT_CREATE` i rollback per fons insuficients;
- `REFUND_EXIT` i rollback contra dret ja consumit;
- `COMPENSATION_ALLOCATION` cap a factura/línia/inscripció;
- reintents idempotents;
- separació de `correlation_id` respecte de la identitat econòmica.

Aquesta verificació **no tanca** titularitat, autorització, evidència bancària genèrica ni la UI UC-006.

## 2. Regles de seguretat

1. **`sif/tests/run-uc006-tests.php` només contra una BD de test descartable** com `sif_test`.
2. El runner crida `TestDatabase::fresh()`: **no executar-lo contra `sif_pre`, cap BD compartida ni producció**.
3. Els scripts `process-*` refusen `SIF_ENV=production`, però en preproducció igualment s'han d'usar només amb dades de prova identificades.
4. Guardar commit SHA, entorn, timestamps, UUIDs i captures/outputs JSON com a evidència.
5. No provar un refund bancari real des d'aquesta guia; `ManualRefundService` registra un REFUND intern confirmat i no substitueix l'evidència externa.

## 3. Fase A — suite selectiva sobre `sif_test`

Variables mínimes:

```bash
export SIF_ENV=test
export SIF_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=sif_test;charset=utf8mb4'
export SIF_DB_USER='...'
export SIF_DB_PASSWORD='...'
export SIF_LEGACY_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=sif_legacy_test;charset=utf8mb4'
export SIF_LEGACY_DB_USER='...'
export SIF_LEGACY_DB_PASSWORD='...'
```

Executar:

```bash
php -l sif/tests/run-uc006-tests.php
php sif/tests/run-uc006-tests.php
```

Resultat exigible:

- cap `[INFRASTRUCTURE FAIL]`;
- cap `[MISSING TEST FILE]` / `[MISSING TEST CLASS]`;
- `UC-006 selective suite: N passed, 0 failed`;
- les migracions, incloses `000033` i `000034`, s'apliquen en una BD neta.

## 4. Fase B — lint dels fitxers econòmics modificats

```bash
php -l sif/src/Repository/EnrollmentFundMovementRepository.php
php -l sif/src/Service/PaymentService.php
php -l sif/src/Service/ManualRefundPayloadBuilder.php
php -l sif/src/Service/ManualRefundService.php
php -l sif/src/Service/CreditBalancePayloadBuilder.php
php -l sif/src/Service/CreditBalanceService.php
php -l sif/tests/Integration/CreditBalanceServiceTest.php
php -l sif/tests/Integration/ManualRefundServiceTest.php
```

Tots han de retornar `No syntax errors detected`.

## 5. Fase C — inspecció prèvia en preproducció

Abans de mutar res, identificar una inscripció de prova amb fons atribuïts:

```sql
SELECT
    ID_INSC_DESTI,
    UUID_PAYMENT,
    UUID_FACTURA,
    ID_FACTURA_LINIA,
    IMPORT,
    CORRELATION_ID
FROM enrollment_fund_movement
WHERE MOVEMENT_TYPE = 'EXTERNAL_ALLOCATION'
ORDER BY ID DESC;
```

Inventariar tots els moviments de la inscripció:

```sql
SELECT
    ID,
    UUID_MOVEMENT,
    MOVEMENT_TYPE,
    UUID_PAYMENT,
    UUID_CREDIT,
    UUID_FACTURA,
    ID_FACTURA_LINIA,
    ID_INSC_ORIGEN,
    ID_INSC_DESTI,
    IMPORT,
    IDEMPOTENCY_KEY,
    CORRELATION_ID,
    CREATED_AT
FROM enrollment_fund_movement
WHERE ID_INSC_ORIGEN = :id_insc
   OR ID_INSC_DESTI = :id_insc
ORDER BY ID;
```

No usar una inscripció real de producció ni una preproducció compartida amb proves alienes.

## 6. Fase D — crear saldo des de fons d'una inscripció

Preview:

```bash
php sif/scripts/preview-credit-balance.php 80.00 \
  --holder-type=STUDENT \
  --holder-id=<HOLDER_ID> \
  --holder-name='UC006 TEST' \
  --source-type=BAIXA \
  --source-enrollment-id=<ID_INSC_ORIGEN> \
  --uuid-factura-origen=<UUID_FACTURA_ORIGEN> \
  --idempotency-key='UC006|PRE|CREDIT|<ID_INSC>|80' \
  --correlation-id='UC006-PRE-CREDIT-A'
```

Process controlat:

```bash
php sif/scripts/process-credit-balance.php 80.00 \
  --holder-type=STUDENT \
  --holder-id=<HOLDER_ID> \
  --holder-name='UC006 TEST' \
  --source-type=BAIXA \
  --source-enrollment-id=<ID_INSC_ORIGEN> \
  --uuid-factura-origen=<UUID_FACTURA_ORIGEN> \
  --idempotency-key='UC006|PRE|CREDIT|<ID_INSC>|80' \
  --correlation-id='UC006-PRE-CREDIT-A'
```

Repetir exactament el mateix fet amb una correlació nova:

```bash
# Mateixa K i payload econòmic; només canvia la traça.
php sif/scripts/process-credit-balance.php 80.00 \
  --holder-type=STUDENT \
  --holder-id=<HOLDER_ID> \
  --holder-name='UC006 TEST' \
  --source-type=BAIXA \
  --source-enrollment-id=<ID_INSC_ORIGEN> \
  --uuid-factura-origen=<UUID_FACTURA_ORIGEN> \
  --idempotency-key='UC006|PRE|CREDIT|<ID_INSC>|80' \
  --correlation-id='UC006-PRE-CREDIT-B'
```

Esperat:

- mateix `uuid_credit`;
- segon resultat amb `idempotency_reused=true`;
- una sola fila `credit_balance`;
- un sol `CREDIT_CREATE`;
- si l'entrada inicial era 120 €, resten 40 € disponibles al ledger.

Evidència:

```sql
SELECT UUID_CREDIT, IDEMPOTENCY_KEY, IMPORT_ORIGINAL, IMPORT_DISPONIBLE, ESTAT
FROM credit_balance
WHERE IDEMPOTENCY_KEY = 'UC006|PRE|CREDIT|<ID_INSC>|80';

SELECT *
FROM enrollment_fund_movement
WHERE MOVEMENT_TYPE = 'CREDIT_CREATE'
  AND ID_INSC_ORIGEN = <ID_INSC_ORIGEN>;
```

## 7. Fase E — bloqueig per fons insuficients

Sobre el mateix origen, si només resten 40 €, intentar crear 50 € addicionals amb una K nova.

Esperat:

- 409/conflicte;
- cap segon `credit_balance`;
- cap segon `CREDIT_CREATE`;
- dret disponible continua sent 40 €.

Capturar recompte abans/després:

```sql
SELECT COUNT(*) FROM credit_balance;
SELECT COUNT(*) FROM enrollment_fund_movement WHERE ID_INSC_ORIGEN = <ID_INSC_ORIGEN>;
```

## 8. Fase F — refund contra el mateix dret

Usar una inscripció de prova amb dret disponible suficient i una factura SIF controlada.

Preview:

```bash
php sif/scripts/preview-manual-refund.php \
  --uuid-factura=<UUID_FACTURA> \
  40.00 '2026-10-04 10:00:00' \
  --idempotency-key='REFUND|EXTERNAL|UC006|PRE|001' \
  --idempotency-key='REFUND|EXTERNAL|UC006|PRE|001' \
  --reference='UC006-PRE-REFUND-001' \
  --source-enrollment-id=<ID_INSC_ORIGEN> \
  --correlation-id='UC006-PRE-REFUND-A'
```

Process:

```bash
php sif/scripts/process-manual-refund.php \
  --uuid-factura=<UUID_FACTURA> \
  40.00 '2026-10-04 10:00:00' \
  --reference='UC006-PRE-REFUND-001' \
  --source-enrollment-id=<ID_INSC_ORIGEN> \
  --correlation-id='UC006-PRE-REFUND-A'
```

Esperat:

- un `payment_transaction.TIPUS_MOVIMENT='REFUND'`;
- un `REFUND_EXIT` amb el mateix `UUID_PAYMENT`;
- reintent equivalent no duplica cap fila.

## 9. Fase G — prova creuada saldo vs refund

Escenari controlat:

1. `EXTERNAL_ALLOCATION = 120`;
2. `CREDIT_CREATE = 80`;
3. intentar `REFUND_EXIT = 50`.

Esperat:

- el refund falla;
- cap `REFUND` queda persistit si s'ha creat dins la mateixa transacció;
- dret disponible = 40;
- saldo de 80 continua intacte.

Aquesta prova és la verificació operativa de la conservació:

`entrades - sortides = dret disponible >= 0`.

## 10. Fase H — aplicar saldo a una inscripció destí

Cal una factura de prova pendent que tingui exactament una `factura_linia` amb:

- `SOURCE_TYPE='INSCRIPCIO'`;
- `SOURCE_ID=<ID_INSC_DESTI>`.

Preview:

```bash
php sif/scripts/preview-credit-compensation.php \
  --uuid-credit=<UUID_CREDIT> \
  --uuid-factura=<UUID_FACTURA_DESTI> \
  60.00 '2026-10-04 10:30:00' \
  --idempotency-key='COMP|UC006|PRE|ORDER:A' \
  --target-enrollment-id=<ID_INSC_DESTI> \
  --correlation-id='UC006-PRE-COMP-A'
```

Process:

```bash
php sif/scripts/process-credit-compensation.php \
  --uuid-credit=<UUID_CREDIT> \
  --uuid-factura=<UUID_FACTURA_DESTI> \
  60.00 '2026-10-04 10:30:00' \
  --target-enrollment-id=<ID_INSC_DESTI> \
  --correlation-id='UC006-PRE-COMP-A'
```

Esperat:

- `payment_transaction=COMPENSATION`;
- `payment_allocation` a la factura;
- `COMPENSATION_ALLOCATION` amb `UUID_CREDIT`, `UUID_PAYMENT`, factura, línia i destí;
- si el saldo original era 80 €, `IMPORT_DISPONIBLE=20`;
- el dret de la inscripció destí augmenta en 60 €.

## 11. Fase I — destí invàlid

Repetir una compensació amb un `target_enrollment_id` que **no** existeix a les línies de la factura.

Esperat:

- 409;
- rollback del `COMPENSATION`;
- cap nova `payment_allocation`;
- cap nou `COMPENSATION_ALLOCATION`;
- saldo disponible sense canvis.

## 12. Evidència que s'ha de conservar

Per cada escenari:

- commit SHA provat;
- entorn i nom de BD;
- ordre executada;
- JSON complet de resposta;
- UUID payment/credit/factura/movement;
- files de `credit_balance`;
- files de `payment_transaction` i `payment_allocation`;
- files de `enrollment_fund_movement`;
- estat de `factura.ESTAT_COBRAMENT`;
- hora Europe/Madrid;
- resultat `PASS/FAIL` i incidència si n'hi ha.

## 13. Criteri de sortida

El bloc tècnic de ledger UC-006 es pot marcar **VERIFICAT EN ENTORN** quan:

1. la suite selectiva passa sobre `sif_test`;
2. les migracions s'apliquen netes;
3. reús i conflictes idempotents coincideixen amb els tests;
4. saldo i refund no poden consumir el mateix valor dues vegades;
5. compensació només atribueix a una línia/inscripció real de la factura;
6. cap rollback deixa payment/credit/ledger parcial;
7. l'evidència queda conservada.

Encara després d'això continuaran pendents per al tancament funcional complet: titularitat, autorització, evidence layer del retorn real, `INTERNAL_TRANSFER`, audit gateway i E2E de la intranet.
