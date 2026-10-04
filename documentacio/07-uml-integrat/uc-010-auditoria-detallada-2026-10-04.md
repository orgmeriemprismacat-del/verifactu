# UC-010 · Auditoria detallada reconciliada · Configuració i versió del SIF

**Data:** 2026-10-04  
**Branca:** `audit/uc-010-reconciliacio-2026-10-04`  
**Antecedent:** PR #139 (draft, no mergejat i divergent respecte de `main`); aquesta auditoria el substitueix com a candidat d'integració.  
**Baseline auditat:** `main` actual, SHA inicial `2bd2a751832fc3f767b1e250b955922145a5577a`  
**Objectiu canònic:** saber quina versió/configuració/esquema/declaració correspon al SIF que realment està executant-se i impedir que una intenció de desplegament es confongui amb una activació acreditada.

## 1. Resultat executiu

Abans de l'auditoria UC-010 **no estava tancat**. Hi havia dues peces documentals i persistència parcial, però no un circuit executable:

- existia la fitxa funcional `documentacio/06-fitxes-funcionals/uc-010.md`;
- existia la fitxa UML integrada `documentacio/07-uml-integrat/uc-010-gestionar-configuracio-versio.md`;
- existien les taules `sif_version`, `sif_declaration` i `backup_restore_evidence`;
- existia `MigrationRunner`, que valida el ledger de migracions;
- existia un catàleg viu de configuració a `sif/config/README.md`;
- **no** existien repositoris UC-010, servei de domini, endpoint/panell, JavaScript, manifest de release, activació serialitzada, journal d'activacions ni proves UC-010;
- **no** existien documents separats de classes, seqüències i activitats ACTUAL/FINAL.

La branca d'auditoria incorpora aquestes peces. El codi queda dissenyat perquè **activar no sigui desplegar**: l'activació només es pot registrar quan el runtime que ja està en servei coincideix amb la candidata observada.

## 2. Inventari exhaustiu

| Àrea | Baseline `main` | Branca UC-010 | Estat |
| --- | --- | --- | --- |
| Fitxa funcional | Sí, però genèrica/contaminada per boilerplate fiscal | Reescrita específicament | DOCUMENTAT |
| UML integrada | Sí, tot el servei era `DISSENY` | Actualitzada i enllaçada amb UML detallada | DOCUMENTAT |
| Classes ACTUAL/FINAL | No | `uc-010-classes-actual-final.md` | DOCUMENTAT |
| Seqüències ACTUAL/FINAL | No | `uc-010-sequencies-actual-final.md` | DOCUMENTAT |
| Activitats per pàgina/apartat | No | `uc-010-activitats-pagines-actual-final.md` | DOCUMENTAT |
| `sif_version` / `sif_declaration` | Sí | Conservades + idempotència | IMPLEMENTAT |
| Singleton versió activa | No | `sif_version_state` | IMPLEMENTAT |
| Journal activacions | No | `sif_version_activation` | IMPLEMENTAT |
| Repositori candidata | No | `SifVersionRepository` | IMPLEMENTAT |
| Repositori declaració | No | `SifDeclarationRepository` | IMPLEMENTAT |
| Evidència backup read-only | Taula sí, repositori no | `BackupRestoreEvidenceRepository` | IMPLEMENTAT |
| Auditoria UC-010 | Taula general sí, writer específic no | `SifAuditEventRepository` + `OperationalEventRepository` | IMPLEMENTAT |
| Manifest bytes release | No | `build-release-manifest.php` + verifier | IMPLEMENTAT |
| Fingerprint config runtime | No | `RuntimeConfigFingerprint` | IMPLEMENTAT |
| Inspecció runtime | No | `RuntimeVersionInspector` | IMPLEMENTAT |
| Servei UC-010 | No | `SifVersionService` | IMPLEMENTAT |
| Preflight CLI UC-010 | No | `preflight-version-governance.php` | IMPLEMENTAT |
| Panell SIF | No | `public/sif/versions/` | IMPLEMENTAT |
| JS UC-010 | No | `public/sif/versions/app.js` | IMPLEMENTAT |
| Entrada intranet | No | botó + target HMAC allowlisted | IMPLEMENTAT |
| Proves UC-010 | No | unitàries + integració + contracte UI | IMPLEMENTAT; CI pendent en aquesta revisió |
| Evidència preproducció | No | scripts preparats | PENDENT D'ENTORN |
| Autorització producció | No | expressament fora de l'abast del preflight | PENDENT / DECISIÓ OPERATIVA |

