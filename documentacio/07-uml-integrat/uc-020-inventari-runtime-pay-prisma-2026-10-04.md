# UC-020 — Inventari runtime web / pont pay.prisma.cat / SIF — 04/10/2026

**Objectiu:** separar evidència executable ACTUAL, pont candidat i FINAL SIF per evitar atribuir desplegament a codi que només existeix al repositori.

## 1. Regla d'evidència

`codi-drive/README.md` és explícit:

- `web-actual` = **web/ecommerce actual sense canvis VERI*FACTU**, font del flux llegat inspeccionat;
- `pay-prisma-cat-canvis-verifactu` = **proposta/candidata** de `pay.prisma.cat`;
- les carpetes `codi-drive` són evidència/referència i **no acrediten desplegament**;
- qualsevol afirmació de runtime actiu requereix evidència de desplegament o execució a l'entorn corresponent.

Aquesta regla supersedeix les frases UC-020 anteriors que deien «checkout de targeta actiu integrat a SIF» només a partir de la presència del codi candidat.

## 2. ACTUAL/fallback inspeccionat — `codi-drive/web-actual`

### Entrada

`.htaccess` enruta:

`/efectPagAuto/`
→ `pagina_efectuar_pagament_automatic.php`.

### Comportament observat

`web-actual/pagina_efectuar_pagament_automatic.php`:

1. valida POST i rellegeix la matrícula mitjançant `JasomNovicePaymentGate`;
2. obté `idpag`, curs, total, pagat, import de pagament i fraccionament des de servidor;
3. genera un `DS_ORDER` local amb `random_int`;
4. construeix directament els paràmetres Redsys;
5. usa `createMerchantParametersV2()` i `createMerchantSignatureV2()`;
6. no conté `SifRedsysCourseIntentClient`;
7. no crida `/api/redsys/course-intent.php`.

Per tant, el flux actual inspeccionat **encara no acredita** que abans de Redsys existeixi una `redsys_payment_intent` SIF per UC-020.

Els flags `SIF_REDSYS_COURSE_CUTOVER_ENABLED` i `SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED` existeixen al fallback, però en aquesta còpia només bloquegen/retiren el checkout llegat; no converteixen per si mateixos la pàgina en client SIF.

## 3. PONT CANDIDAT implementat — `codi-drive/pay-prisma-cat-canvis-verifactu`

`pagina_efectuar_pagament_automatic.php` de la candidata:

1. aplica el mateix gate servidor de matrícula;
2. carrega `SifRedsysCourseIntentClient.php`;
3. crida `SifRedsysCourseIntentClient::create(idPag, requestedAmount, terminal)`;
4. el client signat envia `POST /api/redsys/course-intent.php`;
5. el SIF rellegeix la matrícula;
6. si `TIPUS_DESC=1`, deriva a `PrismaStudentCourseCheckoutService`;
7. la pàgina usa el `ds_order` i l'`amount` retornats pel SIF per construir Redsys;
8. el callback es pot tallar cap al SIF només amb cutover + drain explícits.

**Classificació:** `IMPLEMENTAT_PONT_CANDIDAT`, no `VERIFICAT_DESPLEGAT`.

## 4. FINAL SIF disponible al repositori

Existeixen i estan traçats:

- `sif/public/api/redsys/course-intent.php`;
- `RedsysCoursePaymentIntentService`;
- `LegacyPrismaStudentPriceSnapshotResolver`;
- `PrismaStudentCourseCheckoutService`;
- `RedsysPaymentIntentService`;
- `CourseIntentSnapshotValidator`;
- repositoris `commercial_operation`, participant, línia, validació i intenció.

Per AP, el nucli pot:

`historial → policy v2 → tarifa autoritativa → commercial_operation → discount_validation → operation line/party → redsys_payment_intent`.

Això és **IMPLEMENTAT_SIF**, però no demostra que el canal web desplegat l'estigui invocant.

## 5. Matriu de classificació

| Peça | Estat |
| --- | --- |
| Preview/alta AP web hardenitzada a la branca UC-020 | IMPLEMENTAT_BRANCA · CI HEAD PENDENT |
| `web-actual` preparar Redsys directament | ACTUAL/FALLBACK INSPECCIONAT |
| `pay-prisma-cat-canvis-verifactu` crear intenció SIF abans de Redsys | IMPLEMENTAT_PONT_CANDIDAT |
| Endpoint `/api/redsys/course-intent.php` | IMPLEMENTAT_SIF |
| Checkout AP SIF intern | IMPLEMENTAT_SIF |
| Pont candidat desplegat a `pay.prisma.cat` | **NO VERIFICAT** |
| Cutover callback SIF en entorn real | **NO VERIFICAT** |
| E2E navegador → intenció → Redsys → callback → worker → factura | **PENDENT EXECUCIÓ CONTROLADA** |

## 6. Gate necessari per promocionar «candidat» a «actiu»

Cal conservar evidència de:

1. document root/versió desplegada de `pay.prisma.cat`;
2. SHA o release desplegada;
3. presència/configuració del client `SifRedsysCourseIntentClient`;
4. URL interna SIF i secret configurats sense exposar-los;
5. flags cutover/drain efectius;
6. transacció controlada on l'ordre Redsys coincideixi amb `redsys_payment_intent.DS_ORDER`;
7. callback processat pel circuit esperat;
8. cobrament/factura i sincronització llegada;
9. prova de duplicat/reintent.

Fins llavors, la redacció correcta és **«pont candidat implementat; desplegament i cutover pendents de verificar»**.
