# Scripts operatius del SIF

Aquesta carpeta conté utilitats executables per preparar, validar i operar fluxos del SIF.

## Famílies

- `preflight-*.php`: comproven prerequisits i bloquejos abans d'activar un flux.
- `preview-*.php`: calculen o mostren una operació sense executar-ne els efectes finals.
- `process-*.php`: executen operacions de negoci controlades.
- `run-*.php`: workers o processos continus/puntuals.
- `reconcile-*.php`: reconciliació entre estats o sistemes.
- `verify-*.php` / `test-*.php`: verificació dirigida i evidència.
- `run-migrations.php`: aplica migracions.
- `go-no-go-preproduction.php`: agregador tècnic de preproducció.
- `local-test.ps1`: helper de l'entorn local.

## Regla d'ús

Els scripts no són dreceres per saltar-se els serveis del SIF. Han d'usar les mateixes regles de domini, validacions, transaccions i auditoria que l'API.

## Preflight i GO/NO-GO

Un `GO` tècnic significa que les comprovacions codificades han passat. No equival a:

- autorització de producció;
- homologació AEAT;
- prova de restauració;
- verificació de secrets/configuració reals;
- validació manual de totes les pantalles.

Cada script nou hauria d'indicar entorns admesos, efectes, dependències, sortides i proves associades.