## 3. Auditoria de la fitxa funcional baseline

La fitxa original tenia una estructura homogènia amb altres UC, però contenia afirmacions massa genèriques per UC-010:

- parlava d'estat de factura/pagament/AEAT i d'imports/divisa com a entrada principal;
- incloïa regles de càlcul decimal que no són el nucli de governança de versions;
- definia `sif_version/sif_declaration` com a persistència però no concretava com acreditar el runtime;
- no diferenciava “commit registrat”, “bytes desplegats” i “procés que està en execució”;
- no definia exclusivitat transaccional d'una versió activa;
- no descrivia el contracte de bytes físics de la declaració responsable;
- no tenia una frontera clara amb UC-38, UC-46, UC-60, UC-83 i UC-85.

Aquesta contaminació és funcionalment rellevant: una fitxa pot estar marcada com a documentada i, alhora, no constituir una especificació executable.

## 4. Auditoria del codi PHP/SQL baseline

### 4.1. Persistència que sí existia

La migració `2026_09_15_000003_add_functional_audit_control.sql` ja definia:

- `sif_version`: codi, Git revision, artifact hash, config hash, versió BD, estat, creador i data d'activació;
- `sif_declaration`: versió, hash de document, storage key, aprovador i data;
- `backup_restore_evidence`: referència/hash/status/integritat/RPO/RTO/evidència.

Això era **persistència potencial**, no implementació del cas d'ús.

### 4.2. Mancances del model baseline

1. `STATUS='ACTIVE'` a `sif_version` no tenia cap mecanisme que impedís dues files actives.
2. No hi havia journal immutable de cada decisió d'activació.
3. No hi havia idempotència de candidata/declaració.
4. `sif_declaration.STATUS` tenia default `ACTIVE`, però una fila SQL no prova que els bytes del document existeixin ni que el hash sigui correcte.
5. `backup_restore_evidence` no estava vinculada per codi a cap gate d'activació.
6. No existia cap reader/writer de les taules UC-010.

### 4.3. Migració aplicada a la branca

`2026_10_03_000001_add_uc010_version_governance.sql` és additiva i crea:

- claus d'idempotència a candidata i declaració;
- `sif_version_state(ID=1)` com a punt de serialització;
- `sif_version_activation` com a journal immutable amb:
  - versió nova i anterior;
  - declaració;
  - evidència backup opcional/obligatòria segons política;
  - actor/rol/motiu/correlació;
  - entorn;
  - Git/hash artefacte/hash configuració/versió BD observats;
  - evidència runtime JSON.

No es modifica cap migració ja registrada.

## 5. Auditoria de configuració real

`sif/config/sif.php` ja contenia configuració real de BD, HMAC, documents, Redsys, AEAT, rols i altres UC. La branca afegeix `version_governance`:

- `read_roles`;
- `manage_roles`;
- `runtime_git_revision`;
- `release_manifest_path`;
- `declaration_root`;
- `activation_enabled`;
- `require_backup_evidence`.

Propietat crítica: no hi ha rols per defecte i `activation_enabled` és fals per defecte.

## 6. Acreditació del runtime

### 6.1. Git no és suficient

`SIF_RUNTIME_GIT_REVISION` identifica el commit que l'operador declara desplegat, però UC-010 no el considera suficient.

### 6.2. Bytes desplegats

`build-release-manifest.php` calcula SHA-256 de fitxers sota:

- `src/`;
- `public/`;
- `config/`;
- `scripts/`;
- `database/migrations/`.

El manifest s'ha d'emmagatzemar **fora de tot l'arbre del release `sif/`**. La reconciliació 2026-10-04 reforça aquest invariant tant al builder com al verifier; només «fora del webroot» era insuficient perquè un manifest dins `config/` es podia auto-incloure. `ReleaseManifestVerifier` torna a calcular cada SHA-256 sobre els bytes físics abans d'acceptar el runtime.

