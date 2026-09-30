# UC-014 — Auditoria detallada i matriu de traçabilitat

**Data:** 29/09/2026  
**Branca d'auditoria:** `audit/uc-014-completa-2026-09-29`  
**Estat global:** **DOC AMPLIADA / IMP SIF AVANÇADA / WIRING CURS VERIFICAT PER CI / TALL PRODUCTIU I E2E PREPRODUCCIÓ PENDENTS**.

## 1. Evidència revisada

### Documentació
- [Fitxa funcional UC-014](../06-fitxes-funcionals/uc-014.md)
- [UML principal](uc-014-comprar-curs-redsys.md)
- [Classes ACTUAL/FINAL](uc-014-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-014-sequencies-actual-final.md)
- [Activitats per pàgina/apartat](uc-014-activitats-pagines-redsys-actual-final.md)

### Codi web actual/llegat
- `codi-drive/web-actual/PagamentCursAutomatic.php`
- `codi-drive/web-actual/pagina_pagament_automatic.php`
- `codi-drive/web-actual/pagina_confirmacio_inscripcio_automatic.php`
- `codi-drive/web-actual/pagina_efectuar_pagament_automatic.php`
- `codi-drive/web-actual/realitzaPagamentAutomatic.php`
- `codi-drive/web-actual/respostaOkPagamentAutomatic.php`
- `codi-drive/web-actual/respostaKoPagamentAutomatic.php`
- còpia candidata `codi-drive/pay-prisma-cat-canvis-verifactu/`

### Codi SIF
- `sif/src/Service/RedsysPaymentIntentService.php`
- `sif/src/Service/RedsysCallbackService.php`
- `sif/src/Service/RedsysCallbackWorker.php`
- `sif/src/Service/RedsysCallbackDispatcher.php`
- `sif/src/Service/RedsysCourseInvoiceService.php`
- `sif/src/Service/LegacyCourseInvoicePayloadBuilder.php`
- `sif/src/Service/RedsysInvoicePayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`

### Proves existents
- `RedsysCourseInvoiceServiceTest`
- `RedsysAsyncFlowTest`
- `RedsysPaymentIntentTest`

## 2. Matriu per acció

| ID | Pàgina/apartat/acció | PHP/JS/servei | BD/efecte ACTUAL | FINAL | Estat |
| --- | --- | --- | --- | --- | --- |
| A14-01 | Mostrar confirmació | `PagamentCursAutomatic::mostrarPaginaConfirmacio` + JS extern | llegeix `inscripcions/curs` | vista basada en estat autoritatiu | ACTUAL contrastat / FINAL pendent integració |
| A14-02 | Mostrar pagament | `PagamentCursAutomatic::mostrar` | calcula pendent amb camps llegats | ledger + regles servidor | ACTUAL contrastat |
| A14-03 | Preparar targeta | `pagina_efectuar_pagament_automatic.php` | usa POST del navegador | crear intent persistent | GAP P0 |
| A14-04 | Crear DS_ORDER | `time()` | no hi ha intent previ acreditat | `RedsysPaymentIntentService` | SIF implementat / adaptador pendent |
| A14-05 | Enviar import TPV | `importPagare * 100` | import del POST | `EXPECTED_AMOUNT` recomputat | GAP P0 |
| A14-06 | Callback | `realitzaPagamentAutomatic.php` | GET + POST Redsys | `RedsysCallbackService` | migració pendent |
| A14-07 | Signatura | `RedsysAPI` | comparació no localitzada | validació obligatòria | GAP P0; verificar versió desplegada |
| A14-08 | Comparar ordre/import | script llegat | no acreditat | intenció vs callback | GAP P0 |
| A14-09 | Facturar | INSERT directe a `factures` | factura llegada | `InvoiceService` | FINAL implementat |
| A14-10 | Numeració | MAX/últim + 1 | canal web | seqüència fiscal central | GAP P0 |
| A14-11 | Registrar cobrament | UPDATE `inscripcions.PAGAMENT` | acumulatiu | `payment_transaction/allocation` | FINAL implementat parcial |
| A14-12 | Fraccionament | `FRACCIO` + suma | mutació camp | moviments immutables | GAP P1 |
| A14-13 | Callback duplicat | no acreditat | risc de segon efecte | idempotència | tests existents / execució no acreditada |
| A14-14 | Correu | callback | enviament immediat | outbox/postcommit | pendent |
| A14-15 | Retorn OK/KO | pàgines UX | assumeix resultat | consulta estat real | GAP P1 |
| A14-16 | Sync acadèmica | barrejat/parcial | efectes postpagament | procés recuperable separat | pendent |
| A14-17 | Atribució per inscripció | implícita per IDPAG | sense moviment quantitatiu explícit | moviment EXTERNAL→INSCRIPCIÓ | DISSENY/PENDENT |

