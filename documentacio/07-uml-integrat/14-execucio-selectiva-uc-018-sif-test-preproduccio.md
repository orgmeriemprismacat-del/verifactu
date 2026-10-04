# UC-018 · Execució selectiva a sif_test* i preproducció · 04/10/2026

**Objectiu:** obtenir evidència reproduïble d'UC-018 independentment de la suite global, sense executar cap test destructiu contra producció.

## 1. Gate selectiu disponible

- Runner PHP: `sif/tests/run-uc018-tests.php`
- Workflow: `.github/workflows/uc018-sif-checks.yml`
- Preflight d'entorn: `sif/scripts/preflight-gift-redemption.php`
- Verificador real: `sif/scripts/verify-gift-redemption-preproduction.php`

El runner selectiu carrega només proves de redeem, staging, resolver, concurrència, E2E, recovery, outbox, frontera web i cobertura de regals històrics directament relacionada amb UC-018.

## 2. Requisits

- PHP 8.4;
- extensions `pdo_mysql`, `openssl`, `mbstring`;
- MySQL 8;
- una BD SIF amb nom `sif_test*` en `127.0.0.1` o `localhost`;
- una BD legacy de test separada;
- mai reutilitzar credencials o DSN de producció.

`TestDatabase` rebutja explícitament entorns diferents de `SIF_ENV=test` i bases SIF que no compleixin el patró `sif_test*`.

## 3. Execució selectiva en test

Variables d'entorn mínimes:

```bash
export SIF_ENV=test
export SIF_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=sif_test_uc018;charset=utf8mb4'
export SIF_DB_USER='...'
export SIF_DB_PASSWORD='...'

export SIF_LEGACY_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=sif_legacy_test_uc018;charset=utf8mb4'
export SIF_LEGACY_DB_USER='...'
export SIF_LEGACY_DB_PASSWORD='...'

php sif/tests/run-uc018-tests.php
```

No guardar passwords reals al repositori ni a la documentació.

### Resultat esperat

```text
UC-018: <N> passed, 0 failed
```

Qualsevol `[FAIL]`, `[INFRASTRUCTURE FAIL]` o zero proves executades és NO-GO.

## 4. Lint selectiu

El workflow `UC-018 gift redemption checks` valida sintaxi PHP/JS de les superfícies UC-018 i després executa el runner selectiu.

Inclou, entre d'altres:

- `BescanviaRegal.php`;
- `RegalCurs.php`;
- `.htaccess` i `pagina_bescanvia.php`;
- `SifGiftRedemptionClient.php`;
- `GiftRedemptionConfirmationToken.php` i superfície de confirmació;
- endpoints AJAX de validació/bescanvi;
- `enviarInscripcioBescanvia.php`;
- bundle JS de bescanvi;
- `GiftEntitlementIssuerService`;
- `GiftRedemptionTrustedContextResolver`;
- `GiftEnrollmentStager`;
- `GiftRedemptionService`;
- `GiftRedemptionOrchestrator`;
- `LegacyGiftUsageReconciler`;
- `GiftRedemptionConfirmationBoundaryTest` (AEAD, tampering, fragment, routing i POST).

## 5. Preflight sobre test/preproducció

Abans d'un redeem real controlat:

```bash
php sif/scripts/preflight-gift-redemption.php
```

Ha de retornar:

- `environment_is_test_or_preproduction=true`;
- connectivitat SIF i legacy;
- taules obligatòries disponibles;
- claus internes configurades;
- circuit de redeem i notificacions present;
- cobertura dels regals històrics no consumits;
- `go_no_go_decision=GO`.

## 6. Verificació preproducció dry-run

```bash
php sif/scripts/verify-gift-redemption-preproduction.php
```

Sense `--execute` no consumeix cap regal.

## 7. Verificació controlada amb execució

Preparar un regal i una matrícula exclusivament de prova. El codi regal **no** es passa com argument CLI.

```bash
export SIF_GIFT_REDEMPTION_TEST_ENROLLMENT_ID='...'
export SIF_GIFT_REDEMPTION_TEST_CODE='...'

php sif/scripts/verify-gift-redemption-preproduction.php --execute
```

El JSON d'evidència sanititza camps de secrets/codis.

## 8. Invariants que ha d'acreditar el verificador

- primer redeem acaba en `CONSUMED`;
- `regal.USAT` queda reconciliat amb la matrícula;
- replay reutilitza la mateixa operació;
- existeix una única operació de destí;
- existeix una única `COMPENSATION_ALLOCATION`;
- existeix un únic event `CONSUME`;
- l'entitlement queda consumit per la mateixa operació;
- es materialitzen sis notificacions i el replay les reutilitza;
- no apareix cap factura nova;
- no apareix cap `CHARGE` nou.

## 9. Evidència a conservar

Per cada execució:

- SHA/branch exactes;
- data/hora;
- entorn;
- resultat del runner selectiu;
- resultat de preflight;
- JSON sanititzat del verificador;
- comptadors abans/després de factures i CHARGE;
- UUID d'operació i entitlement;
- evidència SMTP separada, si s'activa el transport real.

No conservar el codi de regal, secrets HMAC, passwords ni signatures a la documentació.

## 10. Criteri de tancament

L'ordre recomanat és:

```text
lint selectiu + routing/confirmació
→ runner UC-018: 0 failed
→ preflight: GO
→ dry-run
→ redeem controlat
→ replay controlat
→ evidència SMTP
→ revisió POLICY/DATA de preu-catàleg
```

El runner selectiu permet atribuir el resultat a UC-018. La suite global continua sent obligatòria abans de merge/producció, però una fallada transversal d'un altre UC no s'ha de presentar com una regressió d'UC-018 sense traça concreta.
