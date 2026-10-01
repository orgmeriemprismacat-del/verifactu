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

## 2. Preflight

```bash
php sif/scripts/preflight-redsys-pack.php
php sif/scripts/preflight-redsys-callback-queue.php
```

Configuració esperada per preproducció:
- `SIF_REDSYS_PAYMENT_URL=https://sis-t.redsys.es:25443/sis/realizarPago`;
- `SIF_REDSYS_CALLBACK_URL` HTTPS;
- API d'intenció/rols/secrets configurats.

Resultat:
- preflight PACK:
- `redsys_payment_url_allowed=true`:
- preflight cua:

## 3. Preview read-only

```bash
php sif/scripts/preview-redsys-pack.php <DS_ORDER>
```

Comprovar:
- `SOURCE_TYPE=PACK`;
- snapshot congelat;
- import esperat;
- N línies;
- ordinal contigu;
- receptor fiscal coherent.

## 4. Execució

Flux productiu preferit:

```bash
php sif/scripts/process-redsys-callback-queue.php --limit=25 --worker-id=uc015-preproduction
```

Processor manual controlat, només si cal diagnosticar un DS_ORDER ja validat:

```bash
php sif/scripts/process-redsys-pack.php <DS_ORDER> --sync-legacy
```

## 5. Verificació automàtica post-execució

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

## 7. Outbox

Per UC-015 és suficient acreditar l'**enqueue idempotent**.

- `PENDING`: vàlid per UC-015, lliurament pendent UC-58.
- `SENT`: només marcar enviat si UC-58 té evidència del transport.
- no considerar l'existència de la fila com a prova d'enviament.

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
- [ ] callback fiscal legacy continua desactivat
- [ ] retirada física del callback legacy planificada
- [ ] decisió sobre posició comercial explícita documentada
- [ ] estat UC-58 separat de l'enqueue UC-015
- [ ] cap dada sensible ni secret a l'evidència

**UC-015 verificat en preproducció:** SÍ / NO
