# Guia del xat pont

Aquest document conte les instruccions exactes per crear el xat pont.

## Que es el xat pont

El xat pont es un xat de transicio entre el xat antic massa gran i els xats especialitzats.

La seva funcio no es executar tot el projecte. La seva funcio es:

1. recuperar informacio del xat antic;
2. comparar-la amb la documentacio actual;
3. completar documents;
4. registrar decisions;
5. preparar xats especialitzats petits.

## Primer missatge per al xat pont

Copia i enganxa aquest text en un xat nou:

```text
Treballarem com a xat pont del projecte VERI*FACTU PrisMa.

Carpeta del projecte:
C:\Users\Usuario\Documents\Codex\2026-05-19\hola-com-faig-per-recuperar-tota\projecte-verifactu-pont

Abans de proposar res, llegeix:
- README.md
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- 00-control/informacio-a-recuperar-del-xat-antic.md
- 00-control/checklist-completitud.md
- 00-control/mapa-xats.md
- documentacio/README.md

Objectiu del xat:
Revisar el xat antic per apartats, detectar informacio important que no estigui reflectida als documents, i completar la documentacio sense carregar tota la conversa de cop.

Regles:
- No intentis llegir tot el JSONL complet en context.
- Busca per temes concrets dins de xat-original.
- Treballa un apartat cada vegada.
- Abans de modificar documents, digues quin apartat revisaras i quins fitxers tocara.
- Al final de cada bloc, actualitza 00-control/estat-projecte.md, 00-control/registre-decisions.md, 00-control/informacio-a-recuperar-del-xat-antic.md i 00-control/checklist-completitud.md.

Primer bloc a treballar:
Fes un inventari inicial dels temes del xat antic que podrien no estar reflectits als documents actuals. No completis encara tots els documents; primer proposa l'ordre de revisio per blocs.
```

## Metode de treball del xat pont

Per cada bloc:

1. Escollir una area concreta.
2. Buscar al xat antic paraules clau relacionades.
3. Llegir els documents actuals d'aquella area.
4. Fer una llista de:
   - informacio ja documentada;
   - informacio nova;
   - dubtes;
   - contradiccions;
   - decisions a registrar.
5. Completar els documents necessaris.
6. Actualitzar els fitxers de control.

## Ordre recomanat de revisio

1. Context actual de PrisMa.
2. Casos de facturacio i excepcions.
3. Pagaments, Redsys i `pay.prisma.cat`.
4. Base de dades i relacions.
5. Compliment AEAT i declaracio responsable.
6. Pantalles, permisos i operacio interna.
7. Proves, produccio i governanca.

## Quan obrir xats especialitzats

Quan el xat pont hagi revisat una area i actualitzat els documents, es pot obrir un xat especialitzat seguint `00-control/mapa-xats.md`.

El xat especialitzat no ha de llegir el xat antic sencer. Ha de llegir:

- `00-control/estat-projecte.md`
- `00-control/registre-decisions.md`
- `00-control/mapa-xats.md`
- els documents de la seva area

