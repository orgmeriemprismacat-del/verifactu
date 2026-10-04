# Execució local/preproducció de proves UC-111

Aquesta via és l'alternativa a GitHub Actions quan el repositori no genera `workflow run`.

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
- `main` o una branca/PR basada en el `main` vigent; registrar sempre el commit exacte executat.

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
3. executa `php sif/tests/run-tests.php`;
4. la suite aplica/verifica migracions sobre la BD de test;
5. executa totes les proves unitàries i d'integració, incloses:
   - `NovicePromotionPostPaymentFlowTest`;
   - `NovicePromotionStudentSummaryServiceTest`;
   - `NovicePromotionGrantServiceTest`;
   - `NovicePromotionInvoiceLinkServiceTest`;
6. desa la sortida a `sif/test-results/uc111-YYYYMMDD-HHMMSS.log`.

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


## Prova de preproducció de la intranet

La suite `sif_test*` acredita serveis, migracions i integració SIF, però **no substitueix** la prova del bridge de la intranet.

### Configuració prèvia

A l'host de la intranet de preproducció:

- `SIF_NOVICE_PROMOTION_UI_ENABLED=0` durant la configuració;
- `SIF_APP_ROOT` apuntant al codi SIF desplegat;
- `SIF_DB_DSN` / usuari / contrasenya de la BD SIF de preproducció;
- `SIF_INTERNAL_NOVICE_PROMOTION_URL` HTTPS;
- `SIF_INTERNAL_NOVICE_PROMOTION_SIGNED_PATH=/api/novice-promotion/manage.php`;
- `SIF_INTERNAL_API_KEY_ID` i `SIF_INTERNAL_API_SECRET`;
- `INTRANET_ALLOWED_ORIGINS` amb l'origen real de la intranet de preproducció.

Al SIF:

- `SIF_NOVICE_PROMO_WRAP_KEY_HEX` de 64 hex;
- `SIF_NOVICE_PROMO_KEY_VERSION`;
- `SIF_NOVICE_PROMOTION_MANAGE_ROLES`;
- el mateix HMAC key id/secret/path;
- connexions SIF + legacy correctes.

No reutilitzar secrets de producció en test/preproducció.

### Matriu mínima manual

| ID | Acció | Resultat esperat |
| --- | --- | --- |
| UC111-PRE-01 | Feature flag a 0 i obrir Consulta / Modifica alumne | No es carrega la targeta UC-111. |
| UC111-PRE-02 | Flag a 1, usuari amb permís, alumne sense dret | Targeta visible amb «cap dret» i sense error 500. |
| UC111-PRE-03 | Usuari sense permís intenta cridar l'endpoint directament | 403/fail-closed; cap dada retornada. |
| UC111-PRE-04 | POST sense CSRF o amb CSRF incorrecte | 403; cap dada retornada. |
| UC111-PRE-05 | Origen/XHR no autoritzat | 403; cap dada retornada. |
| UC111-PRE-06 | JASOM validat però 50 € de 120 € pagats | Cap grant ni codi preparat. |
| UC111-PRE-07 | Segon pagament 70 € | Un únic grant de 120 € i una única preparació de codi. |
| UC111-PRE-08 | Repetir callback/job final | Reutilitza factura/pagament/grant/codi; no duplica events. |
| UC111-PRE-09 | Dret 90 €, aplicació 70 € | Consulta mostra 90/70/20 i historial APPLIED. |
| UC111-PRE-10 | Inspeccionar resposta JSON | No hi ha `NOV-*`, ciphertext, wrapping key ni UUID interns de correlació que la UI no necessiti. |
| UC111-PRE-11 | Validació Sí/No des de secretaria | Legacy i SIF coincideixen; si la projecció SIF falla, resposta de reconciliació pendent, no fals èxit complet. |

### Evidència de navegador/preproducció

Guardar només evidència anonimitzada:

- captura del panell sense DNI, noms, email ni factura real;
- resposta HTTP/codi d'estat de 401/403/405/422 quan correspongui;
- log SIF de l'operació sintètica;
- recompte de grant/outbox/events;
- SHA del commit desplegat;
- flags activats (sense valors secrets);
- versió PHP/MySQL;
- resultat final `PASS`, `FAIL` o `BLOCKED` per cada UC111-PRE-*.

