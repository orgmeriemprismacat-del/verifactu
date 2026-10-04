# UC-017 · Implementació i hardening del tall Redsys/SIF · 2026-10-03

## 1. Objectiu

Aquesta peça documenta els canvis implementats després de l'auditoria exhaustiva
d'UC-017. No equival a desplegament productiu ni a evidència de preproducció.

**Estat:** IMPLEMENTAT EN BRANCA CANDIDATA · PENDENT DE VERIFICACIÓ D'ENTORN.

Branca:
`audit/uc-017-exhaustiva-2026-10-03`

## 2. Tall implementat

```text
checkout regal
  -> SifRedsysGiftIntentClient
  -> POST signat /api/redsys/gift-intent.php
  -> RedsysGiftPaymentIntentService
  -> redsys_payment_intent (REGAL + snapshot autoritatiu)
  -> Redsys
  -> /api/redsys/callback.php
  -> redsys_notifications
  -> redsys_callback_queue
  -> RedsysCallbackWorker
  -> RedsysGiftInvoiceService
  -> InvoiceService
  -> GiftEntitlementIssuerService
  -> GiftPaymentNotificationService
  -> notification_outbox
  -> estat autoritatiu /api/redsys/gift-status.php
```

## 3. Components creats

| Component | Responsabilitat | Estat branca |
| --- | --- | --- |
| `RedsysGiftPaymentIntentService` | rellegir regal llegat, validar import/ID/FACT_REL, congelar snapshot i crear intenció | implementat |
| `/api/redsys/gift-intent.php` | endpoint intern signat `PAYMENT_CHANNEL` | implementat |
| `SifRedsysGiftIntentClient` | client HTTPS/HMAC des de pay.prisma.cat | implementat |
| `RedsysGiftPaymentStatusService` | estat autoritatiu del cobrament/job | implementat |
| `/api/redsys/gift-status.php` | consulta interna signada de l'estat | implementat |
| `SifRedsysGiftStatusClient` | client de consulta d'estat | implementat |
| `GiftPaymentReturnStatus` | evita convertir retorn navegador en confirmació fiscal | implementat |
| `GiftPaymentNotificationService` | outbox idempotent del comprador | implementat |

## 4. Components modificats

### 4.1. `RedsysPaymentIntentService`

Per `SOURCE_TYPE=REGAL` valida ara:
- existència de `snapshot.gift`;
- ID positiu i coherent amb `SOURCE_ID`;
- import del snapshot igual a `EXPECTED_AMOUNT`;
- codi de regal no buit;
- absència d'una `FACT_REL` ja emesa.

Això protegeix també l'endpoint genèric d'intencions i evita saltar-se el servei
específic enviant un snapshot inconsistent.

### 4.2. Checkout candidat de regal

`codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_regal_automatic.php`:
- deixa d'usar `time()` com a `DS_ORDER`;
- deixa de confiar en l'import del POST;
- recupera ordre, `gift_id` i import des del SIF;
- usa `HMAC_SHA512_V2`;
- externalitza merchant code, terminal, clau i gateway;
- usa `MerchantData` signat només per al fallback transitori;
- elimina PII/import del `MerchantURL`;
- elimina el correu de les URL OK/KO;
- exigeix un tall explícit per flags.

### 4.3. Callback llegat candidat

`realitzaPagamentRegalAutomatic.php` conserva el fallback només mentre el tall
no està activat. Abans de qualsevol efecte:
- valida envelope/signatura;
- valida context signat `UC017G<giftId>A<amountCents>`;
- valida ordre, import, moneda, terminal, merchant code i transaction type;
- resol el regal per ID, no per dades GET;
- no porta la clau Redsys al codi;
- no envia correu de depuració abans de validar.

Quan:
```text
SIF_REDSYS_GIFT_CUTOVER_ENABLED=1
SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED=1
```
el callback llegat respon `410` abans de carregar dependències o mutar dades.

## 5. Retorn del navegador

El retorn OK/KO de Redsys no és font d'autoritat.

`gift-status` exposa:
- `PENDING`: encara no hi ha callback;
- `PROCESSING`: notificació validada / job pendent;
- `REJECTED`: notificació denegada;
- `REVIEW`: incident o job processat sense identitats fiscals completes;
- `CONFIRMED`: job `PROCESSED` amb `UUID_FACTURA` i `UUID_PAYMENT`.

La pàgina només mostra “confirmat” en aquest últim cas.

## 6. Dret de regal i recovery

Ordre dels efectes derivats:
1. factura + cobrament idempotents;
2. operació `GIFT_PURCHASE` + entitlement `GIFT`;
3. outbox de confirmació;
4. marcatge del job com `PROCESSED`.

