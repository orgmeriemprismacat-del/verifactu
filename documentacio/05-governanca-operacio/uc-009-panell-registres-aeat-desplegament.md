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
