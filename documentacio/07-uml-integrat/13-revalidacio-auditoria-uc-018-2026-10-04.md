# UC-018 · Revalidació exhaustiva i reconciliació final · 04/10/2026

**Cas:** UC-018 · Bescanviar regal  
**Branca neta:** `audit/uc-018-revalidacio-2026-10-04-v2`  
**PR:** #160  
**Baseline acreditada:** PR #115 / PR #117 — 858 passed, 0 failed; 49 PASS GIFT/UC-018  
**Estat d'aquest tall:** `CODE_PATCHED / CI_HEAD_PENDING / ENVIRONMENT_GO_PENDING`

## 1. Abast revisat

S'ha contrastat la fitxa funcional amb el codi PHP/JS executable, el model SIF, els endpoints interns, classes ACTUAL/FINAL, seqüències ACTUAL/FINAL, activitats per superfície, proves, replay, concurrència, notificacions i operació de preproducció.

Superfícies web canòniques:
- `pagina_bescanvia.php`;
- `mostrarBescanvia.min.js`;
- `codiRegalValid.php`;
- `buscarCursRegalat.php`;
- `bescanviaUnCurs.php`;
- `inscripcioDuplicada.php`;
- `buscarSiHaRealitzatElCurs.php`;
- `enviamentPubli.php`;
- `enviarInscripcioBescanvia.php`;
- confirmació web.

Nucli SIF:
- `GiftEntitlementIssuerService`;
- `CommercialEntitlementRepository`;
- `GiftRedemptionTrustedContextResolver`;
- `GiftEnrollmentStager`;
- `GiftRedemptionService`;
- `EnrollmentFundMovementRepository`;
- `LegacyGiftUsageReconciler`;
- `GiftRedemptionOrchestrator`;
- `GiftRedemptionNotificationBundleService`;
- `NotificationOutboxDeliveryService`;
- endpoints `redeem.php` i `notifications.php`.

## 2. Troballes 04/10

| ID | Troballa | Resolució |
| --- | --- | --- |
| UC18-RV2-001 | PR #127 estava 61 commits darrere del main i no era una base fiable per continuar. | Nova branca des del main actual; només es trasllada el delta UC-018. |
| UC18-RV2-002 | 10 fallades GIFT del CI provenien de fixtures amb `RULE_SNAPSHOT_JSON='{}'`, incompatibles amb el nou contracte immutable correcte. | Fixtures alineats amb `GiftEntitlementIssuerService::RULE_VERSION` i snapshot real. |
| UC18-RV2-003 | Tests de regal genèric mutaven `regal.CCURS` sense actualitzar el snapshot de compra; estaven simulant tampering involuntàriament. | Helper de compra actualitza regal + snapshot; tampering queda en una prova específica. |
| UC18-RV2-004 | La UI legacy d'un regal concret permet canviar-lo per un altre curs de les mateixes hores, però resolver/stager exigien el mateix codi de curs. | Mateix curs acceptat; swap acceptat només si les hores del curs original són històricament unívoques i coincideixen amb l'edició escollida. |
| UC18-RV2-005 | El writer podia commitar una matrícula abans que el SIF detectés un curs manipulat/incompatible. | Guard curs/hores dins la transacció legacy, abans de crear/commitar `ID_INSC`; SIF revalida després. |
| UC18-RV2-006 | La fitxa encara contenia frases genèriques sobre rol d'usuari, audit tables i gateway que no descrivien el camí executable. | Fitxa v1.6 reconciliada amb bearer code + matrícula compromesa + HMAC de servei i persistència real. |
| UC18-RV2-007 | Decisions de romanent/caducitat apareixien com si bloquegessin tot el cas. | Reclassificades com POLICY/VARIANT: no bloquegen el flux base fail-closed. |
| UC18-RV2-008 | L'elegibilitat legacy és per hores, però no hi ha evidència al camí auditat que tots els cursos de les mateixes hores tinguin sempre el mateix preu de catàleg. `Curs.php` acredita que el catàleg disposa de `curs.ID_PREU` i consulta `preu.IMPORT`, però UC-018 no el contrasta. | Es manté l'aplicació exacta de `FACE_VALUE`; l'equivalència preu-curs↔regal queda [POLICY/DATA] fins decidir preu/snapshot/data/descomptes autoritatius o documentar un invariant de negoci. |
| UC18-RV2-009 | L'`.htaccess` públic enviava `/bescanvia-regal` a `pagina_bescanvia_prova.php`, però aquest fitxer no existeix. | Routing corregit a `pagina_bescanvia.php`; boundary específic. |
| UC18-RV2-010 | La confirmació usava AES-CBC + HMAC només del ciphertext: l'IV no estava autenticat. El parser extreia el token de `REQUEST_URI` amb un `substr(...,-16)` fràgil i el bearer anava al path/query. | Token v2 AES-256-GCM/base64url, fragment URL, endpoint POST-only, `no-store/no-referrer`, correu emmascarat i prova anti-tampering. |
| UC18-RV2-011 | Un error SMTP posterior al `CONSUME` feia que el writer retornés error i ocultés una matrícula ja confirmada. | El writer conserva `FAILED/SENDING` a outbox/log i retorna igualment el token de confirmació. Retry/reconciliació manual continua com a OPS pendent d'UC-058. |
| UC18-RV2-012 | `dies-inscriu-cursos` admet regla per hores i per curs, però el legacy històric no fixa una precedència contractual si coexisteixen amb valors diferents. | Classificat DATA/CONFIG: validar el dataset de preproducció; no inferir una política nova en codi UC-018. |

