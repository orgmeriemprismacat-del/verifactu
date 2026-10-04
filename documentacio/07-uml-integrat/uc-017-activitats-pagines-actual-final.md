# UC-017 · Activitats per pàgina i apartat ACTUAL / FINAL

## Pàgina 1 · Catàleg de regals
**ACTUAL:** `pagina_regal.php` + `mostrar_pagina_regal.php` + `mostrar_cursos_regal.php`.

**FINAL:** mateixa UX possible, però les dades de producte/preu han de provenir d'un snapshot server-side versionat.

## Pàgina 2 · Destinatari i dedicatòria
**ACTUAL:** el JS conserva `desti`, `origen`, `dedicatoria`.

**FINAL:** dades comercials del regal; no confondre-les amb receptor fiscal ni identitat del futur alumne.

## Pàgina 3 · Previsualització
**ACTUAL:** `previsualitza_regal.php`; es mostra/crea el codi a la UX.

**FINAL:** el codi utilitzable s'ha de custodiar com a credencial. La previsualització no ha de permetre fabricar/forçar un codi arbitrari.

## Pàgina 4 · Dades comprador/receptor fiscal
**ACTUAL:** formulari de comprador dins del wizard JS.

**FINAL:** validació server-side, snapshot fiscal immutable abans de cobrar.

## Pàgina de pagament
**ACTUAL:** `pagina_efectuar_pagament_regal_automatic.php`.
- genera `DS_ORDER=time()`;
- usa import rebut del flux web;
- conté configuració Redsys al PHP;
- callback inclou dades per GET.

**FINAL:** crear intenció SIF idempotent i construir Redsys exclusivament des del snapshot persistent.

## Callback
**ACTUAL:** `realitzaPagamentRegalAutomatic.php`.
- factura al llegat;
- actualitza `regal.FACT_REL`;
- envia correus.

**FINAL:** callback mínim, autenticació criptogràfica, persistència notificació i encolat. Sense factura ni correu dins HTTP.

## Confirmació
**ACTUAL:** `respostaOkPagamentRegal.php` informa que el pagament s'ha registrat.

**FINAL:** la UI només pot afirmar estats confirmats pel SIF; si la factura/dret encara és asíncron, mostrar estat de processament i oferir recuperació segura.

## Activitat global FINAL

```mermaid
flowchart TD
 A[Seleccionar regal] --> B[Destinatari + dedicatòria]
 B --> C[Dades comprador/receptor]
 C --> D[Validar preu i fiscalitat server-side]
 D --> E[Crear intenció REGAL]
 E --> F[Redsys]
 F --> G{Notificació vàlida?}
 G -- No --> H[Registrar error / no facturar]
 G -- Sí --> I[Worker UC-017]
 I --> J[Factura + payment SIF]
 J --> K[Crear/reutilitzar entitlement GIFT]
 K --> L[Outbox + lliurament]
 K --> M[Sync llegat posterior]
```


## Estat candidat per pantalla — 2026-10-03

| Pantalla/entrada | Candidat |
| --- | --- |
| wizard de regal | es manté; dades econòmiques deixen de ser autoritat |
| pàgina de pagament | integrada amb `SifRedsysGiftIntentClient` |
| MerchantURL | commutable a callback SIF amb flags |
| callback llegat | validat i fail-closed; 410 després del cutover |
| URL OK | consulta estat SIF; no afirma èxit pel retorn del navegador |
| URL KO | consulta estat SIF; distingeix denegat/pending/review |
| correu confirmació | substituït en camí final per outbox idempotent |

Falta demostrar aquestes pantalles/entrades en el domini desplegat de preproducció.
