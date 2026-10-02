# UC-014 — Pla de tall final Redsys cap al SIF

**Data inicial:** 30/09/2026 · **Revalidació:** 02/10/2026  
**Objectiu:** retirar l'autoritat fiscal dels callbacks llegats `doit.php` / `realitzaPagamentAutomatic.php` sense perdre sincronització econòmica ni efectes acadèmics.

## Estat actual acreditat

Ja està integrat a `main`:
- intenció Redsys autoritativa de curs;
- `DS_ORDER` generat al servidor;
- validació reforçada de signatura, ordre i import als callbacks candidats;
- callback SIF, cua i worker;
- emissió via `RedsysCourseInvoiceService` + `InvoiceService`;
- `CourseLegacyPaymentSyncService`;
- productor durable `CoursePaymentNotificationService` → `notification_outbox` per cobrament CURS, idempotent per `DS_ORDER`;
- wiring de `CourseLegacyPaymentSyncService` dins `RedsysLegacySyncingProcessor`;
- prova `RedsysLegacySyncingProcessorCourseTest`;
- prova `RedsysCourseEndToEndSimulatedTest`, que cobreix pagament complet + callback duplicat i parcial → complet;
- verificador `verify-redsys-course-preproduction.php` amb dry-run per defecte i execució explícita;
- prova `RedsysCoursePreproductionBoundaryTest`, que blinda fail-closed, `--execute`, sync llegada completa i sanitització d'evidències;
- cutover explícit amb `SIF_REDSYS_COURSE_CUTOVER_ENABLED` i prova `RedsysCourseCutoverBoundaryTest`; quan és `1`, la MerchantURL SIF és obligatòria i HTTPS, i `doit.php` / `realitzaPagamentAutomatic.php` responen 410 abans de qualsevol efecte;
- retorn navegador read-only via `RedsysCoursePaymentStatusService`, `course-status.php` i client HMAC del pont candidat;
- proves `RedsysCoursePaymentStatusServiceTest` i `RedsysCourseReturnBoundaryTest`, que impedeixen convertir URLOK/URLKO en autoritat de pagament;
- CI verd del wiring, E2E intern, fund allocation i boundaries de preproducció UC-014: PR #95 amb `SIF PHP MySQL tests` **841 passed / 0 failed**, `SIF checks`, `UC-111 integration verification` i `UC-004 SIF secure flow checks` verds.

Això acredita un **E2E intern simulat** amb MySQL SIF real de test, la projecció llegada controlada i el **tooling de preproducció fail-closed**. **No acredita encara** una transacció contra Redsys/preproducció real ni el tall productiu.

## Pas 1 — preproducció

1. Configurar `sif_test*` / preproducció amb BD SIF i legacy separades. Configurar `SIF_REDSYS_CALLBACK_URL` amb la URL HTTPS del callback SIF i `REDSYS_GATEWAY_URL` amb l'endpoint HTTPS Redsys de l'entorn; mantenir `SIF_REDSYS_COURSE_CUTOVER_ENABLED=0` i `SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=0` fins que els preflights siguin verds.
2. Rotar qualsevol credencial Redsys històrica potencialment exposada i configurar credencials exclusivament via secret store/entorn: `REDSYS_MERCHANT_CODE`, `REDSYS_MERCHANT_KEY`, `REDSYS_TERMINAL`, `SIF_REDSYS_MERCHANT_KEY`, `SIF_INTERNAL_API_KEY_ID` i `SIF_INTERNAL_API_SECRET`. Les claus Redsys del pont i del callback SIF han de correspondre al mateix comerç/entorn, sense registrar-ne el valor. Verificar que el codi desplegat no conté literals.
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
   - una sola `payment_allocation`;
   - un sol `enrollment_fund_movement` `EXTERNAL_ALLOCATION` per `DS_ORDER + ID_INSC`, amb el mateix import del tram i UUIDs de factura/pagament;
   - job `PROCESSED`;
   - projecció llegada coherent a `inscripcions.PAGAMENT`;
   - una ordre `notification_outbox` `COURSE_PAYMENT_CONFIRMED` amb `UUID_NOTIFICATION`, sense email/DNI/nom al payload.
9. Repetir amb:
   - pagament parcial;
   - pagament complet;
   - callback duplicat, comprovant que no crea una segona factura, `CHARGE`, `payment_allocation`, `EXTERNAL_ALLOCATION` ni ordre d'outbox;
   - reintent de worker;
   - payload/import/order incompatible;
   - alumne morós `M -> 1` només quan queda totalment pagat.
10. Reexecutar el worker/sync i confirmar idempotència.
11. Iniciar el tall en dues fases. Primer posar `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1` i mantenir `SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=0`: no s'han de crear nous checkouts, però els callbacks llegats ja iniciats han de continuar entrant.
12. Confirmar que no queda cap sessió TPV llegada en vol (finestra definida operativament + revisió de logs/DS_ORDER pendents). Llavors posar `SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=1`: el checkout candidat passa a MerchantURL SIF i els checkouts/callbacks llegats responen 410.
13. Fer un pagament Redsys de proves i comprovar el retorn navegador: primer pot mostrar `PROCESSING`, però només ha de mostrar `CONFIRMED` quan la cua sigui `PROCESSED` i existeixin `UUID_FACTURA` + `UUID_PAYMENT`.
14. Comprovar també el retorn `REJECTED` i un cas `REVIEW`; una fallada de consulta no pot mostrar èxit.
12. Fer un pagament Redsys de proves i comprovar el retorn navegador: primer pot mostrar `PROCESSING`, però només ha de mostrar `CONFIRMED` quan la cua sigui `PROCESSED` i existeixin `UUID_FACTURA` + `UUID_PAYMENT`.
13. Comprovar també el retorn `REJECTED` i un cas `REVIEW`; una fallada de consulta no pot mostrar èxit.

