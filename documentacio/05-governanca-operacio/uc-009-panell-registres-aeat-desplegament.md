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
```

Els rols reals s'han d'alinear amb els valors que ja utilitza la taula `apartats.ROLS_VISUALITZAR`.

## 3. Alta de la pàgina al menú d'intranet

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

Hi ha tres barreres independents:

1. sessió d'intranet vàlida;
2. rol HMAC autoritzat a `SIF_AEAT_READ_ROLES`;
3. per a `reconcile`, rol inclòs a `SIF_AEAT_RECONCILE_ROLES` + CSRF de sessió.

El fet de veure la pàgina al menú no concedeix permisos d'API.

## 5. Reconciliació REVIEW

La UI només mostra «Conciliar sense reenviar» quan:
- la cua és `REVIEW`;
- hi ha un `aeat_submission_attempt` del mateix `FISCAL_QUEUE_ID`;
- l'intent té estat terminal remot `ACCEPTED`, `ACCEPTED_WITH_ERRORS` o `REJECTED`.

El servidor torna a validar totes aquestes condicions dins d'una transacció.

### No es permet
- convertir un `UNCERTAIN` en acceptat;
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
5. provar `reconcile` només amb dades `sif_test*`;
6. comprovar que un intent `UNCERTAIN` retorna 409 i manté `REVIEW`;
7. afegir l'apartat al menú de preproducció;
8. validar permisos amb un usuari autoritzat i un no autoritzat.

## 7. Producció

Aquest panell no habilita l'enviament AEAT de producció. `SoapTransport` continua limitat a l'endpoint de proves fins a la qualificació corresponent.


## 8. Evidència executada el 2026-09-30

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
9. Crear/usar un cas controlat `REVIEW`: verificar que `UNCERTAIN` no es pot conciliar i que un resultat terminal coincident sí es pot tancar sense nou SOAP.
10. Conservar captures/logs/IDs de cua i intents com a evidència de preproducció.
11. Fer la prova externa AEAT de preproducció.
12. Mantenir producció bloquejada fins al tancament formal.

## 11. Criteri actual de tancament

**Codi i proves automàtiques: VERIFICAT.**

**Desplegament i operació real: PENDENT D'ENTORN.**

UC-009 només podrà passar a tancament operatiu quan constin evidències del desplegament de preproducció, permisos, panell real, reconciliació controlada i prova AEAT corresponent.
