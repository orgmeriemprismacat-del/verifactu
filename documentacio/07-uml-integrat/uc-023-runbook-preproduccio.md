# UC-023 — Runbook de validació en test/preproducció

**Objectiu:** obtenir evidència reproduïble del recorregut complet de cobrament fraccionat abans d'activar el tall definitiu de la intranet.

## 1. Condicions prèvies

Executar només amb `SIF_ENV=test` o `SIF_ENV=preproduction`.

Han d'estar configurats, sense documentar-ne els valors:

- `SIF_INTERNAL_API_KEY_ID`
- `SIF_INTERNAL_API_SECRET`
- `SIF_INTERNAL_INSTALLMENT_SIGNED_PATH=/api/payments/installment.php`
- `SIF_INSTALLMENT_PAYMENT_WRITE_ROLES`
- URL server-to-server de l'endpoint UC-023 a la intranet
- mateix key id/secret HMAC als dos extrems

No activar encara `SIF_INSTALLMENT_PAYMENT_ENFORCED=1` fins haver completat el preflight.

## 2. Migracions

```bash
php sif/scripts/run-migrations.php
```

Resultat esperat:

- migracions aplicades sense error;
- existeix `payment_external_receipt_claim`;
- índex únic `uq_payment_external_receipt_type_value`;
- FK `fk_payment_external_receipt_payment` amb `ON DELETE CASCADE`.

## 3. Preflight UC-023

```bash
php sif/scripts/preflight-manual-installment.php
```

Resultat esperat:

```json
{
  "ok": true
}
```

No continuar si `ok=false`.

## 4. Cas A — fracció parcial amb referència bancària

Triar una factura de prova existent que cobreixi l'ID d'inscripció.

### Preview

```bash
php sif/scripts/preview-manual-installment.php \
  --num-visible="NUM_FACTURA_TEST" \
  40.00 \
  "2026-10-04" \
  --id-insc=ID_INSC_TEST \
  --user="uc023-pre" \
  --reference="UC023-TRF-001" \
  --operation-id="UC023-EVENT-001" \
  --bank="CAIXA"
```

### Execució

```bash
php sif/scripts/process-manual-installment.php \
  --num-visible="NUM_FACTURA_TEST" \
  40.00 \
  "2026-10-04" \
  --id-insc=ID_INSC_TEST \
  --user="uc023-pre" \
  --reference="UC023-TRF-001" \
  --operation-id="UC023-EVENT-001" \
  --bank="CAIXA"
```

Conservar el `uuid_payment`.

### Evidència

```bash
php sif/scripts/verify-manual-installment-evidence.php \
  --reference="UC023-TRF-001"
```

Comprovar:

- `ok=true` al verificador;
- un únic `payment_transaction`;
- una assignació a la factura correcta;
- estat de factura `PARTIAL`;
- `correlation_id` present;
- `payment_action_events` amb almenys `REQUESTED` i terminal `SUCCEEDED` o `REUSED`;
- `operational_events` amb `REGISTER_INSTALLMENT_PAYMENT`;
- `sif_audit_events` correlacionats amb el mateix identificador;
- el `REQUEST_ID` és traçable i canvia en un reintent nou, mentre la correlació econòmica es manté;
- no apareix un registre fiscal nou pel simple cobrament.

## 5. Cas B — reintent idempotent

Repetir exactament l'ordre del Cas A amb el mateix `operation-id` i referència.

Esperat:

- mateix `uuid_payment`;
- `idempotency_reused=true`;
- cap segon `payment_transaction`;
- cap segona assignació.

## 6. Cas C — mateix rebut extern amb payload contradictori

Repetir la mateixa `--reference=UC023-TRF-001` canviant l'import, per exemple a 30.00.

Esperat:

- `409 CONFLICT`;
- no es crea cap nou moviment;
- no canvia el saldo de la factura.

## 7. Cas D — inscripció aliena a la factura

Executar amb una `--id-insc` que no estigui coberta per `fact_rels` de la factura.

Esperat:

- `409`;
- zero nous moviments;
- zero noves assignacions.

## 8. Cas E — sobrepagament

Després d'una fracció parcial, intentar registrar un import superior al saldo pendent.

Esperat:

- `409`;
- rollback;
- estat econòmic anterior intacte.

## 9. Cas F — DS_ORDER / TPV

Preparar o seleccionar un cobrament de test amb DS_ORDER conegut.

```bash
php sif/scripts/process-manual-installment.php \
  --num-visible="NUM_FACTURA_TEST" \
  40.00 \
  "2026-10-04" \
  --id-insc=ID_INSC_TEST \
  --user="uc023-pre" \
  --ds-order="123456789012" \
  --operation-id="UC023-TPV-001" \
  --bank="tpv"
```

Si el DS_ORDER ja existeix com a cobrament equivalent:

- reutilitza el `UUID_PAYMENT` existent;
- `reconciled_existing=true`;
- no crea segon CHARGE.

Si import o factura divergeixen:

- `409 CONFLICT`.

## 10. Cas G — canal real d'intranet

Només després dels casos CLI:

1. configurar el client intern de la intranet;
2. activar `SIF_INSTALLMENT_PAYMENT_ENFORCED=1` en test/pre;
3. obrir `alumnes-pagaments`;
4. seleccionar import, data i banc;
5. informar `REFERÈNCIA`;
6. confirmar el cobrament.

Verificar que:

- la petició navegador→intranet és POST;
- porta CSRF;
- la intranet envia HMAC server-to-server;
- l'actor real substitueix qualsevol user del navegador;
- `idPag` queda només al fallback llegat;
- el SIF rep l'`ID_INSC` real;
- Caixa/BBVA envia `REFERENCIA_BANCARIA`;
- `tpv` envia `DS_ORDER`;
- el mateix intent conserva el mateix `operationId`.

## 11. Evidència a conservar

Per cada cas:

- data/hora;
- entorn;
- factura de prova;
- ID_INSC de prova;
- referència o DS_ORDER;
- operation id;
- UUID_PAYMENT;
- JSON de preview/process;
- JSON de `verify-manual-installment-evidence.php`, incloent `payment_action_events`, `operational_events` i `sif_audit_events`;
- request id i correlation id de cada intent;
- resultat HTTP del canal intranet quan correspongui;
- captura o log de l'estat econòmic final;
- hash/commit desplegat.

No conservar secrets HMAC.

## 12. Go / No-Go

**GO** només si:

- preflight `ok=true`;
- parcial, reintent, conflicte, inscripció aliena, sobrepagament i DS_ORDER passen;
- la intranet usa l'ID_INSC real;
- la conciliació externa evita el doble CHARGE;
- cada operació té `REQUESTED` + terminal i traça operacional/SIF correlacionada;
- una fallada de l'auditoria terminal no deixa un cobrament parcialment commitejat;
- CI del commit desplegat és verda o hi ha evidència equivalent controlada;
- no s'ha creat cap registre fiscal addicional pel simple cobrament.

**NO-GO** si falla qualsevol control anterior.

Després d'un GO estable es pot retirar el fallback llegat de `efectuarPagament.php`.
