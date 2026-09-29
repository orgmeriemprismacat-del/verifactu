# Importador Trello — utilitat heretada

**Revisió: 2026-09-24.** El nou mapa de taulers i el protocol de control és [el registre vigent](../trello/README.md). Aquest script és una utilitat històrica, **no** és la font de veritat de les targetes ni s'ha validat per als 12 taulers actuals. Els seus àlies només cobreixen parcialment l'estructura existent. No executar importacions massives sense revisar el codi, la correspondència exacta amb els ID dels taulers, la deduplicació entre taulers i fer una prova en sec.

## Credencials

No desar claus ni tokens al repositori públic. Utilitzar variables d'entorn `TRELLO_KEY` i `TRELLO_TOKEN` en un entorn local controlat.

## Prova en sec (sense crear targetes)

```powershell
php .\00-control\trello-import\trello-import-cards.php --input=.\00-control\trello-import\targetes-noves.sample.json
```

L'opció `--execute` escriu a Trello. No utilitzar-la fins que l'entrada s'hagi reconciliat amb les targetes originals per ID i resultat, i s'hagi confirmat el tauler propietari segons l'índex vigent. Una coincidència de nom en una sola llista **no** garanteix deduplicació entre taulers.

Aquest README no és un inventari i no conté cap fotografia actual de targetes. La documentació de l'importador queda com a referència fins a la seva revisió tècnica.