`ARTIFACT_HASH` és el SHA-256 de la representació canònica del mapa path→hash.

### 6.3. Configuració

`RuntimeConfigFingerprint` calcula un hash canònic de la configuració carregada. La configuració completa no s'emmagatzema ni es retorna: només el SHA-256.

### 6.4. Esquema

`RuntimeVersionInspector` executa `MigrationRunner::inspect()`:

- verifica que cada migració aplicada continua existint;
- verifica el SHA-256 de cada migració aplicada;
- verifica taules i columnes declarades;
- deriva `DATABASE_VERSION` de la darrera migració versionada.

**Límit auditat:** no comprova exhaustivament tipus SQL, índexs, defaults ni constraints; per tant `schema_verified=true` no s'ha de descriure com una equivalència bit-a-bit de tot l'esquema.

## 7. Servei de domini implementat

`SifVersionService` separa cinc operacions:

### 7.1. `runtime`

Read-only. Retorna únicament evidència tècnica segura: entorn, Git, artifact hash, config hash, versió BD, verificació d'esquema i resultat del manifest.

### 7.2. `registerCurrentRuntime`

- exigeix rol de gestió;
- **no accepta hashes aportats pel navegador**;
- torna a inspeccionar el runtime;
- registra una candidata DRAFT amb els valors observats;
- és idempotent per payload;
- escriu `sif_audit_event` i `operational_event`.

### 7.3. `attachDeclaration`

- exigeix candidata existent;
- rep només una versió documental i `STORAGE_KEY`;
- resol el fitxer sota `SIF_DECLARATION_ROOT`;
- rebutja path traversal;
- calcula el SHA-256 dels bytes;
- usa l'actor autenticat com aprovador;
- persisteix `APPROVED`;
- és idempotent.

No és un uploader ni un signador automàtic.

### 7.4. `preflight`

Comprova simultàniament:

- gate d'activació explícit;
- runtime complet;
- Git igual;
- artifact hash igual;
- config hash igual;
- database version igual;
- schema íntegre;
- declaració APPROVED existent;
- bytes de declaració encara concordants;
- evidència de backup acceptable si la política l'exigeix.

### 7.5. `activate`

- no desplega;
- fa preflight abans de mutar;
- serialitza primer amb `sif_version_state FOR UPDATE`;
- reexecuta sota aquest lock la comprovació idempotent autoritativa;
- exigeix que la candidata continuï `DRAFT`;
- detecta múltiples ACTIVE o pointer inconsistent;
- rellegeix evidència mentre manté el lock de versió;
- marca l'anterior `SUPERSEDED`;
- marca la candidata `ACTIVE`;
- actualitza el pointer;
- escriu journal immutable;
- escriu audit i operational event;
- té replay idempotent.

## 8. Auditoria del JavaScript

### Baseline

No existia JS UC-010.

### Branca

`sif/public/sif/versions/app.js`:

- només usa POST JSON same-origin cap a `actions.php`;
- envia CSRF de sessió;
- genera request/correlation/idempotency IDs;
- no permet editar `GIT_REVISION`, `ARTIFACT_HASH`, `CONFIG_HASH` ni `DATABASE_VERSION`;
- separa formulari de candidata, declaració, preflight i activació;
- mostra explícitament que activar no desplega;
- no conté secrets HMAC.

La intranet només demana un launch target `versions`; el servidor limita els targets a `incidents|versions`.

## 9. Auditoria de les pàgines i endpoints

### 9.1. `intranet/sif-verifactu.php`

ACTUAL baseline: resum i botó d'incidències.  
FINAL branca: afegeix “Configuració i versions”.

No gestiona versions directament.

### 9.2. `ajax/sif/sifPanelLaunch.php`

ACTUAL baseline: només incidències.  
FINAL: `panel` només pot ser `incidents` o `versions`; cada target usa path/URL coneguts i la mateixa signatura HMAC anti-replay.

### 9.3. `pay.prisma.cat/sif/versions/index.php`

Nova pàgina:

1. Runtime observat.
2. Alta de candidata — només manage.
3. Llistat de versions.
4. Detall de versió.
5. Declaració aprovada.
6. Historial d'activacions.
7. Preflight — només manage.
8. Activació — només manage.

### 9.4. `actions.php`

POST-only + sessió + CSRF + autorització backend. El fet d'amagar formularis per rol no substitueix l'autorització de `SifVersionService`.

## 10. Fronteres amb altres casos d'ús

| UC | Responsabilitat | Relació amb UC-010 |
| --- | --- | --- |
| UC-38 | Configuració SIF, certificat i secrets | UC-010 n'observa el fingerprint/estat; no custodiar secrets duplicats |
| UC-46 | Decisió/acta d'activació i tall | UC-010 proporciona el gate i journal tècnic; el desplegament/cutover continua separat |
| UC-60 | Monitorització/estat | Consumeix estat/version metadata read-only; no activa |
| UC-83 | Registrar versió i declaració | Ha d'apuntar al mateix servei/repositoris; no crear un segon writer |
| UC-85 | Backup/restauració/reconciliació | Proporciona `backup_restore_evidence` usada pel gate |

Decisió d'arquitectura: **un sol writer** per `sif_version`, `sif_declaration`, `sif_version_state` i `sif_version_activation`: UC-010/SifVersionService. UC-46 i UC-83 són consumidors/escenaris del mateix contracte, no implementacions paral·leles.

## 11. Impacte fiscal

UC-010 té impacte fiscal **indirecte de governança**, però no crea cap factura ni registre VERI*FACTU.

No ha de:

- reescriure factures;
- renumerar sèries;
- rehashar registres fiscals antics;
- repetir cobraments;
- reenviar una cua només perquè canvia la versió.

Un canvi de release que modifica lògica fiscal s'ha de provar abans del tall; els fets ja registrats conserven la seva història.

## 12. Concurrència i idempotència

### Concurrència

`sif_version_state(ID=1) FOR UPDATE` converteix l'activació en una secció crítica. La reconciliació mou aquest lock **abans** del replay idempotent autoritatiu de la transacció; després bloqueja candidata i files ACTIVE. Això evita una cursa entre dues activacions simultànies amb la mateixa key.

### Idempotència

- candidata: key + hash canònic del payload observat;
- declaració: key + hash canònic de versió/document/aprovador;
- activació: key única + payload hash al journal.

Una mateixa key amb un altre payload és conflicte.

## 13. Seguretat

Controls implementats:

- llançament HMAC signat i anti-replay;
- sessió SIF separada i cookie HttpOnly/SameSite;
- CSRF en mutacions del panell;
- rols read/manage fail-closed;
- cap secret al navegador;
- manifest i declaracions fora del webroot;
- path traversal rebutjat;
- configuració canònica no retornada;
- activació deshabilitada per defecte.

## 14. Proves incorporades

### Unit

`ReleaseManifestVerifierTest`:

- manifest correcte;
- drift de bytes;
- path traversal;
- estabilitat del config fingerprint.

### Integració

`SifVersionServiceTest`:

- alta candidata;
- replay candidata;
- declaració;
- replay declaració;
- preflight;
- activació;
- replay activació;
- singleton actiu;
- audit + operational events;
- drift de bytes bloquejant;
- rol read vs manage;
- traversal de declaració.

### Contracte UI

`VersionPanelUiContractTest`:

- no hi ha inputs editables de hashes;
- separació declaració/preflight/activació;
- CSRF;
- target HMAC allowlisted des de la intranet.

L'estat VERIFICAT només es pot elevar quan GitHub Actions executi aquestes proves sobre MySQL controlat.

## 15. Estat DOCUMENTAT / IMPLEMENTAT / VERIFICAT / PENDENT

### DOCUMENTAT

- objectiu i frontera UC;
- classes ACTUAL/FINAL;
- seqüències ACTUAL/FINAL;
- activitats per pàgina/apartat;
- configuració i variables;
- model de persistència;
- controls de seguretat;
- proves i criteris de gate.

### IMPLEMENTAT EN BRANCA

