# UC-009 · Desplegament del panell intern «Registres AEAT»

**Data:** 2026-09-30  
**Àmbit:** intranet Prisma + SIF intern.  
**URL prevista:** `/sif-registres-aeat.php`.

## 1. Components

### Intranet
- `sif-registres-aeat.php`
- `js/sif-registres-aeat.js`
- `css/sif-registres-aeat.css`
- `SifInternalAeatClient.php`
- `ajax/sif/sifAeat.php`

### SIF
- `/api/aeat/operations.php`
- `AeatOperationsReadRepository`
- `AeatReviewReconciliationService`
- `AeatSubmissionAttemptRepository`
- `FiscalQueueRepository`

## 2. Variables d'entorn

### SIF
```text
SIF_AEAT_READ_ROLES=ROL_TECNIC,ROL_FACTURACIO
SIF_AEAT_RECONCILE_ROLES=ROL_TECNIC
SIF_INTERNAL_AEAT_OPERATIONS_SIGNED_PATH=/api/aeat/operations.php
```

### Intranet
```text
SIF_INTERNAL_AEAT_URL=https://<host-sif>/api/aeat/operations.php
SIF_INTERNAL_AEAT_OPERATIONS_SIGNED_PATH=/api/aeat/operations.php
SIF_INTERNAL_API_KEY_ID=<id-clau>
SIF_INTERNAL_API_SECRET=<secret>
SIF_AEAT_EVIDENCE_DIR=<directori-privat-fora-del-webroot>
```

Els rols reals s'han d'alinear amb els valors que ja utilitza la taula `apartats.ROLS_VISUALITZAR`.

## 3. Alta de la pàgina al menú d'intranet

Abans de qualsevol INSERT, executar en preproducció el preflight read-only:

```bash
php codi-drive/intranet-actual/preflight-sif-registres-aeat-menu.php
```

El resultat ha de ser `ALREADY_PRESENT` amb un únic registre, o bé `CONFIRM_PARENT_ROLES_ORDER_BEFORE_INSERT` amb candidats revisables. `DUPLICATE_TARGET_URL` bloqueja l'alta fins resoldre el duplicat.


El repositori no conté una migració executable de la BD de menú. No s'ha de hardcodejar `ID_NIVELL_PARE` perquè varia segons l'entorn.

Abans d'executar res, identificar el registre pare de Facturació:

```sql
SELECT ID, NOM, NIVELL, URL, ROLS_VISUALITZAR, ORDRE
FROM apartats
WHERE NOM LIKE '%Factur%'
ORDER BY NIVELL, ORDRE;
```

Amb l'ID correcte validat manualment, l'alta idempotent és:

```sql
SET @parent_id := <ID_FACTURACIO_VALIDAT>;
SET @nivell := (SELECT NIVELL + 1 FROM apartats WHERE ID = @parent_id);

INSERT INTO apartats
    (ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR, ORDRE)
SELECT
    '<span class="material-icons">receipt_long</span>',
    'Registres AEAT',
    @nivell,
    '/sif-registres-aeat.php',
    @parent_id,
    '<ROLS_VISUALITZAR_VALIDATS>',
    COALESCE((SELECT MAX(a2.ORDRE) + 1 FROM apartats a2 WHERE a2.ID_NIVELL_PARE = @parent_id), 1)
WHERE NOT EXISTS (
    SELECT 1 FROM apartats WHERE URL = '/sif-registres-aeat.php'
);
```

No executar aquesta sentència sense substituir:
- `<ID_FACTURACIO_VALIDAT>`;
- `<ROLS_VISUALITZAR_VALIDATS>`.

## 4. Permisos

Hi ha quatre barreres independents:

1. sessió d'intranet vàlida;
2. gate local de pàgina: els rols vigents de sessió han d'intersectar `SIF_AEAT_READ_ROLES`; si no, HTTP 403 abans de renderitzar;
3. API HMAC: qualsevol operació exigeix rol de lectura;
4. `reconcile` i `reconcile_evidence` exigeixen **rol de lectura + rol de reconciliació** i, quan venen del browser, CSRF de sessió.

`SIF_AEAT_READ_ROLES` s'ha de configurar coherentment tant a l'entorn d'intranet com al SIF. El fet de veure la pàgina al menú no concedeix permisos d'API.

## 5. Reconciliació REVIEW

La UI només mostra «Conciliar sense reenviar» quan:
- la cua és `REVIEW`;
- hi ha un `aeat_submission_attempt` del mateix `FISCAL_QUEUE_ID`;
- l'intent té estat terminal remot `ACCEPTED`, `ACCEPTED_WITH_ERRORS` o `REJECTED`.

El servidor torna a validar totes aquestes condicions dins d'una transacció.

