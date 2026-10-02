# UC-018 · Tancament de l'auditoria · 02/10/2026

**Repositori:** `orgmeriemprismacat-del/verifactu`  
**PR de tancament:** #115  
**Head executable verificat:** `7ba6cf0f982960a1164561e0d624b1f9e729af73`  
**Merge a main:** `66c63765254623e017cdfae8ad81ac72fafa8903`  
**Main observat després del merge:** `8206d6b58fb1eb4d7455d02860a3846eb8600aea`  
**CI principal:** run `37051232707` — **858 passed / 0 failed**  
**Proves GIFT/UC-018 identificades dins del run:** **49 PASS**

## 1. Estat de tancament

L'auditoria del UC-018 queda **TANCADA** amb l'estat:

`AUDIT_CLOSED + CODE_COMPLETE + DOCUMENTATION_RECONCILED + CI_GREEN + ENVIRONMENT_ACCEPTANCE_PENDING`

Aquesta classificació separa:

- **auditoria tècnica/codi:** tancada;
- **validació automatitzada:** verda;
- **acceptació real de preproducció/producció:** pendent d'entorn.

No queda cap buit PHP/JS, servei, repository, UML, recovery, concurrència o prova automatitzada obligatòria detectat dins l'abast base auditat que impedeixi tancar UC-018.

## 2. Abast funcional tancat

El flux base verificat és el **bescanvi d'un regal a valor exacte**.

La seqüència tancada és:

1. compra UC-017 pagada i reconciliada;
2. emissió/reutilització d'un dret `GIFT`;
3. resolució autoritativa del participant i del snapshot econòmic dins del SIF;
4. creació o reutilització d'una única inscripció;
5. `CLAIM → RESERVE → COMPENSATION_ALLOCATION → CONSUME`;
6. reconciliació compare-and-set de `regal.USAT`;
7. materialització idempotent de sis notificacions;
8. claim/complete independent de cada notificació;
9. replay convergent davant timeout o resposta perduda.

Les diferències de valor regal/curs continuen fail-closed fins a una decisió funcional específica. No es crea automàticament cobrament complementari, saldo, romanent, devolució ni consum parcial.

## 3. Invariants verificats

La suite final acredita el contracte següent:

```text
1 compra pagada
1 dret GIFT
1 inscripció
1 COMPENSATION_ALLOCATION
1 consum
0 CHARGE addicionals
0 factures addicionals
replay idempotent
concurrència determinista
notificacions post-SIF governades
```

També queda verificat que:

- el codi regal no viatja a URL;
- el caller web no declara `holder_party_key` ni `trusted_price_snapshot`;
- les rutes internes són POST/HMAC amb anti-replay;
- el replay reutilitza la mateixa `ID_INSC` i reentra al SIF per reconstruir/reutilitzar l'outbox si cal;
- un destí concurrent diferent rep conflicte;
- l'API de notificacions GIFT només opera sobre `TEMPLATE_CODE` amb prefix `GIFT_REDEEM_`;
- `SENDING` ambigu no provoca reenviament automàtic;
- `SENT` és idempotent;
- `FAILED` queda per revisió manual/controlada.

## 4. Evidència automatitzada final

### 4.1. SIF PHP MySQL tests

Run `37051232707` — **SUCCESS**.

Resultat final:

```text
858 passed, 0 failed
```

Dins del mateix run s'han identificat **49 PASS** relacionats directament amb GIFT/UC-018, incloent:

- `GiftEnrollmentStagerTest`;
- `GiftRedemptionConcurrencyTest`;
- `GiftRedemptionEndToEndTest`;
- `GiftRedemptionEndpointBoundaryTest`;
- `GiftRedemptionLegacyMailBoundaryTest`;
- `GiftRedemptionNotificationBundleServiceTest`;
- `GiftRedemptionPreproductionBoundaryTest`;
- `GiftRedemptionRecoveryCliBoundaryTest`;
- `GiftRedemptionServiceTest`;
- `GiftRedemptionTrustedContextResolverTest`;
- `GiftRedemptionWebClientBoundaryTest`;
- `HistoricalGiftEntitlementBackfillServiceTest`;
- `HistoricalGiftEntitlementPreflightScriptTest`;
- `NotificationOutboxDeliveryServiceTest`.

### 4.2. Altres suites del mateix snapshot

També finalitzen en **SUCCESS**:

- `SIF checks` — run `37051232680`;
- `UC-111 integration verification` — run `37051232657`;
- `UC-004 SIF secure flow checks` — run `37051232695`.

Per tant, el snapshot `7ba6cf0...` queda amb **4/4 workflows aplicables en verd**.

## 5. Documentació reconciliada

El paquet UC-018 queda format, com a mínim, per:

- fitxa funcional: `documentacio/06-fitxes-funcionals/uc-018.md`;
- fitxa/UML integrada: `uc-018-bescanviar-regal.md`;
- classes ACTUAL/FINAL: `uc-018-classes-actual-final.md`;
- seqüències ACTUAL/FINAL: `uc-018-sequencies-actual-final.md`;
- activitats per superfície: `uc-018-activitats-pagines-bescanvi-regal-actual-final.md`;
- auditoria detallada: `04-auditoria-detallada-uc-018-bescanviar-regal-2026-09-30.md`;
- matriu de proves: `05-proves-pendents-uc-018-implementacio.md`;
- aquest tancament final: `10-tancament-auditoria-uc-018-2026-10-02.md`.

## 6. PR i branques reconciliades

El PR **#115** és la font consolidada del tancament.

Van quedar tancats sense merge perquè havien estat absorbits o superats:

`#86, #87, #88, #89, #90, #91, #92, #96, #100, #101, #103, #104, #108`.

No queda cap PR UC-018 obert després del merge del #115.

## 7. Canvis posteriors a main

Entre el merge UC-018 `66c63765...` i el `main` observat `8206d6b5...` hi ha un commit posterior orientat principalment a UC-015/pack i als seus workflows.

No modifica les peces executables UC-018 consolidades al #115. Per tant, la traça de CI del snapshot UC-018 continua sent aplicable al tancament del cas.

## 8. Acceptació d'entorn pendent

Per declarar també `ENVIRONMENT_CLOSED`, falta executar en preproducció controlada:

1. `preflight-gift-redemption.php`;
2. `verify-gift-redemption-preproduction.php --execute`;
3. un regal de prova controlat aportat per variables d'entorn;
4. evidència de la reconciliació real amb les dues BD;
5. evidència del transport SMTP desplegat;
6. confirmació que el desplegament manté secrets, HMAC, TLS i rols configurats.

Aquestes comprovacions necessiten l'entorn real i no s'han de substituir per dades sintètiques de CI.

## 9. Criteri final

**Auditoria UC-018: TANCADA.**  
**Codi UC-018 base: COMPLET segons l'abast auditat.**  
**Documentació/UML: RECONCILIATS.**  
**CI: VERD, 858/0; 49 PASS GIFT/UC-018; 4/4 workflows SUCCESS.**  
**Acceptació preproducció/producció: PENDENT D'ENTORN, no pendent de codi.**