- migració additiva;
- repositoris;
- runtime inspector;
- manifest;
- config fingerprint;
- servei;
- audit;
- preflight CLI;
- panell PHP/JS;
- llançador intranet;
- tests.

### VERIFICAT

Del PR antecedent #139 s'ha pogut recuperar evidència automatitzada concreta:

- `SifVersionServiceTest`: PASS en registre/declaració/preflight/activació/replay, drift i permisos/traversal;
- `VersionPanelUiContractTest`: PASS;
- `ReleaseManifestVerifierTest`: PASS en els casos existents;
- suite global: `926 passed / 6 failed`.

Els sis errors del PR #139 eren de PACK/Redsys, no de l'UC-010. El `main` actual ja conté posteriorment el commit `2bd2a751...` que alinea aquests tests amb el codi actual. **Això no substitueix una nova CI** sobre la branca reconciliada, perquè UC-010 també ha canviat en aquesta auditoria.

### PENDENT D'ENTORN

- aplicar migracions a `sif_test*` / preproducció;
- definir rols UC-010;
- preparar `SIF_DECLARATION_ROOT` privat;
- definir `SIF_RELEASE_MANIFEST_PATH`;
- generar manifest després del deploy;
- definir `SIF_RUNTIME_GIT_REVISION`;
- executar `preflight-version-governance.php`;
- crear/seleccionar evidència UC-85 si és obligatòria;
- executar el flux E2E en preproducció;
- conservar evidència;
- només després decidir activació productiva.

## 16. No es considera verificat per

- tenir una fila `sif_version`;
- tenir `STATUS=ACTIVE`;
- tenir una branca Git mergejada;
- tenir un manifest sense tornar a verificar els bytes;
- tenir un `STORAGE_KEY` sense fitxer;
- obtenir `GO` en un script de preproducció.

## 17. Criteri de tancament UC-010

Es pot tancar **codi/documentació** quan:

1. CI és verd.
2. No hi ha regressions als workflows SIF/intranet.
3. La fitxa i els diagrames reflecteixen el codi final.

Es pot tancar **entorn preproducció** quan:

1. migració aplicada;
2. manifest real generat;
3. runtime complet;
4. declaració real verificada;
5. backup evidence, si requerida;
6. preflight GO tècnic;
7. activació controlada sobre una candidata de prova;
8. evidència guardada.

Producció continua sent una decisió separada i no s'autoritza per aquest document.


## 18. Reconciliació amb `main` del 2026-10-04

La branca antiga del PR #139 no era una base integrable segura:

- comparació amb `main`: `diverged`;
- 40 commits per davant;
- 70 commits per darrere;
- PR draft, no mergejat i reportat com no mergeable;
- `main` ja tenia una implementació més nova de `SifAuditEventRepository` amb constructor obligatori `UuidGenerator`, mentre el PR #139 n'introduïa una variant antiga.

Decisió aplicada: crear `audit/uc-010-reconciliacio-2026-10-04` des del `main` actual i portar només les peces UC-010, adaptant-les als components compartits vigents. **No s'ha sobreescrit** `SifAuditEventRepository` de `main`.

## 19. Troballes noves i correccions aplicades

| ID | Severitat | Troballa | Correcció |
| --- | --- | --- | --- |
| UC010-AUD-01 | Alta | La fitxa exigia candidata DRAFT, però el codi podia activar una fila no DRAFT | Guard a servei/repositori + preflight `candidate_is_draft` + test |
| UC010-AUD-02 | Alta | Replay idempotent dins TX es comprovava abans de serialitzar l'activació | Lock singleton primer; replay autoritatiu després del lock |
| UC010-AUD-03 | Alta | El manifest només s'obligava fora del webroot; dins `sif/config` es podia auto-incloure i invalidar | Builder + verifier exigeixen manifest fora de tot l'arbre del release |
| UC010-AUD-04 | Mitjana | Mutacions JS no capturaven errors de forma local i regeneraven key després d'una fallada incerta | Gestió explícita d'errors i conservació de key en network/5xx |
| UC010-AUD-05 | Mitjana | Activació sensible sense confirmació explícita de l'operador | `window.confirm` abans de mutar |
| UC010-AUD-06 | Mitjana | Regex UUID permissiva acceptava 36 caràcters hex/`-` sense estructura | Format canònic 8-4-4-4-12 en repositoris UC-010 |
| UC010-AUD-07 | Mitjana | Documentació descrivia `MigrationRunner::inspect()` com a verificació d'esquema més forta del que implementa | Documentació corregida amb abast real |
| UC010-AUD-08 | Bloquejant entorn | UC-85 encara és DISSENY/NOT_COMPLETE | UC-010 només tracta la fila com a gate parcial; no es declara recuperabilitat acreditada |

