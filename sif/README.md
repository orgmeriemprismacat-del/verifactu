# SIF PrisMa · Nucli executable

`sif/` conté el nucli executable del Sistema Informàtic de Facturació de PrisMa i les peces necessàries per integrar-lo amb web, intranet, Redsys, dades legacy i AEAT.

## Responsabilitats

El SIF concentra o ha de concentrar:

- emissió i rectificació de factures;
- numeració fiscal;
- registres i cadena de hash;
- cobrament, assignació, devolució, saldo i compensació;
- intents i callbacks Redsys;
- idempotència amb validació de payload;
- documents fiscals i custòdia;
- cues, reintents i incidències;
- registre d'auditoria;
- integració AEAT;
- sincronització controlada cap als sistemes legacy.

## Estructura

- `src/`: domini, serveis, repositoris, contractes, HTTP, AEAT i infraestructura.
- `public/`: endpoints HTTP i panell/recursos exposables.
- `database/`: migracions, permisos i seeds.
- `scripts/`: preflight, previews, processos, workers, reconciliacions i eines operatives.
- `tests/`: proves unitàries, integració i suport de test.
- `config/`: variables i contractes de configuració documentats.
- `resources/`: recursos auxiliars del SIF.
- `tools/`: eines de suport documental/tècnic.

## Frontera de confiança

Els canals externs poden iniciar accions, però les decisions econòmiques/fiscals sensibles s'han de revalidar al servidor amb dades autoritatives. El navegador no és font de veritat per preus, titulars, imports pagats, rols o identitat fiscal.

## Regles estructurals

- no editar factures emeses com si fossin registres mutables;
- usar rectificació/subsanació/anul·lació segons el cas;
- no reutilitzar una clau idempotent amb un payload materialment diferent;
- no considerar un callback Redsys com a únic origen de veritat sense validació;
- no exposar secrets al navegador ni al repositori;
- separar persistència funcional, auditoria i evidència;
- fallar tancat quan una operació crítica no pot validar identitat, permisos o estat.

## Estat d'una funcionalitat

Per cada UC cal distingir:

| Estat | Significat |
| --- | --- |
| Documentat | requisit o flux definit |
| Implementat | codi executable integrat |
| Provat | proves automatitzades/reproduïbles |
| Evidenciat | resultat associat al commit/CI/entorn |
| Desplegat | aplicat a l'entorn objectiu |

## Execució

- Proves i entorn local: [`tests/README.md`](tests/README.md)
- Configuració: [`config/README.md`](config/README.md)
- Persistència: [`database/README.md`](database/README.md)
- Scripts operatius: [`scripts/README.md`](scripts/README.md)
- Codi: [`src/README.md`](src/README.md)
- Superfície HTTP: [`public/README.md`](public/README.md)

## Producció

Un preflight tècnic verd o una suite verda **no autoritzen per si sols producció**. Cal mantenir separats els checks tècnics, l'evidència de preproducció, la configuració real, la seguretat, la restauració i la decisió formal d'activació.