## 3. Regla funcional de curs reconciliada

El PHP legacy estableix dues formes de compra:

1. `regal.CCURS=N`: regal genèric per N hores;
2. `regal.CCURS=<codi>`: regal d'un curs concret.

En el segon cas la UI permet, **abans del consum**, canviar el curs regalat per un altre del mateix nombre d'hores.

Regla FINAL implementada:

```text
CCURS numèric
  -> hores(edició seleccionada) == CCURS

CCURS concret i curs seleccionat == CCURS
  -> acceptar

CCURS concret i curs seleccionat != CCURS
  -> hores històriques del curs original han de ser un únic valor positiu
  -> hores(edició seleccionada) han de coincidir
  -> si hi ha ambigüitat o diferència: CONFLICT 409
```

El snapshot SIF conserva immutable `legacy_gift_id` i `legacy_course_code`; modificar només `regal.CCURS` després de la compra és tampering i es rebutja.

## 4. Ordre de mutació corregit

```text
POST browser
  -> BEGIN legacy tx
  -> SELECT regal FOR UPDATE
  -> FACT_REL > 0
  -> validar CCURS / hores / edició
  -> get-or-create ID_INSC
  -> COMMIT legacy
  -> POST/HMAC SIF
  -> resolver context immutable
  -> stage/reserve
  -> COMPENSATION_ALLOCATION
  -> CONSUME
  -> reconciliar regal.USAT
  -> materialitzar/reutilitzar 6 outbox
  -> claim/SMTP/complete
  -> confirmació
```

Una destinació incompatible ja no deixa una nova matrícula compromesa abans del rebuig.

## 5. Estat per capa

| Capa | Documentat | Implementat | Verificat |
| --- | --- | --- | --- |
| Fitxa funcional específica | Sí, v1.6 | N/A | Reconciliada 04/10 |
| PHP/JS web + routing | Sí | Sí | CI head pendent |
| POST codi/PII | Sí | Sí | Boundary; CI head pendent |
| Codi bearer CSPRNG | Sí | Sí | Boundary; CI head pendent |
| Snapshot GIFT immutable | Sí | Sí | Integració; CI head pendent |
| Regal genèric per hores | Sí | Sí | Match/mismatch; CI head pendent |
| Swap de regal concret same-hours | Sí | Sí | Match/mismatch; CI head pendent |
| Guard curs/hores pre-commit | Sí | Sí | Boundary; CI head pendent |
| Routing públic canònic | Sí | `/bescanvia-regal` → `pagina_bescanvia.php` | Boundary; CI head pendent |
| Confirmació segura | Sí | AES-256-GCM + fragment + POST + no-store/no-referrer | Boundary; CI head pendent |
| Staging/redeem/compensació | Sí | Sí | Baseline PR #115 + regressió pendent |
| Replay/resposta perduda | Sí | Sí | Baseline PR #115 |
| Concurrència multiprocés | Sí | Sí | Baseline PR #115; fixture revalidat |
| Reconciliació `regal.USAT` | Sí | Sí | Baseline PR #115 |
| Sis correus idempotents | Sí | Sí | Baseline PR #115 |
| Preproducció real/SMTP | Sí | Scripts sí | [ENV] pendent |
| Throttle/rate-limit públic | Sí com a gap | No acreditat al repo | [ENV/SECURITY] pendent |
| Preu de catàleg del curs triat vs FACE_VALUE | Gap documentat | No contrastat autoritativament | [POLICY/DATA] |
| Diferències de valor / romanent / devolució | Sí | Fail-closed | [POLICY] |

