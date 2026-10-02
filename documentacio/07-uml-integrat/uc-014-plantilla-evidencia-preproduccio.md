# UC-014 — Plantilla d'evidència de preproducció Redsys

**Data/hora:**  
**Entorn:** test / preproduction  
**Commit SHA:**  
**Operador:**  
**Resultat global:** PASS / FAIL

## 1. Identitat de l'operació

- DS_ORDER:
- IDPAG:
- UUID_INTENT:
- UUID_FACTURA:
- NUM_VISIBLE:
- UUID_PAYMENT:
- UUID_JOB:
- UUID_NOTIFICATION:
- NOTIFICATION_STATUS:
- SIF_REDSYS_COURSE_CUTOVER_ENABLED: 0 / 1
- SIF_REDSYS_CALLBACK_URL configurada amb HTTPS: SÍ / NO (no copiar secrets ni query sensible)
- REDSYS_GATEWAY_URL configurada amb HTTPS i corresponent a l'entorn: SÍ / NO
- SIF_INTERNAL_API_KEY_ID / SECRET configurats: SÍ / NO (no copiar els valors)
- Paths HMAC `course-intent` i `course-status` coherents amb el pont: SÍ / NO
- Credencial Redsys rotada/configurada via secret store o entorn: SÍ / NO (no copiar el valor)

## 2. Preflight

Comanda:

```bash
php sif/scripts/preflight-redsys-course.php
php sif/scripts/preflight-redsys-callback-queue.php
```

Resultat:
- preflight curs:
- preflight cua:

## 3. Dry-run

Comanda:

```bash
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER>
```

Resultat:
- preview_ok:
- import esperat:
- moneda:
- terminal:
- source_type:
- source_id:

## 4. Execució controlada

Només a `test` o `preproduction`.

Comanda sense sync llegada:

```bash
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER> --execute
```

Comanda amb sync llegada:

```bash
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER> --execute --sync-legacy
```

Resultat:
- process_ok:
- factura creada/reutilitzada:
- cobrament creat/reutilitzat:
- legacy_sync_executed:

## 5. Estat BD SIF

Comprovar i anotar:
- `redsys_payment_intent`: 1 intent per DS_ORDER;
- `redsys_notifications`: 1 notificació lògica;
- `redsys_callback_queue`: estat final;
- `factura`: UUID i NUM_VISIBLE;
- `payment_transaction`: UUID, import, DS_ORDER, IDPAG;
- `payment_allocation`: assignació;
- `enrollment_fund_movement`: exactament un `EXTERNAL_ALLOCATION` per `DS_ORDER + ID_INSC`, amb `UUID_PAYMENT`, `UUID_FACTURA`, `ID_INSC_DESTI` i import coherent;
- `fiscal_queue`: registre pendent/enviat segons l'entorn;
- `notification_outbox`: una fila `COURSE_PAYMENT_CONFIRMED` per `DS_ORDER`, amb UUID i estat; verificar que `PAYLOAD_JSON` no conté email, DNI, nom ni adreça.

## 6. Estat BD llegada

Comprovar:
- `inscripcions.PAGAMENT`;
- `DATA PAG`;
- `INSC CURS`;
- `FACTURA_RELACIONADA` si aplica;
- `OBSERVACIONS` amb marcador SIF sense duplicats.

## 7. Escenaris obligatoris

### Pagament complet
- resultat:
- factura única:
- cobrament únic:
- sync llegada:

### Callback duplicat
- resultat:
- factura continua sent única:
- cobrament continua sent únic:
- `EXTERNAL_ALLOCATION` continua sent únic per `DS_ORDER + ID_INSC`:
- notificació outbox continua sent única:
- job duplicat/no duplicat:

### Pagament parcial
- import primer tram:
- estat `PARTIALLY_PAID`:
- PAGAMENT llegat:

### Segon tram fins completar
- import segon tram:
- total confirmat:
- estat `PAID`:
- PAGAMENT llegat:

### Reintent worker
- resultat:
- duplicacions detectades:

### Callback incompatible
- tipus: import / order / moneda / terminal
- resultat esperat: rebutjat
- resultat obtingut:

## 8. Logs i captures

Adjuntar només evidència sense secrets:
- log callback;
- log worker;
- captures BD;
- sortida JSON sanititzada del verificador.

**No adjuntar:** claus Redsys, signatures, contrasenyes, secrets HMAC, payloads crus amb dades sensibles.

## 9. Decisió de tall

- [ ] `SIF_REDSYS_CALLBACK_URL` configurada amb HTTPS
- [ ] `REDSYS_GATEWAY_URL` configurada amb HTTPS i sense endpoint hardcoded al codi
- [ ] API interna configurada (`SIF_INTERNAL_API_KEY_ID`/`SECRET`) i paths HMAC coherents
- [ ] clau Redsys del pont i clau del callback SIF corresponen al mateix comerç/entorn
- [ ] `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1` només a preproducció durant la prova
- [ ] `doit.php` retorna 410 amb cutover actiu
- [ ] `realitzaPagamentAutomatic.php` retorna 410 amb cutover actiu
- [ ] rollback (`cutover=0`) documentat abans de retirada definitiva
- [ ] E2E real de preproducció complet
- [ ] duplicat validat
- [ ] parcial/complet validat
- [ ] `EXTERNAL_ALLOCATION` present, idempotent i coherent amb factura/CHARGE/inscripció
- [ ] reintent validat
- [ ] sync llegada idempotent
- [ ] outbox CURS creada/reutilitzada idempotentment
- [ ] `PAYLOAD_JSON` de notificació sense PII directa
- [ ] estat del lliurament UC-58 documentat (no marcar enviat si només és `PENDING`)
- [ ] credencial històrica Redsys rotada si correspon
- [ ] checkout/callback desplegats sense secrets literals
- [ ] cap secret a evidències
- [ ] MerchantURL preparada per apuntar al callback SIF
- [ ] retirada d'autoritat fiscal llegada planificada

**Autorització per tall:** SÍ / NO
