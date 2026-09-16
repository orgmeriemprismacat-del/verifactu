# Generador de fitxes funcionals

MVP local per preparar i validar fitxes funcionals auditables. La síntesi semàntica la fa l'assistent seguint `INSTRUCTIONS.md`; els scripts PHP congelen les fonts i comproven mecànicament l'estructura, les cites i els estats.

## Abast i autoritat

- El generador és transversal i no substitueix les responsabilitats dels xats definides a `00-control/mapa-xats.md`.
- Xat 3 continua sent el responsable de validar les decisions funcionals i fiscals.
- El codi històric és evidència del comportament anterior, no autoritat sobre el disseny objectiu.
- La sortida és sempre una previsualització. No modifica automàticament `documentacio/`, Trello ni cap font canònica.
- Si el cas ja figura a `documentacio/04-estat-final/33-casos-us-sif.md`, la fitxa proposa completar-lo i no crea una especificació canònica paral·lela.

## Peces

- `input.schema.json`: contracte de les dades aportades per cas.
- `fixtures/canvi-curs.json`: entrada pilot d'UC-26.
- `sources.php`: llista explícita de fonts permeses i el seu nivell de validació.
- `output-template.md`: estructura exacta de 21 seccions.
- `INSTRUCTIONS.md`: regles de síntesi i classificació.
- `prepare-functional-card.php`: genera el manifest amb fragments, línies, estat Git i SHA-256.
- `validate-functional-card.php`: valida la fitxa contra el manifest congelat.

## Ús

Des de l'arrel de `projecte-verifactu-pont`:

```powershell
php sif/scripts/prepare-functional-card.php --input=sif/tools/functional-card/fixtures/canvi-curs.json --output=$env:TEMP/canvi-curs.manifest.json
```

Amb el manifest, l'assistent emplena una còpia temporal de `output-template.md` aplicant `INSTRUCTIONS.md`. La previsualització s'ha de guardar fora de `documentacio/` i validar així:

```powershell
php sif/scripts/validate-functional-card.php --card=$env:TEMP/fitxa-canvi-curs.md --manifest=$env:TEMP/canvi-curs.manifest.json
```

Els scripts retornen codi `0` quan acaben correctament. El validador retorna codi `1` quan detecta errors. El preparador rebutja sobreescriptures i no permet escriure la previsualització dins `documentacio/`.

## Límit de l'MVP

No hi ha API de model, base vectorial ni interfície web. Afegir un cas nou requereix una entrada JSON i, quan calgui, una entrada explícita a `sources.php`. Cap afirmació pot ser `CONFIRMAT` si la seva font no consta com a `AUTORITZADA`, existeix i manté el hash del manifest.
