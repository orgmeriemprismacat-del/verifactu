# UC-008 · Tancament de l'auditoria · 02/10/2026

**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Branca de verificació:** `main`  
**Main observat:** `f7fa0822f82be96e842d9f2d031e643ab07f617c`  
**Tall executable UC-008 verificat:** `5cc0410018929bed53d0e2e2078f4b4c4f2bf6f7`  
**CI aplicable:** run `36943995075` — **844 passed / 0 failed**  
**Proves relacionades amb incidències/UC-008 dins del run:** **74 PASS**

## 1. Estat de tancament

L'auditoria del UC-008 queda **TANCADA** amb l'estat:

`AUDIT_CLOSED + CODE_COMPLETE + DOCUMENTATION_RECONCILED + CI_GREEN + ENVIRONMENT_ACCEPTANCE_PENDING`

Aquesta classificació separa dues coses que no s'han de confondre:

- **auditoria del cas d'ús:** tancada;
- **acceptació real de preproducció/producció:** pendent fins disposar de les evidències generades a l'entorn real.

No queda cap buit PHP/JS, de model, UML o prova automatitzada obligatòria detectat dins l'abast auditat que impedeixi tancar l'auditoria.

## 2. Documentació auditada

El paquet queda format, com a mínim, per:

- fitxa funcional: `documentacio/06-fitxes-funcionals/uc-008.md`;
- fitxa/UML integrada: `uc-008-gestionar-incidencia-sif.md`;
- classes ACTUAL/FINAL: `uc-008-classes-actual-final.md`;
- seqüències ACTUAL/FINAL: `uc-008-sequencies-actual-final.md`;
- activitats ACTUAL/FINAL per pàgina/apartat: `uc-008-activitats-pagines-incidencies-actual-final.md`;
- auditoria detallada: `04b-auditoria-detallada-uc-008-2026-09-30.md`;
- proves i acceptació d'entorn: `05-proves-pendents-uc-008-implementacio.md`;
- desplegament: `06-desplegament-uc-008-panell-incidencies.md`;
- menú intranet: `07-alta-menu-intranet-uc-008.md`;
- evidència E2E gestor: `08-evidencia-gestor-uc-008.md`.

## 3. Implementació contrastada

S'ha contrastat contra `main`:

- `IncidentLifecycleService`;
- `IncidentRepository`;
- `IncidentActionRepository`;
- API interna `public/api/incidents/manage.php`;
- panell `public/sif/incidencies/`;
- sessió del panell i CSRF;
- handoff HMAC intranet → SIF;
- client HMAC intern de la intranet;
- pont intranet read-only;
- obertura/deduplicació d'incidències des de Redsys i AEAT;
- preflight, E2E read-only, preparador E2E gestor, verificador gestor i validador final de tres evidències.

El canvi posterior del journal multirol queda inclòs: les mutacions persisteixen el **rol gestor efectiu** que autoritza l'acció, no necessàriament el primer rol de l'actor.

## 4. Evidència automatitzada final

El run `36943995075`, sobre `5cc0410018929bed53d0e2e2078f4b4c4f2bf6f7`, finalitza amb **844/0**.

Dins del mateix run s'han identificat **74 PASS** relacionats amb incidències/UC-008, incloent:

- esquema i identitat de la incidència;
- idempotència de capçalera i accions;
- concurrència real d'obertura i assignació;
- permisos read/manage;
- HMAC, timestamp i anti-replay;
- actor i rol efectiu al journal;
- `ASSIGN`, `ADD_EVIDENCE`, `RESOLVE`, `DISMISS` i `REOPEN`;
- contracte UI, timeline append-only i CSRF;
- frontera read-only de la intranet;
- E2E tècnic read-only;
- preflight del panell;
- descoberta/preflight del menú;
- preparació idempotent del cas sintètic gestor;
- verificador read-only del gestor;
- validador final de les tres evidències;
- integracions d'incidència Redsys i AEAT.

Entre aquest tall executable i el `main` observat hi ha **10 commits** que només afecten documentació d'altres UC i el registre mestre. No hi ha cap canvi executable UC-008 posterior que invalidi aquesta evidència.

## 5. Frontera funcional confirmada

UC-008 governa l'expedient d'incidència: obertura, consulta, triatge, evidència, tancament, dismissal i reobertura.

No és responsabilitat d'UC-008 executar una reparació fiscal/econòmica genèrica. La reparació real es deriva al cas d'ús responsable conservant correlació i evidència. Per això no s'ha creat un `RepairRouter` genèric ni una acció massiva de retry.

## 6. Acceptació d'entorn pendent

Per declarar també `ENVIRONMENT_CLOSED`, cal executar a preproducció real i conservar:

1. `uc-008-preproduction-evidence.json` amb `environment=preproduction`, preflight verd i E2E read-only verd;
2. `uc-008-menu-evidence.json` amb `scope=uc-008-intranet-menu-discovery`, una única entrada `/sif-verifactu.php` i `status=ALREADY_PRESENT`;
3. `uc-008-manager-e2e-evidence.json` amb el flux real `ASSIGN → ADD_EVIDENCE → RESOLVE` sobre la incidència sintètica `UC008_E2E_MANAGER / UC008_E2E`;
4. `uc-008-closure-validation.json` generat per `validate-uc008-evidence.php` amb `ok=true` i SHA-256 dels tres inputs.

Aquests passos necessiten l'entorn real, els rols reals i la BD real del menú. No es substitueixen per dades sintètiques ni per una execució amb `SIF_ENV=test`.

## 7. Criteri final

**Auditoria UC-008: TANCADA.**  
**Codi UC-008: COMPLET segons l'abast auditat.**  
**Documentació/UML: RECONCILIATS.**  
**CI: VERD, 844/0.**  
**Acceptació preproducció/producció: PENDENT D'ENTORN, no pendent de codi.**


## 8. Addenda de revalidació · 03/10/2026

Aquesta acta conserva correctament l'evidència **històrica de tancament del 02/10** (`844/0`). Després del tancament, `main` ha avançat fins a `b0e8ff7150c5a8b415cc109d298d82f0db1f68df` amb 44 commits addicionals i canvis executables compartits, principalment Redsys i altres UC.

La revalidació del 03/10 estableix:

- els documents, UML i el nucli PHP/JS UC-008 continuaven exactament iguals al merge PR #116;
- les proves explícites UC-008 observades al HEAD del PR #118 continuen en **PASS**;
- el HEAD del PR #118 havia registrat **917 passed / 6 failed**; després de corregir l'assert Redsys compartit, el run PR #123 `37128124387` registra **918 passed / 5 failed**. Per tant, l'etiqueta `CI_GREEN` d'aquesta acta s'ha d'interpretar com a estat del **baseline de tancament**, no com a estat global de la revalidació;
- les cinc fallades restants són PACK/UC-015; la fallada Redsys compartida està resolta i passa en CI;
- el criteri funcional no canvia: **AUDIT_CLOSED + CODE_COMPLETE + ENVIRONMENT_ACCEPTANCE_PENDING**.

La font vigent per a l'estat posterior és [UC-008 · Revalidació exhaustiva contra main · 03/10/2026](uc-008-revalidacio-main-2026-10-03.md).