## Pas 2 — tall de MerchantURL

Només quan les proves anteriors siguin verdes i el retorn autoritatiu també hagi estat contrastat en preproducció. El tall exigeix com a mínim: `REDSYS_GATEWAY_URL=<https://...>`, `SIF_REDSYS_CALLBACK_URL=<https://.../sif/public/api/redsys/callback.php>` i `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1`, a més de les credencials Redsys/API interna. La URL de callback sola no activa el tall.

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

Quan `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1`, els callbacks candidats `doit.php` i `realitzaPagamentAutomatic.php` responen HTTP 410 abans de carregar dependències o executar efectes. Quan el callback SIF estigui actiu i acreditat:
- `doit.php` i `realitzaPagamentAutomatic.php` deixen d'emetre factures;
- no calculen numeració fiscal;
- no creen cobraments fiscals;
- no poden fer un segon efecte per callback duplicat;
- poden quedar temporalment només com a via de rollback controlat mentre no s'hagi fet la retirada definitiva. El rollback previ a la retirada permanent consisteix a tornar `SIF_REDSYS_COURSE_CUTOVER_ENABLED=0`; no s'ha d'utilitzar després d'eliminar l'autoritat fiscal llegada.

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
- logs o captures sense secrets;
- evidència de rotació/configuració de credencials sense copiar-ne el valor.

## Criteri de tancament

UC-014 només passa a **TANCAT AMB EVIDÈNCIA** quan:
- la MerchantURL apunta al callback SIF a l'entorn objectiu;
- callback i worker processen `CURS`;
- parcial/complet són coherents;
- cada tram confirmat té exactament un `EXTERNAL_ALLOCATION` coherent amb factura, línia i `CHARGE`, i el parcial→complet suma l'import contractual esperat;
- callback duplicat no duplica factura ni cobrament;
- la sincronització llegada és idempotent;
- el productor de notificació deixa una única ordre durable per `DS_ORDER` i el preflight acredita `notification_outbox`;
- abans del tall productiu s'ha decidit/implementat la política UC-58 de lliurament dels correus que deixa d'enviar el callback llegat;
- els callbacks llegats ja no tenen autoritat fiscal;
- la prova end-to-end de preproducció queda adjunta amb evidències;
- els retorns OK/KO consulten l'estat SIF i no poden presentar `CONFIRMED` només pel redirect del navegador.


## Execució assistida

Abans de qualsevol tall:

```bash
php sif/scripts/preflight-redsys-course.php
php sif/scripts/preflight-redsys-callback-queue.php
```

Amb una notificació Redsys de preproducció ja validada:

```bash
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER>
```

Això només fa **dry-run**.

Per executar emissió/cobrament a `test` o `preproduction`:

```bash
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER> --execute
```

Per incloure la projecció llegada:

```bash
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER> --execute --sync-legacy
```

El verificador rebutja qualsevol entorn diferent de `test` o `preproduction`.

L'evidència s'ha de conservar amb la plantilla [UC-014 — Plantilla d'evidència de preproducció](uc-014-plantilla-evidencia-preproduccio.md).


## Dependència operativa UC-58

El tall fiscal ja no necessita que el callback llegat enviï correus, perquè UC-014 deixa el fet de notificació de pagament de curs a `notification_outbox`. Però **no hi ha encara worker/transport genèric acreditat que lliuri aquesta ordre**. Per tant, un GO de preproducció pot validar la creació durable de l'avís, però el GO productiu ha de mantenir-se condicionat a UC-58 si es vol conservar el correu operatiu a alumne/gestió sense regressió funcional.


## Revalidació de hardening fallback 02/10

La branca d'auditoria 02/10 afegeix una protecció temporal del fallback mentre encara existeixi:

- `FRACCIONAT` rellegit al servidor i import parcial rebutjat quan no correspon;
- outputs del checkout sanejats;
- merchant code/key via entorn;
- signatura, `Ds_Order` i `Ds_Amount` validats abans d'efectes;
- cap correu de depuració pre-validació;
- `RedsysCourseLegacyFallbackBoundaryTest`.

Aquesta protecció **no substitueix el cutover SIF**. El hardening ha quedat revalidat al PR #105. En la segona passada del 02/10, el pont candidat deixa també d'hardcodejar el gateway Redsys, el path HMAC de `course-intent` queda declarat explícitament i `preflight-redsys-course.php` exigeix entorn test/preproduction, callback/gateway HTTPS, clau/secret de l'API interna i paths signats coherents amb els clients del pont.


## Tall en dues fases i drenatge de sessions legacy

El canvi de MerchantURL no s'ha de fer de forma atòmica mentre hi pugui haver un TPV llegat obert al navegador.

1. **NORMAL:** `cutover=0`, `drain=0`. Flux antic/candidat de rollback disponible.
2. **DRAIN:** `cutover=1`, `drain=0`. Es bloquegen nous checkouts (503), però els callbacks llegats en vol encara es processen.
3. **CUTOVER CONFIRMAT:** `cutover=1`, `drain=1`. El candidat utilitza callback SIF i checkout/callback llegats queden retirats (410).
4. **ROLLBACK abans de retirada definitiva:** tornar `cutover=0` i `drain=0` només si s'ha verificat que la configuració i les sessions en vol ho permeten.

Aquesta seqüència evita que un pagament iniciat abans del canvi rebi un 410 abans de ser reconciliat.
