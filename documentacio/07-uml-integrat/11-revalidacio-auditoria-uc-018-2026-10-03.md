# UC-018 · Revalidació exhaustiva de l'auditoria · 03/10/2026

**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Base revalidada:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Branca de correcció:** `audit/uc-018-revalidacio-2026-10-03`  
**Tancament anterior:** PR #115 + PR #117  
**Estat en aquesta branca:** `AUDIT_REOPENED_AND_PATCHED / CI_REVALIDATION_PENDING / ENVIRONMENT_GO_PENDING`

## 1. Motiu de la revalidació

El tancament del 02/10 acreditava correctament el nucli SIF del bescanvi a valor exacte, però una revisió completa de la superfície web real ha detectat dues classes de divergència:

1. documentació integrada i apartats històrics que encara descrivien UC-018 com a disseny/no implementat;
2. frontera navegador→legacy no coberta per les proves anteriors, amb codi regal i dades personals enviats per query string.

A més, `pagina_bescanvia.php` referenciava `mostrarBescanvia_prova.min.js`, fitxer absent del repositori, mentre que el bundle rastrejable era `mostrarBescanvia.min.js`.

## 2. Resultat de la comparació amb main

Entre el merge documental UC-018 del PR #117 i `main@b0e8ff...` hi ha dos commits posteriors centrats en UC-014/Redsys. No modifiquen els executables UC-018 consolidats al PR #115.

Per tant:

- el nucli SIF verificat al snapshot del 02/10 continua sent la base executable;
- els canvis d'aquesta revalidació afecten la frontera pública legacy, les proves boundary i la reconciliació documental.

## 3. Troballes noves

| ID | Troballa | Estat |
| --- | --- | --- |
| UC18-RV-001 | `uc-018-bescanviar-regal.md` marcava serveis reals com a DISSENY/no implementats i acabava en NO-GO. | CORREGIT |
| UC18-RV-002 | Activitats i fitxa conservaven el replay antic amb retorn abans de reentrar al SIF. | CORREGIT |
| UC18-RV-003 | `mostrarBescanvia.min.js` enviava `codiRegal`, DNI i resta de dades de matrícula en URL amb GET. | PATCH APLICAT |
| UC18-RV-004 | `codiRegalValid.php`, `buscarCursRegalat.php`, `inscripcioDuplicada.php` i `enviarInscripcioBescanvia.php` acceptaven `$_GET`. | PATCH APLICAT |
| UC18-RV-005 | La resposta pública distingia codi inexistent, pendent i utilitzat, facilitant enumeració d'estat. | PATCH APLICAT |
| UC18-RV-006 | `buscarCursRegalat.php` es podia invocar directament sense repetir la validació de bescanviabilitat. | PATCH APLICAT |
| UC18-RV-007 | El writer podia arribar a materialitzar legacy sense bloquejar explícitament `FACT_REL <= 0` abans del commit. | PATCH APLICAT |
| UC18-RV-008 | `pagina_bescanvia.php` carregava `mostrarBescanvia_prova.min.js`, absent del repositori. | PATCH APLICAT |
| UC18-RV-009 | La suite comprovava POST/HMAC només al client intern servidor→SIF, no al navegador→legacy. | PROVA AFEGIDA |
| UC18-RV-010 | Les dues consultes legacy de codi usaven `LIKE ?`, permetent semàntica wildcard en crida directa. | PATCH APLICAT + PROVA |
| UC18-RV-011 | UC-017 generava el secret bearer amb `base_convert(uniqid(), 16, 36)`, predictible respecte del temps. | PATCH CSPRNG + PROVA |
| UC18-RV-012 | No s'ha localitzat throttle/rate-limit específic per intents de codi al repo. | ENV/SECURITY PENDENT |

## 4. Correccions de codi aplicades

### 4.1. Navegador → legacy

Les quatre fronteres que transporten secret o PII passen a POST amb cos de petició:

- `codiRegalValid.php`;
- `buscarCursRegalat.php`;
- `inscripcioDuplicada.php`;
- `enviarInscripcioBescanvia.php`.

El JS ja no construeix URLs amb `codiRegal` ni `dni`.

### 4.2. Endpoints legacy

