# UC-017 · Plantilla d'evidència de preproducció

**Cas:** Comprar regal  
**Objectiu:** demostrar el tall web → Redsys → callback SIF → cua → factura/cobrament → entitlement → outbox → projecció llegada sense duplicats.

> No enganxar secrets, signatures Redsys, payloads crus, contrasenyes, claus privades ni el codi de regal bescanviable en aquesta evidència.

## 1. Identificació de l'execució

| Camp | Valor |
| --- | --- |
| Data/hora | |
| Entorn | `test` / `preproduction` |
| Commit desplegat | |
| Branca/PR | |
| Host SIF | |
| BD SIF | |
| BD llegada | |
| Operador/a | |
| `DS_ORDER` | |
| `gift_id` | |
| Import | |
| Moneda | EUR |
| Terminal | |
| UUID intent | |
| UUID job | |
| UUID factura | |
| UUID payment | |
| UUID operation | |
| UUID entitlement | |

## 2. Configuració requerida — només presència/validació

No copiar valors secrets. Marcar únicament **OK/KO**.

| Configuració | OK/KO |
| --- | --- |
| `SIF_INTERNAL_API_BASE_URL` HTTPS | |
| `SIF_INTERNAL_API_KEY_ID` | |
| `SIF_INTERNAL_API_SECRET` | |
| gift intent/status signed paths | |
| `REDSYS_MERCHANT_CODE` | |
| `REDSYS_MERCHANT_KEY` | |
| `REDSYS_TERMINAL` | |
| `REDSYS_GATEWAY_URL` HTTPS | |
| `SIF_REDSYS_CALLBACK_URL` HTTPS | |
| `SIF_AEAT_SYSTEM_NAME` | |
| `SIF_AEAT_SYSTEM_ID` | |
| `SIF_AEAT_SYSTEM_VERSION` | |
| `SIF_AEAT_INSTALLATION_ID` | |
| `SIF_AEAT_PRODUCER_NAME` | |
| `SIF_AEAT_PRODUCER_NIF` | |
| `SIF_AEAT_GIFT_TAX_CODE` validat fiscalment | |
| `SIF_AEAT_GIFT_REGIME_KEY` validat fiscalment | |
| `SIF_AEAT_GIFT_EXEMPTION_CODE` validat fiscalment | |

## 3. Preflight abans del cobrament

Executar:

```bash
php sif/scripts/preflight-redsys-gift.php
```

Conservar el JSON sanititzat.

Controls obligatoris en `true`:
- entorn test/preproduction;
- merchant code/clau/terminal;
- URLs HTTPS;
- internal API;
- gift intent/status paths;
- configuració AEAT explícita;
- NIF emissor coherent;
- `fiscal_chain_official_compatible`;
- connectivitat BD SIF/llegada;
- taules fiscal/payment/Redsys/entitlement/outbox;
- codi de regal únic;
- endpoints i serveis UC-017 presents.

**Resultat preflight:**  
**Fitxer d'evidència:**  

## 4. Creació de la intenció

Validar a `redsys_payment_intent`:

| Control | Esperat | Resultat |
| --- | --- | --- |
| `SOURCE_TYPE` | `REGAL` | |
| `SOURCE_ID` | gift ID numèric | |
| `EXPECTED_AMOUNT` | import llegit server-side | |
| `CURRENCY` | EUR | |
| `TERMINAL` | terminal configurat | |
| `STATUS` | PENDING abans callback | |
| snapshot | regal congelat | |
| `DS_ORDER` | 4–12 alfanum., inicia 4 dígits | |

No provar l'import canviant-lo al navegador com a font d'autoritat: el SIF l'ha de substituir/rebutjar.

## 5. Callback Redsys

Registrar evidència que:
- signatura vàlida;
- merchant code vàlid;
- terminal vàlid;
- moneda 978/EUR;
- import = intent;
- `DS_ORDER` = intent;
- resposta autoritzada;
- una notificació persistida;
- un job de cua creat.

### Callback duplicat

Reenviar exactament la mateixa notificació de prova.

Esperat:
- una sola notificació lògica;
- un sol job;
- cap factura abans del worker;
- resposta marcada com a duplicada/reutilitzada.

## 6. Worker i efectes SIF

Executar el worker de l'entorn segons procediment operatiu.

Esperat després de processar:
- `factura`: 1;
- `factura_linia`: línia REGAL, detall `Val regal`, sense codi bescanviable;
- `fact_rels`: origen REGAL, `VISIBLE_ALUMNE=0`;
- `payment_transaction`: 1 CHARGE Redsys;
- `payment_allocation`: 1 assignació;
- `commercial_operation`: 1 `GIFT_PURCHASE`;
- `commercial_entitlement`: 1 `GIFT`, ACTIVE;
- `commercial_entitlement_event`: 1 ISSUE;
- `notification_outbox`: confirmació idempotent sense codi cru;
- cua Redsys: PROCESSED amb UUID factura/payment.

