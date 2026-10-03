# UC-008 · Revalidació exhaustiva contra main · 03/10/2026

**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Branca de revisió:** `audit/uc-008-revalidacio-2026-10-03`  
**Main revalidat:** `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Baseline de tancament anterior:** merge PR #116 `549d7ef9280df3cd5249340e3785a4bf23a14b78`  
**Diferència entre baseline i main:** 44 commits.

## 1. Conclusió

L'UC-008 continua **AUDIT_CLOSED + CODE_COMPLETE**. No s'ha detectat cap fitxa, diagrama ACTUAL/FINAL, PHP, JS, API, repositori o prova específica d'incidències absent.

El `main` actual ja no es pot descriure globalment com a `CI_GREEN`: el HEAD del PR #118 va executar la suite SIF amb **917 passed / 6 failed**. Tanmateix, les proves explícites d'incidències i del worker Redsys que deriva errors a incidència continuen passant. Les sis fallades observades són cinc contractes PACK i una expectativa antiga de `payload_hash` al test de signatura Redsys.

Aquesta branca corregeix aquesta expectativa Redsys perquè el fixture actual inclou `Ds_TransactionType`; el SHA-256 de la cadena `Ds_MerchantParameters` actual és `b585ea0d53cc71fc58e366ccde647457220e9e7732734c0b904a014f589813ff`. No es modifica la lògica de validació ni el lifecycle UC-008.

## 2. Paquet documental comprovat

Els fitxers següents existeixen a `main` i, abans d'aquesta revalidació, mantenien exactament el mateix blob SHA que al merge del PR #116:

| Peça | Fitxer | Blob SHA verificat |
| --- | --- | --- |
| Fitxa funcional | `documentacio/06-fitxes-funcionals/uc-008.md` | `af56132fd3cb821ae9fa540d45365768b7c84ede` |
| Fitxa/UML integrada | `documentacio/07-uml-integrat/uc-008-gestionar-incidencia-sif.md` | `9e515f5074dc670894a8fbe65c4563de901a4a8a` |
| Classes ACTUAL/FINAL | `documentacio/07-uml-integrat/uc-008-classes-actual-final.md` | `e395c94c3520bf55ba2e97ab93f138e149cda1df` |
| Seqüències ACTUAL/FINAL | `documentacio/07-uml-integrat/uc-008-sequencies-actual-final.md` | `6216720dc19b2c958b84aad0a17c9ecf6ef586e1` |
| Activitats per pàgina/apartat | `documentacio/07-uml-integrat/uc-008-activitats-pagines-incidencies-actual-final.md` | `80eb1cc06b8be3afbc3634f93f608c461833da11` |
| Auditoria detallada | `documentacio/07-uml-integrat/04b-auditoria-detallada-uc-008-2026-09-30.md` | `c60af5adc3ef03e13df1e0b870a5ea1e54646c0d` |
| Proves/acceptació | `documentacio/07-uml-integrat/05-proves-pendents-uc-008-implementacio.md` | `041f957aa38d4fea14f889680866b1c3378f36f0` |
| Acta de tancament | `documentacio/07-uml-integrat/09-tancament-auditoria-uc-008-2026-10-02.md` | `44977d8a2629ce42527b7c369f2de304df8bdde4` |

Per tant, **sí que disposem de totes les peces principals** que demana l'auditoria: fitxa, classes, seqüències i activitats ACTUAL/FINAL, auditoria, proves i tancament.

## 3. Cobertura de diagrames

### Classes

La fitxa de classes manté:

- `CL-008-ACTUAL` · backend executable;
- inventari de classes actuals;
- `CL-008-FINAL` · arquitectura objectiu reconciliada;
- diferència ACTUAL/FINAL;
- frontera funcional i pendent d'entorn.

### Seqüències

Es mantenen documentades:

1. Redsys → incidència atòmica;
2. AEAT → integritat/dead-letter;
3. acció manual autenticada;
4. reintent després d'una transició;
5. concurrència idempotent;
6. panell llistat/detall;
7. reparació explícita i tancament;
8. resum intranet read-only.

### Activitats per superfície

La cobertura RM-037 continua incloent:

- `P-INC-01` llistat;
- `P-INC-02` detall;
- `P-INC-03A` assignació/triage;
- `P-INC-03B` evidència;
- `P-INC-03C` resolució/dismissal;
- `P-INC-03D` reobertura;
- `A-INC-05` obertura automàtica;
- `A-INC-06` derivació a reparació;
- `P-INC-04` resum VERI*FACTU de la intranet.

No s'ha detectat cap pàgina o apartat UC-008 executable sense activitat ACTUAL/FINAL corresponent.

## 4. Codi PHP/JS revalidat

El nucli següent és byte-a-byte igual al baseline de tancament:

| Component | Blob SHA |
| --- | --- |
| `sif/src/Service/IncidentLifecycleService.php` | `1def8ffda4662433af23926052ef1bdc07a8b629` |
| `sif/src/Repository/IncidentRepository.php` | `f10abc38d1048dfb19e360e2499a6b98331b956f` |
| `sif/src/Repository/IncidentActionRepository.php` | `afab9cae71f585526c7c27835f4a0385c9b9d786` |
| `sif/public/api/incidents/manage.php` | `0cdb49140f8eaa9ae5c45febd11a4bc3bde55773` |
| `sif/public/sif/incidencies/index.php` | `c85255ee5df1937e11043533c868e9b387939c6b` |
| `sif/public/sif/incidencies/app.js` | `fbc5a23ef80e5dfee913f16beeb5091ce6820e9f` |
| `sif/src/Service/RedsysCallbackWorker.php` | `122f305eec4303619ad53d0a647b07c201e9b409` |
| `sif/tests/Integration/RedsysCallbackWorkerTest.php` | `d53c1cfad7f5cf913434879b5bc75b84f179fd40` |

El lifecycle continua exposant `list`, `summary`, `view`, `open`, `assign`, `addEvidence`, `resolve`, `dismiss` i `reopen`, amb permisos read/manage fail-closed, transaccions, idempotència, bloqueig `FOR UPDATE`, journal append-only i rol gestor efectiu.

La intranet continua sent frontera **read-only** i el panell SIF és la superfície de mutació, amb sessió pròpia, CSRF i handoff autenticat.

## 5. Canvis posteriors que comparteixen frontera amb UC-008

Entre PR #116 i el `main` actual s'han modificat peces Redsys compartides, entre elles:

- `RedsysCallbackService`;
- `RedsysSignatureValidator`;
- `RedsysNotificationRepository`;
- workflows SIF.

En canvi, `RedsysCallbackWorker` i la seva suite específica d'incidències no han canviat.

La suite actual mostra PASS per:

- seguretat API interna d'incidències;
- autenticació del launch del panell;
- preflight del panell;
- E2E read-only;
- rollback si falla la inserció de la incidència;
- redacció de dades sensibles;
- conversió a incidència després del cinquè error tècnic;
- callback Redsys denegat sense efectes fiscals/econòmics.

Això manté acreditada la frontera UC-008 dins del `main` actual.

## 6. Estat de CI actual

Runs del HEAD del PR #118:

- `37060976805` · **SIF PHP MySQL tests** → failure;
- `37060976877` · **SIF checks** → failure;
- tots dos executen la suite amb **917 passed / 6 failed**.

Les sis fallades són:

1. `PackEnrollmentIdempotencyBoundaryTest::testEnrollmentReusesSingleAuthoritativePriceSnapshot`;
2. `PackEnrollmentTransportBoundaryTest::testPackEnrollmentMutationUsesPostAndDoesNotReadGetParameters`;
3. `PackPaymentPrivacyBoundaryTest::testPackRedsysPayloadUsesNameNotDniAndOmitsEmailFromReturnUrls`;
4. `PackPaymentPrivacyBoundaryTest::testPaymentResponsePagesTreatEmailAsOptionalEscapedHint`;
5. `PackPublicEnrollmentBoundaryTest::testPublicPackEnrollmentHasSameSiteRequestBoundaryBeforeInputProcessing`;
6. `RedsysSignatureValidatorTest::testValidNotificationDecodesAndNormalizesSignedPayload`.

Les cinc primeres corresponen a PACK/UC-015. La sisena és un assert de fixture Redsys desfasat i es corregeix en aquesta branca.

Per això l'estat correcte és:

- **UC-008 regression:** PASS dins la suite actual;
- **global SIF suite:** RED fins corregir els cinc contractes PACK restants;
- **baseline 844/0 del 02/10:** evidència històrica vàlida, però no s'ha d'usar per afirmar que el `main` actual és globalment verd.

## 7. Classificació final

| Dimensió | Estat 03/10/2026 |
| --- | --- |
| Documentat | **COMPLET** |
| Implementat | **COMPLET dins l'abast UC-008** |
| Verificat | **UC-008 REGRESSION PASS al main actual; suite global 917/6** |
| Pendent UC-008 | **Acceptació real de preproducció/producció** |
| Pendent extern a UC-008 | **5 fallades PACK de la suite global** |

## 8. Acceptació d'entorn que continua pendent

No es fabriquen evidències. Per declarar `ENVIRONMENT_CLOSED` continuen faltant:

1. `uc-008-preproduction-evidence.json`;
2. `uc-008-menu-evidence.json` amb `ALREADY_PRESENT`;
3. `uc-008-manager-e2e-evidence.json`;
4. `uc-008-closure-validation.json` amb `ok=true`.

Aquests punts necessiten preproducció, rols/secrets reals i la BD real del menú.

## 9. Veredicte

**L'auditoria UC-008 continua tancada.** No es reobre codi funcional ni UML. Aquesta revalidació actualitza la traçabilitat al `main` actual, corregeix un test compartit Redsys desfasat i separa de forma explícita:

- la salut específica UC-008, que continua verificada;
- la salut global del repositori, actualment vermella per cinc fallades PACK alienes al cas;
- l'acceptació operativa d'entorn, encara pendent.
