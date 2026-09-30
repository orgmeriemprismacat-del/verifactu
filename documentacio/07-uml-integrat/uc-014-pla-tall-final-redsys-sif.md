# UC-014 — Pla de tall final Redsys cap al SIF

**Data:** 30/09/2026  
**Objectiu:** retirar l'autoritat fiscal dels callbacks llegats `doit.php` / `realitzaPagamentAutomatic.php` sense perdre sincronització econòmica ni efectes acadèmics.

## Estat actual acreditat

Ja està integrat a `main`:
- intenció Redsys autoritativa de curs;
- `DS_ORDER` generat al servidor;
- validació reforçada de signatura, ordre i import als callbacks candidats;
- callback SIF, cua i worker;
- emissió via `RedsysCourseInvoiceService` + `InvoiceService`;
- `CourseLegacyPaymentSyncService`;
- wiring de `CourseLegacyPaymentSyncService` dins `RedsysLegacySyncingProcessor`;
- prova `RedsysLegacySyncingProcessorCourseTest`;
- CI verd del wiring UC-014: `SIF PHP MySQL tests`, `SIF checks` i `UC-111 integration verification`.

Això **no acredita encara** el tall productiu ni una prova end-to-end contra Redsys/preproducció.

## Pas 1 — preproducció

1. Configurar `sif_test*` / preproducció amb BD SIF i legacy separades.
2. Configurar credencials Redsys de proves i secrets d'API interna.
3. Crear una intenció de curs ordinari.
4. Comprovar:
   - una sola fila a `redsys_payment_intent`;
   - `EXPECTED_AMOUNT` igual al saldo pendent autoritatiu;
   - snapshot amb inscripció, curs i context de pagament.
5. Simular callback autoritzat contra `sif/public/api/redsys/callback.php`.
6. Verificar:
   - notificació `VALIDATED`;
   - job `QUEUED`;
   - cap factura abans del worker.
7. Executar worker.
8. Verificar:
   - una sola factura;
   - un sol `CHARGE`;
   - una sola assignació;
   - job `PROCESSED`;
   - projecció llegada coherent a `inscripcions.PAGAMENT`.
9. Repetir amb:
   - pagament parcial;
   - pagament complet;
   - callback duplicat;
   - reintent de worker;
   - payload/import/order incompatible;
   - alumne morós `M -> 1` només quan queda totalment pagat.
10. Reexecutar el worker/sync i confirmar idempotència.

## Pas 2 — tall de MerchantURL

Només quan les proves anteriors siguin verdes:

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
RedsysLegacySyncingProcessor
        ↓
CourseLegacyPaymentSyncService
        ↓
inscripcions
```

## Pas 3 — retirada de l'autoritat fiscal llegada

Quan el callback SIF estigui actiu i acreditat:
- `doit.php` i `realitzaPagamentAutomatic.php` deixen d'emetre factures;
- no calculen numeració fiscal;
- no creen cobraments fiscals;
- no poden fer un segon efecte per callback duplicat;
- poden quedar temporalment només per compatibilitat UX si cal.

## Evidències obligatòries

Per cada prova conservar:
- commit SHA;
- entorn;
- `DS_ORDER`;
- UUID de la intenció;
- UUID de factura;
- UUID de pagament;
- estat del job;
- files afectades a `inscripcions`;
- resultat esperat / resultat obtingut;
- logs o captures sense secrets.

## Criteri de tancament

UC-014 només passa a **TANCAT AMB EVIDÈNCIA** quan:
- la MerchantURL apunta al callback SIF a l'entorn objectiu;
- callback i worker processen `CURS`;
- parcial/complet són coherents;
- callback duplicat no duplica factura ni cobrament;
- la sincronització llegada és idempotent;
- els callbacks llegats ja no tenen autoritat fiscal;
- la prova end-to-end de preproducció queda adjunta amb evidències.