## 20. Traçabilitat codi → prova → document

| Invariant | Codi | Prova | Document |
| --- | --- | --- | --- |
| Runtime server-authoritative | `RuntimeVersionInspector`, `SifVersionService::registerCurrentRuntime` | `SifVersionServiceTest` | fitxa §5/8 |
| Bytes release | `ReleaseManifestVerifier` | `ReleaseManifestVerifierTest` | fitxa §4/7 |
| Manifest extern | verifier + `build-release-manifest.php` | test ubicació dins release rebutjada | README + auditoria |
| Candidata DRAFT | `SifVersionService` + `SifVersionRepository` | reactivació amb key nova → 409 | fitxa §4/11 |
| Declaració física | `inspectDeclarationFile` | integració + traversal | fitxa §9 |
| Serialització | `sif_version_state FOR UPDATE` | integració; E2E concurrència real pendent | seqüència FINAL |
| Idempotència | repositoris + ordre de lock | replay mateix payload | fitxa §7 R09 |
| Panell segur | HMAC + sessió + CSRF + rols | `VersionPanelUiContractTest` | activitats |
| Backup | `BackupRestoreEvidenceRepository` | reader indirecte | pendent UC-85 |

## 21. Mancances que continuen obertes

1. **CI de la branca reconciliada:** és imprescindible executar-la després de tots els canvis.
2. **MySQL/preproducció:** aplicar migració, generar manifest real, registrar candidata, vincular declaració i executar activació de prova.
3. **UC-85:** definir i implementar un contracte de backup/restauració que acrediti scope, resultat i recuperabilitat; avui UC-010 no pot convertir la fila existent en una prova completa.
4. **Política de `CONFIG_HASH`:** el fingerprint actual inclou valors secrets en memòria i només persisteix el hash. Cal decidir abans de producció si una rotació de secret ha de considerar-se drift de configuració executable.
5. **Segregació de funcions:** el mateix rol `manage` pot vincular una declaració i activar. Si es requereix maker-checker o aprovació legal separada, s'ha d'afegir com a regla explícita.
6. **TTL de sessió del panell:** RESOLT — TTL absolut configurable, default 1800 s, fail-closed i reentrada des de la intranet.
7. **Verificació SQL profunda:** si cal acreditar índexs/constraints/tipus, cal ampliar el preflight o afegir checks específics.
8. **Producció:** queda fora de l'abast i no s'autoritza per cap preflight tècnic.

## 22. Classificació final de l'auditoria

- **DOCUMENTAT:** sí, fitxa funcional + UML integrada + classes + seqüències + activitats/pàgines + auditoria i configuració.
- **IMPLEMENTAT:** sí, en branca reconciliada basada en `main`.
- **VERIFICAT:** parcial — evidència UC-010 positiva del PR antecedent; nova CI i E2E de la branca final encara necessaris.
- **PENDENT:** CI final, MySQL/preproducció, evidència real de declaració, contracte UC-85, decisions de seguretat/governança i producció.

**UC-010 no es considera tancat en producció.** El codi/documentació sí queden preparats per a una nova validació sobre la base actual del repositori.


## 23. Continuació de l'auditoria · hardening addicional

