# UC-022 · Runbook de verificació a test/preproducció

**Objectiu:** acreditar el flux complet de registre manual d'una transferència sobre una factura SIF ja emesa, sense tornar a mutar la factura fiscal al legacy.

## 1. Arquitectura que s'ha de desplegar

Flux autoritatiu:

`navegador intranet -> POST + CSRF -> intranet server -> HMAC -> SIF -> payment_transaction/payment_allocation -> projecció operacional legacy -> notification_outbox`

Regles:

- la intranet **no** escriu directament el cobrament al legacy;
- la intranet **no** conté cap segon projector;
- el SIF és l'únic propietari del registre econòmic i de la projecció;
- `GeneratedInvoiceLegacyPaymentSyncService` només projecta estat de pagament a `inscripcions`;
- la projecció **no modifica `factures`**;
- TPV/Redsys queda fora de UC-022.

## 2. Variables d'entorn

### 2.1. Host SIF de test/preproducció

Obligatòries:

- `SIF_ENV=test` o `preproduction`;
- `SIF_DB_DSN`, `SIF_DB_USER`, `SIF_DB_PASSWORD`;
- `SIF_INTERNAL_API_KEY_ID`;
- `SIF_INTERNAL_API_SECRET`;
- `SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH=/api/payments/manual-transfer.php`;
- `SIF_MANUAL_TRANSFER_ROLES=<rols autoritzats>`;
- `SIF_LEGACY_DB_DSN`, `SIF_LEGACY_DB_USER`, `SIF_LEGACY_DB_PASSWORD`;
- `SIF_LEGACY_INTRANET_DB_DSN`, `SIF_LEGACY_INTRANET_DB_USER`, `SIF_LEGACY_INTRANET_DB_PASSWORD`.

Els secrets no s'han de versionar ni copiar a l'evidència.

### 2.2. Host intranet de test/preproducció

Obligatòries:

- `SIF_INTERNAL_API_BASE_URL` apuntant al SIF de l'entorn; per al test preparat, `https://pay-test.prisma.cat`;
- `SIF_INTERNAL_API_KEY_ID`;
- `SIF_INTERNAL_API_SECRET`;
- `SIF_INTERNAL_MANUAL_TRANSFER_SIGNED_PATH=/api/payments/manual-transfer.php`;
- `SIF_MANUAL_TRANSFER_ROLES=<mateix contracte de rols>`.

La clau i el secret només existeixen al servidor. No s'envien mai al navegador.

Referències de configuració:
- `uc-022-intranet-pre.env.example` per `intranet-pre.prisma.cat`;
- `uc-022-pay-test-config.md` per `pay-test.prisma.cat` / `sif_test`.

## 3. Preflight obligatori

### 3.1. Host intranet-pre

Al host `intranet-pre.prisma.cat`:

```bash
php scripts/preflight-uc022-intranet.php
```

Ha de validar com a mínim:

- `SIF_INTERNAL_API_BASE_URL=https://pay-test.prisma.cat`;
- HTTPS obligatori;
- key id i secret presents al servidor;
- secret d'una longitud mínima de 32 caràcters;
- signed path exactament `/api/payments/manual-transfer.php`;
- rols UC-022 configurats;
- presència de guard, client, gateway, endpoints AJAX i JS del canal.

Per conservar evidència:

```bash
mkdir -p evidence/uc-022
php scripts/preflight-uc022-intranet.php \
  > evidence/uc-022/intranet-preflight-$(date +%Y%m%d-%H%M%S).json
```

El JSON només conserva hashes de key id/secret, no el secret en clar.

### 3.2. Host SIF

Al host SIF:

```bash
php sif/scripts/preflight-uc022-manual-transfer.php
```

Per conservar evidència:

```bash
mkdir -p evidence/uc-022
php sif/scripts/preflight-uc022-manual-transfer.php \
  > evidence/uc-022/preflight-$(date +%Y%m%d-%H%M%S).json
```

El resultat ha de tenir `"ok": true`.

El preflight comprova:

- entorn test/preproduction;
- connexió SIF;
- taules `factura`, `payment_transaction`, `payment_allocation`;
- taules d'auditoria i anti-replay;
- `notification_outbox`;
- key/secret/path HMAC;
- rols UC-022;
- connexió a BD legacy web;
- `factures` i `inscripcions`;
- connexió a BD intranet;
- `entitats` i `entitats_resp`;
- endpoint i serveis UC-022 carregables.

