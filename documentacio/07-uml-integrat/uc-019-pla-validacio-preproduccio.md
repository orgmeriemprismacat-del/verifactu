# UC-019 — Pla de validació `sif_test` / `sif_pre`

Data: 2026-10-04.

## Objectiu

Convertir l'estat actual **VERIFICAT EN CI** en **VERIFICAT EN PREPRODUCCIÓ**, amb evidència reproduïble del flux real entre intranet, BD llegada i SIF.

## 1. Preflight obligatori

Executar:

```bash
php sif/scripts/preflight-usoc-intranet.php
```

Cal obtenir `ok=true` i tots aquests checks en `true`:

- `sif_database`
- `usoc_financing_case_table`
- `usoc_validation_decision_table`
- `usoc_lifecycle_execution_table`
- `internal_api_key_id`
- `internal_api_secret`
- `internal_api_usoc_signed_path`
- `usoc_read_or_manage_roles`
- `usoc_manage_roles`
- `legacy_database_configured`
- `legacy_database_connectivity`
- `usoc_validation_decision_service`
- `usoc_api_endpoint_file`

Guardar el JSON de sortida com a evidència.

## 2. Cas nominal — aprovació

Preparar una inscripció de prova:

- `TIPUS_DESC=4`
- `VALID_DESC=0`
- cap decisió prèvia amb el mateix `requestId`

Des de la intranet de proves:

1. obrir `/alumnes/validar-descomptes/`;
2. marcar la sol·licitud com a vàlida;
3. confirmar.

Esperat:

- POST, mai GET;
- CSRF correcte;
- actor i rol enviats al SIF;
- fila `usoc_validation_decision` creada;
- `DESIRED_VALID_DESC=1`;
- transició observable `REQUESTED → COMMITTED`;
- `VALID_DESC` llegat acaba a 1;
- `LEGACY_AFTER_VALID_DESC=1`;
- `LEGACY_STATE_HASH` informat;
- no es crea cap factura ni cobrament pel simple fet de validar.

## 3. Cas nominal — denegació

Partir de:

- `TIPUS_DESC=4`
- `VALID_DESC=0`

Denegar la sol·licitud.

Esperat:

- `DESIRED_VALID_DESC=2`;
- `VALID_DESC=2`;
- estat final `COMMITTED`;
- sense factura/cobrament automàtic.

## 4. Reintent idempotent

Repetir exactament la mateixa petició amb el mateix `requestId`.

Esperat:

- no crear segona fila;
- no reaplicar la mutació al llegat;
- mateixa `UUID_DECISION`;
- resposta coherent amb estat `COMMITTED`.

## 5. Conflicte de payload

Reutilitzar un `requestId` existent canviant la decisió 1 ↔ 2.

Esperat:

- HTTP 409;
- cap canvi addicional al llegat;
- cap segona decisió.

## 6. Conflicte llegat / REVIEW_REQUIRED

Escenari controlat:

1. crear REQUESTED per `desired=1`;
2. abans de completar, posar manualment `VALID_DESC=2` a la BD llegada de proves;
3. executar el `complete`.

Esperat:

- `STATE=REVIEW_REQUIRED`;
- `REVIEW_REASON=LEGACY_DECISION_CONFLICT`;
- cap intent de forçar el valor a 1.

## 7. Interrupció entre begin i complete

Simular una interrupció després d'haver aplicat el valor al llegat però abans del `complete`.

Després executar:

```bash
php sif/scripts/reconcile-usoc-validation-decisions.php --limit=100
```

Esperat:

- localitza només decisions `REQUESTED`;
- reconcilia la decisió amb el valor llegat;
- passa a `COMMITTED` si coincideix;
- retorna `REVIEW_REQUIRED` si no coincideix;
- `errors=0` per al cas nominal.

## 8. Seguretat del canal

Comprovar:

### Navegador → intranet

- sense CSRF → 403;
- sessió absent → 401;
- rol no autoritzat → 403;
- `idInsc` invàlid → 422;
- `requestId` invàlid → 422;
- GET → 405.

### Intranet → SIF

- secret HMAC incorrecte → rebutjat;
- timestamp caducat → rebutjat;
- actor sense rol USOC → 403;
- cap secret visible al HTML/JS.

## 9. Evidència mínima a conservar

Per cada cas:

- timestamp;
- entorn;
- commit SHA;
- `ID_INSC` de prova;
- `requestId`;
- resposta HTTP;
- fila abans/després de `usoc_validation_decision`;
- `TIPUS_DESC/VALID_DESC` abans/després;
- sortida del preflight;
- sortida del reconciliador quan s'utilitzi;
- captura o export dels logs sense secrets.

No guardar documentació personal real d'afiliació en aquesta evidència tècnica.

## 10. Criteri de tancament

Marcar UC-019 com **CLOSED / VERIFIED_PREPRODUCTION** només quan:

- tots els casos anteriors passen;
- migració 000031 està aplicada;
- el preflight retorna `ok=true`;
- no existeixen decisions `REQUESTED` antigues sense justificació;
- no hi ha cap mutació USOC per GET;
- HMAC, rols i CSRF estan actius;
- queda documentat el procediment de negoci amb què Gestió comprova l'afiliació.

## 11. CI actual

A la PR #143, els tests específics UC-019 passen. La suite global queda vermella per 6 errors no relacionats amb UC-019 (5 PACK + 1 RedsysSignatureValidator). Això no invalida el resultat d'aquest cas, però sí impedeix afirmar que la suite global del repositori és verda.
