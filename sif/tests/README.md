# Proves locals SIF (PHP/MySQL)

L'entorn instal·lat el 2026-09-17 usa PHP 8.4.25 CLI NTS x64 i MySQL
Community 8.4.10. Els binaris, les dades, les credencials i els logs viuen
exclusivament a `sif/var/`, exclòs de Git. No requereix Composer ni servei
Windows global. MySQL escolta només a `127.0.0.1:3307`, amb MySQL X desactivat.

## Execució en aquest ordinador

Des de l'arrel del repositori, amb PowerShell:

```powershell
./sif/scripts/local-test.ps1 -Action Start     # només si MySQL està aturat
./sif/scripts/local-test.ps1 -Action Migrate
./sif/scripts/local-test.ps1 -Action Test
./sif/scripts/local-test.ps1 -Action Preflight
./sif/scripts/local-test.ps1 -Action GoNoGo
./sif/scripts/local-test.ps1 -Action Stop      # atura únicament aquesta instància
```

El helper carrega `sif/var/test-env.json` i restaura les variables del procés
quan acaba. `Test`, `Migrate`, `Preflight` i `GoNoGo` retornen el codi de sortida
PHP. No s'ha afegit PHP al PATH global: el helper usa el binari local.

`Test` buida les 62 taules de negoci/control de **sif_test** i conserva el ledger de
migracions. No s'ha d'executar contra dades a conservar. Exigeix `SIF_ENV=test`,
nom `sif_test` o `sif_test_*`, i comprova també el nom real de la connexió.
Un lock MySQL impedeix dues suites simultànies a la mateixa BD.

`Preflight` comprova infraestructura SIF i l'esquema complet. `GoNoGo` inclou
legacy i Redsys, però el seu `GO` només és tècnic: sempre retorna
`scope=technical_preflight_only` i `production_authorized=false`. No executa
la suite ni acredita les portes G1..G7, permisos, restauració o homologació.
La BD `sif_legacy_test` instal·lada és buida: no s'han inventat dades de negoci
ni claus Redsys per fer passar aquestes comprovacions.

## Reproducció en un altre entorn

Descarregar els paquets oficials:

- [PHP per a Windows](https://www.php.net/downloads.php?os=windows&version=8.4).
- [MySQL Community 8.4](https://dev.mysql.com/downloads/mysql/8.4.html).

PHP necessita `pdo_mysql`, `openssl` i `mbstring`; configurar
`date.timezone=Europe/Madrid`. Crear una instància MySQL 8 aïllada i les BD
`sif_test` i `sif_legacy_test` amb `utf8mb4_unicode_ci`. L'usuari de tests té
privilegis només sobre aquestes BD; no és un usuari operatiu de producció.

Configurar variables d'entorn (o el JSON local del helper Windows):

```text
SIF_ENV=test
SIF_DB_DSN=mysql:host=127.0.0.1;port=3307;dbname=sif_test;charset=utf8mb4
SIF_DB_USER=sif_test
SIF_DB_PASSWORD=<secret local>
SIF_LEGACY_DB_DSN=mysql:host=127.0.0.1;port=3307;dbname=sif_legacy_test;charset=utf8mb4
SIF_LEGACY_DB_USER=sif_test
SIF_LEGACY_DB_PASSWORD=<secret local>
```

Amb PHP al PATH, els comandaments portables són:

```text
php sif/scripts/run-migrations.php
php sif/tests/run-tests.php
php sif/scripts/preflight-sif.php
php sif/scripts/go-no-go-preproduction.php
```

## Migracions i recuperació

`MigrationRunner` és compartit entre script, tests i preflight. Aplica SQL
ordenat i registra nom/SHA-256 només quan el fitxer acaba correctament.
Una migració aplicada canviada o absent bloqueja l'execució; crear migracions
additives per evolucionar un esquema ja desplegat. El preflight contrasta
cada hash, les 62 taules i les columnes declarades en CREATE/ADD COLUMN.
No és una comparació completa de tipus, índexs, triggers o grants.

El conjunt vigent amb el worker AEAT a 2026-09-23 conté deu fitxers de migració: s'ordenen pel
nom complet, no pel sufix numèric (hi ha sufixos repetits en dates diferents).
`.gitattributes` fixa LF per a aquests SQL. Això evita que el checkout de
Windows canviï els bytes i invalidi els hashes del ledger; la comprovació
SHA-256 continua sent estricta, sense ignorar diferències de contingut.

`MigrationInfrastructureTest` executa les migracions reals, comprova que la
reexecució conserva dades, detecta hashes/taules/columnes alterats, verifica
la neteja de dades i el restabliment de foreign keys, i rebutja una segona
suite o l'entorn productiu abans de buidar la BD. Les proves de preflight i
go/no-go executen també els CLI amb `PHP_BINARY`, validen JSON i codis de
sortida i comproven que un `NO-GO` no s'amaga darrere de la presència dels
fitxers. No envien peticions a Redsys ni a AEAT.

MySQL fa commits implícits de DDL. Una fallada parcial no es desfà amb
ROLLBACK: no manipular el ledger per simular èxit. En aquesta instància
prescindible, després del primer error 1064 de `ROW_NUMBER`, s'ha recreat
únicament `sif_test` i s'han tornat a aplicar les sis migracions. En una BD
amb dades cal recuperació revisada/restauració abans de reintentar.

La correcció de quoting de `ROW_NUMBER` a 000005 precedeix la seva primera
aplicació completa local. Un altre entorn que ja en tingui un hash registrat
no l'acceptarà silenciosament: cal revisar la seva situació explícitament.

Les proves actuals combinen tests executables i comprovacions estàtiques.
El smoke de concurrència existent és seqüencial; una suite verda no acredita
concurrència real multiprocés, integració dels canals, restauració ni AEAT.

## Evidència local

Els logs d'execució es conserven a `sif/var/evidence/`, fora de Git. Els
fitxers amb prefix `2026-09-23-` documenten la represa sobre el codi integrat,
inclosa una instal·lació de les nou migracions en una BD temporal buida i la
seva reexecució. La BD temporal s'elimina després de la comprovació.
Una prova anterior no valida canvis de codi posteriors: contrastar sempre
el manifest de hashes de l'execució amb els fitxers que es volen desplegar.

### Execució completa del 2026-09-24

Els fitxers `sif/var/evidence/2026-09-24-infra-*` documenten la validació
actual: deu migracions en instal·lació buida i reexecució idempotent, 62 taules
de model, lint de 328 PHP i **378 passed, 0 failed** a la suite completa.
El manifest no presenta canvis de fonts durant aquesta execució.

`Preflight` retorna exit 0. `GoNoGo` retorna exit 1/NO-GO per la clau Redsys
absent i les taules legacy `inscripcions`, `curs`, `regal` i `respGrups` absents.
Les proves AEAT fan servir dobles de transport; no s'ha enviat res a serveis
externs. La validació local no substitueix les portes de producció.