## 3.3. Política d'identitat bancària

Abans de T01, aplicar `uc-022-politica-identitat-bancaria.md`: l'ID ha de provenir de l'extracte/detall/export del banc i no pot ser un identificador sintètic construït amb dades internes.

## 4. Dataset controlat

Utilitzar una factura de prova:

- emesa abans del cobrament;
- present al SIF i al legacy;
- amb inscripcions relacionades;
- sense dades reals sensibles;
- import fàcil de dividir, per exemple 120,00 €.

Registrar abans de començar:

- SHA/commit desplegat;
- entorn;
- número visible de la factura;
- total;
- estat inicial SIF;
- suma inicial de `inscripcions.PAGAMENT`.

## 5. Matriu E2E

### T01 · Transferència parcial nova

Exemple:

- total factura: 120,00 €;
- event bancari: identificador immutable de prova 1;
- import: 40,00 €.

Esperat:

- HTTP 200;
- `status=CREATED`;
- 1 `payment_transaction`;
- 1 allocation;
- factura SIF `PARTIAL`;
- projecció legacy suma 40,00 €;
- cap `UPDATE factures` derivat del projector;
- auditories CREATE/SYNC_LEGACY presents;
- notificacions en outbox.

### T02 · Retry idempotent exacte

Repetir T01 amb el mateix banc, event, factura, import i data.

Esperat:

- `status=REUSED`;
- mateix `uuid_payment`;
- continua existint un sol cobrament;
- cap increment addicional al legacy;
- auditories de reuse.

### T03 · Conflicte

Reutilitzar el mateix event bancari de T01 canviant l'import.

Esperat:

- HTTP 409;
- `status=CONFLICT`;
- cap segon cobrament;
- cap nova allocation;
- cap modificació legacy.

### T04 · Completar factura amb un segon moviment real

Nou event bancari immutable; import restant 80,00 €.

Esperat:

- `status=CREATED`;
- segon `payment_transaction`;
- factura SIF `PAID`;
- suma legacy 120,00 €;
- inscripcions completament pagades amb `DATA PAG` quan correspon.

### T05 · Separació TPV

Intentar seleccionar `TPV`/Redsys com a banc al flux manual.

Esperat:

- bloqueig;
- cap payment manual.

### T06 · PENDING_RETRY de projecció

Només a entorn de test controlat: provocar indisponibilitat temporal de la connexió legacy **després de disposar del dataset i sense tocar producció**.

Esperat:

- el cobrament SIF queda confirmat;
- resposta HTTP 202;
- `status=PENDING_RETRY`;
- no es crea un segon cobrament al reintent;
- després de restaurar legacy, repetir exactament el mateix event;
- el payment es reutilitza i la projecció acaba correctament.

## 6. Verificador no destructiu

Després de T01/T02/T04/T06:

```bash
UC022_VERIFY_BANK="BANC_TEST" \
UC022_VERIFY_BANK_EVENT_ID="<id-immutable>" \
php sif/scripts/verify-uc022-preproduction.php "<NUM_VISIBLE>"
```

Per conservar evidència:

```bash
UC022_VERIFY_BANK="BANC_TEST" \
UC022_VERIFY_BANK_EVENT_ID="<id-immutable>" \
php sif/scripts/verify-uc022-preproduction.php "<NUM_VISIBLE>" \
  > evidence/uc-022/verify-$(date +%Y%m%d-%H%M%S).json
```

El JSON no publica l'event bancari ni el número visible: conserva els seus SHA-256.

## 7. Criteri de tancament

UC-022 es pot marcar `VERIFIED_PREPRODUCTION` només quan existeixi evidència de:

- preflight verd;
- T01 PASS;
- T02 PASS;
- T03 PASS;
- T04 PASS;
- T05 PASS;
- T06 PASS o evidència automatitzada equivalent explícitament acceptada;
- cap mutació de `factures` per la projecció;
- traces d'auditoria;
- notification outbox;
- commit desplegat identificat.

La prova CI no substitueix aquesta evidència de desplegament.
