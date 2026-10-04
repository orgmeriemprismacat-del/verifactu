# Entorns SIF · DEV / TEST / PRE / PROD

Aquest document fixa la topologia d'entorns del SIF i les fronteres amb web i intranet. No conté secrets ni credencials reals.

## Matriu SIF

| Entorn | SIF_ENV | Domini | BD | Ús |
| --- | --- | --- | --- | --- |
| DEV | `development` | `pay-dev.prisma.cat` | `sif_dev` | desenvolupament funcional i integració |
| TEST | `test` | `pay-test.prisma.cat` | `sif_test` | proves automatitzades, destructives i E2E controlats |
| PRE | `preproduction` | `pay-pre.prisma.cat` | `sif_pre` | candidat a producció i evidència |
| PROD | `production` | `pay.prisma.cat` | BD SIF de producció | ús real |

`local` es manté únicament per compatibilitat amb eines locals existents i no representa cap entorn desplegat.

## Canals web

- `web-dev.prisma.cat` → `pay-dev.prisma.cat` → `sif_dev`.
- `web-pre.prisma.cat` → `pay-pre.prisma.cat` → `sif_pre`.
- `prisma.cat` → `pay.prisma.cat` → BD SIF de producció.

La web DEV i PRE es mantenen separades per subdomini perquè no existeix un mecanisme equivalent al rol d'entorn de la intranet.

## Intranet

La intranet no productiva continua a `intranet-pre.prisma.cat`, amb la separació DEV/PRE ja implementada per rol d'usuari:

- rol DEV → `pay-dev.prisma.cat` → `sif_dev`;
- rol PRE → `pay-pre.prisma.cat` → `sif_pre`;
- intranet de producció → `pay.prisma.cat` → BD SIF de producció.

La resolució de l'endpoint SIF s'ha de fer al servidor. No s'ha de confiar en un valor aportat pel navegador per decidir l'entorn.

## Aïllament obligatori

Cada entorn ha de tenir, com a mínim:

- usuari MySQL independent i limitat a la seva BD;
- configuració i secrets independents;
- sessions/cookies independents quan hi ha dominis diferents;
- logs independents;
- directori privat de documents independent;
- claus HMAC independents;
- configuració Redsys adequada a l'entorn;
- configuració AEAT adequada a l'entorn;
- workers/cron i cues identificables per entorn.

Cap DEV/TEST/PRE pot escriure a la BD SIF de producció.

## Document root

Per a cada `pay-*`, el document root ha d'apuntar exclusivament a `public/`. El codi, els secrets, els certificats, els logs i els documents privats no han de quedar sota el webroot.

Estructura lògica:

```text
<entorn>/
├── public/    # únic webroot
├── sif/       # codi/aplicació no exposat directament
└── private/   # secrets/certificats fora del webroot
```

## Migracions

`sif/scripts/run-migrations.php` admet:

- `development`;
- `local` (compatibilitat);
- `test`;
- `preproduction`.

`production` queda bloquejat expressament. El procediment de migració de producció requereix una via controlada específica amb còpia/restore verificat, aprovació GO/NO-GO, evidència de preproducció i pla de recuperació.

No modificar ni renombrar una migració ja registrada al ledger `sif_schema_migration`. Les migracions es processen pel nom complet del fitxer i es valida el SHA-256.

## Ordre de promoció

```text
codi en branca
    ↓
DEV · pay-dev / sif_dev
    ↓
TEST · pay-test / sif_test
    ↓
PRE · pay-pre / sif_pre
    ↓
GO/NO-GO + evidència
    ↓
PROD · pay / BD SIF producció
```

Una versió que arriba a PRE ha de ser la mateixa candidata que es vol promoure a PROD; no s'ha de continuar programant directament sobre PRE.

## Variables mínimes per entorn

Com a mínim s'han de definir de manera explícita:

```text
SIF_ENV
SIF_DB_DSN
SIF_DB_USER
SIF_DB_PASSWORD
SIF_LEGACY_DB_DSN
SIF_LEGACY_DB_USER
SIF_LEGACY_DB_PASSWORD
SIF_LEGACY_INTRANET_DB_DSN
SIF_LEGACY_INTRANET_DB_USER
SIF_LEGACY_INTRANET_DB_PASSWORD
SIF_INTERNAL_API_KEY_ID
SIF_INTERNAL_API_SECRET
SIF_DOCUMENT_ROOT
```

I, quan s'activin els fluxos corresponents, Redsys, AEAT, rols, workers i paths específics de cada UC.

Els valors reals i secrets no es versionen.
