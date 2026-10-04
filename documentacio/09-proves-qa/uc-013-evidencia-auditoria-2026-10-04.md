# UC-013 · Evidència d'auditoria i reconciliació · 04/10/2026

## Identitat

- Repositori: `orgmeriemprismacat-del/verifactu`
- PR: #152
- Branca: `audit/uc-013-tancament-2026-10-04-v2`
- Base reconciliada: `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`
- Abast: fitxa funcional, PHP/JS legacy i SIF, UML ACTUAL/FINAL, traçabilitat, proves i mancances.

## Artefactes documentals confirmats

- `documentacio/06-fitxes-funcionals/uc-013.md`
- `documentacio/07-uml-integrat/uc-013-orquestrar-doble-facturacio-usoc.md`
- `documentacio/07-uml-integrat/uc-013-classes-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-sequencies-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-activitats-pagines-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-canvi-curs-usoc-contracte-final.md`
- `documentacio/07-uml-integrat/uc-013-auditoria-tracabilitat-2026-09-29.md`

## Troballa de la CI del capçal anterior

El capçal `0ad09aff557e7da0c2034fb45ed29105ea41df11` va executar la suite SIF en tres workflows i va obtenir el mateix resultat: **1017 PASS / 2 FAIL**.

Fallades:

- `UsocCourseChangeLegacyHandoffServiceTest::testPendingLegacySourceKeepsExecutionReadyWithoutApplyingEffects`: el test esperava `REVIEW_REQUIRED`, però el servei retornava `REQUESTED`.
- `UsocCourseChangeLegacyHandoffServiceTest::testDestinationMismatchFailsBeforeAdvancingCheckpoint`: el test esperava `REQUESTED`, però el servei persistia `REVIEW_REQUIRED`.

## Resolució

El codi del servei i el contracte funcional coincideixen:

- origen legacy encara pendent → operació preparada, sense efectes, `REQUESTED + DESTINATION_RESERVED`;
- divergència de la destinació → `REVIEW_REQUIRED + LEGACY_DESTINATION_MISMATCH`.

Per tant la regressió era al test, no al servei. Els dos asserts s'han corregit. No s'ha rebaixat cap control fail-closed.

## Reconciliació amb main

Abans de la correcció, el PR #152 havia quedat 22 commits per darrere. El delta d'aquests commits afectava 15 fitxers i **cap** dels 53 fitxers UC-013. El paquet s'ha reconstruït sobre el `main` actual conservant els 53 fitxers auditats.

## Estat

- DOCUMENTAT: sí.
- IMPLEMENTAT: sí, per l'abast executiu definit.
- VERIFICAT ESTÀTICAMENT: sí.
- CI HISTÒRICA: sí.
- CI DEL CAPÇAL RECONCILIAT: en cua en el moment d'aquesta evidència.
- PREFLIGHT REAL DE PREPRODUCCIÓ: pendent.
- E2E NAVEGADOR/PREPRODUCCIÓ: pendent.
- PRODUCCIÓ: no acreditada.

## Pendents funcionals/fiscals

- variant curs gratuït / part alumne = 0;
- part entitat = 0 fora del contracte executiu actual;
- decisió comercial 20 % públic vs 25 % històric;
- validació fiscal de les variants EXEMPT/E1;
- resolució explícita dels excessos per pagador (refund o `credit_balance`);
- evidència de configuració real de secrets, rols i billing a preproducció.


## Segona troballa · aritmètica monetària amb float

La continuació de l'auditoria ha detectat una mancança transversal: components UC-013 antics arrodonien o comparaven imports amb `float`.

### Superfície corregida

- `LegacyUsocSnapshotRepository`;
- `LegacyUsocInvoicePayloadBuilder`;
- `RedsysUsocInvoiceService`;
- `UsocEntityInvoiceService`;
- `UsocFinancingCaseRepository`;
- `UsocStudentInvoiceLinkRepository`;
- `UsocCaseReconciler`;
- `EnrollmentFundMovementRepository`;
- `UsocCourseChangeInvoicePayloadBuilder`;
- `UsocCourseChangeExecutionService`;
- store legacy de reserva de canvi de curs;
- comparació de l'import reservat al handoff intranet.

### Solució

`DecimalAmount` centralitza el parseig/format SIF en cèntims enters. Les peces legacy sense autoload SIF repliquen el parser decimal estricte mínim. La cerca global del diff del PR ja no mostra cap càlcul monetari nou amb `float` dins l'UC-013.

### Proves afegides

- més de dues posicions decimals → fail-closed;
- base/descompte que no reconcilien → fail-closed;
- notació científica → fail-closed;
- contracte de reserva/handoff sense normalització float.

**Verificació automàtica:** pendent de la CI del capçal resultant.


### Coherència de metadades

La revisió final també ha bloquejat snapshots on `usoc.entity_amount` divergeix del descompte aplicat a la factura alumne o de l'import de la factura entitat. Aquesta incoherència ara falla abans de l'emissió.


## Tercera troballa · preflight del runtime intranet

Els scripts SIF indicaven quines variables d'intranet eren necessàries, però no podien demostrar que estiguessin definides al host intranet. S'ha afegit `codi-drive/intranet-actual/preflight-sif-usoc-runtime.php`, CLI-only, que comprova URL HTTPS, correspondència URL/path signat HMAC, key-id/secret presents, rols, feature flag i fitxers executables sense imprimir els secrets.

L'acceptació de preproducció queda pendent fins conservar tant el JSON SIF com el JSON intranet.


## Quarta troballa · assets de producció a preproducció

S'han eliminat URLs fixes de `intranet.prisma.cat` de les superfícies UC-013 que han de ser provades a `intranet-pre`. Els JS del canvi de curs/USOC i la pantalla autònoma de finançament ara són same-origin. Un contract-test comprova que no es reintrodueixi aquesta barreja d'entorns.


## Cinquena troballa · compatibilitat cross-host

S'ha afegit un comparador dels JSON de preflight perquè dos hosts puguin estar individualment ben configurats però ser incompatibles entre ells. El comparador valida key-id, path HMAC, rols menu/manage i feature flag sense llegir ni imprimir cap secret.


## Sisena troballa · aïllament del target SIF per entorn

El runtime intranet disposa ara de `SIF_INTERNAL_USOC_EXPECTED_HOST`. El preflight falla si `SIF_INTERNAL_USOC_URL` apunta a un host diferent, encara que HTTPS, path i HMAC estiguin configurats correctament. Això evita que una intranet de preproducció pugui validar accidentalment contra un SIF d'un altre entorn.

També s'ha alineat `SIF_USOC_UI_ENABLED=1` entre pantalla i menú, amb rols normalitzats de forma consistent.
