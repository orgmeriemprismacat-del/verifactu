# UC-009 · Tancament tècnic i evidència

**Data:** 2026-09-30  
**Cas:** UC-009 · Remetre registre fiscal a AEAT  
**Estat de codi/documentació:** TANCAT TÈCNICAMENT  
**Estat operatiu d'entorn:** PENDENT DE PREPRODUCCIÓ/AEAT

## 1. Merge i versió

- PR #23 fusionada a `main`.
- Merge commit: `4b7ffc16bf95b957457b74cb9a0b70069fc400d8`.
- Traçabilitat UC-009 actualitzada a `main`.
- Registre mestre actualitzat amb estat de tancament tècnic.

## 2. Lliurables existents

- Fitxa funcional: `documentacio/06-fitxes-funcionals/uc-009.md`.
- UML integrat: `documentacio/07-uml-integrat/uc-009-remetre-registre-aeat.md`.
- Activitats ACTUAL/FINAL: `documentacio/07-uml-integrat/uc-009-activitats-actual-final.md`.
- Desplegament/panell: `documentacio/05-governanca-operacio/uc-009-panell-registres-aeat-desplegament.md`.
- Panell intranet: `codi-drive/intranet-actual/sif-registres-aeat.php`.
- API interna: `sif/public/api/aeat/operations.php`.
- Reconciliació REVIEW: `sif/src/Service/AeatReviewReconciliationService.php`.
- Persistència d'intents: `sif/src/Repository/AeatSubmissionAttemptRepository.php`.
- Fencing de cua: `fiscal_queue.CLAIM_TOKEN`.

## 3. Evidència automàtica

Resultat CI verificat abans del merge:
- `SIF PHP MySQL tests`: PASS.
- `SIF checks`: PASS.
- `Intranet AO batch checks`: PASS.
- `UC-111 integration verification`: PASS.
- Suite SIF: **558 passed / 0 failed**.
- PHP lint: PASS.
- JavaScript syntax: PASS.

## 4. Invariants verificats

- payload fiscal immutable abans de SOAP;
- fencing de claim amb token;
- stale worker no pot tancar/fallar claim nou;
- intent AEAT persistit abans de xarxa;
- `SENT` separat d'`ACCEPTED/ACCEPTED_WITH_ERRORS/REJECTED`;
- resultat remot incert → `REVIEW`;
- `UNCERTAIN` no es reconcilia;
- reconciliació només amb l'últim intent del mateix job;
- `REQUEST_HASH` ha de coincidir amb l'XML del snapshot immutable;
- reconciliació no fa segon SOAP;
- permisos de lectura i reconciliació separats;
- reconciliació d'intranet protegida amb sessió + HMAC + CSRF.

## 5. No acreditat encara

Aquest document **no** afirma:
- migracions aplicades a la BD real de preproducció;
- variables/secrets configurats al servidor;
- alta real del panell a la taula `apartats`;
- prova amb usuaris reals;
- prova contra AEAT preproducció;
- certificat/configuració AEAT validats en entorn real;
- habilitació de producció.

## 6. Criteri

A partir d'aquest punt, qualsevol feina de codi del UC-009 només s'ha de reobrir si:
1. falla una prova reproduïble;
2. una prova de preproducció mostra discrepància;
3. AEAT retorna un cas no cobert;
4. apareix un requisit funcional nou.

La resta de passos són de desplegament, configuració i evidència d'entorn.
