# UC-017 · Inventari de codi PHP/JS · 2026-10-03

## 1. Web actual revisat

| Fitxer | Funció UC-017 | Estat |
| --- | --- | --- |
| `pagina_regal.php` | shell de la pantalla | ACTUAL |
| `js1619773569/mostrarRegal.min.js` | wizard, dades, previsualització, comprador | ACTUAL |
| `ajax/mostrar_pagina_regal.php` | contingut inicial | ACTUAL |
| `ajax/mostrar_cursos_regal.php` | cursos regal | ACTUAL |
| `ajax/obtenirPreuHoresNomCursRegal.php` | preu/hores/nom | ACTUAL |
| `ajax/previsualitza_regal.php` | previsualització | ACTUAL |
| `ajax/efectuarPagamentRegalAutomatic.php` | mail d'intent; no és core fiscal | ACTUAL |
| `pagina_efectuar_pagament_regal_automatic.php` | construeix request Redsys | ACTUAL · A SUBSTITUIR |
| `realitzaPagamentRegalAutomatic.php` | callback + factura llegada + mail | ACTUAL · BLOQUEJANT |
| `respostaOkPagamentRegal.php` | confirmació UX | ACTUAL |

## 2. Core SIF revisat

| Fitxer | Responsabilitat | Estat |
| --- | --- | --- |
| `LegacyGiftSnapshotRepository.php` | snapshot regal per ID/codi | IMPLEMENTAT |
| `LegacyGiftInvoicePayloadBuilder.php` | payload fiscal REGAL | IMPLEMENTAT |
| `RedsysGiftInvoiceService.php` | valida notificació/import, emet factura i dret | IMPLEMENTAT |
| `GiftEntitlementIssuerService.php` | GIFT_PURCHASE + GIFT entitlement | IMPLEMENTAT |
| `process-redsys-gift.php` | execució CLI no productiva | IMPLEMENTAT AUXILIAR |
| `RedsysGiftInvoiceServiceTest.php` | invoice/payment/idempotència/entitlement | IMPLEMENTAT; EXECUCIÓ NO ACREDITADA EN AQUESTA AUDITORIA |

## 3. JS: dades que travessen el wizard

S'han localitzat com a mínim:
- `codiCurs`
- `formAfort_PerQqui`
- `formAfort_DeQui`
- `formAfort_Dedicatoria`
- `codiRegal`
- `estilRegal`
- `preu`
- dades comprador/receptor

Aquest estat en client **no és font fiable** per a import, identitat fiscal o codi bescanviable.

## 4. Tall de migració requerit

```text
mostrarRegal.min.js
  -> POST autenticat/CSRF cap a adapter web
  -> RedsysPaymentIntentService
  -> Redsys
  -> callback SIF validat
  -> worker
  -> RedsysGiftInvoiceService
  -> InvoiceService
  -> GiftEntitlementIssuerService
```

Quan aquest tall sigui operatiu, `realitzaPagamentRegalAutomatic.php` no ha de crear factures.


## 5. Candidat FINAL implementat — 2026-10-04

| Fitxer/component | Responsabilitat | Estat |
| --- | --- | --- |
| `web-actual/pagina_regal.php + ajax/previsualitza_regal.php + mostrarRegal.min.js` | preview POST+CSRF, no-store i reserva server-side del codi | IMPLEMENTAT |
| `web-actual/RegalCurs.php` | escaping de preview/PDF i allowlist d'estils | IMPLEMENTAT |
| `pay-prisma.../SifRedsysGiftIntentClient.php` | crea intenció signada contra SIF | IMPLEMENTAT |
| `sif/public/api/redsys/gift-intent.php` | endpoint intern `PAYMENT_CHANNEL` | IMPLEMENTAT |
| `RedsysGiftPaymentIntentService.php` | rellegeix `regal`, valida import/estat i congela snapshot | IMPLEMENTAT |
| `pagina_efectuar_pagament_regal_automatic.php` candidat | DS_ORDER/import/secrets des del SIF/config | IMPLEMENTAT |
| `sif/public/api/redsys/callback.php` + cua/worker | callback mínim, validació i processament asíncron | IMPLEMENTAT |
| `RedsysGiftInvoiceService.php` | factura + payment + entitlement | IMPLEMENTAT |
| `GiftAeatInvoicePayloadEnricher.php` | snapshot AEAT explícit en pre/prod | IMPLEMENTAT; VALORS REALS PENDENTS |
| `GiftPaymentNotificationService.php` | outbox idempotent sense codi cru | IMPLEMENTAT |
| `LegacySyncService.php` | projecció llegada després d'èxit SIF | IMPLEMENTAT |
| `gift-status.php` + client/return status | estat autoritatiu navegador | IMPLEMENTAT |
| `preflight-redsys-gift.php` | readiness específica UC-017 | IMPLEMENTAT |
| `verify-redsys-gift-preproduction.php` | evidència E2E read-only | IMPLEMENTAT |
| `go-no-go-preproduction.php` | NO-GO/GO tècnic sense autoritzar producció | IMPLEMENTAT |

## 6. Estat resultant

La còpia **ACTUAL** es conserva com a evidència del sistema llegat i del rollback. El **FINAL candidat ja no està pendent de programació coneguda**. Resten: CI finalitzat, configuració/rotació de secrets, valors AEAT confirmats, desplegament test/preproducció, compra controlada, callback duplicat/retry i evidència conservada.
