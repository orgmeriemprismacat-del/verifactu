# UC-019 — Refactor pendent de notificacions a outbox

Data: 2026-10-04.

## Motiu

La implementació actual de `Intranet::sendMsgValidatCurosDescomptes()` barreja en un únic mètode:

- lectura de dades de la inscripció;
- càlcul de preus;
- mutació de `TIPUS_DESC`, `VALID_DESC` i `A_PAGAR`;
- actualització del cas de docent novell;
- construcció del contingut del correu;
- enviament SMTP a secretaria;
- enviament SMTP a l'alumne.

Això impedeix garantir idempotència completa de les notificacions davant una caiguda entre mutació i SMTP.

## Infraestructura existent aprofitable

El SIF ja disposa de:

- `NotificationOutboxRepository`;
- `NotificationOutboxDeliveryService`;
- idempotency key;
- estats `PENDING / SENDING / SENT / FAILED`;
- política conservadora: un enviament `SENDING` ambigu no es repeteix automàticament.

## Refactor proposat

Separar el mètode llegat en tres responsabilitats:

1. `applyDiscountValidationDecision()`
   - només mutació i càlcul;
   - retorna snapshot de resultat.

2. `buildDiscountValidationNotification()`
   - només construeix dades/template;
   - sense SMTP.

3. `enqueueDiscountValidationNotifications()`
   - crea dues notificacions idempotents:
     - secretaria;
     - alumne.

Claus suggerides:

- `USOC_VALIDATION:<requestId>:SECRETARIA`
- `USOC_VALIDATION:<requestId>:ALUMNE`

## Invariant

El `COMMITTED` del SIF representa que la decisió i la mutació llegada són coherents.

L'estat del correu és independent:

- `PENDING`
- `SENDING`
- `SENT`
- `FAILED / REVIEW`

No revertir la decisió USOC perquè SMTP falli.

## No implementat en aquesta auditoria

No s'ha reescrit el fitxer monolític `Intranet.php` perquè és codi llegat de gran volum i la separació necessita proves de regressió específiques del contingut del correu, preus i promocions.

Aquesta tasca queda classificada com a millora arquitectònica posterior al tancament funcional del UC-019, però és obligatòria si es vol garantir exactament-una notificació o evidència durable d'enviament.