## 7. Snapshot fiscal / VERI*FACTU

Comprovar el registre creat:
- conté bloc `aeat`;
- `type=RegistroAlta`;
- emissor coherent;
- número/data congelats;
- destinatari = comprador/receptor fiscal;
- `DescripcionOperacion` coherent;
- `Desglose` usa la classificació fiscal validada;
- `SistemaInformatico` complet;
- `TipoUsoPosibleSoloVerifactu=S`;
- `TipoUsoPosibleMultiOT=N`;
- `IndicadorMultiplesOT=N`;
- hash/cadena coherents;
- cap codi de regal bescanviable al payload fiscal.

**No enviar a AEAT real si l'entorn/procediment no ho autoritza.**

## 8. Projecció llegada

A `regal`:
- no crear una segona factura llegada;
- no reutilitzar numeració fiscal llegada;
- afegir només la projecció/marker SIF prevista;
- el mateix regal pagat no ha de poder originar una nova intenció.

## 9. Retorn web

Provar retorn OK i KO.

Esperat:
- OK del navegador **no** implica automàticament CONFIRMED;
- CONFIRMED només quan el job és PROCESSED amb UUID factura/payment;
- PROCESSING/PENDING indica no repetir el pagament;
- INCIDENT → REVIEW;
- callback rebutjat → REJECTED quan correspongui;
- cap email o PII a query string.

## 10. Recovery després de factura

Forçar de manera controlada un error tècnic després de crear factura/payment i abans de completar entitlement/outbox/sync.

Reintentar el mateix job.

Esperat:
- mateixa factura;
- mateix payment;
- un sol GIFT_PURCHASE;
- un sol entitlement;
- un sol ISSUE;
- outbox idempotent;
- cap segon CHARGE;
- job acaba PROCESSED o INCIDENT segons resultat.

## 11. Verificador final

Executar:

```bash
php sif/scripts/verify-redsys-gift-preproduction.php <DS_ORDER>
```

Conservar el JSON sanititzat i exigir:
- `ok=true`;
- intent REGAL present;
- snapshot/import coherents;
- notification VALIDATED;
- worker PROCESSED/CONFIRMED;
- UUID factura/payment;
- GIFT_PURCHASE present i enllaçat;
- GIFT entitlement present i ACTIVE.

## 12. Criteri GO UC-017

Només marcar **VERIFICAT** quan:
- CI del commit desplegat és verd;
- preflight és verd;
- classificació AEAT ha estat validada;
- compra nominal és correcta;
- callback duplicat no duplica;
- retry no duplica;
- retorn web no confirma prematurament;
- factura/payload/outbox no exposen el codi;
- projecció llegada és posterior al SIF;
- evidència final s'ha conservat.

**Decisió:** GO / NO-GO  
**Responsable tècnic:**  
**Validació funcional/fiscal:**  
**Observacions:**

## 13. Addenda obligatòria de revalidació — 2026-10-04

### 13.1 Checkout i secret dedicat

| Control | Esperat | Evidència |
| --- | --- | --- |
| `UC017_GIFT_CHECKOUT_HMAC_SECRET` | present, >= 32 bytes, valor no exposat | |
| formulari web | envia `giftToken`, no `giftId/codiRegal/email/import` econòmics | |
| token manipulat | rebutjat abans de crear intent SIF | |
| token caducat | rebutjat | |

### 13.2 Mapping de superfície

Conservar evidència de:
- DocumentRoot de `pay-test.prisma.cat` i `pay-pre.prisma.cat`;
- URL exacta a la qual envia el formulari de regal;
- fitxer/versió desplegada de `pagina_efectuar_pagament_regal_automatic.php`;
- MerchantURL real del sandbox;
- resposta 410 del callback llegat només després de confirmar drain.

### 13.3 Doble intent / doble ordre

Executar:
1. obrir dues pestanyes o repetir el checkout abans del callback;
2. comprovar que totes dues resolen el mateix intent/DS_ORDER pendent;
3. després d'un callback validat, demanar un nou intent i comprovar HTTP/conflicte;
4. en test controlat, simular una segona notificació amb un altre DS_ORDER del mateix
   regal i comprovar que continuen existint **1 factura, 1 CHARGE i 1 entitlement**.

### 13.4 Entrada llegada xifrada

Registrar si la URL AES-CBC llegada continua habilitada. Si continua activa, UC-017 no
es considera superfície web sanejada definitivament fins que l'IV quedi autenticat
o el mecanisme sigui retirat.