Si 2 o 3 fallen tècnicament, `RedsysCallbackWorker` deixa el job en `RETRY`.
El reintent:
- reutilitza factura/cobrament per la clau Redsys;
- reutilitza o crea l'entitlement per la seva clau;
- reutilitza la notificació outbox per `DS_ORDER`;
- no crea un segon `CHARGE`.

Un conflicte funcional 409/422 passa a incidència en lloc de reintentar
indefinidament.

## 7. Outbox

`GiftPaymentNotificationService` crea:
- template: `GIFT_PAYMENT_CONFIRMED`;
- destinatari: `COMPRADOR`;
- correlació: `REDSYS|<DS_ORDER>`;
- identitats: factura, payment i entitlement;
- `gift_id`, però **no el codi de regal en clar**.

El transport/delivery final de l'outbox encara s'ha de provar en entorn; aquest
canvi no afirma que el correu real ja s'estigui enviant.

## 8. Variables d'entorn del tall

```text
SIF_INTERNAL_API_BASE_URL
SIF_INTERNAL_API_KEY_ID
SIF_INTERNAL_API_SECRET
SIF_INTERNAL_REDSYS_GIFT_INTENT_SIGNED_PATH=/api/redsys/gift-intent.php
SIF_INTERNAL_REDSYS_GIFT_STATUS_SIGNED_PATH=/api/redsys/gift-status.php

REDSYS_MERCHANT_CODE
REDSYS_MERCHANT_KEY
REDSYS_TERMINAL
REDSYS_GATEWAY_URL

SIF_REDSYS_CALLBACK_URL
SIF_REDSYS_GIFT_CUTOVER_ENABLED
SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED

SIF_AEAT_SYSTEM_NAME
SIF_AEAT_SYSTEM_ID
SIF_AEAT_SYSTEM_VERSION
SIF_AEAT_INSTALLATION_ID
SIF_AEAT_PRODUCER_NAME
SIF_AEAT_PRODUCER_NIF
SIF_AEAT_GIFT_TAX_CODE
SIF_AEAT_GIFT_REGIME_KEY
SIF_AEAT_GIFT_EXEMPTION_CODE
```

Els secrets no s'han de versionar.

## 9. Preflight

`sif/scripts/preflight-redsys-gift.php` comprova, entre d'altres:
- entorn test/preproduction;
- coherència merchant code/clau;
- terminal;
- HTTPS de gateway/callback/internal API;
- credencials internes;
- paths signats d'intent i estat;
- coherència cutover/drain;
- configuració explícita AEAT del productor, sistema, impost, règim i exempció del regal;
- NIF emissor coherent entre configuració SIF i registre AEAT;
- cadena fiscal buida o compatible amb snapshot oficial AEAT;
- connectivitat SIF/llegat;
- taules de factura, payment, intenció, cua, entitlement i outbox;
- endpoints i serveis requerits.

## 10. Proves afegides

- `RedsysGiftPaymentIntentServiceTest`
- `RedsysGiftIntentSnapshotValidationTest`
- `RedsysGiftCutoverBoundaryTest`
- `RedsysGiftPaymentStatusServiceTest`
- `GiftPaymentNotificationServiceTest`
- `RedsysGiftRecoveryBoundaryTest`
- `RedsysGiftWorkerEndToEndTest`
- `GiftAeatInvoicePayloadEnricherTest`
- `RedsysGiftPreproductionVerificationTest`

Es mantenen:
- `RedsysGiftInvoiceServiceTest`
- preflight/preview/preproduction tests existents.

## 11. Passos de desplegament

1. Desplegar SIF en `pay-test`/preproducció amb migracions existents.
2. Configurar secrets, paths interns i classificació AEAT de UC-017 validada per negoci/fiscalitat.
3. Executar `preflight-redsys-gift.php` i exigir també `fiscal_chain_official_compatible=true`.
4. Provar amb `CUTOVER=0` la creació d'intenció sense canviar callback.
5. Confirmar que no hi ha intents llegats pendents que depenguin del callback antic.
6. Activar `LEGACY_DRAIN_CONFIRMED=1`.
7. Activar `CUTOVER=1`.
8. Executar pagament de prova i worker.
9. Verificar factura, payment, registre fiscal, entitlement, outbox i estat de retorn.
10. Repetir callback i worker per provar idempotència.
11. Forçar/reproduir un error recuperable entre factura i entitlement/outbox.
12. Conservar evidències abans de retirar definitivament el fallback.

## 12. Condició de tancament

El codi candidat redueix els bloquejos de programació, però UC-017 continua
**NO VERIFICAT / NO TANCAT** fins que els passos de preproducció tinguin
evidència conservada i el tall real estigui aprovat.