### No es permet
- convertir un `UNCERTAIN` en terminal **sense** `EVIDENCE_ID` estructurat, bundle complet/íntegre, HTTP 200, metadata coincident i resposta validada per `ResponseParser`;
- fer un segon SOAP;
- canviar número de factura;
- crear un registre fiscal nou;
- modificar el payload immutable;
- conciliar un intent d'un altre job.

## 6. Validació abans de desplegar

1. executar `SIF checks`;
2. comprovar que la migració `2026_09_29_000010_add_aeat_queue_claim_token.sql` està aplicada;
3. configurar rols i URL interna;
4. verificar `summary`, `list`, `detail`, `preflight`;
5. provar `reconcile` i `reconcile_evidence` només amb dades `sif_test*`;
6. comprovar que un `UNCERTAIN` sense evidència vàlida retorna 409 i manté `REVIEW`;
7. comprovar que un `UNCERTAIN` amb evidència completa, íntegra i coincident es tanca sense segon SOAP;
8. afegir l'apartat al menú de preproducció;
9. validar tres perfils: sense read (403 de pàgina), read-only (sense botons de conciliació) i read+reconcile (mutació autoritzada).

## 7. Producció

Aquest panell no habilita l'enviament AEAT de producció. `SoapTransport` continua limitat a l'endpoint de proves fins a la qualificació corresponent.


## 8. Evidència històrica executada el 2026-09-30

Estat verificat a la PR #23:

- `Intranet AO batch checks`: **PASS**.
- `SIF PHP MySQL tests`: **PASS**.
- `UC-111 integration verification`: **PASS**.
- `SIF checks`: **PASS**.
- Suite SIF: **558 proves passades, 0 fallades**.
- Lint PHP del SIF: **PASS**.
- Lint PHP dels fitxers UC-009 d'intranet: **PASS**.
- Sintaxi JavaScript del panell UC-009: **PASS**.
- PR #23: **mergeable** i marcada `Ready for review`.

Durant la validació es va detectar un defecte transversal ja existent: `IncidentLifecycleService` i diversos fluxos cridaven `IncidentRepository::openDetailed()`, `findById()`, `list()` i `updateLifecycle()` sense que el repositori els implementés. La PR #23 completa aquesta capa i la suite passa de **543 passades / 14 fallades** a **558 passades / 0 fallades**.

## 9. Tasques que no es poden executar només des del repositori

Aquestes accions requereixen accés real a l'entorn i **no s'han de donar per fetes**:

| Tasca | Estat | Per què no es pot executar des de GitHub |
| --- | --- | --- |
| Aplicar la migració `2026_09_29_000010_add_aeat_queue_claim_token.sql` a preproducció | PENDENT ENTORN | Requereix credencials i connexió MySQL de l'entorn SIF |
| Configurar `SIF_AEAT_READ_ROLES` i `SIF_AEAT_RECONCILE_ROLES` | PENDENT ENTORN | Requereix configuració/secrets del servidor |
| Configurar `SIF_INTERNAL_AEAT_URL`, key id i secret a intranet | PENDENT ENTORN | Requereix configuració privada del servidor; els secrets no s'han de versionar |
| Donar d'alta `/sif-registres-aeat.php` a `apartats` | PENDENT ENTORN | Cal consultar l'ID pare i els rols reals de la BD d'intranet abans d'inserir |
| Provar usuari autoritzat / no autoritzat a la intranet real | PENDENT ENTORN | Requereix sessió i usuaris reals de preproducció |
| Provar `summary/list/detail/preflight` contra el SIF desplegat | PENDENT ENTORN | Requereix desplegament i xarxa interna entre intranet i SIF |
| Provar `reconcile` sobre un cas REVIEW real de preproducció | PENDENT ENTORN | Requereix dades reals/controlades de preproducció i operadora autoritzada |
| Enviament real a AEAT preproducció | PENDENT AEAT | Requereix certificat, representació/configuració i connectivitat AEAT |
| Habilitar endpoint AEAT de producció | BLOQUEJAT | Només després de proves, qualificació i aprovació de release |

## 10. Ordre recomanat de desplegament

1. Fusionar la PR #23 després de revisió humana.
2. Fer backup/config snapshot de preproducció.
3. Aplicar migracions SIF pendents.
4. Configurar rols i secrets només a l'entorn.
5. Desplegar SIF i intranet.
6. Executar `summary`, `list`, `detail` i `preflight`.
7. Donar d'alta la pàgina a `apartats` amb l'ID pare i rols comprovats.
8. Verificar permisos amb usuari autoritzat i usuari sense permís.
9. Crear/usar casos controlats `REVIEW`: resultat terminal coincident → `reconcile`; `UNCERTAIN` amb evidència vàlida → `reconcile_evidence`; evidència incompleta/mismatch → 409 i mantenir `REVIEW`.
10. Conservar captures/logs/IDs de cua i intents com a evidència de preproducció.
11. Fer la prova externa AEAT de preproducció.
12. Mantenir producció bloquejada fins al tancament formal.

