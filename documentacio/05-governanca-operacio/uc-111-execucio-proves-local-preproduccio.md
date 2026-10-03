# Execució local/preproducció de proves UC-111

Aquesta via permet repetir la verificació UC-111 fora de GitHub Actions i separar-la de fallades d'altres UC a la suite global.

## Proteccions

El runner i `TestDatabase` rebutgen qualsevol execució destructiva si:

- `SIF_ENV` no és `test`;
- la base no es diu `sif_test` o `sif_test_*`;
- el host MySQL no és `localhost` o `127.0.0.1`.

No s'ha d'utilitzar mai una còpia de producció com a DSN de test.

## Requisits

- PHP amb `pdo_mysql`, `openssl` i `mbstring`;
- MySQL 8 local o en la mateixa màquina de preproducció;
- client `mysql`;
- branca `integrate/uc-111-clean-2026-09-29` o la versió integrada equivalent.

## Execució recomanada

```bash
chmod +x sif/scripts/test-uc111-local.sh

SIF_TEST_DB_NAME=sif_test_uc111 \
SIF_TEST_DB_USER=root \
SIF_TEST_DB_PASSWORD='...' \
./sif/scripts/test-uc111-local.sh
```

Per defecte usa:

- host: `127.0.0.1`;
- port: `3306`;
- base: `sif_test_uc111`.

## Què executa

1. crea `sif_test_uc111` si no existeix;
2. exporta configuració SIF de test;
3. executa `php sif/tests/run-uc111-tests.php`;
4. la suite aplica/verifica migracions sobre la BD de test;
5. executa només les proves UC-111 i la porta JASOM, incloses:
   - `NovicePromotionPostPaymentFlowTest`;
   - `NovicePromotionStudentSummaryServiceTest`;
   - `NovicePromotionGrantServiceTest`;
   - `NovicePromotionInvoiceLinkServiceTest`;
6. desa la sortida a `sif/test-results/uc111-YYYYMMDD-HHMMSS.log`.

Per executar també la suite SIF global després de la suite UC-111:

```bash
SIF_TEST_RUN_FULL_SUITE=1 ./sif/scripts/test-uc111-local.sh
```

Una fallada de la suite global no invalida per si sola UC-111: cal identificar si la prova fallida pertany a aquest cas d'ús.

## Criteris UC-111 que han de quedar en PASS

### Pagament
- primer pagament parcial: cap grant;
- pagament complet reconciliat: un únic grant;
- callback/job repetit: mateixa factura/pagament idempotent;
- mateix `UUID_ENTITLEMENT`;
- una sola outbox de codi;
- un sol event `ISSUE`;
- un sol event `ACTIVATE`.

### Consulta / Modifica alumne
- 90,00 € concedits;
- 70,00 € aplicats;
- 20,00 € disponibles;
- historial `APPLIED` amb curs destí i factura;
- cap token `NOV-*` al JSON;
- cap ciphertext al JSON.

## Evidència

No marcar les targetes Trello com a validades fins tenir:

- log complet de la suite;
- nombre de proves PASS/FAIL;
- commit executat;
- nom de la BD `sif_test*`;
- versió PHP/MySQL;
- si hi ha fallada, stack/error i correcció associada.


## Reconciliació de decisió de secretaria

Si el legacy ja ha gravat `recent_titulat.VALIDAT=1/2` però la projecció síncrona al SIF ha fallat, no s'ha de repetir ni inventar la decisió. En una BD `sif_test*` es pot verificar la recuperació amb:

```bash
SIF_ENV=test \
SIF_DB_DSN='mysql:host=127.0.0.1;dbname=sif_test_uc111;charset=utf8mb4' \
SIF_LEGACY_DB_DSN='mysql:host=127.0.0.1;dbname=sif_legacy_test;charset=utf8mb4' \
php sif/scripts/reconcile-novice-decisions.php --limit=100
```

El reconciliador només projecta decisions legacy explícites 1/2 sobre operacions JASOM que continuen `PENDING_VALIDATION`; si el legacy encara és 0, manté el pagament tancat. En aquesta fase el CLI continua restringit a `SIF_ENV=test` fins a l'aprovació de preproducció.