| ID | Severitat | Troballa | Correcció |
| --- | --- | --- | --- |
| UC010-AUD-09 | Alta | Es podia afegir una nova declaració APPROVED a una versió ja ACTIVE/SUPERSEDED, fent que el detall mostrés una declaració diferent de la usada a l'activació | `attachDeclaration()` exigeix `DRAFT` abans i dins la transacció; prova de regressió després d'activar |
| UC010-AUD-10 | Alta | El manifest validava files llistats però no detectava un fitxer nou introduït després del build | inventari exhaustiu de roots governats + `UNEXPECTED_FILE`; prova específica |
| UC010-AUD-11 | Alta | Els symlinks podien quedar fora del manifest del builder | builder falla davant symlink i verifier els marca `SYMLINK_NOT_ALLOWED`/`UNEXPECTED_SYMLINK` |
| UC010-AUD-12 | Mitjana | El preflight retornava la fila completa `backup_restore_evidence` al rol lector | projecció mínima; s'eliminen `EVIDENCE_JSON`, `BACKUP_REFERENCE`, `EXECUTED_BY` i camps no necessaris |

Aquests canvis mantenen el criteri de tancament: **CI final de la branca + E2E MySQL/preproducció continuen pendents**.


## 24. Hardening final abans de CI

| ID | Severitat | Troballa | Correcció |
| --- | --- | --- | --- |
| UC010-AUD-13 | Alta | Un retry de `register_current` tornava a inspeccionar runtime abans de reconèixer la mateixa idempotency key; un drift posterior podia convertir un replay legítim en 503 | fast replay per key + `VERSION_CODE` + actor abans d'inspeccionar runtime; prova amb drift posterior |
| UC010-AUD-14 | Alta | El journal referenciava declaració/backup per UUID però no congelava el snapshot que havia justificat el GO | `RUNTIME_EVIDENCE_JSON` incorpora hash/metadades de declaració i resum mínim del backup; audit after-snapshot també els conserva |
| UC010-AUD-15 | Mitjana | `actions.php` podia ser cachejat i un 500 podia exposar el missatge intern de l'excepció | `no-store`, `nosniff`, `Allow: POST`; 5xx genèric amb detall només al log servidor |
| UC010-AUD-16 | Alta | La sessió guardava `authenticated_at` però no caducava; rols antics podien quedar vius fins al logout/tancament | TTL absolut configurable, default 1800 s, test de sessió fresca/expirada |
| UC010-AUD-17 | Operativa | Molts pushes generaven gates UC-010 supersedits a la cua | `concurrency` amb `cancel-in-progress` al workflow UC-010 per als runs futurs |

### Estat després del hardening

- **DOCUMENTAT:** complet i reconciliat amb el codi de la branca.
- **IMPLEMENTAT:** circuit UC-010 complet en branca, inclosos controls addicionals d'integritat, idempotència, minimització i sessió.
- **VERIFICAT:** proves específiques existeixen i l'evidència històrica del PR #139 és positiva per UC-010; el gate del head final continua pendent d'execució perquè GitHub Actions manté els jobs en cua.
- **PENDENT D'ENTORN:** MySQL/preproducció, manifest real, declaració real, dependència UC-85 i decisió productiva.


## 25. Via d'evidència de preproducció preparada

S'ha incorporat `SifVersionEvidenceVerifier` i el CLI `sif/scripts/verify-version-governance-evidence.php UUID_VERSION`. És **read-only** i comprova de manera conjunta:

- versió `ACTIVE` i singleton coherent;
- exactament una versió `ACTIVE`;
- journal `ACTIVATED` existent;
- declaració `APPROVED`, hash físic i hash congelat al snapshot d'activació;
- runtime actual complet i concordant amb Git/artefacte/config/BD de la versió i del journal;
- backup present/acceptable quan la política el requereix;
- traça `sif_audit_event` i `operational_event` d'activació;
- sortida minimitzada, sense storage paths ni `EVIDENCE_JSON`;
- `production_authorized=false` sempre.

En producció el CLI falla tancat tret que s'habiliti explícitament `SIF_UC010_EVIDENCE_ALLOW_PRODUCTION=1`. Aquest script permet conservar un JSON d'evidència després de l'E2E de `sif_test*`/preproducció sense executar cap mutació addicional.

**Estat del CI en aquest tall:** el PR #165 continua mergeable i `behind_by=0`, però GitHub Actions manté el gate UC-010 i les suites compartides en estat `queued`; encara no existeixen logs del head final que permetin marcar CI verd o vermell.
