# UC-008 · Alta segura del menú VERI*FACTU a la intranet

**Estat:** preparada al repositori; **NO executada** sobre la BD de la intranet.

## 1. Contracte real verificat

El menú lateral actual es construeix des de la taula `apartats` a:

`codi-drive/intranet-actual/ajax/mostrarSideBarMenu_v5.php`

Camps llegits pel codi real:

- `ID`
- `ICONA`
- `NOM`
- `NIVELL`
- `URL`
- `ID_NIVELL_PARE`
- `ROLS_VISUALITZAR`
- `ORDRE`

`ROLS_VISUALITZAR` és una llista separada per `|` i es contrasta directament contra `$_SESSION['usuari']->getRols()`.

La URL nova ja existeix al codi:

`/sif-verifactu.php`

## 2. Regla de seguretat

No s'ha de crear cap fila amb un `ID_NIVELL_PARE`, `NIVELL`, rols o `ORDRE` inventats.

Els rols del menú han de correspondre als mateixos codis de rol que arriben des de la sessió de la intranet i que s'han configurat a:

- `SIF_INCIDENT_READ_ROLES`
- `SIF_INCIDENT_MANAGE_ROLES`

El menú només controla visibilitat. L'autorització efectiva continua sent del backend SIF.

## 3. Descoberta obligatòria abans de l'INSERT

Executar **només lectura** a la BD de la intranet:

```sql
SHOW CREATE TABLE apartats;

SELECT
    ID,
    ICONA,
    NOM,
    NIVELL,
    URL,
    ID_NIVELL_PARE,
    ROLS_VISUALITZAR,
    ORDRE
FROM apartats
WHERE
    NOM LIKE '%Factur%'
    OR URL LIKE '%factur%'
    OR URL LIKE '%sif%'
ORDER BY NIVELL, ID_NIVELL_PARE, ORDRE, ID;

SELECT
    ID,
    NOM,
    NIVELL,
    URL,
    ID_NIVELL_PARE,
    ROLS_VISUALITZAR,
    ORDRE
FROM apartats
WHERE URL = '/sif-verifactu.php';
```

Cal conservar el resultat com a evidència de desplegament.

## 4. Valors que s'han de confirmar

Abans d'escriure:

```text
PARENT_ID       = <ID real del node pare>
TARGET_LEVEL    = <nivell del pare + 1>
TARGET_ROLES    = <rol1|rol2|...>
TARGET_ORDER    = <ordre real dins del mateix pare>
TARGET_ICON     = <mateix patró visual de les entrades germanes>
TARGET_NAME     = VERI*FACTU
TARGET_URL      = /sif-verifactu.php
```

No assumir que el node pare s'anomena exactament `Facturació`: s'ha de seleccionar pel resultat real de la consulta.

## 5. INSERT idempotent — plantilla

**No executar fins substituir els quatre placeholders marcats.**

```sql
START TRANSACTION;

SET @UC008_PARENT_ID    := <PARENT_ID_CONFIRMAT>;
SET @UC008_LEVEL        := <TARGET_LEVEL_CONFIRMAT>;
SET @UC008_ROLES        := '<TARGET_ROLES_CONFIRMATS>';
SET @UC008_ORDER        := <TARGET_ORDER_CONFIRMAT>;
SET @UC008_ICON         := '<TARGET_ICON_CONFIRMADA>';

SELECT
    ID, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR, ORDRE
FROM apartats
WHERE ID = @UC008_PARENT_ID
FOR SHARE;

SELECT COUNT(*) AS EXISTENTS
FROM apartats
WHERE URL = '/sif-verifactu.php';

INSERT INTO apartats (
    ICONA,
    NOM,
    NIVELL,
    URL,
    ID_NIVELL_PARE,
    ROLS_VISUALITZAR,
    ORDRE
)
SELECT
    @UC008_ICON,
    'VERI*FACTU',
    @UC008_LEVEL,
    '/sif-verifactu.php',
    @UC008_PARENT_ID,
    @UC008_ROLES,
    @UC008_ORDER
WHERE NOT EXISTS (
    SELECT 1
    FROM apartats
    WHERE URL = '/sif-verifactu.php'
);

SELECT
    ID, ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR, ORDRE
FROM apartats
WHERE URL = '/sif-verifactu.php';

-- COMMIT només després de revisar la fila anterior.
-- ROLLBACK;
```

## 6. Validacions abans del COMMIT

La fila creada ha de complir tot això:

1. una sola fila amb `URL='/sif-verifactu.php'`;
2. `ID_NIVELL_PARE` apunta al node real correcte;
3. `NIVELL` és coherent amb les entrades germanes;
4. `ORDRE` no desplaça de manera inesperada altres entrades;
5. `ROLS_VISUALITZAR` només conté rols autoritzats;
6. el nom és `VERI*FACTU`;
7. el menú es renderitza sense alterar altres branques del sidebar.

## 7. Prova funcional posterior

Amb un usuari autoritzat:

- el menú mostra `VERI*FACTU`;
- l'enllaç obre `/sif-verifactu.php`;
- el resum consulta el SIF;
- el botó «Obrir incidències SIF» fa el handoff signat;
- el panell SIF s'obre amb sessió pròpia.

Amb un usuari sense rol:

- l'entrada no s'hauria de mostrar si els rols de menú estan ben restringits;
- encara que s'intenti obrir la URL directament, l'API SIF ha de respondre fail-closed.

## 8. Rollback de configuració

Si la fila creada és incorrecta i encara no hi ha dependències:

```sql
DELETE FROM apartats
WHERE URL = '/sif-verifactu.php';
```

Abans d'executar aquest rollback s'ha de verificar que només existeix la fila creada per UC-008.

## 9. Estat de tancament

- Codi de la pàgina: **implementat**.
- Contracte de menú: **verificat al codi real**.
- Plantilla d'alta: **preparada i idempotent**.
- ID pare, rols, ordre i icona productius: **pendents de consultar a la BD real**.
- INSERT productiu: **no executat**.
