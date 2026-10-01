# Persistència del SIF

Aquesta carpeta defineix l'evolució i els controls de persistència del SIF.

## Estructura

- `migrations/`: evolució additiva de l'esquema.
- `permissions/`: plantilles i regles de privilegis.
- `seeds/`: dades de suport quan existeixen.

## Principis

- les migracions aplicades formen part del contracte del sistema;
- no es modifica silenciosament una migració ja registrada;
- les taules d'auditoria o append-only no s'han de tractar com a registres CRUD normals;
- els secrets i comptes reals no es versionen;
- els permisos definits al repo s'han de verificar també a l'entorn real.

## Model objectiu

La BD SIF ha de poder representar, segons el flux: factures i línies, registres fiscals, rectificacions, documents, cadena fiscal, cues, pagaments i assignacions, relacions amb legacy, intents/callbacks Redsys, saldos/drets comercials, incidències i auditoria.

## Validació

L'esquema es comprova mitjançant migracions, preflight i proves. Una migració present al repositori no acredita que estigui aplicada a preproducció o producció.

Vegeu també [`permissions/README.md`](permissions/README.md) i [`../tests/README.md`](../tests/README.md).
