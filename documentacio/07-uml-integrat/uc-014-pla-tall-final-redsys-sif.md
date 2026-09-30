# UC-014 — Pla de tall final Redsys cap al SIF

**Data:** 30/09/2026  
**Objectiu:** retirar l'autoritat fiscal dels callbacks llegats `doit.php` / `realitzaPagamentAutomatic.php` sense perdre efectes acadèmics ni de cobrament.

## 1. Estat previ al tall

Ja existeixen i estan integrats a `main`:
- creació d'intenció autoritativa de curs al SIF;
- `DS_ORDER` generat al servidor;
- validació de signatura/ordre/import als callbacks candidats llegats;
- callback SIF amb validació criptogràfica;
- cua i worker Redsys;
- emissió via `RedsysCourseInvoiceService` + `InvoiceService`;
- `CourseLegacyPaymentSyncService` amb proves unitàries;
- documentació ACTUAL/FINAL i RM-037 del UC-014.

## 2. Punt que NO s'ha d'activar encara

No canviar `DS_MERCHANT_MERCHANTURL` al callback SIF en producció fins completar els passos següents.

## 3. Seqüència de preproducció

1. Configurar un entorn `sif_test*` o preproducció amb:
   - SIF DB;
   - legacy DB;
   - credencials Redsys de proves;
   - secrets d'API interna.
2. Executar tota la suite:
   `php sif/tests/run-tests.php`
3. Crear una intenció de curs ordinari i comprovar:
   - una sola fila a `redsys_payment_intent`;
   - `EXPECTED_AMOUNT` = saldo pendent autoritatiu;
   - snapshot amb inscripció/curs/pagament.
4. Simular callback autoritzat contra `sif/public/api/redsys/callback.php`.
5. Comprovar:
   - notificació VALIDATED;
   - job QUEUED;
   - cap factura abans del worker.
6. Executar worker.
7. Comprovar:
   - una sola factura;
   - un sol CHARGE;
   - una sola assignació;
   - job PROCESSED.
8. Connectar la projecció de cobrament de curs al processor llegat existent i repetir:
   - pagament parcial;
   - pagament complet;
   - callback duplicat;
   - reintent de worker;
   - alumne morós M -> 1 només quan queda totalment pagat.
9. Validar que la projecció llegada és idempotent.
10. Només quan totes les evidències són correctes, canviar la MerchantURL de curs al callback SIF.

## 4. Tall operatiu

Quan preproducció sigui verda:

```text
pagina_efectuar_pagament_automatic.php
        ↓
Redsys
        ↓
sif/public/api/redsys/callback.php
        ↓
redsys_notifications
        ↓
redsys_callback_queue
        ↓
worker
        ↓
InvoiceService + payment_transaction
        ↓
sync llegada
```

En aquest punt:
- `doit.php` i `realitzaPagamentAutomatic.php` deixen de crear factures;
- no s'hi calcula numeració fiscal;
- no s'hi registra cap CHARGE fiscal;
- només poden quedar temporalment per compatibilitat UX si és necessari.

## 5. Evidències obligatòries per tancar UC-014

Per cada prova conservar:
- commit SHA;
- entorn;
- DS_ORDER;
- UUID_INTENT;
- UUID_FACTURA;
- UUID_PAYMENT;
- estat del job;
- files afectades a `inscripcions`;
- resultat esperat/obtingut;
- captura/log sense secrets.

## 6. Criteri de tancament

UC-014 només passa a **TANCAT AMB EVIDÈNCIA** quan:
- la MerchantURL apunta al callback SIF a l'entorn objectiu;
- el worker processa el flux CURS;
- la sincronització llegada funciona i és idempotent;
- callback duplicat no duplica factura ni cobrament;
- parcial/complet són coherents;
- els callbacks llegats ja no tenen autoritat fiscal;
- la suite i la prova end-to-end estan acreditades.