## 3. Mancances prioritzades

### P0 — abans de considerar el flux apte
1. Integrar ecommerce amb `RedsysPaymentIntentService`.
2. No confiar en import/curs/order funcionals transportats des del client o MerchantURL.
3. Verificar criptogràficament la notificació Redsys abans de qualsevol efecte.
4. Comparar DS_ORDER/import/moneda/terminal amb la intenció persistida.
5. Retirar la generació directa de factura i numeració del callback web.
6. Garantir idempotència de callback/job/factura/pagament.
7. Externalitzar secrets Redsys del codi.
8. Executar i conservar evidència de proves d'integració.

### P1
1. Completar atribució monetària explícita per inscripció.
2. Migrar fraccionament a moviments immutables.
3. Separar sincronització acadèmica de l'emissió fiscal.
4. Fer que les pantalles OK/KO consultin estat real.
5. Incorporar o materialitzar el JS necessari per completar traçabilitat.

## 4. Estat independent

| Dimensió | Estat |
| --- | --- |
| Documentació funcional | AMPLIADA; pendent validació de negoci |
| Classes UML | ACTUAL/FINAL documentades |
| Seqüències UML | ACTUAL/FINAL documentades |
| Activitats RM-037 | Documentades per 6 superfícies + variants |
| Codi llegat | Contrastat estàticament |
| Codi SIF Redsys | Implementació real localitzada |
| Adaptador ecommerce | PENDENT |
| Ledger per inscripció | PARCIAL/DISSENY |
| Tests | EXISTENTS, NO ACREDITATS COM EXECUTATS |
| Preproducció | NO ACREDITADA |
| Producció | NO ACREDITADA |

## 5. Criteri de tancament del UC-014

No marcar **TANCAT AMB EVIDÈNCIA** fins que:
- la web crea una intenció SIF abans de Redsys;
- callback i worker final s'usen en l'entorn objectiu;
- callback duplicat i payload incompatible s'han provat;
- pagament parcial/complet i reintent són reproduïbles;
- factura, registre fiscal, CHARGE, assignació i sync acadèmica tenen traça;
- les pantalles de retorn reflecteixen l'estat real;
- s'adjunta evidència de prova amb commit, BD, entorn, data i resultat.

## 6. Nota de seguretat

Durant l'auditoria s'han observat secrets Redsys literals en còpies de codi del repositori. Aquest document no els reprodueix. Cal rotació/externalització segons la política de secrets i verificar quina configuració està activa abans de desplegar.


## 7. Evidència CI i pla de tall final

El wiring de sincronització de curs al worker Redsys ha estat integrat a `main` i verificat per CI en els workflows `SIF PHP MySQL tests`, `SIF checks` i `UC-111 integration verification`. Això acredita el codi i la suite automatitzada, però **no** una execució end-to-end contra Redsys/preproducció.

El procediment de tall operatiu queda definit a [UC-014 — Pla de tall final Redsys cap al SIF](uc-014-pla-tall-final-redsys-sif.md).
