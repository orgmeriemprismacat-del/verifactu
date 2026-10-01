# Generador de fitxes funcionals

Eina local per preparar i validar fitxes funcionals auditables. La síntesi funcional s'ha de basar en fonts autoritzades; els scripts congelen fonts i comproven mecànicament estructura, cites i estats.

## Abast i autoritat

- És una eina de suport; **no és la font canònica dels requisits**.
- El codi històric és evidència del comportament anterior, no autoritat del disseny objectiu.
- La sortida és una previsualització i no modifica automàticament `documentacio/`, Trello ni cap font canònica.
- Si el cas ja existeix al catàleg, l'eina ajuda a completar-lo; no crea una especificació paral·lela.
- Cap afirmació s'ha de considerar confirmada només perquè el validador estructural passi.

## Peces

- `input.schema.json`: contracte de dades d'entrada.
- `fixtures/`: entrades de prova.
- `sources.php`: fonts permeses i nivell de validació.
- `output-template.md`: estructura de la fitxa.
- `INSTRUCTIONS.md`: regles de síntesi i classificació.
- `prepare-functional-card.php`: manifest de fonts, línies, estat Git i SHA-256.
- `validate-functional-card.php`: validació de la fitxa contra el manifest.

## Ús

Des de l'arrel del repositori:

```powershell
php sif/scripts/prepare-functional-card.php --input=sif/tools/functional-card/fixtures/canvi-curs.json --output=$env:TEMP/canvi-curs.manifest.json
php sif/scripts/validate-functional-card.php --card=$env:TEMP/fitxa-canvi-curs.md --manifest=$env:TEMP/canvi-curs.manifest.json
```

El preparador rebutja sobreescriptures i no ha d'escriure la previsualització dins `documentacio/`.

## Límit

L'eina valida estructura i traçabilitat de fonts; no substitueix:

- revisió del codi real;
- decisions funcionals/fiscals;
- verificació de BD;
- UML ACTUAL/FINAL;
- proves executables;
- evidència de CI/preproducció.

El criteri de completitud continua sent el del projecte i del UC, no el retorn `0` del validador.
