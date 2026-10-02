# UC-018 · Matriu de proves i criteri de tancament

> Actualització 2026-10-02. `[x]` = cobert per prova/codi; `[ENV]` = només executable en preproducció; `[POLICY]` = variant deliberadament bloquejada fins a decisió funcional.

## 1. Servei de domini

- [x] preview read-only amb GIFT vàlid.
- [x] codi desconegut/alterat no autoritza bescanvi.
- [x] dret caducat/cancel·lat/consumit contradictori rebutjat.
- [x] replay equivalent reutilitza resultat.
- [x] holder no autoritzat rebutjat.
- [x] origen ha d'estar pagat/reconciliat.

## 2. Persistència, idempotència i concurrència

- [x] `FOR UPDATE` sobre dret.
- [x] `CLAIM`, `RESERVE`, `CONSUME`, `RELEASE` append-only.
- [x] mateix `ID_INSC`: dos processos convergeixen al mateix resultat.
- [x] dos `ID_INSC`: un únic guanyador i l'altre `409`.
- [x] un únic `COMPENSATION_ALLOCATION`.
- [x] resposta SIF perduda: retry reutilitza operació, moviment i reconciliació.

## 3. Inscripció

- [x] crea/reutilitza una única matrícula.
- [x] timeout/reintent no crea matrícula duplicada.
- [x] `regal.USAT` només es reconcilia via `LegacyGiftUsageReconciler`.
- [x] replay ja reconciliat retorna la mateixa ID abans dels efectes laterals.

## 4. Economia i fiscalitat

- [x] bescanvi a valor exacte: 0 `CHARGE` nous.
- [x] conserva `UUID_PAYMENT` d'origen.
- [x] 0 factures noves.
- [x] factura original immutable.
- [POLICY] valor del regal inferior/superior al curs: no inventar cobrament, refund, saldo ni consum parcial; el flux queda bloquejat fins a política aprovada.

## 5. Seguretat

- [x] codi regal fora de URL.
- [x] POST/HMAC al SIF.
- [x] anti-replay.
- [x] holder i snapshot resolts al servidor.
- [x] secrets/codis sanititzats a la verificació de preproducció.
- [x] dades sensibles no persistides en clar a l'outbox UC-018.
- [x] l'endpoint GIFT només pot claim/complete files amb `TEMPLATE_CODE` prefix `GIFT_REDEEM_`.

## 6. Notificacions

- [x] cap SMTP abans del redeem/reconciliació.
- [x] sis notificacions legacy materialitzades de manera idempotent.
- [x] claim at-most-once.
- [x] `SENDING` ambigu no es reintenta automàticament.
- [x] `SENT` idempotent.
- [x] `FAILED` queda per revisió.

## 7. E2E

- [x] E2E intern UC-017 → GIFT → inscripció → compensació → consum → replay.
- [x] concurrència multiprocés.
- [x] recovery resposta perduda.
- [ENV] preflight real amb configuració de preproducció.
- [ENV] `verify-gift-redemption-preproduction.php --execute` amb regal de prova controlat.
- [ENV] evidència operativa final del desplegament/SMTP real.

## 8. Criteri de tancament de l'auditoria

El codi/documentació queda tancable quan CI del PR de tancament acredita:

```text
1 compra pagada
1 dret GIFT
1 inscripció
1 COMPENSATION_ALLOCATION
1 consum
0 cobraments duplicats
0 factures duplicades
replay idempotent
concurrència determinista
correus post-SIF governats
```

Els punts `[ENV]` són gates de desplegament i no deute de disseny/codi.