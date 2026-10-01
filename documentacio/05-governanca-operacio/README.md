# Governança i operació

Aquesta carpeta defineix com s'ha de governar, provar, operar i auditar el SIF quan passa de codi a entorn real.

## Àmbits

- registre de versions i canvis;
- pla de proves i validació;
- seguretat, permisos i accessos;
- manual operatiu;
- captures/evidències visuals;
- diccionari de camps;
- guies ràpides;
- instruccions de desplegament de UC concrets.

## Regla

Cal separar sempre:

**codi implementat → prova tècnica → preproducció → evidència → activació → operació**.

Cap pas implica automàticament el següent. Els runbooks i guies s'han d'actualitzar quan canvia la superfície executable.
