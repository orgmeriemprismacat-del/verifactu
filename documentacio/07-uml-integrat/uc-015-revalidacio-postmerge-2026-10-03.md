# UC-015 · Revalidació post-merge · 2026-10-03

## 1. Punt de tall

Revalidació executada després de la fusió del PR #102.

- merge UC-015: 8206d6b58fb1eb4d7455d02860a3846eb8600aea;
- main revalidat: b0e8ff7150c5a8b415cc109d298d82f0db1f68df;
- objectiu: comprovar que els merges posteriors no hagin reobert gaps funcionals PACK i reconciliar la CI del main.

## 2. Estat funcional

No s'ha detectat cap regressió funcional UC-015.

El main conserva:

- alta pública POST-only;
- autorització web compartida PublicWebMutationAuthorization::assertSameOriginAjax();
- idempotència server-side amb REQUEST_ID, fingerprint i lock;
- replay equivalent i conflicte 409 per payload divergent;
- transacció atòmica de les N inscripcions;
- disponibilitat server-side de tots els components;
- ordre canònic DATAI, ID_CURS congelat a PACK_ORDINAL;
- preu/snapshot backend-authoritative;
- checkout PACK → intenció SIF;
- factura/payment, ledger, outbox enqueue i sync legacy;
- callbacks fiscals PACK legacy retirats de producció;
- preflight, verificador i evidència de preproducció.

## 3. Canvis posteriors compartits

Entre el merge UC-015 i el main revalidat, les dependències compartides rellevants modificades són:

- RedsysPaymentIntentService;
- RedsysSignatureValidator.

La branca PACK de RedsysPaymentIntentService continua utilitzant validatePackSnapshot() sense canvi de contracte. El hardening del validador Redsys amplia signatures, valida merchant/transaction type i elimina normalització monetària amb float; no invalida el contracte PACK.

## 4. CI de main detectada

Els workflows del main@b0e8ff7... van finalitzar amb:

- SIF PHP MySQL tests: FAILURE;
- UC-111 integration verification: FAILURE;
- suite: **917 passed / 6 failed**.

Els sis errors identificats són de contracte de prova desfasat, no de lògica productiva PACK:

1. PackEnrollmentIdempotencyBoundaryTest: interpolació accidental de requestId dins l'assert.
2. PackEnrollmentTransportBoundaryTest: esperava lectures directes POST; el codi ja normalitza amb request = POST.
3. PackPaymentPrivacyBoundaryTest: mantenia expectatives antigues sobre email a URLs/pàgines de retorn.
4. PackPaymentPrivacyBoundaryTest: el helper autoritatiu ja no consumeix email del query string.
5. PackPublicEnrollmentBoundaryTest: esperava HTTP_ORIGIN/REFERER inline; la validació s'ha centralitzat a PublicWebMutationAuthorization.
6. RedsysSignatureValidatorTest: payload_hash esperat hard-coded no corresponia al fixture actual.

També s'ha detectat un warning de prova per interpolació accidental de fractional.

## 5. Correcció aplicada en aquesta branca

La branca de revalidació:

- manté intacte el codi productiu UC-015;
- actualitza els boundary tests perquè validin el contracte executable actual i no literals obsolets;
- valida que la frontera d'origen es delega al helper compartit;
- valida que PACK no exposa email a URLs de retorn;
- valida que les pàgines de retorn no consumeixen email;
- deriva payload_hash directament del fixture signat;
- elimina el warning d'interpolació de fractional.

## 6. Classificació

### Documentat
**SÍ.** El paquet documental UC-015 continua complet.

### Implementat
**SÍ.** No s'ha detectat cap gap nou de programació UC-015.

### Verificat
La verificació funcional prèvia continua vigent; aquesta revalidació exigeix recuperar CI verda sobre el HEAD corrector abans de fusionar.

### Pendent propi d'UC-015
Només l'acceptació runtime amb DS_ORDER real de preproducció, tal com ja constava al tancament integral.

### Dependència externa
UC-58 continua essent responsable del lliurament/retries efectius de notificacions; no és un gap intern d'UC-015.
