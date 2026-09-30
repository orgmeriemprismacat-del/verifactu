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
- `fiscal_queue`: registre pendent/enviat segons l'entorn.

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

- [ ] E2E real de preproducció complet
- [ ] duplicat validat
- [ ] parcial/complet validat
- [ ] reintent validat
- [ ] sync llegada idempotent
- [ ] cap secret a evidències
- [ ] MerchantURL preparada per apuntar al callback SIF
- [ ] retirada d'autoritat fiscal llegada planificada

**Autorització per tall:** SÍ / NO
