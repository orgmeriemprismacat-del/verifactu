# UC-008 · Inventari de codi PHP/JS ACTUAL/FINAL · 03/10/2026

**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Branca d'auditoria:** `audit/uc-008-revalidacio-2026-10-03`  
**Main de referència:** `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`

## 1. Objectiu

Aquest inventari respon de forma explícita a la pregunta de si l'UC-008 disposa del **codi real** que correspon a la fitxa funcional i als diagrames ACTUAL/FINAL.

**Conclusió:** sí. Les superfícies i serveis necessaris per a l'abast documentat existeixen al repositori. No s'ha detectat cap fitxer PHP/JS obligatori absent per completar el lifecycle UC-008. El que continua pendent és desplegament/acceptació d'entorn, no implementació.

## 2. Backend SIF · lifecycle i persistència

| Peça | Fitxer real | Blob SHA verificat | Estat |
| --- | --- | --- | --- |
| Servei lifecycle | `sif/src/Service/IncidentLifecycleService.php` | `1def8ffda4662433af23926052ef1bdc07a8b629` | IMPLEMENTAT |
| Capçalera incidència | `sif/src/Repository/IncidentRepository.php` | `f10abc38d1048dfb19e360e2499a6b98331b956f` | IMPLEMENTAT |
| Journal append-only | `sif/src/Repository/IncidentActionRepository.php` | `afab9cae71f585526c7c27835f4a0385c9b9d786` | IMPLEMENTAT |
| API interna | `sif/public/api/incidents/manage.php` | `0cdb49140f8eaa9ae5c45febd11a4bc3bde55773` | IMPLEMENTAT |
| Migració lifecycle | `sif/database/migrations/2026_09_29_000010_add_incident_lifecycle.sql` | `f46960e3f1e831b1bda3584bd7b0ccf26fda1703` | IMPLEMENTAT |

El servei real exposa:

- `list()`;
- `summary()`;
- `view()`;
- `open()`;
- `assign()`;
- `addEvidence()`;
- `resolve()`;
- `dismiss()`;
- `reopen()`.

La implementació aplica rols de lectura/gestió fail-closed, transaccions, idempotència, correlació, `FOR UPDATE`, recuperació de cursa per duplicate key, evidència obligatòria per `RESOLVE`, criteri de tancament i journal amb rol gestor efectiu.

## 3. Panell oficial SIF · P-INC-01/P-INC-02/P-INC-03

| Superfície | Fitxer real | Blob SHA | Estat |
| --- | --- | --- | --- |
| Entrada/HTML del panell | `sif/public/sif/incidencies/index.php` | `c85255ee5df1937e11043533c868e9b387939c6b` | IMPLEMENTAT |
| Endpoint de sessió/accions | `sif/public/sif/incidencies/actions.php` | `a3b3d80685a3ce9562b38441fe6f266640d201f7` | IMPLEMENTAT |
| JS de llistat/detall/lifecycle | `sif/public/sif/incidencies/app.js` | `fbc5a23ef80e5dfee913f16beeb5091ce6820e9f` | IMPLEMENTAT |
| Sessió del panell | `sif/src/Http/IncidentPanelSession.php` | `d1a3ecf6c3f222f3649f7c9bc11379cf7e768ba4` | IMPLEMENTAT |
| Autenticació del launch | `sif/src/Service/PanelLaunchAuthenticator.php` | `dcc074922f12ba00da6b37c6167adfebf9aba490` | IMPLEMENTAT |

Correspondència amb activitats:

- **P-INC-01 · llistat:** `index.php + app.js + actions.php → list/summary`;
- **P-INC-02 · detall:** `app.js + actions.php → view`, timeline de `sif_incident_action`;
- **P-INC-03A · assignació:** `app.js → assign → IncidentLifecycleService::assign()`;
- **P-INC-03B · evidència:** `app.js → evidence → addEvidence()`;
- **P-INC-03C · resolve/dismiss:** formulari de tancament → `resolve()/dismiss()`;
- **P-INC-03D · reopen:** formulari de reobertura → `reopen()`.

El JS genera identificadors d'operació amb Web Crypto, crea claus idempotents per mutació i no delega la decisió d'autorització al navegador.

## 4. Intranet VERI*FACTU · P-INC-04

