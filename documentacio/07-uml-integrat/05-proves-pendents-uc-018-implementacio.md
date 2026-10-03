# UC-018 · Matriu de proves i criteri de tancament

> Revalidació 2026-10-03. `[x]` = cobert; `[CI]` = patch implementat amb prova però CI de la branca pendent; `[ENV]` = només executable en preproducció; `[POLICY]` = variant deliberadament bloquejada.

## 1. Navegador i frontera pública

- [CI] `pagina_bescanvia.php` carrega `mostrarBescanvia.min.js?ver=6.0`, no un bundle absent/no rastrejable.
- [CI] validació del codi via POST body.
- [CI] lookup del curs via POST body.
- [CI] comprovació de duplicat via POST body; DNI fora de query string.
- [CI] writer final via POST body; codi regal i PII fora de query string.
- [CI] els tres endpoints exclusius UC-018 rebutgen mètodes diferents de POST; `inscripcioDuplicada.php` és compartit, conserva compatibilitat legacy i UC-018 el crida per POST.
- [CI] validació, lookup i writer UC-018 no usen `$_GET`; el duplicat compartit pot llegir GET per compatibilitat d'altres casos, però UC-018 no envia el DNI per URL.
- [CI] resposta pública neutra per codi no bescanviable.
- [CI] validació/lookup fan match exacte del codi; cap `WHERE CODI LIKE ?`.
- [CI] generació de nous codis regal amb CSPRNG i longitud compatible de 10 caràcters.
- [ENV/SECURITY] throttle/rate-limit d'intents de codi acreditat fora del repo o implementat abans del GO públic.
- [CI] lookup del curs revalida server-side la bescanviabilitat.
- [CI] writer bloqueja `FACT_REL <= 0` abans de materialitzar/reutilitzar la matrícula.

Cobertura: `GiftRedemptionWebClientBoundaryTest` ampliat el 03/10.

## 2. Servei de domini

- [x] preview read-only amb GIFT vàlid.
- [x] codi desconegut/alterat no autoritza bescanvi.
- [x] dret caducat/cancel·lat/consumit contradictori rebutjat.
- [x] replay equivalent reutilitza resultat.
- [x] holder no autoritzat rebutjat.
- [x] origen pagat/reconciliat obligatori.
- [x] caller no pot declarar holder ni snapshot econòmic.

## 3. Persistència, idempotència i concurrència

- [x] `FOR UPDATE` sobre dret.
- [x] `CLAIM`, `RESERVE`, `CONSUME`, `RELEASE` append-only.
- [x] mateix `ID_INSC`: dos processos convergeixen al mateix resultat.
- [x] dos `ID_INSC`: un únic guanyador i l'altre conflicte.
- [x] un únic `COMPENSATION_ALLOCATION`.
- [x] resposta SIF perduda: retry reutilitza operació, moviment i reconciliació.
- [x] replay amb `regal.USAT` ja reconciliat **reentra al SIF** i permet reconstruir/reutilitzar outbox; no fa early-return.

## 4. Inscripció

- [x] crea/reutilitza una única matrícula.
- [x] timeout/reintent no crea matrícula duplicada.
- [x] `regal.USAT` només es reconcilia via `LegacyGiftUsageReconciler`.
- [x] candidat existent contradictori (curs/DNI/import/factura/codi) és conflicte.
- [CI] regal no pagat (`FACT_REL <= 0`) no pot passar pel writer públic revalidat.

## 5. Economia i fiscalitat

- [x] valor exacte: 0 `CHARGE` nous.
- [x] conserva `UUID_PAYMENT` d'origen.
- [x] 0 factures noves.
- [x] factura original immutable.
- [x] `GROSS_AMOUNT = FACE_VALUE`, `DISCOUNT_AMOUNT = FACE_VALUE`, `NET_AMOUNT = 0`.
- [POLICY] regal inferior/superior al curs: no inventar cobrament, refund, saldo ni consum parcial.

## 6. Seguretat interna SIF

- [x] legacy→SIF via POST/HMAC/HTTPS.
- [x] anti-replay.
- [x] `BODY_HASH`, no request body, al ledger d'anti-replay.
- [x] holder i snapshot resolts al servidor.
- [x] secrets/codis sanititzats a verificació de preproducció.
- [x] dades sensibles no persistides en clar a outbox UC-018.
- [x] endpoint de notificacions limitat a `GIFT_REDEEM_*`.

## 7. Notificacions

- [x] cap SMTP abans del redeem/reconciliació.
- [x] sis notificacions legacy materialitzades idempotentment.
- [x] claim at-most-once.
- [x] `SENDING` ambigu no es reintenta automàticament.
- [x] `SENT` idempotent.
- [x] `FAILED` queda per revisió/reconciliació.

## 8. E2E i recovery

- [x] E2E intern UC-017 → GIFT → inscripció → compensació → consum → replay.
- [x] concurrència multiprocés.
- [x] recovery després de resposta perduda.
- [x] recovery d'outbox després de saga consumida.
- [CI] boundary navegador→legacy afegit 03/10.
- [ENV] preflight real amb configuració de preproducció.
- [ENV] `verify-gift-redemption-preproduction.php --execute` amb regal controlat.
- [ENV] evidència operativa de les dues BD i SMTP desplegat.

## 9. Evidència CI del nucli — 02/10

PR #115 / snapshot `7ba6cf0f982960a1164561e0d624b1f9e729af73`:

- `SIF PHP MySQL tests` run `37051232707`: **858 passed / 0 failed**.
- **49 PASS** GIFT/UC-018.
- `SIF checks` run `37051232680`: SUCCESS.
- `UC-111 integration verification` run `37051232657`: SUCCESS.
- `UC-004 SIF secure flow checks` run `37051232695`: SUCCESS.

Aquesta evidència continua sent vàlida per al nucli no modificat, però **no acredita per si sola el patch navegador→legacy del 03/10**.

## 10. Criteri de re-tancament 03/10

Per tornar a `AUDIT_CLOSED + CODE_COMPLETE + CI_GREEN`:

1. CI del patch de revalidació en verd;
2. prova boundary pública inclosa en el run;
3. cap regressió al nucli GIFT/SIF.

Els punts `[ENV]` són gates de desplegament. Els `[POLICY]` no bloquegen el flux base de valor exacte.