Els tres endpoints exclusius UC-018 (`codiRegalValid.php`, `buscarCursRegalat.php`, `enviarInscripcioBescanvia.php`) rebutgen mètodes diferents de POST amb 405, llegeixen `$_POST` i no llegeixen `$_GET`. `inscripcioDuplicada.php` és transversal: UC-018 l'invoca per POST, però conserva fallback GET per compatibilitat amb altres fluxos legacy.

A més:

- `buscarCursRegalat.php` revalida que el codi sigui bescanviable abans de revelar el curs;
- `enviarInscripcioBescanvia.php` bloqueja el writer si `FACT_REL <= 0`.

### 4.3. Enumeració de codi

`BescanviaRegal::codiRegalValid()` conserva la decisió al servidor però retorna un missatge públic únic quan el codi no és utilitzable. No retorna el codi introduït ni diferencia públicament inexistent/pendent/consumit.

### 4.4. Bundle executable

`pagina_bescanvia.php` deixa de referenciar el bundle absent `mostrarBescanvia_prova.min.js` i carrega `mostrarBescanvia.min.js?ver=6.0`.

## 5. Prova de regressió nova

`GiftRedemptionWebClientBoundaryTest` cobreix ara també la frontera pública:

- bundle rastrejable referenciat per la pàgina;
- quatre AJAX sensibles via POST;
- `data: {}` en lloc de query string;
- absència de `?codiRegal=`, `&codiRegal=`, `?dni=` i `&dni=`;
- tres endpoints UC-018 POST-only i sense `$_GET`, més comprovació específica que el duplicat compartit rep POST des del bundle UC-018;
- revalidació server-side del lookup de curs;
- bloqueig del writer per regal no pagat;
- resposta pública neutra.

Aquesta prova complementa, no substitueix, les proves ja existents de POST/HMAC servidor→SIF, idempotència, concurrència, recovery, outbox i E2E.

## 6. Estat per capa

| Capa | Documentat | Implementat | Verificat abans del patch | Revalidació 03/10 |
| --- | --- | --- | --- | --- |
| Nucli GIFT/SIF | Sí | Sí | CI 858/0 del PR #115 | Sense canvi executable |
| Staging/redeem/reconciliació | Sí | Sí | Integració/E2E | Sense canvi executable |
| Concurrència/recovery | Sí | Sí | Verificat | Sense canvi executable |
| Outbox/correus | Sí | Sí | Verificat | Sense canvi executable |
| Navegador→legacy | Sí, però incorrectament descrit | Patch aplicat | No cobert | PROVA AFEGIDA; CI PENDENT |
| Bundle de pàgina | Parcial | Patch aplicat | No cobert | PROVA AFEGIDA; CI PENDENT |
| Diferències de preu | Sí | Fail-closed | Verificat | POLICY PENDENT |
| Preproducció real | Sí | Scripts disponibles | Boundary automatitzat | ENV PENDENT |

## 7. Replay vigent

El replay correcte **no retorna abans del SIF** quan `regal.USAT` ja està reconciliat.

El writer:

1. bloqueja el regal;
2. reutilitza la mateixa `ID_INSC` si és coherent;
3. fa commit de la part legacy;
4. torna a executar `redeemCommittedEnrollment()`;
5. el SIF reutilitza operació, moviment, consum i reconciliació;
6. reconstrueix o reutilitza l'outbox si la resposta anterior es va perdre;
7. els correus només s'envien després de claim individual.

Aquesta és la semàntica que han de mostrar fitxa, seqüències i activitats.

## 8. Pendent real després d'aquesta passada

### [CI]

Cal que el CI de la branca/PR acrediti el patch nou de frontera pública.

### [ENV]

Continuen pendents d'entorn:

- `preflight-gift-redemption.php` en preproducció real;
- `verify-gift-redemption-preproduction.php --execute`;
- evidència de les dues BD i del transport SMTP desplegat;
- secrets/HMAC/TLS/rols configurats.

### [POLICY]

Continuen fora del flux base i fail-closed fins a decisió explícita:

- regal inferior o superior al curs;
- romanent/saldo;
- cobrament complementari;
- devolució;
- consum parcial;
- transferibilitat/caducitat especial quan no estigui ja definida per UC-18a.

## 9. Criteri de re-tancament

UC-018 es pot tornar a marcar com `AUDIT_CLOSED + CODE_COMPLETE + CI_GREEN` quan el CI del patch públic sigui verd. El gate `ENVIRONMENT_GO_PENDING` continuarà separat fins a l'execució controlada de preproducció.
