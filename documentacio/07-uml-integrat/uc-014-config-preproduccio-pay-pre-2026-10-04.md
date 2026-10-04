# UC-014 — Configuració segura de preproducció a pay-pre.prisma.cat

**Data:** 04/10/2026  
**Entorn:** PREPRODUCTION  
**Host de pagament:** `pay-pre.prisma.cat`  
**DocumentRoot:** `public/`  
**BD SIF:** `sif_pre`

Aquesta fitxa conté només valors no secrets i noms de variables. **No copiar-hi claus, contrasenyes, signatures ni secrets HMAC.**

## 1. URLs canòniques de l'entorn

Com que el DocumentRoot del host apunta a `public/`, el fitxer de repositori:

`sif/public/api/redsys/callback.php`

s'exposa a:

`https://pay-pre.prisma.cat/api/redsys/callback.php`

Configuració no secreta:

```dotenv
SIF_ENV=preproduction

SIF_REDSYS_EXPECTED_PAY_HOST=pay-pre.prisma.cat
SIF_REDSYS_RETURN_BASE_URL=https://pay-pre.prisma.cat
SIF_REDSYS_LEGACY_CALLBACK_URL=https://pay-pre.prisma.cat/doit.php
SIF_REDSYS_CALLBACK_URL=https://pay-pre.prisma.cat/api/redsys/callback.php

SIF_INTERNAL_API_BASE_URL=https://pay-pre.prisma.cat
SIF_INTERNAL_REDSYS_COURSE_INTENT_SIGNED_PATH=/api/redsys/course-intent.php
SIF_INTERNAL_REDSYS_COURSE_STATUS_SIGNED_PATH=/api/redsys/course-status.php

SIF_REDSYS_COURSE_CUTOVER_ENABLED=0
SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=0
```

`REDSYS_GATEWAY_URL` ha d'apuntar a l'endpoint HTTPS de **proves/preproducció Redsys** configurat per al comerç. No copiar aquí una URL si no s'ha contrastat amb la configuració real del TPV.

## 2. Variables sensibles que han d'existir al secret store/entorn

Només comprovar presència, mai registrar el valor:

```text
REDSYS_MERCHANT_CODE
REDSYS_MERCHANT_KEY
REDSYS_TERMINAL
SIF_REDSYS_MERCHANT_CODE
SIF_REDSYS_MERCHANT_KEY
SIF_INTERNAL_API_KEY_ID
SIF_INTERNAL_API_SECRET
SIF_DB_DSN
SIF_DB_USER
SIF_DB_PASSWORD
SIF_LEGACY_DB_DSN
SIF_LEGACY_DB_USER
SIF_LEGACY_DB_PASSWORD
```

El merchant code/key del pont i del callback SIF han de correspondre al mateix comerç i entorn.

## 3. NORMAL — abans de DRAIN

Flags:

```dotenv
SIF_REDSYS_COURSE_CUTOVER_ENABLED=0
SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=0
```

Executar:

```bash
php sif/scripts/preflight-redsys-course.php
php sif/scripts/preflight-redsys-callback-queue.php
```

El primer preflight ha de retornar:

```json
{
  "ok": true,
  "environment": "preproduction",
  "cutover_phase": "NORMAL"
}
```

No continuar si qualsevol check és fals.

## 4. DRAIN

Canviar només:

```dotenv
SIF_REDSYS_COURSE_CUTOVER_ENABLED=1
SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=0
```

Reexecutar el preflight. Ha de mostrar:

```json
{
  "ok": true,
  "environment": "preproduction",
  "cutover_phase": "DRAIN"
}
```

Durant aquesta fase:

- un checkout nou ha de respondre 503;
- el bloqueig ha d'ocórrer abans de crear cap nova intenció;
- callbacks Redsys legacy que ja estaven en vol poden continuar;
- revisar logs i `DS_ORDER` pendents fins confirmar que no queda cap sessió TPV llegada oberta.

## 5. CUTOVER CONFIRMAT

Només després del drenatge:

```dotenv
SIF_REDSYS_COURSE_CUTOVER_ENABLED=1
SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=1
```

El preflight ha de mostrar:

```json
{
  "ok": true,
  "environment": "preproduction",
  "cutover_phase": "CUTOVER_CONFIRMED"
}
```

A partir d'aquí:

- el checkout candidat utilitza `SIF_REDSYS_CALLBACK_URL`;
- els callbacks/checkouts llegats retirats han de respondre 410;
- els retorns OK/KO continuen sent només una vista read-only de l'estat SIF.

## 6. Prova Redsys real

Amb una operació de proves:

1. iniciar el checkout al host de preproducció;
2. completar el TPV de proves;
3. conservar `DS_ORDER`;
4. comprovar callback → notificació → cua;
5. executar worker;
6. verificar `UUID_FACTURA`, `UUID_PAYMENT`, `payment_allocation`, `EXTERNAL_ALLOCATION`, sync llegada i outbox;
7. reexecutar callback/worker i comprovar idempotència;
8. omplir [la plantilla d'evidència](uc-014-plantilla-evidencia-preproduccio.md) sense dades personals ni secrets.

Verificació CLI:

```bash
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER>
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER> --execute
php sif/scripts/verify-redsys-course-preproduction.php <DS_ORDER> --execute --sync-legacy
```

## 7. Rollback

Abans de retirar físicament l'autoritat fiscal llegada, el rollback controlat és:

```dotenv
SIF_REDSYS_COURSE_CUTOVER_ENABLED=0
SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED=0
```

Reexecutar els preflights després del canvi. No utilitzar aquest rollback un cop el callback fiscal llegat s'hagi retirat definitivament.

## 8. GO / NO-GO

**GO preproducció** només si:

- preflight curs = verd;
- preflight cua = verd;
- URLs/host corresponen a `pay-pre.prisma.cat`;
- Redsys gateway correspon a proves;
- secrets presents i no exposats;
- DRAIN verificat;
- callback SIF accessible;
- prova real conserva traça completa i idempotent.

**NO-GO productiu** mentre no s'hagin resolt la rotació/configuració definitiva de secrets, el drenatge/cutover productiu i la decisió/transport UC-058.