## 6. Invariants de tancament de codi

- una compra pagada UC-017;
- un entitlement GIFT;
- una inscripció de destí;
- una `COMPENSATION_ALLOCATION`;
- un consum;
- zero `CHARGE` addicionals;
- zero factures addicionals;
- replay equivalent reutilitza resultat;
- destí diferent en concurrència entra en conflicte;
- codi/PII no apareixen a query string;
- compra immutable i `regal.CCURS` no poden divergir;
- curs no elegible no es commita al legacy;
- la ruta pública no depèn de fitxers inexistents;
- una incidència de notificació no altera ni oculta el resultat ja confirmat del bescanvi;
- el token de confirmació és autenticat íntegrament i no viatja al servidor/Referer com a part de la URL;
- la confirmació no torna a exposar el codi regal i només mostra el correu emmascarat.

## 7. Documents reconciliats

- `documentacio/06-fitxes-funcionals/uc-018.md`;
- `uc-018-bescanviar-regal.md`;
- `uc-018-classes-actual-final.md`;
- `uc-018-sequencies-actual-final.md`;
- `uc-018-activitats-pagines-bescanvi-regal-actual-final.md`;
- `04-auditoria-detallada-uc-018-bescanviar-regal-2026-09-30.md` (històric);
- `05-proves-pendents-uc-018-implementacio.md`;
- `10-tancament-auditoria-uc-018-2026-10-02.md` (baseline);
- `11-revalidacio-auditoria-uc-018-2026-10-03.md` (passada anterior);
- `12-inventari-codi-php-js-uc-018-2026-10-03.md`;
- aquest document 13;
- `14-execucio-selectiva-uc-018-sif-test-preproduccio.md`.

## 8. Pendents reals

### [CI]
El head de PR #160 ha d'acreditar els patches 03–04/10. No es reutilitza el 858/0 del 02/10 com a prova dels canvis nous. S'ha creat un gate selectiu UC-018 (`run-uc018-tests.php` + `uc018-sif-checks.yml`) per separar regressions pròpies del cas de fallades transversals.

### [ENV]
- executar `preflight-gift-redemption.php` en preproducció;
- executar `verify-gift-redemption-preproduction.php --execute` amb dades controlades;
- conservar evidència de transport SMTP;
- acreditar throttle/rate-limit a WAF/web server o implementar-lo;
- definir/implementar via operativa de reconciliació per outbox `FAILED/SENDING` (UC-058), sense reenviament automàtic ambigu;
- validar que `dies-inscriu-cursos` no tingui regles simultànies contradictòries per hores/codi o aprovar-ne una precedència explícita.

### [POLICY]
Cal decidir o acreditar si `mateixes hores` implica sempre `mateix preu de catàleg`. Romanent/diferència de valor, devolució, consum parcial, caducitat/pròrroga i variants econòmiques continuen fora del flux base i fail-closed.

## 9. Veredicte del tall

**DOCUMENTAT:** sí.  
**IMPLEMENTAT:** sí, inclosos routing públic, guard pre-commit i confirmació AEAD.  
**VERIFICAT:** baseline antiga sí; head 04/10 pendent de CI.  
**PENDENT:** CI del head + gates ENV/SECURITY + equivalència preu-catàleg/hores [POLICY/DATA] + variants POLICY.

No marcar `CI_GREEN` ni `AUDIT_CLOSED_REVALIDATED` fins disposar del resultat del head actual.