| Peça | Fitxer real | Blob SHA | Estat |
| --- | --- | --- | --- |
| Pantalla resum read-only | `codi-drive/intranet-actual/sif-verifactu.php` | `41c45daf9cbc3d1eb0dd69f17c94687b7b5f2ebb` | IMPLEMENTAT |
| JS resum/launch | `codi-drive/intranet-actual/js/sif-verifactu.js` | `0fbf538e7a0d275fbea1eea1126bab186749fe33` | IMPLEMENTAT |
| Client intern HMAC | `codi-drive/intranet-actual/SifInternalIncidentClient.php` | `36e0a8a1fd40394de981ed776982c4a3dcd2fdde` | IMPLEMENTAT |
| AJAX read-only | `codi-drive/intranet-actual/ajax/sif/sifIncidents.php` | `87aa720f9d4b8aab102a29baacdc75cf2732d20a` | IMPLEMENTAT |
| AJAX launch panell | `codi-drive/intranet-actual/ajax/sif/sifPanelLaunch.php` | `6dfcfdc3875b2d339c5597a8c799b311c0356a1c` | IMPLEMENTAT |
| Preflight menú BD | `codi-drive/intranet-actual/preflight-sif-verifactu-menu.php` | `5250c0948670c9b1d6ea9f7c63a0e22ce04f4ea0` | IMPLEMENTAT |

La frontera està ben separada:

- intranet → **consulta/resum + launch**;
- SIF → **mutacions del lifecycle**;
- HMAC inclou actor, rols, timestamp, request id i hash del body;
- HTTP insegur només és admissible explícitament per localhost de proves;
- no hi ha mutació UC-008 directa des de la pàgina resum de la intranet.

## 5. Obertura automàtica · A-INC-05

| Origen | Fitxer principal | Blob SHA verificat / estat |
| --- | --- | --- |
| Redsys retries/conflictes | `sif/src/Service/RedsysCallbackWorker.php` | `122f305eec4303619ad53d0a647b07c201e9b409` · IMPLEMENTAT |
| AEAT/review/dead-letter | `sif/src/Service/FiscalQueueProcessor.php` | IMPLEMENTAT i cobert per la documentació/CI UC-008/UC-009 |

`RedsysCallbackWorker` continua byte-a-byte igual al baseline del PR #116 i les proves actuals confirmen:

- retry tècnic;
- rollback si falla la inserció de la incidència;
- redacció de dades sensibles;
- conversió a `INCIDENT` després d'esgotar intents.

Els canvis Redsys posteriors al PR #116 afecten peces compartides de validació/callback, però no han substituït ni eliminat el mecanisme UC-008 d'obertura d'incidència.

## 6. Reparació · A-INC-06

UC-008 **no implementa un `RepairRouter` genèric**. Aquesta absència és deliberada i coherent amb la frontera funcional:

- la incidència conserva recurs, correlació, evidència i estat;
- el panell ofereix deep-links cap al cas responsable;
- la reparació fiscal/econòmica real correspon a l'UC específic;
- UC-008 no repeteix automàticament cobraments ni modifica factures emeses.

Per tant, `A-INC-06 FINAL` és una derivació traçable, no una mutació genèrica dins del lifecycle d'incidències.

## 7. Tooling de preproducció i tancament

| Eina | Fitxer real | Blob SHA | Estat |
| --- | --- | --- | --- |
| Preflight panell | `sif/scripts/preflight-incidents-panel.php` | `f981024741b019415f6e00e66baad04e158cdd1c` | IMPLEMENTAT |
| E2E read-only | `sif/scripts/e2e-incidents-panel.php` | `2049c31027c9a7f2079a7348c436628a4163712f` | IMPLEMENTAT |
| Verificador preproducció | `sif/scripts/verify-incidents-panel-preproduction.php` | `2019751f1fb5eb3107592f52fcf0da91a80d10f2` | IMPLEMENTAT |
| Preparador gestor | `sif/scripts/prepare-incident-manager-e2e.php` | `43ee53b4c032844c6f09efa12cb860668a37fdcb` | IMPLEMENTAT |
| Verificador evidència gestor | `sif/scripts/verify-incident-manager-evidence.php` | `74c4610707c9b5cf3450484cae205064cad3dccb` | IMPLEMENTAT |
| Gate final d'evidències | `sif/scripts/validate-uc008-evidence.php` | `bb1182a561c88fd68e04c3704f6e981fb18c8ab3` | IMPLEMENTAT |

