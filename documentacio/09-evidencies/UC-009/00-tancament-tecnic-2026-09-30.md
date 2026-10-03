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

## 3. Evidència automàtica històrica del tall 2026-09-30

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


## 7. Revalidació posterior — 2026-10-03

Aquesta secció **no altera** l'evidència històrica anterior; documenta que el repositori ha evolucionat.

Tall revalidat: `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`.

Workflow `SIF PHP MySQL tests`, run `37061206441` del 2026-10-02:

- resultat global: **917 passed / 6 failed**;
- els tests UC-009/AEAT visibles al log passen;
- les 6 fallades són de PACK/Redsys, no del worker/panell/reconciliació AEAT.

Per tant:

1. el **558/0** d'aquest document continua sent evidència correcta del tall 30/09;
2. no s'ha d'utilitzar per afirmar que la suite global del `main` actual és verda;
3. el UC-009 continua tenint evidència automàtica específica favorable dins el run actual;
4. la branca `audit/uc-009-revalidacio-2026-10-03` amplia cobertura amb contracte del panell, lint/path de CI, preflight de menú i validació UUID estricta;
5. la nova cobertura queda pendent del resultat CI de la branca/PR.

No hi ha encara evidència versionada d'un enviament real al servei AEAT de preproducció.
