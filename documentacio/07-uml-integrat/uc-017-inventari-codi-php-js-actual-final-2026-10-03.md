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