Aquest tooling està implementat; el pendent és executar-lo **contra preproducció real** amb rols/secrets/BD reals i conservar-ne les sortides.

## 8. Proves específiques localitzades

| Suite | Fitxer | Blob SHA | Cobertura |
| --- | --- | --- | --- |
| Lifecycle | `sif/tests/Integration/IncidentLifecycleTest.php` | `2ad4d13c84d70136216778c46091bdeda220b648` | transicions, idempotència, evidència, rols |
| Concurrència | `sif/tests/Integration/IncidentConcurrencyTest.php` | `497424fd80fb90789c4ab38adf2f5a93ef3b29e4` | obertura/assignació concurrent |
| API/HMAC | `sif/tests/Integration/IncidentInternalApiSecurityTest.php` | `d23237a9abd40863cd052395cddcbb3412e288ec` | signatura, timestamp, replay, permisos |
| UI contract | `sif/tests/Integration/IncidentPanelUiContractTest.php` | `bfa96156324950c21fce195e346467cd47c64f1a` | HTML/JS/API del panell |
| Frontera intranet | `sif/tests/Integration/IncidentPanelIntranetBoundaryTest.php` | `3cfd8caa33ab293a6e207b9c804028746f29e8ff` | read-only/launch |
| Launch auth | `sif/tests/Integration/IncidentPanelLaunchAuthenticatorTest.php` | `fca5155b56577c9e61ce7ab949b7a572c696f09e` | HMAC launch |
| Preflight | `sif/tests/Integration/IncidentPanelPreflightScriptTest.php` | `7b23b4bffe03b7b593f82a53a35193fe19babad8` | rols, secrets, superfície |
| E2E tècnic | `sif/tests/Integration/IncidentPanelE2eScriptTest.php` | `3e37ab90f717dddb39a62dd645cc5d2078db3654` | fail-closed + read-only |
| Gate evidències | `sif/tests/Integration/IncidentPanelEvidenceValidationScriptTest.php` | `ddc3d208beab5ca2906c142a8e8bb821f493073a` | closure JSON |
| Verificació preprod | `sif/tests/Integration/IncidentPanelPreproductionVerificationScriptTest.php` | `3ca6c88d6ea2463404bd7857a0b5ccbcdaa3278d` | bundle de preproducció |

A més, `RedsysCallbackWorkerTest.php` cobreix la derivació Redsys→incidència i la suite AEAT cobreix la derivació de cues/review/dead-letter.

## 9. Matriu DOCUMENTAT / IMPLEMENTAT / VERIFICAT / PENDENT

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Model i lifecycle | Sí | Sí | Sí, CI | — |
| Idempotència/concurrència | Sí | Sí | Sí, MySQL/CI | — |
| API interna HMAC | Sí | Sí | Sí, CI | secrets reals d'entorn |
| Panell SIF | Sí | Sí | contractes/preflight/E2E tècnic | navegador real preprod |
| Intranet read-only | Sí | Sí | contractes/CI | menú BD real |
| Redsys→incident | Sí | Sí | tests actuals PASS | observabilitat real preprod |
| AEAT→incident | Sí | Sí | suite transversal | evidència real preprod |
| Gestor ASSIGN→EVIDENCE→RESOLVE | Sí | Sí | preparador/verificador CI | execució humana/real |
| Gate final d'evidències | Sí | Sí | tests | quatre JSON reals |
| Reparació genèrica | N/A deliberadament | No | frontera verificada | es deriva a UC responsable |

## 10. Veredicte

No falta cap fitxa de codi imprescindible ni cap superfície executable UC-008 identificada per l'auditoria.

**ACTUAL:** coincideix amb el codi PHP/JS real localitzat.  
**FINAL:** difereix únicament en activació/configuració i evidència d'entorn real, no en una arquitectura pendent d'implementar.

Vegeu també:

- [fitxa funcional](../06-fitxes-funcionals/uc-008.md);
- [classes ACTUAL/FINAL](uc-008-classes-actual-final.md);
- [seqüències ACTUAL/FINAL](uc-008-sequencies-actual-final.md);
- [activitats ACTUAL/FINAL](uc-008-activitats-pagines-incidencies-actual-final.md);
- [revalidació contra main](uc-008-revalidacio-main-2026-10-03.md).
