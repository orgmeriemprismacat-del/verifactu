# UC-015 — Plantilla d'evidència E2E de preproducció PACK

**Data/hora:**  
**Entorn:** test / preproduction  
**Commit SHA:**  
**Operador:**  
**Resultat global:** PASS / FAIL

## 1. Identitat tècnica

- DS_ORDER:
- IDPAG:
- UUID_INTENT:
- UUID_FACTURA:
- NUM_VISIBLE:
- UUID_PAYMENT:
- UUID_JOB:
- Estat callback:
- Estat outbox:

No copiar correu, DNI/NIF, adreces, secrets Redsys, signatures ni payloads crus.

## 2. Orquestrador canònic de preproducció

El flux preferit és `verify-redsys-pack-preproduction.php`. Rebutja qualsevol entorn que no sigui `test` o `preproduction`.

### Dry-run obligatori abans de mutar

```bash
php sif/scripts/verify-redsys-pack-preproduction.php <DS_ORDER>
```

Aquest mode executa:
- preflight PACK;
- preflight de cua;
- preview read-only;
- validació de DS_ORDER, IDPAG, total i import de cobrament.

Ha d'acabar amb `ok=true`, `mode=dry-run` i sense efectes fiscals/econòmics.

### Execució controlada

Només després del dry-run verd:

```bash
php sif/scripts/verify-redsys-pack-preproduction.php <DS_ORDER> --execute
```

Si la prova ha d'acreditar també la projecció legacy:

```bash
php sif/scripts/verify-redsys-pack-preproduction.php <DS_ORDER> --execute --sync-legacy
```

L'orquestrador valida, entre d'altres:
- `preflight_pack_ok`;
- `preflight_queue_ok`;
- `preview_is_dry_run`;
- `preview_payment_matches_total`;
- identitat de factura i payment;
- múltiples moviments d'atribució;
- suma del ledger = total preview;
- identitat de l'outbox;
- `legacy_sync_executed` quan s'ha demanat `--sync-legacy`.

## 3. Preflight manual — només diagnòstic

Si l'orquestrador falla, es poden executar separadament:

```bash
php sif/scripts/preflight-redsys-pack.php
php sif/scripts/preflight-redsys-callback-queue.php
php sif/scripts/preview-redsys-pack.php <DS_ORDER>
```

Configuració esperada per preproducció:
- `SIF_REDSYS_PAYMENT_URL=https://sis-t.redsys.es:25443/sis/realizarPago`;
- `SIF_REDSYS_CALLBACK_URL` HTTPS;
- API d'intenció/rols/secrets configurats.

## 4. Execució del worker / diagnòstic manual

Flux asíncron preferit:

```bash
php sif/scripts/process-redsys-callback-queue.php --limit=25 --worker-id=uc015-preproduction
```

Processor manual controlat, només per diagnosticar un `DS_ORDER` ja validat:

```bash
php sif/scripts/process-redsys-pack.php <DS_ORDER> --sync-legacy
```

Tant `preview-redsys-pack.php` com `process-redsys-pack.php` rebutgen `SIF_ENV=production`.

## 5. Verificació persistent post-execució

Després de l'execució, comprovar l'estat persistit:

```bash
php sif/scripts/verify-redsys-pack-evidence.php <DS_ORDER>
```

La sortida ha de tenir `ok=true` i tots els checks a `true`:

- `intent_pack`;
- `notification_validated`;
- `identity_matches`;
- `amount_matches_end_to_end`;
- `callback_processed`;
- `invoice_paid`;
- `single_confirmed_charge`;
- `invoice_lines_present`;
- `relations_complete`;
- `single_invoice_allocation`;
- `fund_ledger_complete`;
- `single_notification_outbox`;
- `fiscal_record_present`;
- `fiscal_queue_present`;
- `legacy_sync_reported`;
- `legacy_inscriptions_paid`;
- `legacy_invoice_marker_present`.

## 6. Escenaris obligatoris

### PK-E2E-01 · Pagament complet
- una intenció PACK;
- una notificació validada;
- un job processat;
- una factura;
- un CHARGE;
- N línies de factura;
- N `EXTERNAL_ALLOCATION`;
- suma línies = factura = cobrament = ledger;
- una notificació `PACK_PAYMENT_CONFIRMED`;
- inscripcions legacy pagades i marcades.

### PK-E2E-02 · Callback duplicat
Repetir el mateix callback Redsys.

Exigir:
- cap factura nova;
- cap payment nou;
- cap moviment de fons nou;
- cap outbox nova;
- mateixa identitat fiscal.

### PK-E2E-03 · Reintent de worker
Forçar/reproduir un reintent segur.

Exigir:
- resultat final `PROCESSED`;
- cap duplicació de factura/payment/ledger/outbox;
- `ATTEMPTS` coherent.

### PK-E2E-04 · Import incompatible
Callback/intenció amb import diferent del snapshot.

Exigir:
- no emetre factura;
- no crear payment;
- no crear ledger/outbox;
- conflicte/incidència controlada.

### PK-E2E-05 · Ordinal
Pack amb components amb la mateixa `DATAI`.

Exigir:
- ordre estable `DATAI, ID_CURS`;
- `PACK_ORDINAL` contigu;
- factura i ledger segueixen el mateix ordinal.

## 7. Outbox i delivery

Per UC-015 cal distingir tres nivells:

1. **enqueue PACK** — implementat per `PackPaymentNotificationService`;
2. **gate de delivery** — implementat per `NotificationOutboxDeliveryService::claim/complete`;
3. **transport/worker SMTP real** — pendent d'acreditació/cutover.

Interpretació:
- `PENDING`: enqueue correcte; encara no prova enviament;
- `SENDING`: hi ha un claim actiu; si queda ambigu, el servei exigeix revisió i no reintenta a cegues;
- `SENT`: només és prova de lliurament si existeix també l'intent/provider ref corresponent;
- `FAILED`: requereix revisió; no s'ha de marcar UC-015 runtime com complet si l'acceptació inclou notificació efectiva.

No considerar mai l'existència de la fila outbox com a prova d'enviament.

## 8. Evidències a conservar

- sortida JSON sanititzada de `verify-redsys-pack-evidence.php`;
- identificadors tècnics de la prova;
- run GitHub Actions del commit desplegat;
- captures BD només si no inclouen PII/secrets;
- logs de callback/worker sanejats.

## 9. Criteri final UC-015

- [ ] CI verd al commit provat
- [ ] preflight verd
- [ ] PK-E2E-01 PASS
- [ ] PK-E2E-02 PASS
- [ ] PK-E2E-03 PASS
- [ ] PK-E2E-04 PASS
- [ ] PK-E2E-05 PASS
- [ ] verificador post-execució `ok=true`
- [x] callbacks fiscals PACK legacy de producció retirats físicament
- [x] ordre comercial v1 documentat: `DATAI, ID_CURS` → `PACK_ORDINAL`
- [ ] enqueue PACK acreditat
- [ ] gate `claim/complete` disponible
- [ ] transport/cutover SMTP separat i acreditat si forma part del go-live
- [ ] cap dada sensible ni secret a l'evidència

**Acceptació UC-015 en preproducció:** SÍ / NO

> Aquesta plantilla és evidència d'acceptació runtime. La implementació i documentació UC-015 poden estar tancades abans de disposar d'un `DS_ORDER` real de preproducció.
