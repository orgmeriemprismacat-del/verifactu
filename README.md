# Projecte VERI*FACTU Pont

Aquest projecte pont serveix per continuar la feina del xat antic sense tornar a carregar tota la conversa dins d'un unic xat.

La regla principal es:

```text
Els xats no son la memoria del projecte.
La memoria del projecte son aquests fitxers.
```

## Estructura

- `documentacio/`: copia de la documentacio preparada al xat antic.
- `xat-original/`: copia del registre complet del xat antic en format `.jsonl`.
- `00-control/`: documents de coordinacio entre xats.

## Punt d'entrada

Per continuar, obre primer un xat pont i enganxa el text indicat a:

```text
00-control/guia-xat-pont.md
```

El xat pont ha de revisar el xat antic per blocs petits, detectar informacio que falta als documents i anar completant la documentacio.

## Com evitar tornar a saturar el context

- No enganxar mai el `.jsonl` complet dins cap xat.
- No demanar a cap xat que llegeixi tota la carpeta a la vegada.
- Treballar sempre per area: AEAT, arquitectura, BD, pagaments, intranet, operacio, proves.
- Al final de cada sessio, actualitzar `00-control/estat-projecte.md` i `00-control/registre-decisions.md`.
- Quan una area estigui prou completa, obrir un xat especialitzat seguint `00-control/mapa-xats.md`.

## Fitxers de control

- `00-control/estat-projecte.md`: resum viu del projecte.
- `00-control/registre-decisions.md`: decisions importants i motiu.
- `00-control/informacio-a-recuperar-del-xat-antic.md`: temes que cal buscar al xat antic.
- `00-control/checklist-completitud.md`: control de revisio del xat antic i dels documents.
- `00-control/mapa-xats.md`: quins xats crear i que posar a cada xat.
- `00-control/guia-xat-pont.md`: instruccions exactes per al xat pont.