## 11. Criteri actual de tancament

**Codi UC-009 i proves AEAT específiques: VERIFICATS al tall actual disponible.**

**CI global:** el 917/6 del 2026-10-02 és un tall històric, no un estat vigent. Abans de merge/release cal mirar el CI del `main` i del PR actuals. L'evidència 558/0 anterior també es conserva només com a històrica.

**Desplegament i operació real: PENDENT D'ENTORN.**

UC-009 només podrà passar a tancament operatiu quan constin evidències del desplegament de preproducció, permisos, panell real, reconciliació controlada i prova AEAT corresponent.


## 12. Revalidació 2026-10-03

Canvis del paquet d'auditoria:

- el workflow `SIF PHP MySQL tests` inclou ara els fitxers intranet UC-009 als triggers;
- s'afegeix lint PHP/JS específic del panell;
- s'afegeix `AeatIntranetUiContractTest` per validar sessió/CSRF/HMAC, separació d'estats i absència de secrets al browser;
- s'afegeix el preflight read-only del menú;
- la validació d'`attempt_uuid` de reconciliació exigeix estructura UUID 8-4-4-4-12.

Aquests canvis milloren la garantia de regressió del codi versionat, però **no substitueixen** la prova real de desplegament, xarxa, certificat i AEAT de preproducció.


## 13. Correccions de preproducció detectades el 2026-10-03

1. **Same-origin intranet:** el panell, assets, AJAX i redirect utilitzen rutes relatives. Això evita que `intranet-pre.prisma.cat` carregui recursos o intenti autenticar-se contra `intranet.prisma.cat`.
2. **Evidence store al preflight web:** `config/sif.php` inclou `SIF_AEAT_EVIDENCE_DIR`; el modal i el CLI comproven la mateixa ruta privada.
3. **Stale worker:** un `PROCESSING` caducat queda en `REVIEW` amb incidència `AEAT_STALE_PROCESSING`. No torna a `RETRY` i no es fa cap segon SOAP automàtic.
4. **Check focalitzat:** `UC-009 AEAT audit` executa lints i la suite pròpia del cas independentment de fallades alienes de PACK/Redsys.

Aquestes quatre correccions són prerequisit abans de considerar una prova real a `intranet-pre`/SIF preproducció.


## 14. Desplegament de la conciliació d'evidència — 2026-10-04

Abans d'usar `reconcile_evidence` a preproducció:

1. aplicar la migració `2026_10_04_000033_add_aeat_attempt_evidence_id.sql`;
2. comprovar que `aeat_submission_attempt.EVIDENCE_ID` existeix i té clau única;
3. configurar `SIF_AEAT_EVIDENCE_DIR` al SIF, fora de repositori/webroot;
4. executar preflight i confirmar `evidence_store_private=true`;
5. generar un cas controlat `UNCERTAIN` amb evidència privada;
6. comprovar que el detall del panell mostra l'acció de conciliació només a l'últim intent;
7. executar la conciliació i verificar:
   - queue `REVIEW → SENT`;
   - attempt `UNCERTAIN → terminal`;
   - `factura_registres.ESTAT_AEAT` terminal;
   - incidència resolta;
   - `operational_event.REASON_CODE=AEAT_EVIDENCE_RECONCILED`;
   - cap nou `aeat_submission_attempt`;
   - cap nova evidència/request de xarxa creada per la conciliació.

No s'ha de copiar `request.xml` o `response.xml` al webroot ni mostrar-ne el contingut al panell.


## 14. Permisos de filesystem per certificat i evidències

Configuració recomanada a Linux/preproducció:

```bash
# Exemples: ajustar usuari/grup del procés PHP/worker.
chmod 0600 /ruta/privada/aeat/certificat.p12
chmod 0700 /ruta/privada/aeat/evidencies
```

El codi admet que el grup del procés tingui permisos si l'operació ho necessita, però **rebutja qualsevol permís per a `others`**. També rebutja que `SIF_AEAT_CERT_PATH` o `SIF_AEAT_EVIDENCE_DIR` siguin symlinks directes.

Abans d'executar el worker:

1. `SIF_AEAT_CERT_PATH` ha d'apuntar a un P12/PFX fora del repositori/webroot;
2. el fitxer ha de ser llegible pel procés, no per altres usuaris del sistema;
3. `SIF_AEAT_EVIDENCE_DIR` ha de ser un directori real, writable, fora del repositori/webroot i no world-accessible;
4. el preflight ha de retornar `certificate_usable=true` i `evidence_directory_private=true`;
5. no n'hi ha prou amb un `.htaccess Deny from all`: la custòdia es valida també a nivell de filesystem i ubicació real.
